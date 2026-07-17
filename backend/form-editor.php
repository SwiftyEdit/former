<?php
require __DIR__.'/../global/bootstrap.php';

$form_id = (int) ($_GET['form_id'] ?? 0);
$form = $former_db->get('forms', '*', ['id' => $form_id]);

if (!$form) {
    echo '<div class="alert alert-danger">Formular nicht gefunden.</div>';
    return;
}

echo '<h1>'.$addon_lang['title_form_editor'].' – '.htmlspecialchars($form['name']).'</h1>';

echo '<div class="row">';

/* -------------------------------------------------------------------
 * Left column: form settings (name, storage, mail, recipients, messages)
 * ---------------------------------------------------------------- */
echo '<div class="col-md-4">';
echo '<div class="card">';
echo '<div class="card-header">'.$addon_lang['title_form_settings'].'</div>';
echo '<div class="card-body" hx-get="/admin-xhr/addons/plugin/former/read/?show=form_settings&form_id='.$form_id.'" hx-trigger="load, update_former_form_settings from:body">LOADING ...</div>';
echo '</div>';
echo '</div>';

/* -------------------------------------------------------------------
 * Right column: field palette (click-to-add) + drag-sortable canvas
 * ---------------------------------------------------------------- */
echo '<div class="col-md-8">';

echo '<div class="card mb-3">';
echo '<div class="card-header">'.$addon_lang['title_field_palette'].'</div>';
echo '<div class="card-body d-flex flex-wrap gap-2">';
foreach (fmr_field_types() as $type_key => $type) {
    echo '<button type="button" class="btn btn-sm btn-default"
        hx-post="/admin-xhr/addons/plugin/former/write/"
        hx-vals=\'{"add_field":"1","field_type":"'.$type_key.'","form_id":"'.$form_id.'","csrf_token":"'.$_SESSION['token'].'"}\'
        hx-target="#formCanvas" hx-swap="beforeend">+ '.htmlspecialchars($type['label']).'</button>';
}
echo '</div>';
echo '</div>';

echo '<div class="card">';
echo '<div class="card-header">'.$addon_lang['title_form_canvas'].'</div>';
echo '<div class="card-body">';
echo '<form hx-post="/admin-xhr/addons/plugin/former/write/" hx-target="#formCanvasResponse" hx-swap="innerHTML">';
echo '<div id="formCanvasResponse"></div>';

/*
 * The .sortable_target class must be present in the *initial* server-rendered
 * HTML, not injected later via an hx-get swap. The admin theme's global
 * SortableJS wiring (public/assets/themes/administration/src/js/backend.js)
 * initializes Sortable + the picker_0[] hidden-input generator by scanning
 * for ".sortable_target" against the *newly loaded content root* on each
 * htmx:load event; for the very first page load that root is <body> (so a
 * canvas rendered here as part of the page is found as a descendant), but
 * for an hx-swap="innerHTML" that replaces the target element's own
 * children, the loaded-content root is the target element itself, whose own
 * class list is not re-scanned by querySelectorAll (it only finds
 * descendants). Rendering the canvas synchronously here - rather than via
 * a follow-up hx-get, as originally attempted - sidesteps that entirely.
 * New fields added later via "add_field" append into this already-
 * initialized Sortable container via hx-swap="beforeend", which Sortable
 * and the MutationObserver-based hidden-input generator both pick up
 * automatically without needing re-initialization.
 */
$fields = $former_db->select('fields', '*', ['form_id' => $form_id, 'ORDER' => ['sort_order' => 'ASC']]);
echo '<div id="formCanvas" class="sortable_target list-group mb-3">';
if (!$fields) {
    echo '<p class="text-muted mb-0" id="formCanvasEmptyHint">'.$addon_lang['msg_no_fields'].'</p>';
} else {
    foreach ($fields as $field) {
        echo fmr_render_field_row($field);
    }
}
echo '</div>';

echo '<input type="hidden" name="save_fields" value="'.$form_id.'">';
echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['token'].'">';
echo '<button type="submit" class="btn btn-primary">'.$addon_lang['btn_save_fields'].'</button>';
echo '</form>';
echo '</div>';
echo '</div>';

echo '</div>'; // col-md-8

echo '</div>'; // row
