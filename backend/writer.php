<?php

require __DIR__.'/../global/bootstrap.php';

/* ---------------------------------------------------------------
 * Forms
 * -------------------------------------------------------------- */

if (isset($_POST['create_form'])) {
    $former_db->insert('forms', [
        'name' => 'Neues Formular',
        'submit_button_label' => 'Senden',
    ]);
    $new_id = $former_db->id();
    header('HX-Redirect: /admin/addons/plugin/former/form-editor/?form_id='.$new_id);
    exit;
}

if (isset($_POST['delete_form'])) {
    $form_id = (int) $_POST['delete_form'];

    $submission_ids = array_column($former_db->select('submissions', ['id'], ['form_id' => $form_id]), 'id');
    if ($submission_ids) {
        $former_db->delete('submission_files', ['submission_id' => $submission_ids]);
    }
    $former_db->delete('submissions', ['form_id' => $form_id]);
    $former_db->delete('fields', ['form_id' => $form_id]);
    $former_db->delete('forms', ['id' => $form_id]);

    $upload_dir = __DIR__.'/../uploads/'.$form_id;
    if (is_dir($upload_dir)) {
        foreach (glob($upload_dir.'/*') as $file) {
            @unlink($file);
        }
        @rmdir($upload_dir);
    }

    header('HX-Trigger: update_former_forms');
    exit;
}

if (isset($_POST['save_form_settings'])) {
    $form_id = (int) $_POST['save_form_settings'];

    $recipients = array_map('intval', $_POST['mail_recipients'] ?? []);

    // Re-validated against the actual folder list rather than trusted from
    // $_POST as-is - a stale/tampered value would otherwise silently fall
    // back to the default templates anyway (fmr_resolve_template() checks
    // is_file() too), but storing only a known-good slug keeps the form
    // settings screen honest about what's actually selected.
    $posted_set = $_POST['template_set'] ?? '';
    $template_set = in_array($posted_set, fmr_list_template_sets(), true) ? $posted_set : '';

    // A pending (unconfirmed) submission needs somewhere durable to live
    // between submit and confirm-click - "Bestätigung per E-Mail" implies
    // "In Datenbank speichern" regardless of what that checkbox itself was
    // set to, rather than leaving an unreachable combination the admin
    // could otherwise pick (require_confirmation on, store_to_db off) that
    // would silently never confirm anything.
    $require_confirmation = isset($_POST['require_confirmation']) ? 1 : 0;
    $store_to_db = ($require_confirmation || isset($_POST['store_to_db'])) ? 1 : 0;

    // Only accept a confirm_email_field that actually names one of this
    // form's own 'email' fields - same re-validate-against-reality idiom as
    // template_set above. An empty value is valid too (auto-detect the
    // first email field at submit time, see fmr_confirm_email_field_key()).
    $email_field_keys = array_column(
        array_filter($former_db->select('fields', ['field_key', 'field_type'], ['form_id' => $form_id]), fn ($f) => $f['field_type'] === 'email'),
        'field_key'
    );
    $posted_confirm_field = $_POST['confirm_email_field'] ?? '';
    $confirm_email_field = in_array($posted_confirm_field, $email_field_keys, true) ? $posted_confirm_field : '';

    $former_db->update('forms', [
        'name' => sanitizeUserInputs($_POST['name'] ?? ''),
        'description' => sanitizeUserInputs($_POST['description'] ?? ''),
        'status' => isset($_POST['status']) ? 1 : 0,
        'disable_captcha' => isset($_POST['disable_captcha']) ? 1 : 0,
        'include_user_data' => isset($_POST['include_user_data']) ? 1 : 0,
        'include_ip_referrer' => isset($_POST['include_ip_referrer']) ? 1 : 0,
        'include_page_info' => isset($_POST['include_page_info']) ? 1 : 0,
        'store_to_db' => $store_to_db,
        'send_mail' => isset($_POST['send_mail']) ? 1 : 0,
        'mail_subject' => sanitizeUserInputs($_POST['mail_subject'] ?? ''),
        'mail_recipients' => json_encode($recipients),
        'success_message' => sanitizeUserInputs($_POST['success_message'] ?? ''),
        'error_message' => sanitizeUserInputs($_POST['error_message'] ?? ''),
        'submit_button_label' => sanitizeUserInputs($_POST['submit_button_label'] ?? ''),
        'template_set' => $template_set,
        'require_confirmation' => $require_confirmation,
        'confirm_email_field' => $confirm_email_field,
        'confirm_mail_subject' => sanitizeUserInputs($_POST['confirm_mail_subject'] ?? ''),
        // Whitelist-strip, not sanitizeUserInputs() - same reasoning as a
        // text_block's 'content' above: this is admin-authored HTML meant
        // to render (it needs at least an <a>), not visitor input.
        'confirm_mail_body' => trim(strip_tags($_POST['confirm_mail_body'] ?? '', fmr_allowed_content_tags())),
        'confirmed_message' => sanitizeUserInputs($_POST['confirmed_message'] ?? ''),
        'confirm_expires_hours' => max(1, (int) ($_POST['confirm_expires_hours'] ?? 48)),
        'updated_at' => date('Y-m-d H:i:s'),
    ], ['id' => $form_id]);

    echo '<div class="alert alert-success">'.$addon_lang['msg_saved'].'</div>';
    header('HX-Trigger: update_former_forms');
    exit;
}

