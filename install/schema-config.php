<?php
// Pure table schema definitions - edit here to add/modify columns

return [
    'forms' => [
        'id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        'name' => 'VARCHAR(255) NOT NULL',
        'description' => 'TEXT NULL',
        'status' => 'TINYINT(1) DEFAULT 1',
        'disable_captcha' => 'TINYINT(1) DEFAULT 0',
        'include_user_data' => 'TINYINT(1) DEFAULT 0',
        'include_ip_referrer' => 'TINYINT(1) DEFAULT 0',
        'include_page_info' => 'TINYINT(1) DEFAULT 0',
        'store_to_db' => 'TINYINT(1) DEFAULT 1',
        'send_mail' => 'TINYINT(1) DEFAULT 0',
        'mail_subject' => 'VARCHAR(255) NULL',
        'mail_recipients' => 'TEXT NULL',
        'submit_button_label' => "VARCHAR(100) DEFAULT 'Senden'",
        'template_set' => 'VARCHAR(100) NULL',
        'success_message' => 'TEXT NULL',
        'error_message' => 'TEXT NULL',
        'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
    ],

    'fields' => [
        'id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        'form_id' => 'INTEGER NOT NULL',
        'field_type' => 'VARCHAR(50) NOT NULL',
        'field_key' => 'VARCHAR(100) NOT NULL',
        'label' => 'VARCHAR(255) NOT NULL',
        'placeholder' => 'VARCHAR(255) NULL',
        'required' => 'TINYINT(1) DEFAULT 0',
        'css_class' => 'VARCHAR(255) NULL',
        'sort_order' => 'INTEGER DEFAULT 0',
        'config' => 'TEXT NULL',
        'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
    ],

    'submissions' => [
        'id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        'form_id' => 'INTEGER NOT NULL',
        'data' => 'TEXT NOT NULL',
        'meta' => 'TEXT NULL',
        'ip_address' => 'VARCHAR(45) NULL',
        'user_agent' => 'VARCHAR(255) NULL',
        'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
    ],

    'submission_files' => [
        'id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        'submission_id' => 'INTEGER NOT NULL',
        'field_key' => 'VARCHAR(100) NOT NULL',
        'original_filename' => 'VARCHAR(255) NOT NULL',
        'stored_filename' => 'VARCHAR(255) NOT NULL',
        'filesize' => 'INTEGER DEFAULT 0',
        'mimetype' => 'VARCHAR(100) NULL',
        'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
    ],

    'recipients' => [
        'id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        'name' => 'VARCHAR(255) NOT NULL',
        'email' => 'VARCHAR(255) NOT NULL',
        'created_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
    ],

    'settings' => [
        'id' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        'key' => 'TEXT NOT NULL UNIQUE',
        'value' => 'TEXT',
    ],
];
