<?php
/**
 * @var object $former_db
 */

//error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);

require_once __DIR__ . '/bootstrap.php';

global $hidden_csrf_token;

if (!isset($former_db)) {
    exit;
}

/* -------------------------------------------------------------------
 * Admin-only file download for a submitted upload, used by
 * backend/submissions.php. No public download surface is exposed -
 * this branch requires an authenticated admin session.
 * ---------------------------------------------------------------- */
if (isset($_GET['download_file'])) {
    if (($_SESSION['user_class'] ?? '') !== 'administrator') {
        exit;
    }

    $file_id = (int) $_GET['download_file'];
    $file = $former_db->get('submission_files', '*', ['id' => $file_id]);

    if (!$file) {
        exit;
    }

    $submission = $former_db->get('submissions', ['form_id'], ['id' => $file['submission_id']]);
    if (!$submission) {
        exit;
    }

    $path = __DIR__.'/../uploads/'.((int) $submission['form_id']).'/'.$file['stored_filename'];
    if (!is_file($path)) {
        exit;
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($file['original_filename']).'"');
    header('Content-Length: '.filesize($path));
    readfile($path);
    exit;
}

/* -------------------------------------------------------------------
 * Double-opt-in: "resend the confirmation mail" (POST, from the
 * pending-confirmation card htmx-swapped into the original page right
 * after submitting). The confirmation landing page/click itself is handled
 * by plugins/former/index.php instead (fmr_handle_confirm_request()) -
 * NOT here - so it renders inside the real page/theme the form is embedded
 * on rather than a bare response from this endpoint. Not part of the
 * normal frontend submission flow further below.
 * ---------------------------------------------------------------- */

if (isset($_POST['fmr_resend_confirm'])) {
    $submission_id = (int) ($_POST['submission_id'] ?? 0);

    // Only ever resent for a submission this exact browser session itself
    // just created (populated further below, right after the original
    // pending submission is stored) - trusting a bare client-supplied
    // submission_id here would let anyone enumerate ids and trigger a mail
    // send to an address they don't control, not just re-send to whoever
    // originally submitted it.
    if (empty($_SESSION['fmr_pending_confirm'][$submission_id])) {
        exit;
    }

    $submission = $former_db->get('submissions', '*', ['id' => $submission_id]);
    $form = $submission ? $former_db->get('forms', '*', ['id' => $submission['form_id']]) : null;

    if (!$submission || !$form || !empty($submission['confirmed_at'])) {
        exit;
    }

    // Rate-limited rather than actually re-sending on every click - a burst
    // of impatient clicks shouldn't turn into a burst of mails.
    $throttled = !empty($submission['confirm_sent_at']) && (time() - strtotime($submission['confirm_sent_at'])) < 60;

    if (!$throttled) {
        $clean = json_decode($submission['data'], true) ?: [];
        $email_field = fmr_confirm_email_field_key($form, $former_db->select('fields', '*', ['form_id' => $form['id']]));
        $email = $email_field ? ($clean[$email_field] ?? '') : '';

        if ($email !== '') {
            [$token, $hash] = fmr_generate_confirm_token();
            $former_db->update('submissions', [
                'confirm_token_hash' => $hash,
                'confirm_expires_at' => date('Y-m-d H:i:s', time() + ((int) ($form['confirm_expires_hours'] ?: 48)) * 3600),
                'confirm_sent_at' => date('Y-m-d H:i:s'),
            ], ['id' => $submission_id]);
            fmr_send_confirmation_mail($form, $email, $token, $submission['confirm_page_slug'] ?? '');
        }
    }

    $pending_tpl = file_get_contents(fmr_resolve_template('pending-confirmation.tpl', $form['template_set'] ?? null));
    $pending_tpl = str_replace('{form_id}', (string) $form['id'], $pending_tpl);
    $pending_tpl = str_replace('{message}', htmlspecialchars('Die Bestätigungs-Mail wurde erneut gesendet. Bitte prüfen Sie auch Ihren Spam-Ordner.'), $pending_tpl);
    $pending_tpl = str_replace('{resend_html}', fmr_render_resend_form((int) $form['id'], $submission_id, $hidden_csrf_token), $pending_tpl);
    echo $pending_tpl;
    exit;
}

/* -------------------------------------------------------------------
 * Frontend form submission
 * ---------------------------------------------------------------- */

if (!isset($_POST['form_id'])) {
    exit;
}

$form_id = (int) $_POST['form_id'];
$form = $former_db->get('forms', '*', ['id' => $form_id]);

if (!$form || (int) $form['status'] !== 1) {
    echo '<div class="alert alert-danger">Formular nicht verfügbar.</div>';
    exit;
}