/* ---------------------------------------------------------------
 * Fields (drag & drop builder)
 * -------------------------------------------------------------- */

if (isset($_POST['add_field'])) {
    $form_id = (int) ($_POST['form_id'] ?? 0);
    $field_type = $_POST['field_type'] ?? '';
    $field_types = fmr_field_types();

    if (!$form_id || !isset($field_types[$field_type])) {
        exit;
    }

    $max_order = $former_db->max('fields', 'sort_order', ['form_id' => $form_id]);

    $former_db->insert('fields', [
        'form_id' => $form_id,
        'field_type' => $field_type,
        'field_key' => 'field',
        'label' => $field_types[$field_type]['label'],
        'required' => 0,
        'sort_order' => ((int) $max_order) + 1,
        'config' => '{}',
    ]);
    $new_id = $former_db->id();

    // field_key derived from the row's own id, guaranteeing uniqueness
    // within the form (ids are unique across the whole table).
    $former_db->update('fields', ['field_key' => $field_type.'_'.$new_id], ['id' => $new_id]);

    $field = $former_db->get('fields', '*', ['id' => $new_id]);
    // out-of-band removal of the "no fields yet" hint, if this was the
    // form's first field (hx-swap="beforeend" only ever appends, it can't
    // remove the now-stale empty-state hint by itself)
    echo '<p id="formCanvasEmptyHint" hx-swap-oob="delete"></p>';
    echo fmr_render_field_row($field);
    exit;
}

if (isset($_POST['delete_field'])) {
    $field_id = (int) $_POST['delete_field'];
    $former_db->delete('fields', ['id' => $field_id]);
    exit;
}

