<?php
use Medoo\Medoo;
use PHPMailer\PHPMailer\PHPMailer;
//error_reporting(E_ALL);

/* -------------------------------------------------------------------
 * Settings (key/value store)
 * ---------------------------------------------------------------- */

function fmr_get_settings() {
    global $former_db;

    if (!isset($former_db)) {
        return [];
    } else {
        $result = $former_db->select("settings", ["key", "value"]);
        return array_column($result, 'value', 'key');
    }
}

function fmr_save_setting(string $key, $value): bool {
    global $former_db;

    $exists = $former_db->has('settings', ['key' => $key]);

    if ($exists) {
        $result = $former_db->update('settings', [
            'value' => (string)$value
        ], ['key' => $key]);
        return $result->rowCount() > 0;
    } else {
        return $former_db->insert('settings', [
            'key' => $key,
            'value' => (string)$value
        ]);
    }
}

/**
 * Ensure table exists and matches current schema.
 */
function fmr_updateOrCreateTable(string $table_name, array $expected_columns): void
{
    global $former_db;

    $tables = $former_db->query("
    SELECT name FROM sqlite_master
    WHERE type='table' AND name = '$table_name' ")->fetchAll();

    if (empty($tables)) {
        $col_definitions = [];
        foreach ($expected_columns as $col_name => $col_type) {
            $col_definitions[] = "$col_name $col_type";
        }
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (" . implode(', ', $col_definitions) . ")";
        $former_db->query($sql);
        echo "Created table $table_name<br>";
        return;
    }

    $tableInfo = $former_db->query("PRAGMA table_info($table_name)")->fetchAll();
    $existing_columns = array_column($tableInfo, 'name');

    foreach ($expected_columns as $col_name => $col_type) {
        if (!in_array($col_name, $existing_columns, true)) {
            $sql = "ALTER TABLE $table_name ADD COLUMN $col_name $col_type";
            $result = $former_db->query($sql);
            if ($result !== false) {
                echo "Added column $col_name to $table_name<br>";
            }
        }
    }
}

/* -------------------------------------------------------------------
 * Captcha
 * ---------------------------------------------------------------- */

function fmr_generate_captcha(): void {
    $a = rand(1, 12);
    $b = rand(1, 12);
    $_SESSION['fmr_captcha_answer'] = $a + $b;
    $_SESSION['fmr_captcha_question'] = "Was ist $a + $b?";
}

function fmr_check_captcha(string $input): bool {
    if (!isset($_SESSION['fmr_captcha_answer'])) {
        return false;
    }
    $correct = (int)$input === (int)$_SESSION['fmr_captcha_answer'];
    unset($_SESSION['fmr_captcha_answer'], $_SESSION['fmr_captcha_question']);
    return $correct;
}

function fmr_verify_recaptcha(string $response, string $secret): bool {
    if ($response === '' || $secret === '') {
        return false;
    }

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => $secret,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);

    if ($result === false) {
        return false;
    }

    $decoded = json_decode($result, true);
    return !empty($decoded['success']);
}

/* -------------------------------------------------------------------
 * Auto-attached submission metadata (logged-in user / user data / page
 * info). Opt-in per form (see the three checkboxes in backend/reader.php's
 * form_settings block), fixed set of keys by design - not user-
 * configurable beyond on/off. Fed into all three sinks alike: the
 * submissions table (own `meta` column), the notification mail, and the
 * former:submitted JS event (see global/xhr.php).
 * ---------------------------------------------------------------- */