/* 1. CSRF / honeypot / time-delta -------------------------------------
 * app/bootstrap.php already ran se_validate_token() globally for any
 * non-empty POST and would have redirected on mismatch. This is a
 * defensive re-check (same idiom as plugins/msr-wsm/global/xhr.php) so a
 * mismatch produces a clean HTMX-swapped error instead of relying on
 * that redirect. */
$valid = ($_POST['csrf_token'] ?? '') === ($_SESSION['token'] ?? null)
    && trim($_POST['fmr_hp'] ?? '') === ''
    && !empty($_POST['sendtime'])
    && (time() - (int) $_POST['sendtime']) >= 10;

/* 2. Captcha ------------------------------------------------------------ */
$fmr_settings = fmr_get_settings();
if ($valid && empty($form['disable_captcha'])) {
    if (($fmr_settings['captcha_type'] ?? 'math') === 'recaptcha_v2') {
        $valid = fmr_verify_recaptcha($_POST['g-recaptcha-response'] ?? '', $fmr_settings['recaptcha_secret_key'] ?? '');
    } else {
        $valid = fmr_check_captcha($_POST['fmr_captcha'] ?? '');
    }
}

if (!$valid) {
    $banner = '<div class="alert alert-danger mb-3">Ihre Eingabe konnte nicht überprüft werden. Bitte versuchen Sie es erneut.</div>';
    echo fmr_render_form($form_id, $_POST, $banner);
    exit;
}

/* 3. Load field defs + sanitize dynamic $_POST -------------------------- */
$fields = $former_db->select('fields', '*', ['form_id' => $form_id, 'ORDER' => ['sort_order' => 'ASC']]);
$field_types = fmr_field_types();
$clean = [];
$errors = [];

foreach ($fields as $field) {
    $key = $field['field_key'];
    $type = $field_types[$field['field_type']] ?? null;
    if (!$type) {
        continue;
    }

    if (!empty($type['has_upload'])) {
        continue; // handled separately via $_FILES below
    }

    if (!empty($type['is_static'])) {
        continue; // text_block etc. - not an input, collects no value
    }

    $raw = $_POST[$key] ?? '';
    $value = fmr_sanitize_value($raw);

    // Hidden fields are typically filled by an external site-wide script
    // (GTM etc.) matching the rendered id/name - required is never
    // enforced for them regardless of the stored flag, since that script
    // not running/matching (ad blockers, consent banners delaying it,
    // visiting the page directly instead of via an ad) would otherwise
    // silently block the whole form for a value the visitor never sees and
    // can't fix. The admin UI (fmr_render_field_row()) already hides the
    // checkbox for this type; this is defense in depth against a
    // field_type='hidden' row that ever ends up with required=1 some
    // other way.
    if ($field['required'] && $field['field_type'] !== 'hidden' && (is_array($value) ? empty($value) : $value === '')) {
        $errors[] = $field['label'].' ist ein Pflichtfeld.';
    }

    if ($field['field_type'] === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
    }

    if (in_array($field['field_type'], ['select', 'radio'], true) && $value !== '') {
        $config = json_decode($field['config'] ?? '{}', true) ?: [];
        $allowed = array_column($config['options'] ?? [], 'value');
        if (!in_array($value, $allowed, true)) {
            $errors[] = 'Ungültige Auswahl bei '.$field['label'].'.';
        }
    }

    $clean[$key] = $value;
}

/* 3b. Double-opt-in needs a real address to send the confirmation link to -
 * checked here, alongside the other field errors, so the visitor sees it
 * the same way as any other validation problem and can fix it before
 * resubmitting, rather than failing silently later at persist time. */
