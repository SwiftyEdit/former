<?php
require __DIR__.'/../global/bootstrap.php';

$form_id = (int) ($_GET['form_id'] ?? 0);
$form = $former_db->get('forms', '*', ['id' => $form_id]);

if (!$form) {
    echo '<div class="alert alert-danger">Formular nicht gefunden.</div>';
    return;
}

echo '<h1>'.$addon_lang['title_submissions'].' – '.htmlspecialchars($form['name']).'</h1>';

echo '<div id="formSubmissionsList" hx-get="/admin-xhr/addons/plugin/former/read/?show=submissions&form_id='.$form_id.'&page=1" hx-trigger="load">LOADING ...</div>';
