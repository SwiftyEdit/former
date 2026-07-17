<?php
//error_reporting(E_ALL);
global $addon_lang, $hidden_csrf_token;
use Medoo\Medoo;

$mod_root = SE_ROOT.'plugins/former/';
$former_db_file = $mod_root.'data/former.sqlite3';

// On the frontend, this file is reached via buffer_script() (a function),
// so a plain `$former_db = ...` below would only become a local variable
// of that function's call frame - invisible to fmr_*() helpers that do
// `global $former_db;`. Declaring it global here forces it into the real
// global scope regardless of which function-local include chain runs it.
global $former_db;

// $addon_info is only pre-set when this bootstrap is reached via the admin
// nav-tab router (acp/core/addons/edit-plugin.php). The admin-xhr reader/writer
// router and the frontend shortcode/xhr entry points do not set it, so it is
// loaded here directly to make bootstrap.php safe in all three contexts.
if(!isset($addon_info)) {
    $addon_info = json_decode(file_get_contents($mod_root.'info.json'), true);
}

if(is_file($former_db_file)) {
    $former_db = new Medoo([
        'type' => 'sqlite',
        'database' => $former_db_file
    ]);
} else {

    if(SE_SECTION === 'backend') {
        include __DIR__ . '/../install/installer.php';
    }

}

require_once __DIR__.'/functions.php';
require_once __DIR__.'/field-types.php';

if(isset($former_db)) {
    $fmr_settings = fmr_get_settings();

    if(SE_SECTION === 'backend') {
        if (version_compare($fmr_settings['version'] ?? '0', $addon_info['addon']['version'], '<')) {
            include __DIR__ . '/../install/updater.php';
        }
    }
}

// se_return_addon_translations() lives in acp/core/functions_addons.php,
// which is only autoloaded on admin pages - it's undefined on the frontend
// (shortcode / xhr.php), where $addon_lang isn't needed anyway.
if(!is_array($addon_lang) && function_exists('se_return_addon_translations')) {
    $addon_lang = se_return_addon_translations('former');
}