$requires_confirmation = !empty($form['require_confirmation']);
$confirm_email_field = null;
$confirm_email = '';
if ($requires_confirmation) {
    $confirm_email_field = fmr_confirm_email_field_key($form, $fields);
    $confirm_email = $confirm_email_field ? ($clean[$confirm_email_field] ?? '') : '';
    if ($confirm_email === '' || !filter_var($confirm_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Es wird eine gültige E-Mail-Adresse benötigt, um die Anmeldung zu bestätigen.';
    }
}

if ($errors) {
    $banner = '<div class="alert alert-danger mb-3"><ul class="mb-0"><li>'.implode('</li><li>', array_map('htmlspecialchars', $errors)).'</li></ul></div>';
    echo fmr_render_form($form_id, $_POST, $banner);
    exit;
}

/* 4. File uploads ---------------------------------------------------------
 * No frontend/anonymous-upload precedent exists in this codebase
 * (acp/core/xhr/upload.php requires an authenticated admin session) -
 * former validates and stores independently, own uploads/ dir + own
 * submission_files table, NOT se_media. */
$upload_dir = __DIR__.'/../uploads/'.$form_id.'/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$uploaded_files = [];

foreach ($fields as $field) {
    $type = $field_types[$field['field_type']] ?? null;
    if (empty($type['has_upload']) || empty($_FILES[$field['field_key']])) {
        continue;
    }

    $config = json_decode($field['config'] ?? '{}', true) ?: [];
    $allowed_ext_raw = $config['allowed_extensions'] ?? ($fmr_settings['allowed_upload_extensions'] ?? '');
    $allowed_ext = array_filter(array_map('trim', explode(',', strtolower($allowed_ext_raw))));
    $max_bytes = (int) ($config['max_size_mb'] ?? ($fmr_settings['max_upload_size_mb'] ?? 5)) * 1024 * 1024;

    $file = $_FILES[$field['field_key']];
    $names = is_array($file['name']) ? $file['name'] : [$file['name']];

    foreach ($names as $i => $orig_name) {
        $tmp = is_array($file['tmp_name']) ? $file['tmp_name'][$i] : $file['tmp_name'];
        $err = is_array($file['error']) ? $file['error'][$i] : $file['error'];
        $size = is_array($file['size']) ? $file['size'][$i] : $file['size'];

        if ($err === UPLOAD_ERR_NO_FILE || $orig_name === '') {
            continue;
        }
        if ($err !== UPLOAD_ERR_OK) {
            $errors[] = 'Fehler beim Hochladen von '.htmlspecialchars($orig_name).'.';
            continue;
        }

        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
        if ($allowed_ext && !in_array($ext, $allowed_ext, true)) {
            $errors[] = 'Dateityp nicht erlaubt: '.htmlspecialchars($orig_name);
            continue;
        }
        if ($size > $max_bytes) {
            $errors[] = 'Datei zu groß: '.htmlspecialchars($orig_name);
            continue;
        }

        $stored = uniqid('fmr_').'_'.clean_filename(pathinfo($orig_name, PATHINFO_FILENAME)).'.'.$ext;
        if (move_uploaded_file($tmp, $upload_dir.$stored)) {
            $uploaded_files[$field['field_key']][] = [
                'original' => se_filter_filepath($orig_name),
                'stored' => $stored,
                'size' => $size,
                'mime' => function_exists('mime_content_type') ? (mime_content_type($upload_dir.$stored) ?: null) : null,
            ];
        } else {
            $errors[] = 'Datei konnte nicht gespeichert werden: '.htmlspecialchars($orig_name);
        }
    }
}

if ($errors) {
    fmr_delete_uploaded_files($uploaded_files, $upload_dir);
    $banner = '<div class="alert alert-danger mb-3"><ul class="mb-0"><li>'.implode('</li><li>', array_map('htmlspecialchars', $errors)).'</li></ul></div>';
    echo fmr_render_form($form_id, $_POST, $banner);
    exit;
}

/* 5. Persist (DB) --------------------------------------------------------- */
$meta = fmr_build_submission_meta($form);

// Lightweight consent proof (fmr_build_consent_log()) - independent of the
// require_confirmation flow below, and of the "auto-attached data"
// checkboxes above: this is tied to one specific checkbox's wording, not
// the whole submission.
$consent_log = fmr_build_consent_log($fields, $clean);
if ($consent_log) {
    $meta['consent_log'] = $consent_log;
}

$submission_id = null;
$confirm_token = null;
// require_confirmation forces store_to_db on when the form is saved
// (backend/writer.php) - the `|| $requires_confirmation` here is just
// defense in depth against a form row that ended up with that
// combination some other way (e.g. a direct DB edit), since a pending
// confirmation with nowhere durable to live could never be confirmed.
if ((int) $form['store_to_db'] === 1 || $requires_confirmation) {
    $insert = [
        'form_id' => $form_id,
        'data' => json_encode($clean, JSON_UNESCAPED_UNICODE),
        'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ];

    if ($requires_confirmation) {
        [$confirm_token, $hash] = fmr_generate_confirm_token();
        $insert['confirm_token_hash'] = $hash;
        $insert['confirm_expires_at'] = date('Y-m-d H:i:s', time() + ((int) ($form['confirm_expires_hours'] ?: 48)) * 3600);
        $insert['confirm_sent_at'] = date('Y-m-d H:i:s');
        // The page this visitor actually submitted from - fmr_confirm_link()
        // builds the mail's link against this exact page, not a hardcoded
        // one, since the same form can be embedded on more than one page.
        // Always present regardless of the "Seiten-Informationen" checkbox
        // (form-wrapper.tpl always sends this hidden field - see
        // fmr_render_form()), unlike meta.page_url, which is opt-in.
        $insert['confirm_page_slug'] = (string) ($_POST['fmr_page_slug'] ?? '');
    }

    $former_db->insert('submissions', $insert);
    $submission_id = $former_db->id();

    foreach ($uploaded_files as $field_key => $files) {
        foreach ($files as $f) {
            $former_db->insert('submission_files', [
                'submission_id' => $submission_id,
                'field_key' => $field_key,
                'original_filename' => $f['original'],
                'stored_filename' => $f['stored'],
                'filesize' => $f['size'],
                'mimetype' => $f['mime'],
            ]);
        }
    }
}

/* 6. Respond to the visitor --------------------------------------------- */

if ($requires_confirmation) {
    // Nothing is final yet: no notification mail to mail_recipients, no
    // former:submitted tracking event - both are deferred to the
    // confirm-click handler above (fmr_confirm_pending_submission()), fired
    // only once the visitor has actually proven the submission was them.
    fmr_send_confirmation_mail($form, $confirm_email, $confirm_token, (string) ($_POST['fmr_page_slug'] ?? ''));

    // Session-scoped whitelist for the "resend" button - see
    // fmr_resend_confirm above for why this matters.
    $_SESSION['fmr_pending_confirm'][$submission_id] = true;

    $pending_tpl = file_get_contents(fmr_resolve_template('pending-confirmation.tpl', $form['template_set'] ?? null));
    $pending_tpl = str_replace('{form_id}', (string) $form_id, $pending_tpl);
    $pending_tpl = str_replace('{message}', htmlspecialchars('Bitte bestätigen Sie Ihre E-Mail-Adresse: Wir haben Ihnen eine Nachricht mit einem Bestätigungslink an '.$confirm_email.' geschickt.'), $pending_tpl);
    $pending_tpl = str_replace('{resend_html}', fmr_render_resend_form($form_id, $submission_id, $hidden_csrf_token), $pending_tpl);
    echo $pending_tpl;
    exit;
}

/* Frontend tracking hook: a `former:submitted` CustomEvent is dispatched
 * (see templates/success.tpl) from an inline <script> in the swapped-in
 * success markup, so it fires the moment htmx settles the response - no
 * former JS of its own, purely a hook for the theme (or GTM, or a snippet
 * in the page) to react to, e.g. firing a Google Ads conversion or a
 * Salesforce Web-to-Lead call. Carries the same sanitized values already
 * collected into $clean above (the visitor's own just-submitted data,
 * returned to their own browser - no former-side network calls are made).
 * JSON_HEX_* escaping makes it safe to embed inside a <script> block even
 * if a submitted value contains "</script>" or HTML. `meta` carries
 * whatever fmr_build_submission_meta() added per the form's "include_user_
 * data" / "include_ip_referrer" / "include_page_info" checkboxes - empty
 * object if none is on. */
$tracking_json = json_encode([
    'form_id' => $form_id,
    'form_name' => $form['name'],
    'submission_id' => $submission_id,
    'data' => $clean,
    'meta' => $meta,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

$success_tpl = file_get_contents(fmr_resolve_template('success.tpl', $form['template_set'] ?? null));
$success_tpl = str_replace('{form_id}', (string) $form_id, $success_tpl);
$success_tpl = str_replace('{message}', htmlspecialchars($form['success_message'] ?: 'Vielen Dank für Ihre Nachricht!'), $success_tpl);
$success_tpl = str_replace('{tracking_event_json}', $tracking_json, $success_tpl);
echo $success_tpl;

if ((int) $form['send_mail'] !== 1) {
    if ((int) $form['store_to_db'] !== 1) {
        fmr_delete_uploaded_files($uploaded_files, $upload_dir);
    }
    exit;
}

$recipient_ids = json_decode($form['mail_recipients'] ?? '[]', true) ?: [];
if (!$recipient_ids) {
    if ((int) $form['store_to_db'] !== 1) {
        fmr_delete_uploaded_files($uploaded_files, $upload_dir);
    }
    exit;
}

/* N recipients via sequential se_send_mail() calls over (possibly slow)
 * SMTP risk exceeding the ~30s web-server read/inactivity timeout. Since
 * the visitor's "thanks" response is already fully sent above, detach
 * the connection where possible so the visitor is never blocked on mail
 * delivery; fall back to a heartbeat between sends otherwise (pattern
 * from plugins/claude-bridge/backend/claude-api.php). */
if (function_exists('fastcgi_finish_request')) {
    if (ob_get_level() > 0) {
        @ob_end_flush();
    }
    @flush();
    fastcgi_finish_request();
} else {
    @set_time_limit(0);
    if (ob_get_level() > 0) {
        @ob_flush();
    }
    @flush();
}

// allow_heartbeat: true - the visitor's own response was already echoed and
// detached (fastcgi_finish_request(), or the manual flush above) before we
// get here, so it's safe for a non-fastcgi fallback to interleave heartbeat
// comments after it.
fmr_send_submission_notification($form, $fields, $clean, $meta, $uploaded_files, $upload_dir, true);

if ((int) $form['store_to_db'] !== 1) {
    fmr_delete_uploaded_files($uploaded_files, $upload_dir);
}

exit;
