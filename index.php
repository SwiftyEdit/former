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

    if (isset($former_db)) {
        // Double-opt-in confirm request (the link in the confirmation mail -
        // see fmr_confirm_link()) takes over this shortcode's spot on the
        // page instead of the normal form, so it renders inside the real
        // page/theme (header, footer, any site-wide tag-manager snippet)
        // rather than a bare /xhr/plugins/former/ response, which has none
        // of that. null means this request wasn't a confirm request at all.
        $fmr_confirm_html = fmr_handle_confirm_request();

        if ($fmr_confirm_html !== null) {
            echo $fmr_confirm_html;
        } elseif ($fmr_form_id > 0) {
            echo fmr_render_form($fmr_form_id);
        }
    }
}
