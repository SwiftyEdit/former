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
 * The admin theme's .sortable_target::after rule (public/assets/themes/
 * administration/src/scss/_form.scss) always renders "DROP IMAGES HERE" -
 * it's meant for the image picker, the other consumer of this shared
 * Sortable component. #formCanvas needs the class for the Sortable wiring
 * (see note below) but the wording is wrong here, so it's overridden with
 * an id+class selector, which outweighs the theme's plain-class selector on
 * specificity - no !important, no core file touched.
 *
 * The rows themselves reuse bootstrap's .list-group-item (see
 * fmr_render_field_row()), which by design collapses adjacent items onto a
 * shared 1px border (".list-group-item + .list-group-item { border-top-
 * width: 0 }") - fine for a simple link list, but with former's multi-row
 * field editors in each item, that shared line is too subtle to tell where
 * one field ends and the next begins. Gapping and re-bordering each row
 * fixes that; scoped to #formCanvas so it doesn't touch list-group items
 * elsewhere. An id-qualified selector again outweighs the theme's
 * class-only ones, so this stays override-only, no !important.
 */
echo '<style>
#formCanvas.sortable_target::after { content: "'.htmlspecialchars($addon_lang['msg_canvas_dropzone']).'"; }
#formCanvas.sortable_target > .draggable {
    margin-bottom: 10px;
    border: 1px solid var(--bs-border-color);
    border-left: 2px solid var(--bs-primary);
    border-radius: var(--bs-border-radius, .375rem);
}
#formCanvas.sortable_target > .draggable:last-child { margin-bottom: 0; }
</style>';

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
echo '<input type="hidden" name="fmr_field_order" id="fmrFieldOrder" value="">';
echo '<button type="submit" class="btn btn-primary">'.$addon_lang['btn_save_fields'].'</button>';
echo '</form>';
echo '</div>';
echo '</div>';

echo '</div>'; // col-md-8

echo '</div>'; // row

/*
 * Own order tracking, deliberately NOT reusing the admin theme's shared
 * picker_0[] mechanism (public/assets/themes/administration/src/js/backend.js,
 * observeContainersForDraggableDivs()). That code only adds a row's
 * picker_0[] hidden input when the row has no <input type="hidden"> at all
 * yet - it can't tell "no order input yet" apart from "already has some
 * other hidden input". A text_block row renders its own hidden
 * field_label[id]/field_key[id] inputs (see fmr_render_field_row()), which
 * trips that check immediately, so those rows never get a picker_0[] entry
 * and silently keep their old position on save. Since that's core theme
 * code, we work around it here instead of patching core: a single hidden
 * input (#fmrFieldOrder) holding a comma-joined list of field ids in DOM
 * order, recomputed from #formCanvas .draggable[data-id] on every mutation
 * (drag-reorder via the theme's own SortableJS instance, or a field
 * appended by "add_field"). backend/writer.php reads fmr_field_order
 * instead of picker_0.
 */
echo '<script>
(function() {
    var canvas = document.getElementById("formCanvas");
    var orderInput = document.getElementById("fmrFieldOrder");
    if (!canvas || !orderInput) { return; }

    function syncOrder() {
        var ids = [];
        canvas.querySelectorAll(":scope > .draggable[data-id]").forEach(function(el) {
            ids.push(el.getAttribute("data-id"));
        });
        orderInput.value = ids.join(",");
    }

    new MutationObserver(syncOrder).observe(canvas, { childList: true, subtree: true });
    syncOrder();
})();
</script>';
