<?php
require __DIR__.'/../global/bootstrap.php';

// This is both the "Einsendungen" tab itself (no form_id - all forms) and
// the target of the "Einsendungen" button on a form row in the Formulare
// list (form_id set - pre-filtered). Same page either way, just a
// different initial value for the filter below - no separate per-form
// page anymore, and no page heading (this being its own tab now makes one
// redundant, same as start.php/settings.php).
$form_id = (int) ($_GET['form_id'] ?? 0);
$forms = $former_db->select('forms', ['id', 'name'], ['ORDER' => ['name' => 'ASC']]);

// A stale/unknown form_id (deleted form, tampered URL) falls back to "all"
// rather than showing an empty/broken filtered view.
if ($form_id > 0 && !in_array($form_id, array_column($forms, 'id'))) {
    $form_id = 0;
}

echo '<div class="row">';

echo '<div class="col-md-9">';
echo '<div id="formSubmissionsList" hx-get="/admin-xhr/addons/plugin/former/read/?show=submissions&form_id='.$form_id.'&page=1" hx-trigger="load">LOADING ...</div>';
echo '</div>';

echo '<div class="col-md-3">';
echo '<div class="card">';
echo '<div class="card-header">'.$addon_lang['label_submissions_filter'].'</div>';
echo '<div class="card-body">';
echo '<select class="form-select" onchange="location.href = \'/admin/addons/plugin/former/submissions/\' + (this.value ? (\'?form_id=\' + this.value) : \'\')">';
echo '<option value=""'.($form_id === 0 ? ' selected' : '').'>'.$addon_lang['option_submissions_all'].'</option>';
foreach ($forms as $f) {
    $selected = ((int) $f['id'] === $form_id) ? ' selected' : '';
    echo '<option value="'.$f['id'].'"'.$selected.'>'.htmlspecialchars($f['name']).'</option>';
}
echo '</select>';
echo '</div>';
echo '</div>';
echo '</div>';

echo '</div>'; // row
