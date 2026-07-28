<?php
/**
 * @var string $former_db_file from bootstrap.php
 * @var string $mod_root
 */
use Medoo\Medoo;
include __DIR__.'/schema.php';


/* INSTALL */

if(!is_file("$former_db_file")) {

    if(!is_dir(dirname($former_db_file))) {
        mkdir(dirname($former_db_file), 0755, true);
    }

    echo '<p class="alert alert-info">We try to generate SQLite File: '.$former_db_file.'</p>';

        // Medoo initialisieren
        $former_db = new Medoo([
            'type' => 'sqlite',
            'database' => $former_db_file
        ]);

        $tables = FormerSchema::getTables();

        // Tabellen erstellen
        foreach ($tables as $table_name => $columns) {
            $col_definitions = [];
            foreach ($columns as $col_name => $col_type) {
                $col_definitions[] = "$col_name $col_type";
            }

            $sql = "CREATE TABLE IF NOT EXISTS $table_name (" .
                implode(', ', $col_definitions) . ")";

            $former_db->query($sql)->execute();
        }

        // Initiale Daten einfügen (sicher mit Prepared Statements)
        $former_db->insert('settings', [
            'key' => 'version',
            'value' => $addon_info['addon']['version']
        ]);

        echo '<p class="alert alert-info">Generated SQLite File: '.$former_db_file.'</p>';

        $defaultSettings = fmr_get_default_settings();

    foreach ($defaultSettings as $key => $value) {
        if (!$former_db->get('settings', 'value', ['key' => $key])) {
            $former_db->insert('settings', [
                'key' => $key,
                'value' => (string)$value,
            ]);
        }
    }

}

if(!is_dir($mod_root.'uploads')) {
    mkdir($mod_root.'uploads', 0755, true);
}

function fmr_get_default_settings(): array {
    return [
        'captcha_type' => 'math',
        'recaptcha_site_key' => '',
        'recaptcha_secret_key' => '',
        'max_upload_size_mb' => 5,
        'allowed_upload_extensions' => 'pdf,doc,docx,jpg,jpeg,png,gif',
    ];
}