function fmr_build_submission_meta(array $form): array {
    $meta = [];

    // $_SESSION['user_id'] is the site-wide login (shared with the shop /
    // profile area etc., not just backend admins) - see
    // app/functions/functions.user.php::se_start_user_session(). Nothing
    // is added if the visitor isn't logged in, even when the checkbox is on.
    if (!empty($form['include_user_data']) && isset($_SESSION['user_id'])) {
        $meta['user_id'] = (int) $_SESSION['user_id'];
        $meta['user_nick'] = $_SESSION['user_nick'] ?? null;
        $meta['user_mail'] = $_SESSION['user_mail'] ?? null;
    }

    // Column is still named include_ip_referrer from before this checkbox
    // was broadened to "Benutzerdaten mitschicken" (IP, referrer, browser) -
    // renaming it would need a DB migration for no real benefit, the name
    // just no longer matches the label shown in the settings form.
    if (!empty($form['include_ip_referrer'])) {
        $meta['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
        // The Referer header on the POST itself - usually just the page the
        // form lives on, not the original ad/landing-page source, once the
        // visitor has navigated around the site first.
        $meta['referrer'] = $_SERVER['HTTP_REFERER'] ?? null;
        $meta['browser'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
    }

    if (!empty($form['include_page_info'])) {
        // The page's own slug, captured server-side into a hidden field at
        // render time (fmr_render_form()'s {page_slug}) rather than read
        // from $_SERVER here - this code runs for the /xhr/plugins/former/
        // endpoint, whose own path is not the page the form was shown on.
        $meta['page_url'] = $_POST['fmr_page_slug'] ?? null;
        $meta['submitted_at'] = date('Y-m-d H:i:s');
    }

    return $meta;
}

/**
 * Display labels for fmr_build_submission_meta() keys, used by the
 * submissions list (backend/reader.php) and the notification mail body.
 */
function fmr_meta_labels(): array {
    global $addon_lang;

    return [
        'user_id' => $addon_lang['label_meta_user_id'] ?? 'User-ID',
        'user_nick' => $addon_lang['label_meta_user_nick'] ?? 'Benutzername',
        'user_mail' => $addon_lang['label_meta_user_mail'] ?? 'E-Mail (angemeldet)',
        'ip_address' => $addon_lang['label_meta_ip_address'] ?? 'IP-Adresse',
        'referrer' => $addon_lang['label_meta_referrer'] ?? 'Referrer',
        'browser' => $addon_lang['label_meta_browser'] ?? 'Browser',
        'page_url' => $addon_lang['label_meta_page_url'] ?? 'Seiten-URL',
        'submitted_at' => $addon_lang['label_meta_submitted_at'] ?? 'Zeitpunkt der Absendung',
    ];
}

/* -------------------------------------------------------------------
 * Mail with attachments. se_send_mail() (app/functions/functions.php)
 * has no attachment support, so submissions with an uploaded file use
 * this local variant instead - it mirrors se_send_mail()'s SMTP setup
 * exactly (same $se_settings / $smtp_* globals and mailer selection),
 * just adds addAttachment() calls.
 * ---------------------------------------------------------------- */

function fmr_send_mail_with_attachments($recipient, $subject, $message, array $attachments = []) {
    global $se_settings;
    global $smtp_host, $smtp_port, $smtp_encryption, $smtp_username, $smtp_psw;

    $prefs_mailer_adr = $se_settings['mailer_adr'];
    $prefs_mailer_name = $se_settings['mailer_name'];
    $prefs_mailer_type = $se_settings['mailer_type'];

    $subject = preg_replace('/(content-type:|bcc:|cc:|to:|from:)/im', '', $subject);
    $message = preg_replace('/(content-type:|bcc:|cc:|to:|from:)/im', '', $message);

    $mail = new PHPMailer(true);

    if ($prefs_mailer_type === 'smtp') {
        $mail->isSMTP();
        $mail->Host = "$smtp_host";
        $mail->SMTPAuth = true;
        $mail->Username = "$smtp_username";
        $mail->Password = "$smtp_psw";
        if ($smtp_encryption != '') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->Port = $smtp_port;
    }

    $mail->setFrom("$prefs_mailer_adr", "$prefs_mailer_name");
    $mail->addAddress($recipient['mail'], $recipient['name']);

    foreach ($attachments as $attachment) {
        if (is_file($attachment['path'])) {
            $mail->addAttachment($attachment['path'], $attachment['name'] ?? basename($attachment['path']));
        }
    }

    $mail->isHTML(true);
    $mail->CharSet = 'utf-8';
    $mail->Subject = "$subject";
    $mail->Body = "$message";

    try {
        if (!$mail->send()) {
            return 'Mailer Error: '.$mail->ErrorInfo;
        }
        return 1;
    } catch (\Exception $e) {
        return 'Mailer Error: '.$e->getMessage();
    }
}

/**
 * Remove files that were already move_uploaded_file()'d for this submission
 * but never ended up referenced by a submission_files row - either a later
 * field in the same submission failed validation (rejecting the whole
 * submission after earlier files were already moved), or the form has
 * database storage turned off (so no row is ever created to track them).
 * Without this, uploads/<form_id>/ accumulates files no admin UI can ever
 * reach again.
 */
function fmr_delete_uploaded_files(array $uploaded_files, string $upload_dir): void {
    foreach ($uploaded_files as $files) {
        foreach ($files as $f) {
            @unlink($upload_dir.$f['stored']);
        }
    }
}

/* -------------------------------------------------------------------
 * Dynamic input sanitizing (sanitizeUserInputs() is scalar-only and
 * throws on arrays in PHP 8 - former's dynamic $_POST fields, e.g.
 * checkbox groups submitted as field[], need a recursive wrapper)
 * ---------------------------------------------------------------- */

function fmr_sanitize_value($raw) {
    if (is_array($raw)) {
        return array_map('fmr_sanitize_value', $raw);
    }
    return sanitizeUserInputs((string) $raw);
}

/**
 * Tags allowed inside a text_block field's content. This is
 * admin-authored content (the form editor, not a public visitor), so
 * it's held to the same trust level as other admin-authored HTML in
 * this CMS (e.g. page content) rather than visitor input - just
 * restricted to a small whitelist since it's meant for short
 * explanatory text, not arbitrary markup.
 */
function fmr_allowed_content_tags(): string {
    return '<p><br><small><strong><em><b><i><a><ul><ol><li>';
}

/* -------------------------------------------------------------------
 * Template-sets - per-form override of the shipped templates/ tree,
 * without touching the active SwiftyEdit theme or the plugin's own
 * templates/ (which a Former update overwrites). Sets live under
 * data/themes/<slug>/, mirroring templates/'s own layout
 * (form-wrapper.tpl, success.tpl, fields/*.tpl); data/ is skipped
 * unconditionally by the core installer's update extractor and
 * excluded from release zips (scripts/build_plugin_release.sh), so a
 * site's own sets survive both Former and core updates untouched.
 * ---------------------------------------------------------------- */

/**
 * Slugs of available template-sets (subfolder names under data/themes/),
 * for the "Template-set" <select> in the form settings and to validate
 * a posted choice against in backend/writer.php. Hidden entries (like
 * .gitkeep) and anything not matching the same charset a set slug is
 * validated against on lookup are silently skipped rather than listed.
 */
function fmr_list_template_sets(): array {
    $dir = __DIR__.'/../data/themes/';
    if (!is_dir($dir)) {
        return [];
    }

    $sets = [];
    foreach (scandir($dir) as $entry) {
        if ($entry === '' || $entry[0] === '.') {
            continue;
        }
        if (is_dir($dir.$entry) && preg_match('/^[a-zA-Z0-9_-]+$/', $entry)) {
            $sets[] = $entry;
        }
    }
    sort($sets);
    return $sets;
}

/**
 * Resolve one template file for a form's chosen set, falling back to the
 * plugin's own shipped copy when the set doesn't override that particular
 * file (or no set is chosen at all) - a set only needs to provide the
 * files it actually changes. $relative_path is always one of the fixed
 * literals this file passes in below, never user input; $set is
 * whitelisted here too as defense in depth, even though callers already
 * only pass a form's stored template_set (itself validated against
 * fmr_list_template_sets() when saved in backend/writer.php).
 */
function fmr_resolve_template(string $relative_path, ?string $set): string {
    if ($set && preg_match('/^[a-zA-Z0-9_-]+$/', $set)) {
        $custom = __DIR__.'/../data/themes/'.$set.'/'.$relative_path;
        if (is_file($custom)) {
            return $custom;
        }
    }

    return __DIR__.'/../templates/'.$relative_path;
}

/* -------------------------------------------------------------------
 * Form rendering - shared between the shortcode entry point
 * (plugins/former/index.php) and the error/re-render paths in
 * global/xhr.php, so a failed submission re-renders identically
 * with the visitor's previous input preserved.
 * ---------------------------------------------------------------- */

function fmr_render_form(int $form_id, ?array $repopulate = null, string $banner_html = ''): string {
    global $former_db, $hidden_csrf_token, $swifty_slug;

    $form = $former_db->get('forms', '*', ['id' => $form_id]);

    if (!$form || (int) $form['status'] !== 1) {
        return '<div class="alert alert-warning">Formular nicht verfügbar.</div>';
    }

    $set = $form['template_set'] ?? null;

    $fields = $former_db->select('fields', '*', [
        'form_id' => $form_id,
        'ORDER' => ['sort_order' => 'ASC'],
    ]);

    $field_types = fmr_field_types();
    $fields_html = '';

    foreach ($fields as $field) {
        $type = $field_types[$field['field_type']] ?? null;
        if (!$type) {
            continue;
        }

        $tpl_file = fmr_resolve_template('fields/'.$type['template'], $set);
        if (!is_file($tpl_file)) {
            continue;
        }
        $tpl = file_get_contents($tpl_file);
        $config = json_decode($field['config'] ?? '{}', true) ?: [];
        $key = $field['field_key'];

        // Static content blocks aren't inputs - no name/label/placeholder/
        // required tokens apply, just the admin-authored text. Handled
        // separately so it's never validated or collected as a value in
        // global/xhr.php's submission-processing loop.
        if (!empty($type['is_static'])) {
            // Re-applying the whitelist here too (not just at save time in
            // backend/writer.php) is cheap defense in depth against the DB
            // value ever containing anything wider than the allowed tags.
            // Not htmlspecialchars()'d - the whole point is to let the
            // admin-authored whitelisted tags render as real markup.
            $content = strip_tags($config['content'] ?? '', fmr_allowed_content_tags());
            $tpl = str_replace('{css_class}', htmlspecialchars($field['css_class'] ?? ''), $tpl);
            $fields_html .= str_replace('{content}', nl2br($content), $tpl);
            continue;
        }

        $repop_value = $repopulate[$key] ?? '';

        $tpl = str_replace('{name}', htmlspecialchars($key), $tpl);
        $tpl = str_replace('{label}', htmlspecialchars($field['label']), $tpl);
        $tpl = str_replace('{placeholder}', htmlspecialchars($field['placeholder'] ?? ''), $tpl);
        $tpl = str_replace('{required}', $field['required'] ? 'required' : '', $tpl);
        $tpl = str_replace('{css_class}', htmlspecialchars($field['css_class'] ?? ''), $tpl);
        $tpl = str_replace('{rows}', htmlspecialchars((string) ($config['rows'] ?? 4)), $tpl);
        $tpl = str_replace('{min}', htmlspecialchars((string) ($config['min'] ?? '')), $tpl);
        $tpl = str_replace('{max}', htmlspecialchars((string) ($config['max'] ?? '')), $tpl);

        if (!empty($type['has_options'])) {
            $options = $config['options'] ?? [];

            if ($field['field_type'] === 'select') {
                $options_html = '';
                foreach ($options as $opt) {
                    $selected = (is_array($repop_value) ? in_array($opt['value'], $repop_value, true) : $repop_value === $opt['value']) ? ' selected' : '';
                    $options_html .= '<option value="'.htmlspecialchars($opt['value']).'"'.$selected.'>'.htmlspecialchars($opt['label']).'</option>';
                }
                $tpl = str_replace('{options_html}', $options_html, $tpl);
            } else {
                // radio
                $options_html = '';
                foreach ($options as $i => $opt) {
                    $checked = ($repop_value === $opt['value']) ? ' checked' : '';
                    $option_id = htmlspecialchars($key.'_'.$i);
                    $options_html .= '<div class="form-check">';
                    $options_html .= '<input class="form-check-input" type="radio" name="'.htmlspecialchars($key).'" id="'.$option_id.'" value="'.htmlspecialchars($opt['value']).'"'.$checked.'>';
                    $options_html .= '<label class="form-check-label" for="'.$option_id.'">'.htmlspecialchars($opt['label']).'</label>';
                    $options_html .= '</div>';
                }
                $tpl = str_replace('{options_html}', $options_html, $tpl);
            }
        }

        if (!empty($type['has_upload'])) {
            $allowed = $config['allowed_extensions'] ?? '';
            $tpl = str_replace('{accept}', $allowed !== '' ? '.'.str_replace(',', ',.', $allowed) : '', $tpl);
            $tpl = str_replace('{multiple}', !empty($config['multiple']) ? 'multiple' : '', $tpl);
        } elseif ($field['field_type'] === 'checkbox') {
            $tpl = str_replace('{checked}', $repop_value !== '' ? 'checked' : '', $tpl);
        } elseif ($field['field_type'] === 'hidden') {
            // On a fresh render there's nothing to repopulate yet, so fall
            // back to the admin-configured default_value (e.g. a fixed
            // "form_source" value) - usually left empty for fields an
            // external script fills in client-side (gclid, UTM_*). On a
            // re-render after a failed submission, keep whatever value the
            // visitor's browser had already set, same as any other field.
            $hidden_value = ($repop_value !== '' && !is_array($repop_value)) ? $repop_value : (string) ($config['default_value'] ?? '');
            $tpl = str_replace('{value}', htmlspecialchars($hidden_value), $tpl);
        } else {
            $tpl = str_replace('{value}', htmlspecialchars(is_array($repop_value) ? '' : (string) $repop_value), $tpl);
        }

        $fields_html .= $tpl;
    }

    $fmr_settings = fmr_get_settings();
    if (!empty($form['disable_captcha'])) {
        $captcha_html = '';
    } elseif (($fmr_settings['captcha_type'] ?? 'math') === 'recaptcha_v2') {
        $captcha_html = str_replace(
            '{site_key}',
            htmlspecialchars($fmr_settings['recaptcha_site_key'] ?? ''),
            file_get_contents(fmr_resolve_template('fields/captcha-recaptcha.tpl', $set))
        );
    } else {
        fmr_generate_captcha();
        $captcha_html = str_replace(
            '{captcha_label}',
            htmlspecialchars($_SESSION['fmr_captcha_question']),
            file_get_contents(fmr_resolve_template('fields/captcha-math.tpl', $set))
        );
    }

    $has_upload_field = false;
    foreach ($fields as $field) {
        if (!empty($field_types[$field['field_type']]['has_upload'])) {
            $has_upload_field = true;
            break;
        }
    }

    $wrapper = file_get_contents(fmr_resolve_template('form-wrapper.tpl', $set));
    $wrapper = str_replace('{form_id}', (string) $form_id, $wrapper);
    $wrapper = str_replace('{enctype}', $has_upload_field ? 'enctype="multipart/form-data"' : '', $wrapper);
    $wrapper = str_replace('{banner_html}', $banner_html, $wrapper);
    $wrapper = str_replace('{fields_html}', $fields_html, $wrapper);
    $wrapper = str_replace('{captcha_html}', $captcha_html, $wrapper);
    $wrapper = str_replace('{submit_label}', htmlspecialchars($form['submit_button_label'] ?: 'Senden'), $wrapper);
    $wrapper = str_replace('{hidden_csrf_token}', $hidden_csrf_token, $wrapper);
    $wrapper = str_replace('{sendtime}', (string) time(), $wrapper);
    // The page's slug for the "Seiten-Informationen" checkbox
    // (fmr_build_submission_meta()). On a fresh render this is the current
    // page ($swifty_slug, set by app/routing.php); on a re-render after a
    // failed submission (xhr.php passing $_POST as $repopulate) it must
    // stay the originally submitted value instead - $swifty_slug at that
    // point is the /xhr/plugins/former/ endpoint's own path, not the page
    // the visitor actually filled the form in on.
    $page_slug = $repopulate['fmr_page_slug'] ?? ($swifty_slug ?? '');
    $wrapper = str_replace('{page_slug}', htmlspecialchars((string) $page_slug), $wrapper);

    return $wrapper;
}

/**
 * Toggle button for one collapsed section of backend/reader.php's
 * form_settings screen (name/status/captcha stay always visible above;
 * everything else - appearance, auto-attached data, storage/mail,
 * messages - is collapsed by default so the settings panel isn't one long
 * scroll). Same data-bs-toggle="collapse" pattern already used in
 * plugins/paddle-pay/backend/settings.php. Each section is its own
 * independent collapse, not a true Bootstrap accordion - more than one can
 * be open at once, which is fine since they're unrelated setting groups,
 * not mutually exclusive choices.
 */
function fmr_settings_section_toggle(string $collapse_id, string $title): string {
    return '<button type="button" class="btn btn-default btn-sm w-100 text-start d-flex justify-content-between align-items-center mb-2" data-bs-toggle="collapse" data-bs-target="#'.htmlspecialchars($collapse_id).'" aria-expanded="false" aria-controls="'.htmlspecialchars($collapse_id).'">'
        .htmlspecialchars($title).' <i class="bi bi-chevron-down" aria-hidden="true"></i></button>';
}

/* -------------------------------------------------------------------
 * Admin builder - one canvas row per field. Used both for the initial
 * canvas load (backend/reader.php, show=form_canvas) and for the
 * HTMX "add field" response (backend/writer.php, add_field), so both
 * produce identical markup.
 * ---------------------------------------------------------------- */

function fmr_render_field_row(array $field): string {
    global $addon_lang;

    $id = (int) $field['id'];
    $type_key = $field['field_type'];
    $field_types = fmr_field_types();
    $type = $field_types[$type_key] ?? ['label' => $type_key, 'config_fields' => []];
    $config = json_decode($field['config'] ?? '{}', true) ?: [];

    $html = '<div class="list-group-item draggable" data-id="'.$id.'">';
    // Marker input, present from the first render of every field type (not
    // just is_static rows, which already carry their own hidden
    // field_label/field_key). The admin theme's shared picker JS
    // (public/assets/themes/administration/.../backend.js,
    // observeContainersForDraggableDivs()) auto-adds its own picker_N[]
    // hidden input AND a client-side-only trash button to any .draggable
    // row that has no <input type="hidden"> yet - meant for its generic
    // image-picker use case, not for former's rows, which already have
    // their own server-backed delete button below. That trash button only
    // does `this.parentElement.remove()`, it never calls the server, so it
    // would silently fail to delete the field while looking like it did.
    // Having this marker present up front makes that check true immediately,
    // so core never adds either element. Core JS is left untouched; see
    // fmr_field_order in backend/form-editor.php for how order is tracked
    // instead of the picker_N[] input core would otherwise have supplied.
    $html .= '<input type="hidden" name="fmr_row_marker['.$id.']" value="1">';
    $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
    // bi-grip-vertical is the theme's own drag-handle icon (used elsewhere
    // for SortableJS-draggable rows); purely decorative here since the
    // whole row - not just this icon - is the drag target.
    $html .= '<h6 class="mb-0 d-flex align-items-center gap-2"><i class="bi bi-grip-vertical text-muted" aria-hidden="true"></i>'.htmlspecialchars($type['label']).'</h6>';
    $html .= '<button type="button" class="btn btn-sm btn-default text-danger"
        hx-post="/admin-xhr/addons/plugin/former/write/"
        hx-vals=\'{"delete_field":"'.$id.'","csrf_token":"'.$_SESSION['token'].'"}\'
        hx-target="closest .draggable" hx-swap="outerHTML swap:0s"
        hx-confirm="'.htmlspecialchars($addon_lang['msg_confirm_delete_field'] ?? 'Delete this field?').'">&times;</button>';
    $html .= '</div>';

    $is_static = !empty($type['is_static']);
    // Hidden fields have no visible input for a visitor to leave blank or
    // fill in - "Placeholder" is meaningless (nothing to display it in) and
    // "Required" is actively dangerous: it would block submission entirely
    // whenever the external script (GTM etc.) that's supposed to fill the
    // value doesn't run or doesn't match, with no way for the visitor to
    // notice or work around it. See global/xhr.php's required check, which
    // skips field_type 'hidden' outright for the same reason - this is
    // just keeping the admin UI from offering a checkbox that either does
    // nothing (if xhr.php ignores it) or actively breaks the form.
    $is_hidden = $type_key === 'hidden';

    $html .= '<div class="row g-2">';
    if ($is_static) {
        // Static content blocks aren't inputs - label/field name/required
        // don't apply. Kept as hidden fields (unchanged values) purely so
        // backend/writer.php's save_fields loop, which reads
        // field_label[id]/field_key[id] to know which rows to touch,
        // needs no special-casing for this type.
        $html .= '<input type="hidden" name="field_label['.$id.']" value="'.htmlspecialchars($field['label']).'">';
        $html .= '<input type="hidden" name="field_key['.$id.']" value="'.htmlspecialchars($field['field_key']).'">';
    } else {
        $html .= '<div class="col-md-6"><input type="text" class="form-control form-control-sm" name="field_label['.$id.']" value="'.htmlspecialchars($field['label']).'" placeholder="'.htmlspecialchars($addon_lang['label_field_label'] ?? 'Label').'"></div>';
        $html .= '<div class="col-md-6"><input type="text" class="form-control form-control-sm" name="field_key['.$id.']" value="'.htmlspecialchars($field['field_key']).'" placeholder="'.htmlspecialchars($addon_lang['label_field_key'] ?? 'Field name').'"></div>';
        // For hidden: no field_placeholder[id]/field_required[id]/
        // field_css_class[id] inputs at all (skipped below too) - missing
        // entries already default to ''/0 in backend/writer.php, no hidden
        // fallback input needed to make that explicit.
        if (!$is_hidden) {
            $html .= '<div class="col-md-6"><input type="text" class="form-control form-control-sm" name="field_placeholder['.$id.']" value="'.htmlspecialchars($field['placeholder'] ?? '').'" placeholder="'.htmlspecialchars($addon_lang['label_field_placeholder'] ?? 'Placeholder').'"></div>';
            $html .= '<div class="col-md-6 form-check mt-2"><input type="checkbox" class="form-check-input" name="field_required['.$id.']" value="1" '.($field['required'] ? 'checked' : '').' id="fmr-req-'.$id.'"><label class="form-check-label" for="fmr-req-'.$id.'">'.htmlspecialchars($addon_lang['label_field_required'] ?? 'Required').'</label></div>';
        }
    }

    // Applies to every other field type incl. static text blocks - both
    // wrap their output in a single outer <div>, so a free-text class is
    // always meaningful here, unlike the type-gated config_fields below.
    // Appended to (never replacing) the wrapper's own hard-coded classes at
    // render time - see fmr_render_form()/the {css_class} token in each
    // field .tpl. Hidden fields have no wrapper (hidden.tpl is a bare
    // <input>, no {css_class} token) - nothing for a class to attach to.
    if (!$is_hidden) {
        $html .= '<div class="col-md-12"><input type="text" class="form-control form-control-sm" name="field_css_class['.$id.']" value="'.htmlspecialchars($field['css_class'] ?? '').'" placeholder="'.htmlspecialchars($addon_lang['label_field_css_class'] ?? 'CSS class(es)').'"></div>';
    }

    if (in_array('content', $type['config_fields'] ?? [], true)) {
        $html .= '<div class="col-md-12"><label class="form-label small">'.htmlspecialchars($addon_lang['label_field_content'] ?? 'Text').'</label><textarea class="form-control form-control-sm" rows="4" name="field_content['.$id.']">'.htmlspecialchars($config['content'] ?? '').'</textarea></div>';
    }
    if (in_array('rows', $type['config_fields'] ?? [], true)) {
        $html .= '<div class="col-md-4"><label class="form-label small">'.htmlspecialchars($addon_lang['label_field_rows'] ?? 'Rows').'</label><input type="number" class="form-control form-control-sm" name="field_rows['.$id.']" value="'.htmlspecialchars((string) ($config['rows'] ?? 4)).'"></div>';
    }
    if (in_array('min', $type['config_fields'] ?? [], true)) {
        $html .= '<div class="col-md-4"><label class="form-label small">'.htmlspecialchars($addon_lang['label_field_min'] ?? 'Min').'</label><input type="number" class="form-control form-control-sm" name="field_min['.$id.']" value="'.htmlspecialchars((string) ($config['min'] ?? '')).'"></div>';
        $html .= '<div class="col-md-4"><label class="form-label small">'.htmlspecialchars($addon_lang['label_field_max'] ?? 'Max').'</label><input type="number" class="form-control form-control-sm" name="field_max['.$id.']" value="'.htmlspecialchars((string) ($config['max'] ?? '')).'"></div>';
    }
    if (in_array('options', $type['config_fields'] ?? [], true)) {
        $lines = [];
        foreach (($config['options'] ?? []) as $opt) {
            $lines[] = $opt['value'].'|'.$opt['label'];
        }
        $html .= '<div class="col-md-12"><label class="form-label small">'.htmlspecialchars($addon_lang['label_field_options'] ?? 'Options').'</label><textarea class="form-control form-control-sm" rows="3" name="field_options['.$id.']">'.htmlspecialchars(implode("\n", $lines)).'</textarea></div>';
    }
    if (in_array('allowed_extensions', $type['config_fields'] ?? [], true)) {
        $html .= '<div class="col-md-6"><label class="form-label small">'.htmlspecialchars($addon_lang['label_field_allowed_extensions'] ?? 'Allowed extensions').'</label><input type="text" class="form-control form-control-sm" name="field_ext['.$id.']" value="'.htmlspecialchars($config['allowed_extensions'] ?? '').'"></div>';
    }
    if (in_array('max_size_mb', $type['config_fields'] ?? [], true)) {
        $html .= '<div class="col-md-3"><label class="form-label small">'.htmlspecialchars($addon_lang['label_field_max_size_mb'] ?? 'Max MB').'</label><input type="number" class="form-control form-control-sm" name="field_maxsize['.$id.']" value="'.htmlspecialchars((string) ($config['max_size_mb'] ?? '')).'"></div>';
    }
    if (in_array('multiple', $type['config_fields'] ?? [], true)) {
        $html .= '<div class="col-md-3 form-check mt-2"><input type="checkbox" class="form-check-input" name="field_multiple['.$id.']" value="1" '.(!empty($config['multiple']) ? 'checked' : '').' id="fmr-multi-'.$id.'"><label class="form-check-label" for="fmr-multi-'.$id.'">'.htmlspecialchars($addon_lang['label_field_multiple'] ?? 'Allow multiple').'</label></div>';
    }
    if (in_array('default_value', $type['config_fields'] ?? [], true)) {
        $html .= '<div class="col-md-6"><label class="form-label small">'.htmlspecialchars($addon_lang['label_field_default_value'] ?? 'Default value').'</label><input type="text" class="form-control form-control-sm" name="field_default_value['.$id.']" value="'.htmlspecialchars($config['default_value'] ?? '').'"></div>';
        $html .= '<div class="col-md-12"><div class="form-text">'.htmlspecialchars($addon_lang['hint_field_default_value'] ?? 'Usually left empty for values an external script (e.g. GTM) fills in via matching id/name.').'</div></div>';
    }

    $html .= '</div>'; // row g-2
    $html .= '</div>'; // list-group-item

    return $html;
}
