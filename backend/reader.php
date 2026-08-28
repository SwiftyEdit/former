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

    // Everything below was previously a flat list of sections separated by
    // <hr>, making the settings form very long once a form has recipients
    // etc. - now each former-<hr> section is a collapsed-by-default
    // Bootstrap collapse instead (same data-bs-toggle pattern already used
    // in plugins/paddle-pay/backend/settings.php), so only name/status/
    // captcha stay always visible.
    echo fmr_settings_section_toggle('fmrCollapseTemplate', $addon_lang['title_template_set']);
    echo '<div class="collapse mb-3" id="fmrCollapseTemplate">';
    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_template_set'].'</label>';
    echo '<select class="form-select" name="template_set">';
    echo '<option value="">'.$addon_lang['option_template_set_default'].'</option>';
    foreach (fmr_list_template_sets() as $set_slug) {
        $selected = ($form['template_set'] ?? '') === $set_slug ? 'selected' : '';
        echo '<option value="'.htmlspecialchars($set_slug).'" '.$selected.'>'.htmlspecialchars($set_slug).'</option>';
    }
    echo '</select>';
    echo '<div class="form-text">'.$addon_lang['hint_template_set'].'</div></div>';
    echo '</div>';

    // Auto-attached data: sent on top of the actual form fields, into all
    // three sinks (submissions table, notification mail, former:submitted
    // JS event - see fmr_build_submission_meta() / global/xhr.php). Not
    // configurable beyond on/off by design - see xhr.php for the fixed set
    // of keys each checkbox adds.
    echo fmr_settings_section_toggle('fmrCollapseAutoData', $addon_lang['title_auto_data']);
    echo '<div class="collapse mb-3" id="fmrCollapseAutoData">';

    echo '<div class="mb-2 form-check"><input type="checkbox" class="form-check-input" name="include_user_data" value="1" id="fmr-include-user" '.($form['include_user_data'] ? 'checked' : '').'>';
    echo '<label class="form-check-label" for="fmr-include-user">'.$addon_lang['label_include_user_data'].'</label></div>';

    echo '<div class="mb-2 form-check"><input type="checkbox" class="form-check-input" name="include_ip_referrer" value="1" id="fmr-include-ip" '.($form['include_ip_referrer'] ? 'checked' : '').'>';
    echo '<label class="form-check-label" for="fmr-include-ip">'.$addon_lang['label_include_ip_referrer'].'</label>';
    echo '<div class="form-text">'.$addon_lang['hint_include_ip_referrer'].'</div></div>';

    echo '<div class="mb-3 form-check"><input type="checkbox" class="form-check-input" name="include_page_info" value="1" id="fmr-include-page-info" '.($form['include_page_info'] ? 'checked' : '').'>';
    echo '<label class="form-check-label" for="fmr-include-page-info">'.$addon_lang['label_include_page_info'].'</label>';
    echo '<div class="form-text">'.$addon_lang['hint_include_page_info'].'</div></div>';
    echo '</div>';

    echo fmr_settings_section_toggle('fmrCollapseStorage', $addon_lang['title_storage_mail']);
    echo '<div class="collapse mb-3" id="fmrCollapseStorage">';

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
    echo '</div>';

    echo fmr_settings_section_toggle('fmrCollapseMessages', $addon_lang['title_messages']);
    echo '<div class="collapse mb-3" id="fmrCollapseMessages">';

    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_success_message'].'</label>';
    echo '<textarea class="form-control" name="success_message">'.htmlspecialchars($form['success_message'] ?? '').'</textarea></div>';

    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_error_message'].'</label>';
    echo '<textarea class="form-control" name="error_message">'.htmlspecialchars($form['error_message'] ?? '').'</textarea></div>';

    echo '<div class="mb-3"><label class="form-label">'.$addon_lang['label_submit_button_label'].'</label>';
    echo '<input type="text" class="form-control" name="submit_button_label" value="'.htmlspecialchars($form['submit_button_label'] ?? '').'"></div>';
    echo '</div>';

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

    // form_id=0 means "all forms" (the top-level Einsendungen tab's
    // default filter, backend/submissions.php) - a submitted field_key can
    // mean something different (or nothing) in another form, so the label
    // lookup has to stay per-form rather than one flat map. Fetched
    // unconditionally across every form either way, not just when
    // filtering - one code path for both cases, and cheap enough for an
    // admin list capped at $per_page rows per page.
    $all_fields = $former_db->select('fields', ['form_id', 'field_key', 'label']);
    $labels_by_form = [];
    foreach ($all_fields as $f) {
        $labels_by_form[(int) $f['form_id']][$f['field_key']] = $f['label'];
    }
    $form_names = array_column($former_db->select('forms', ['id', 'name']), 'name', 'id');
    $meta_labels = fmr_meta_labels();

    $submission_conditions = $form_id > 0 ? ['form_id' => $form_id] : [];
    $total = $former_db->count('submissions', $submission_conditions);
    $submissions = $former_db->select('submissions', '*', $submission_conditions + [
        'ORDER' => ['id' => 'DESC'],
        'LIMIT' => [($page - 1) * $per_page, $per_page],
    ]);

    if (!$submissions) {
        echo '<p class="text-muted">'.$addon_lang['msg_no_submissions'].'</p>';
        exit;
    }

    foreach ($submissions as $submission) {
        $data = json_decode($submission['data'], true) ?: [];
        $meta = json_decode($submission['meta'] ?? '', true) ?: [];
        $files = $former_db->select('submission_files', '*', ['submission_id' => $submission['id']]);
        $labels = $labels_by_form[(int) $submission['form_id']] ?? [];

        echo '<div class="card mb-2"><div class="card-body">';
        echo '<div class="d-flex justify-content-between align-items-start mb-2">';
        echo '<div class="text-muted small">';
        // Which form this belongs to only needs saying in the unfiltered
        // "all forms" view - filtered to one form, it's already implied by
        // the filter dropdown in submissions.php.
        if ($form_id === 0) {
            $form_name = $form_names[(int) $submission['form_id']] ?? ('#'.$submission['form_id']);
            echo '<span class="badge text-bg-secondary me-2">'.htmlspecialchars($form_name).'</span>';
        }
        echo $addon_lang['th_submitted_at'].': '.htmlspecialchars($submission['created_at']).' — '.htmlspecialchars($submission['ip_address'] ?? '').'</div>';
        // Spam etc. - deletes the submission row, its meta, any uploaded
        // files (DB rows + the actual files on disk) in one go, see
        // "delete_submission" in backend/writer.php. hx-swap removes just
        // this card, no full-list reload needed.
        echo '<button type="button" class="btn btn-sm btn-default text-danger flex-shrink-0"
            hx-post="/admin-xhr/addons/plugin/former/write/"
            hx-vals=\'{"delete_submission":"'.$submission['id'].'","csrf_token":"'.$_SESSION['token'].'"}\'
            hx-confirm="'.htmlspecialchars($addon_lang['msg_confirm_delete_submission']).'"
            hx-target="closest .card" hx-swap="outerHTML swap:0s">'.$addon_lang['btn_delete'].'</button>';
        echo '</div>'; // d-flex
        echo '<table class="table table-sm mb-2">';
        foreach ($data as $key => $value) {
            $label = $labels[$key] ?? $key;
            echo '<tr><td class="fw-bold" style="width:30%">'.htmlspecialchars($label).'</td><td>'.htmlspecialchars(is_array($value) ? implode(', ', $value) : (string) $value).'</td></tr>';
        }
        // Auto-attached data (fmr_build_submission_meta()), rendered muted
        // to visually set it apart from the admin-defined fields above.
        foreach ($meta as $key => $value) {
            $label = $meta_labels[$key] ?? $key;
            echo '<tr class="text-muted"><td class="fw-bold" style="width:30%">'.htmlspecialchars($label).'</td><td>'.htmlspecialchars((string) ($value ?? '')).'</td></tr>';
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

/* ---------------------------------------------------------------
 * Help tab (backend/docs.php) - same docs pattern as plugins/paddle-pay
 * (docs/<lang>/*.md + core's se_parse_docs_file(), falls back to docs/en
 * if the admin's language has no folder). Kept to a single index.md for
 * now; sidebar/multi-page is already wired for whenever more pages are
 * needed, same as paddle-pay's.
 * -------------------------------------------------------------- */
if (isset($_GET['show']) && $_GET['show'] === 'docs_nav') {

    // se_parse_docs_file() (acp/core/functions.php) does `global $Parsedown`
    // internally and calls $Parsedown->text(...) - it's never instantiated
    // anywhere in the admin-xhr include chain (public/admin_xhr.php ->
    // acp/data_reader.php -> core/addons/data-reader.php -> here), only
    // locally right before use at its other call sites (see e.g.
    // acp/core/addons/edit-theme.php), so it has to be set here too.
    // Without it this fatals on a null method call, and with admin_xhr.php's
    // error_reporting(0) that comes out as a silently empty response.
    $Parsedown = new Parsedown();

    $docs_root = SE_ROOT.'plugins/former/docs/en';
    if (is_dir(SE_ROOT.'plugins/former/docs/'.$languagePack)) {
        $docs_root = SE_ROOT.'plugins/former/docs/'.$languagePack;
    }

    $current_file = basename($_GET['file'] ?? 'index.md');
    $docsfiles = glob($docs_root.'/*.md');
    $parsed_files = [];

    foreach ($docsfiles as $doc) {
        // skip tooltips
        if (str_starts_with(basename($doc), 'tip-')) {
            continue;
        }

        $parsed_file = se_parse_docs_file($doc);
        $parsed_files[] = [
            'title' => $parsed_file['header']['title'],
            'priority' => $parsed_file['header']['priority'],
            'btn' => $parsed_file['header']['btn'],
            'file' => $doc,
        ];
    }

    $sorted_parsed_files = se_array_multisort($parsed_files, 'priority', SORT_ASC);

    // docs_nav is only fetched once (hx-trigger="load" in backend/docs.php);
    // clicking a button only swaps #docsContent, the sidebar itself is never
    // re-requested. So the active class is toggled client-side on click
    // instead of depending on another server round-trip - hx-on:click is
    // already used elsewhere in the admin theme (e.g. hx-on::after-request
    // in acp/core/settings/labels.php) for exactly this kind of one-off DOM
    // tweak alongside an hx-get.
    $list = '<div class="card mb-3">';
    $list .= '<div class="list-group list-group-flush">';
    foreach ($sorted_parsed_files as $v) {
        $active = basename($v['file']) === $current_file ? ' active' : '';
        $hx_get = '/admin-xhr/addons/plugin/former/read/?show=docs_content&file='.basename($v['file']);
        $list .= '<button class="list-group-item list-group-item-action'.$active.'" hx-get="'.$hx_get.'" hx-target="#docsContent"
            hx-on:click="this.closest(\'.list-group\').querySelectorAll(\'.active\').forEach(function(el){ el.classList.remove(\'active\'); }); this.classList.add(\'active\')">';
        $list .= $v['btn'];
        $list .= '</button>';
    }
    $list .= '</div>';
    $list .= '</div>';
    echo $list;
    exit;
}

if (isset($_GET['show']) && $_GET['show'] === 'docs_content') {

    // see the docs_nav block above for why this is needed
    $Parsedown = new Parsedown();

    $df = basename($_GET['file'] ?? 'index.md');

    $doc_file = SE_ROOT.'plugins/former/docs/'.$languagePack.'/'.$df;
    if (!is_file($doc_file)) {
        $doc_file = SE_ROOT.'plugins/former/docs/en/'.$df;
    }

    if (is_file($doc_file)) {
        $parsed_file = se_parse_docs_file($doc_file);
        echo $parsed_file['content'];
    }
    exit;
}

exit;