if (isset($_POST['save_fields'])) {
    $form_id = (int) $_POST['save_fields'];
    // fmr_field_order, not the admin theme's picker_0[] - see the
    // MutationObserver in backend/form-editor.php for why.
    $order_raw = $_POST['fmr_field_order'] ?? '';
    $order = $order_raw !== '' ? explode(',', $order_raw) : [];

    $labels = $_POST['field_label'] ?? [];
    $keys = $_POST['field_key'] ?? [];
    $placeholders = $_POST['field_placeholder'] ?? [];
    $required = $_POST['field_required'] ?? [];
    $css_classes = $_POST['field_css_class'] ?? [];
    $rows = $_POST['field_rows'] ?? [];
    $mins = $_POST['field_min'] ?? [];
    $maxs = $_POST['field_max'] ?? [];
    $options_raw = $_POST['field_options'] ?? [];
    $extensions = $_POST['field_ext'] ?? [];
    $maxsizes = $_POST['field_maxsize'] ?? [];
    $multiples = $_POST['field_multiple'] ?? [];
    $contents = $_POST['field_content'] ?? [];
    $default_values = $_POST['field_default_value'] ?? [];
    $log_consents = $_POST['field_log_consent'] ?? [];

    $field_types = fmr_field_types();
    $fields = $former_db->select('fields', '*', ['form_id' => $form_id]);
    $fields_by_id = array_column($fields, null, 'id');

    // Process every field belonging to this form, not just the ones
    // present in fmr_field_order - a field missing from it for any reason
    // would otherwise be silently skipped and its edits (incl. a
    // text_block's content) lost. fmr_field_order still drives the order
    // for fields it does list; anything missing from it is appended at
    // the end.
    $ordered_ids = array_map('intval', $order);
    $missing_ids = array_diff(array_map('intval', array_keys($fields_by_id)), $ordered_ids);
    $ordered_ids = array_merge($ordered_ids, $missing_ids);

    $position = 0;
    $used_keys = [];
    foreach ($ordered_ids as $field_id) {
        $field_id = (int) $field_id;
        // Only require the field to actually belong to this form (an
        // authoritative DB-backed check) - NOT that field_label[id] was
        // present in $_POST. That used to gate processing entirely, but
        // a field can legitimately submit field_content[id]/field_key[id]
        // etc. without field_label[id] arriving (observed in practice for
        // a text_block row), which silently dropped its edits.
        if (!isset($fields_by_id[$field_id])) {
            continue;
        }

        $type_key = $fields_by_id[$field_id]['field_type'];
        $type = $field_types[$type_key] ?? ['config_fields' => []];
        $config_fields = $type['config_fields'] ?? [];
        $config = [];

        if (in_array('rows', $config_fields, true)) {
            $config['rows'] = (int) ($rows[$field_id] ?? 4);
        }
        if (in_array('min', $config_fields, true)) {
            $config['min'] = $mins[$field_id] ?? '';
            $config['max'] = $maxs[$field_id] ?? '';
        }
        if (in_array('options', $config_fields, true)) {
            $lines = preg_split('/\r\n|\r|\n/', trim($options_raw[$field_id] ?? ''));
            $options = [];
            foreach ($lines as $line) {
                if (trim($line) === '') { continue; }
                if (str_contains($line, '|')) {
                    [$val, $lbl] = explode('|', $line, 2);
                } else {
                    $val = $lbl = $line;
                }
                $options[] = ['value' => trim(sanitizeUserInputs($val)), 'label' => trim(sanitizeUserInputs($lbl))];
            }
            $config['options'] = $options;
        }
        if (in_array('allowed_extensions', $config_fields, true)) {
            $config['allowed_extensions'] = sanitizeUserInputs($extensions[$field_id] ?? '');
        }
        if (in_array('max_size_mb', $config_fields, true)) {
            $config['max_size_mb'] = (int) ($maxsizes[$field_id] ?? 0);
        }
        if (in_array('multiple', $config_fields, true)) {
            $config['multiple'] = isset($multiples[$field_id]) ? 1 : 0;
        }
        if (in_array('default_value', $config_fields, true)) {
            $config['default_value'] = sanitizeUserInputs($default_values[$field_id] ?? '');
        }
        if (in_array('log_consent', $config_fields, true)) {
            $config['log_consent'] = isset($log_consents[$field_id]) ? 1 : 0;
        }
        if (in_array('content', $config_fields, true)) {
            // Whitelist-strip (not sanitizeUserInputs() - that also runs
            // htmlspecialchars(), which would turn the allowed tags into
            // visible text). Admin-authored content, held to the same
            // trust level as other admin-authored HTML in this CMS.
            $config['content'] = trim(strip_tags($contents[$field_id] ?? '', fmr_allowed_content_tags()));
        }

        // Fall back to the field's existing stored label/key when not
        // present in $_POST, rather than overwriting with an empty string.
        $label = isset($labels[$field_id]) ? sanitizeUserInputs($labels[$field_id]) : $fields_by_id[$field_id]['label'];
        $key = isset($keys[$field_id])
            ? (preg_replace('/[^a-zA-Z0-9_]/', '_', sanitizeUserInputs($keys[$field_id])) ?: 'field_'.$field_id)
            : $fields_by_id[$field_id]['field_key'];

        // A field_key is admin-typed free text with no uniqueness constraint
        // of its own; two fields sharing one would silently overwrite each
        // other's value in $clean[$key] during submission processing
        // (global/xhr.php). Disambiguate duplicates deterministically so no
        // answer is ever silently dropped - the admin will see the
        // suffixed key next time they open the editor and can rename it.
        if (isset($used_keys[$key])) {
            $key .= '_'.$field_id;
        }
        $used_keys[$key] = true;

        $former_db->update('fields', [
            'label' => $label,
            'field_key' => $key,
            'placeholder' => sanitizeUserInputs($placeholders[$field_id] ?? ''),
            'required' => isset($required[$field_id]) ? 1 : 0,
            'css_class' => sanitizeUserInputs($css_classes[$field_id] ?? ''),
            'sort_order' => $position,
            'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
        ], ['id' => $field_id]);

        $position++;
    }

    echo '<div class="alert alert-success">'.$addon_lang['msg_saved'].'</div>';
    exit;
}

