<?php
/**
 * example: [plugin=former]form_id=3[/plugin]
 */
if (SE_SECTION !== 'backend') {

    // Reached via buffer_script(), which may run multiple times per request
    // (e.g. once per snippet in template-setup.php's snippet loop, then again
    // for the page content). require_once only executes bootstrap.php's body
    // - and with it the `global $former_db;` binding - on the first of those
    // calls, so later calls need their own `global` here to see it.
    global $former_db;
    require_once __DIR__.'/global/bootstrap.php';

    $fmr_form_id = (int) ($form_id ?? 0);

    if ($fmr_form_id > 0 && isset($former_db)) {
        echo fmr_render_form($fmr_form_id);
    }
}