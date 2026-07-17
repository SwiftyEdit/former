<?php

include __DIR__.'/schema.php';

    // update
    echo '<p class="alert alert-info">We try to update version: '.$addon_info['addon']['version'].'</p>';

    $tables = FormerSchema::getTables();

    // 1. Update/Create all tables with current schema
    foreach ($tables as $table_name => $columns) {
        fmr_updateOrCreateTable($table_name, $columns);
    }
    // 2. Update version
    $former_db->update('settings', [
        'value' => $addon_info['addon']['version']
    ], [
        'key' => 'version'
    ]);

    echo '<p class="alert alert-info">Updated to version: '.$addon_info['addon']['version'].'</p>';
