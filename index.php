<?php
/**
 * example: [plugin=former]form_id=3[/plugin]
 */
if (SE_SECTION !== 'backend') {

    require_once __DIR__.'/global/bootstrap.php';

    $fmr_form_id = (int) ($form_id ?? 0);

    if ($fmr_form_id > 0 && isset($former_db)) {
        echo fmr_render_form($fmr_form_id);
    }
}
