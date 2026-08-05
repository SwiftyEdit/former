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
 * Form rendering - shared between the shortcode entry point
 * (plugins/former/index.php) and the error/re-render paths in
 * global/xhr.php, so a failed submission re-renders identically
 * with the visitor's previous input preserved.
 * ---------------------------------------------------------------- */

function fmr_render_form(int $form_id, ?array $repopulate = null, string $banner_html = ''): string {
    global $former_db, $hidden_csrf_token;

    $form = $former_db->get('forms', '*', ['id' => $form_id]);

    if (!$form || (int) $form['status'] !== 1) {
        return '<div class="alert alert-warning">Formular nicht verfügbar.</div>';
    }

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

        $tpl_file = __DIR__.'/../templates/fields/'.$type['template'];
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
            $fields_html .= str_replace('{content}', nl2br($content), $tpl);
            continue;
        }

        $repop_value = $repopulate[$key] ?? '';

        $tpl = str_replace('{name}', htmlspecialchars($key), $tpl);
        $tpl = str_replace('{label}', htmlspecialchars($field['label']), $tpl);
        $tpl = str_replace('{placeholder}', htmlspecialchars($field['placeholder'] ?? ''), $tpl);
        $tpl = str_replace('{required}', $field['required'] ? 'required' : '', $tpl);
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
            file_get_contents(__DIR__.'/../templates/fields/captcha-recaptcha.tpl')
        );
    } else {
        fmr_generate_captcha();
        $captcha_html = str_replace(
            '{captcha_label}',
            htmlspecialchars($_SESSION['fmr_captcha_question']),
            file_get_contents(__DIR__.'/../templates/fields/captcha-math.tpl')
        );
    }

    $has_upload_field = false;
    foreach ($fields as $field) {
        if (!empty($field_types[$field['field_type']]['has_upload'])) {
            $has_upload_field = true;
            break;
        }
    }

    $wrapper = file_get_contents(__DIR__.'/../templates/form-wrapper.tpl');
    $wrapper = str_replace('{form_id}', (string) $form_id, $wrapper);
    $wrapper = str_replace('{enctype}', $has_upload_field ? 'enctype="multipart/form-data"' : '', $wrapper);
    $wrapper = str_replace('{banner_html}', $banner_html, $wrapper);
    $wrapper = str_replace('{fields_html}', $fields_html, $wrapper);
    $wrapper = str_replace('{captcha_html}', $captcha_html, $wrapper);
    $wrapper = str_replace('{submit_label}', htmlspecialchars($form['submit_button_label'] ?: 'Senden'), $wrapper);
    $wrapper = str_replace('{hidden_csrf_token}', $hidden_csrf_token, $wrapper);
    $wrapper = str_replace('{sendtime}', (string) time(), $wrapper);

    return $wrapper;
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
    $html .= '<strong>'.htmlspecialchars($type['label']).'</strong>';
    $html .= '<button type="button" class="btn btn-sm btn-default text-danger"
        hx-post="/admin-xhr/addons/plugin/former/write/"
        hx-vals=\'{"delete_field":"'.$id.'","csrf_token":"'.$_SESSION['token'].'"}\'
        hx-target="closest .draggable" hx-swap="outerHTML swap:0s"
        hx-confirm="'.htmlspecialchars($addon_lang['msg_confirm_delete_field'] ?? 'Delete this field?').'">&times;</button>';
    $html .= '</div>';

    $is_static = !empty($type['is_static']);

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
        $html .= '<div class="col-md-6"><input type="text" class="form-control form-control-sm" name="field_placeholder['.$id.']" value="'.htmlspecialchars($field['placeholder'] ?? '').'" placeholder="'.htmlspecialchars($addon_lang['label_field_placeholder'] ?? 'Placeholder').'"></div>';
        $html .= '<div class="col-md-6 form-check mt-2"><input type="checkbox" class="form-check-input" name="field_required['.$id.']" value="1" '.($field['required'] ? 'checked' : '').' id="fmr-req-'.$id.'"><label class="form-check-label" for="fmr-req-'.$id.'">'.htmlspecialchars($addon_lang['label_field_required'] ?? 'Required').'</label></div>';
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

    $html .= '</div>'; // row g-2
    $html .= '</div>'; // list-group-item

    return $html;
}