/* ---------------------------------------------------------------
 * Submissions
 * -------------------------------------------------------------- */

if (isset($_POST['delete_submission'])) {
    $submission_id = (int) $_POST['delete_submission'];
    $submission = $former_db->get('submissions', ['id', 'form_id'], ['id' => $submission_id]);

    if ($submission) {
        // Uploaded files live at uploads/<form_id>/<stored_filename> (see
        // global/xhr.php) - form_id isn't on submission_files itself, only
        // on the parent submission, hence fetching it above.
        $upload_dir = __DIR__.'/../uploads/'.$submission['form_id'].'/';
        $files = $former_db->select('submission_files', ['stored_filename'], ['submission_id' => $submission_id]);
        foreach ($files as $f) {
            @unlink($upload_dir.$f['stored_filename']);
        }

        $former_db->delete('submission_files', ['submission_id' => $submission_id]);
        $former_db->delete('submissions', ['id' => $submission_id]);
    }

    // Reader.php removes the card client-side via hx-swap on this same
    // response - nothing to render, an empty 200 is enough.
    exit;
}

if (isset($_POST['confirm_submission'])) {
    $submission_id = (int) $_POST['confirm_submission'];
    // Manual override for edge cases the automated confirm-link can't cover
    // (e.g. a verbally-confirmed phone signup) - shares its whole effect
    // (confirmed_at + notification mail) with a real confirm-click via
    // fmr_confirm_pending_submission(). Already-confirmed / unknown ids are
    // silently ignored (false return) rather than erroring - the card
    // re-render below is correct either way.
    fmr_confirm_pending_submission($submission_id);

    $submission = $former_db->get('submissions', '*', ['id' => $submission_id]);
    if ($submission) {
        $labels = array_column($former_db->select('fields', ['field_key', 'label'], ['form_id' => $submission['form_id']]), 'label', 'field_key');
        $form = $former_db->get('forms', '*', ['id' => $submission['form_id']]);
        $form_names = $form ? [$form['id'] => $form['name']] : [];
        echo fmr_render_submission_card($submission, false, $labels, $form_names, fmr_meta_labels(), $form);
    }
    exit;
}

/* ---------------------------------------------------------------
 * Recipients
 * -------------------------------------------------------------- */

if (isset($_POST['add_recipient'])) {
    $name = sanitizeUserInputs($_POST['recipient_name'] ?? '');
    $email = sanitizeUserInputs($_POST['recipient_email'] ?? '');

    if ($name !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $former_db->insert('recipients', ['name' => $name, 'email' => $email]);
    }

    header('HX-Trigger: update_former_recipients, update_former_form_settings');
    exit;
}

if (isset($_POST['delete_recipient'])) {
    $recipient_id = (int) $_POST['delete_recipient'];
    $former_db->delete('recipients', ['id' => $recipient_id]);
    header('HX-Trigger: update_former_form_settings');
    exit;
}

/* ---------------------------------------------------------------
 * Plugin settings (captcha, upload defaults)
 * -------------------------------------------------------------- */

if (isset($_POST['save_settings'])) {
    $keys = ['captcha_type', 'recaptcha_site_key', 'recaptcha_secret_key', 'max_upload_size_mb', 'allowed_upload_extensions'];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) {
            fmr_save_setting($key, sanitizeUserInputs($_POST[$key]));
        }
    }

    echo '<div class="alert alert-success">'.$addon_lang['msg_saved'].'</div>';
    exit;
}

exit;
