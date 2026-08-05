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

    if ($field['required'] && (is_array($value) ? empty($value) : $value === '')) {
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

$submission_id = null;
if ((int) $form['store_to_db'] === 1) {
    $former_db->insert('submissions', [
        'form_id' => $form_id,
        'data' => json_encode($clean, JSON_UNESCAPED_UNICODE),
        'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
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

/* 6. Respond to the visitor immediately, THEN send mail(s) --------------- */

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
 * data" / "include_ip_referrer" checkboxes - empty object if neither is on. */
$tracking_json = json_encode([
    'form_id' => $form_id,
    'form_name' => $form['name'],
    'submission_id' => $submission_id,
    'data' => $clean,
    'meta' => $meta,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

$success_tpl = file_get_contents(__DIR__.'/../templates/success.tpl');
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

$recipients = $former_db->select('recipients', ['id', 'name', 'email'], ['id' => $recipient_ids]);
$subject = $form['mail_subject'] ?: ('Neue Formular-Einsendung: '.$form['name']);

$body = '<table cellpadding="5" cellspacing="0" border="1" width="100%">';
foreach ($fields as $field) {
    $type = $field_types[$field['field_type']] ?? [];
    if (!empty($type['has_upload']) || !empty($type['is_static'])) {
        continue;
    }
    $v = $clean[$field['field_key']] ?? '';
    $body .= '<tr><td>'.htmlspecialchars($field['label']).'</td><td>'.htmlspecialchars(is_array($v) ? implode(', ', $v) : (string) $v).'</td></tr>';
}
if ($meta) {
    $meta_labels = fmr_meta_labels();
    foreach ($meta as $key => $value) {
        $body .= '<tr><td>'.htmlspecialchars($meta_labels[$key] ?? $key).'</td><td>'.htmlspecialchars((string) ($value ?? '')).'</td></tr>';
    }
}
$body .= '</table>';

// Uploaded files are attached to the notification mail. se_send_mail()
// has no attachment support, so fmr_send_mail_with_attachments() (a
// local PHPMailer wrapper mirroring the same SMTP config) is used
// whenever the submission has at least one stored file.
$mail_attachments = [];
foreach ($uploaded_files as $files) {
    foreach ($files as $f) {
        $mail_attachments[] = [
            'path' => $upload_dir.$f['stored'],
            'name' => $f['original'],
        ];
    }
}

foreach ($recipients as $i => $r) {
    if ($mail_attachments) {
        fmr_send_mail_with_attachments(['mail' => $r['email'], 'name' => $r['name']], $subject, $body, $mail_attachments);
    } else {
        se_send_mail(['mail' => $r['email'], 'name' => $r['name']], $subject, $body);
    }

    if (!function_exists('fastcgi_finish_request') && $i < count($recipients) - 1) {
        echo '<!-- . -->';
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
    }
}

if ((int) $form['store_to_db'] !== 1) {
    fmr_delete_uploaded_files($uploaded_files, $upload_dir);
}

exit;
