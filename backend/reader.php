<?php

require __DIR__.'/../global/bootstrap.php';

/* ---------------------------------------------------------------
 * Forms list
 * -------------------------------------------------------------- */
if (isset($_GET['show']) && $_GET['show'] === 'forms_list') {

    $forms = $former_db->select('forms', '*', ['ORDER' => ['id' => 'DESC']]);

    echo '<div class="card p-3">';
    echo '<div class="d-flex justify-content-between align-items-center mb-3">';
    echo '<div></div>';
    echo '<button type="button" class="btn btn-primary btn-sm"
        hx-post="/admin-xhr/addons/plugin/former/write/"
        hx-vals=\'{"create_form":"1","csrf_token":"'.$_SESSION['token'].'"}\'>'.$addon_lang['btn_new_form'].'</button>';
    echo '</div>';

    if (!$forms) {
        echo '<p class="text-muted">'.$addon_lang['msg_no_forms'].'</p>';
    } else {
        echo '<table class="table table-sm table-striped table-hover align-middle">';
        echo '<thead><tr>';
        echo '<th>'.$addon_lang['th_name'].'</th>';
        echo '<th>'.$addon_lang['th_shortcode'].'</th>';
        echo '<th>'.$addon_lang['th_submissions'].'</th>';
        echo '<th class="text-end">'.$addon_lang['th_actions'].'</th>';
        echo '</tr></thead><tbody>';

        foreach ($forms as $form) {
            $count = $former_db->count('submissions', ['form_id' => $form['id']]);
            $shortcode = '[plugin=former]form_id='.$form['id'].'[/plugin]';

            echo '<tr>';
            echo '<td>'.htmlspecialchars($form['name']).($form['status'] ? '' : ' <span class="badge text-bg-secondary">inaktiv</span>').'</td>';
            echo '<td><input type="text" class="form-control form-control-sm" readonly value="'.htmlspecialchars($shortcode).'" onclick="this.select()" style="width:260px"></td>';
            echo '<td>'.$count.'</td>';
            echo '<td class="text-end">';
            echo '<a class="btn btn-sm btn-default" href="/admin/addons/plugin/former/form-editor/?form_id='.$form['id'].'">'.$addon_lang['btn_edit'].'</a> ';
            echo '<a class="btn btn-sm btn-default" href="/admin/addons/plugin/former/submissions/?form_id='.$form['id'].'">'.$addon_lang['btn_submissions'].'</a> ';
            echo '<button type="button" class="btn btn-sm btn-default text-danger"
                hx-post="/admin-xhr/addons/plugin/former/write/"
                hx-vals=\'{"delete_form":"'.$form['id'].'","csrf_token":"'.$_SESSION['token'].'"}\'
                hx-confirm="'.htmlspecialchars($addon_lang['msg_confirm_delete_form']).'"
                hx-target="closest tr" hx-swap="outerHTML swap:0s">'.$addon_lang['btn_delete'].'</button>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    echo '</div>';
    exit;
}

/* ---------------------------------------------------------------
 * Form settings sub-form (name, storage, mail, recipients, messages)
 * -------------------------------------------------------------- */
if (isset($_GET['show']) && $_GET['show'] === 'form_settings') {

    $form_id = (int) ($_GET['form_id'] ?? 0);
    $form = $former_db->get('forms', '*', ['id' => $form_id]);
    if (!$form) { exit; }

    $recipients = $former_db->select('recipients', '*');
    $selected_recipients = json_decode($form['mail_recipients'] ?? '[]', true) ?: [];

    echo '<div id="response"></div>';
    echo '<form hx-post="/admin-xhr/addons/plugin/former/write/" hx-target="#response" hx-swap="innerHTML">';

    echo '<input type="hidden" name="save_form_settings" value="'.$form_id.'">';

    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_form_name'].'</label>';
    echo '<input type="text" class="form-control" name="name" value="'.htmlspecialchars($form['name']).'"></div>';

    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_description'].'</label>';
    echo '<textarea class="form-control" name="description">'.htmlspecialchars($form['description'] ?? '').'</textarea></div>';

    echo '<div class="mb-3 form-check"><input type="checkbox" class="form-check-input" name="status" value="1" id="fmr-status" '.($form['status'] ? 'checked' : '').'>';
    echo '<label class="form-check-label" for="fmr-status">'.$addon_lang['label_status'].'</label></div>';

    echo '<div class="mb-3 form-check"><input type="checkbox" class="form-check-input" name="disable_captcha" value="1" id="fmr-disable-captcha" '.($form['disable_captcha'] ? 'checked' : '').'>';
    echo '<label class="form-check-label" for="fmr-disable-captcha">'.$addon_lang['label_disable_captcha'].'</label>';
    echo '<div class="form-text">'.$addon_lang['hint_disable_captcha'].'</div></div>';

    echo '<hr>';

    echo '<div class="mb-2 form-check"><input type="checkbox" class="form-check-input" name="store_to_db" value="1" id="fmr-store" '.($form['store_to_db'] ? 'checked' : '').'>';
    echo '<label class="form-check-label" for="fmr-store">'.$addon_lang['label_store_to_db'].'</label></div>';

    echo '<div class="mb-2 form-check"><input type="checkbox" class="form-check-input" name="send_mail" value="1" id="fmr-mail" '.($form['send_mail'] ? 'checked' : '').'>';
    echo '<label class="form-check-label" for="fmr-mail">'.$addon_lang['label_send_mail'].'</label></div>';

    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_mail_subject'].'</label>';
    echo '<input type="text" class="form-control" name="mail_subject" value="'.htmlspecialchars($form['mail_subject'] ?? '').'"></div>';

    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_mail_recipients'].'</label>';
    if (!$recipients) {
        echo '<p class="text-muted small">'.$addon_lang['msg_no_recipients'].'</p>';
    }
    foreach ($recipients as $r) {
        $checked = in_array((int) $r['id'], $selected_recipients, true) ? 'checked' : '';
        echo '<div class="form-check"><input type="checkbox" class="form-check-input" name="mail_recipients[]" value="'.$r['id'].'" id="fmr-r-'.$r['id'].'" '.$checked.'>';
        echo '<label class="form-check-label" for="fmr-r-'.$r['id'].'">'.htmlspecialchars($r['name']).' &lt;'.htmlspecialchars($r['email']).'&gt;</label></div>';
    }
    echo '</div>';

    echo '<hr>';

    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_success_message'].'</label>';
    echo '<textarea class="form-control" name="success_message">'.htmlspecialchars($form['success_message'] ?? '').'</textarea></div>';

    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_error_message'].'</label>';
    echo '<textarea class="form-control" name="error_message">'.htmlspecialchars($form['error_message'] ?? '').'</textarea></div>';

    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_submit_button_label'].'</label>';
    echo '<input type="text" class="form-control" name="submit_button_label" value="'.htmlspecialchars($form['submit_button_label'] ?? '').'"></div>';

    echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['token'].'">';
    echo '<button type="submit" class="btn btn-primary">'.$addon_lang['btn_save'].'</button>';
    echo '</form>';
    exit;
}

/* ---------------------------------------------------------------
 * Recipients management
 * -------------------------------------------------------------- */
if (isset($_GET['show']) && $_GET['show'] === 'recipients') {

    $recipients = $former_db->select('recipients', '*', ['ORDER' => ['id' => 'ASC']]);

    echo '<div id="response"></div>';

    if (!$recipients) {
        echo '<p class="text-muted">'.$addon_lang['msg_no_recipients'].'</p>';
    } else {
        echo '<table class="table table-sm table-striped">';
        echo '<thead><tr><th>'.$addon_lang['th_recipient_name'].'</th><th>'.$addon_lang['th_recipient_email'].'</th><th></th></tr></thead><tbody>';
        foreach ($recipients as $r) {
            echo '<tr>';
            echo '<td>'.htmlspecialchars($r['name']).'</td>';
            echo '<td>'.htmlspecialchars($r['email']).'</td>';
            echo '<td class="text-end"><button type="button" class="btn btn-sm btn-default text-danger"
                hx-post="/admin-xhr/addons/plugin/former/write/"
                hx-vals=\'{"delete_recipient":"'.$r['id'].'","csrf_token":"'.$_SESSION['token'].'"}\'
                hx-confirm="'.htmlspecialchars($addon_lang['msg_confirm_delete_recipient']).'"
                hx-target="closest tr" hx-swap="outerHTML swap:0s">'.$addon_lang['btn_delete'].'</button></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '<form hx-post="/admin-xhr/addons/plugin/former/write/" hx-target="#response" hx-swap="innerHTML" class="row g-2 align-items-end">';
    echo '<div class="col-md-5"><label class="form-label small">'.$addon_lang['label_recipient_name'].'</label><input type="text" class="form-control form-control-sm" name="recipient_name" required></div>';
    echo '<div class="col-md-5"><label class="form-label small">'.$addon_lang['label_recipient_email'].'</label><input type="email" class="form-control form-control-sm" name="recipient_email" required></div>';
    echo '<div class="col-md-2"><input type="hidden" name="add_recipient" value="1">';
    echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['token'].'">';
    echo '<button type="submit" class="btn btn-sm btn-primary w-100">'.$addon_lang['btn_add_recipient'].'</button></div>';
    echo '</form>';
    exit;
}

/* ---------------------------------------------------------------
 * Captcha + upload settings form
 * -------------------------------------------------------------- */
if (isset($_GET['show']) && $_GET['show'] === 'settings_form') {

    $settings = fmr_get_settings();
    $captcha_type = $settings['captcha_type'] ?? 'math';

    echo '<div id="response"></div>';
    echo '<form hx-post="/admin-xhr/addons/plugin/former/write/" hx-target="#response" hx-swap="innerHTML">';

    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_captcha_type'].'</label>';
    echo '<select class="form-select" name="captcha_type" id="fmr-captcha-type" onchange="document.getElementById(\'fmr-recaptcha-keys\').classList.toggle(\'d-none\', this.value !== \'recaptcha_v2\')">';
    echo '<option value="math" '.($captcha_type === 'math' ? 'selected' : '').'>'.$addon_lang['option_captcha_math'].'</option>';
    echo '<option value="recaptcha_v2" '.($captcha_type === 'recaptcha_v2' ? 'selected' : '').'>'.$addon_lang['option_captcha_recaptcha'].'</option>';
    echo '</select></div>';

    echo '<div id="fmr-recaptcha-keys" class="'.($captcha_type === 'recaptcha_v2' ? '' : 'd-none').'">';
    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_recaptcha_site_key'].'</label>';
    echo '<input type="text" class="form-control" name="recaptcha_site_key" value="'.htmlspecialchars($settings['recaptcha_site_key'] ?? '').'"></div>';
    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_recaptcha_secret_key'].'</label>';
    echo '<input type="text" class="form-control" name="recaptcha_secret_key" value="'.htmlspecialchars($settings['recaptcha_secret_key'] ?? '').'"></div>';
    echo '</div>';

    echo '<hr>';

    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_max_upload_size_mb'].'</label>';
    echo '<input type="number" class="form-control" name="max_upload_size_mb" value="'.htmlspecialchars($settings['max_upload_size_mb'] ?? '5').'"></div>';

    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_allowed_upload_extensions'].'</label>';
    echo '<input type="text" class="form-control" name="allowed_upload_extensions" value="'.htmlspecialchars($settings['allowed_upload_extensions'] ?? '').'"></div>';

    echo '<input type="hidden" name="save_settings" value="1">';
    echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['token'].'">';
    echo '<button type="submit" class="btn btn-primary">'.$addon_lang['btn_save'].'</button>';
    echo '</form>';
    exit;
}

/* ---------------------------------------------------------------
 * Submissions list for a form (simple offset pagination)
 * -------------------------------------------------------------- */
if (isset($_GET['show']) && $_GET['show'] === 'submissions') {

    $form_id = (int) ($_GET['form_id'] ?? 0);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = 20;

    $fields = $former_db->select('fields', ['field_key', 'label'], ['form_id' => $form_id]);
    $labels = array_column($fields, 'label', 'field_key');

    $total = $former_db->count('submissions', ['form_id' => $form_id]);
    $submissions = $former_db->select('submissions', '*', [
        'form_id' => $form_id,
        'ORDER' => ['id' => 'DESC'],
        'LIMIT' => [($page - 1) * $per_page, $per_page],
    ]);

    if (!$submissions) {
        echo '<p class="text-muted">'.$addon_lang['msg_no_submissions'].'</p>';
        exit;
    }

    foreach ($submissions as $submission) {
        $data = json_decode($submission['data'], true) ?: [];
        $files = $former_db->select('submission_files', '*', ['submission_id' => $submission['id']]);

        echo '<div class="card mb-2"><div class="card-body">';
        echo '<div class="text-muted small mb-2">'.$addon_lang['th_submitted_at'].': '.htmlspecialchars($submission['created_at']).' — '.htmlspecialchars($submission['ip_address'] ?? '').'</div>';
        echo '<table class="table table-sm mb-2">';
        foreach ($data as $key => $value) {
            $label = $labels[$key] ?? $key;
            echo '<tr><td class="fw-bold" style="width:30%">'.htmlspecialchars($label).'</td><td>'.htmlspecialchars(is_array($value) ? implode(', ', $value) : (string) $value).'</td></tr>';
        }
        echo '</table>';

        if ($files) {
            echo '<div>'.$addon_lang['th_files'].': ';
            foreach ($files as $f) {
                echo '<a class="btn btn-sm btn-default me-1" href="/xhr/plugins/former/?download_file='.$f['id'].'">'.htmlspecialchars($f['original_filename']).'</a>';
            }
            echo '</div>';
        }

        echo '</div></div>';
    }

    $total_pages = (int) ceil($total / $per_page);
    if ($total_pages > 1) {
        echo '<nav><ul class="pagination">';
        for ($p = 1; $p <= $total_pages; $p++) {
            $active = $p === $page ? ' active' : '';
            echo '<li class="page-item'.$active.'"><a class="page-link" href="#"
                hx-get="/admin-xhr/addons/plugin/former/read/?show=submissions&form_id='.$form_id.'&page='.$p.'"
                hx-target="#formSubmissionsList" hx-swap="innerHTML">'.$p.'</a></li>';
        }
        echo '</ul></nav>';
    }
    exit;
}

exit;
