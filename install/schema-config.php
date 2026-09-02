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
        // Double-opt-in (e-mail confirmation). When on, a submission is
        // stored as "pending" first (see the submissions.confirm_* columns
        // below) - the notification mail to mail_recipients and the
        // former:submitted tracking event only fire once the visitor has
        // clicked through the confirmation link, not at initial submit.
        // Forces store_to_db on when saved (backend/writer.php) since a
        // pending submission needs somewhere durable to live between submit
        // and confirm.
        'require_confirmation' => 'TINYINT(1) DEFAULT 0',
        // field_key of the field whose submitted value is the confirmation
        // address. Empty = auto-detect the form's first field of type
        // 'email' at submit time (fmr_confirm_email_field_key()).
        'confirm_email_field' => 'VARCHAR(100) NULL',
        'confirm_mail_subject' => 'VARCHAR(255) NULL',
        // Body of the confirmation mail; {confirm_link} and {form_name} are
        // replaced at send time (see fmr_send_confirmation_mail()).
        'confirm_mail_body' => 'TEXT NULL',
        // Shown on the landing page once the visitor has actually clicked
        // "confirm" there - the double-opt-in equivalent of success_message.
        'confirmed_message' => 'TEXT NULL',
        'confirm_expires_hours' => 'INTEGER DEFAULT 48',
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
        // Double-opt-in bookkeeping (only ever set when the owning form has
        // require_confirmation on - NULL/empty for every normal submission).
        // Only the SHA-256 hash of the token is stored, never the token
        // itself - same idiom as a password-reset token, so a DB read alone
        // can never produce a working confirmation link.
        'confirm_token_hash' => 'VARCHAR(64) NULL',
        'confirm_expires_at' => 'DATETIME NULL',
        'confirm_sent_at' => 'DATETIME NULL',
        'confirmed_at' => 'DATETIME NULL',
        // The page slug the form was embedded on at submit time (same value
        // already captured into fmr_page_slug/meta.page_url - see
        // fmr_build_submission_meta()), kept independently of that opt-in
        // checkbox so the confirmation link always has somewhere real to
        // point to: the confirm/resend link is built as that page's own URL
        // + ?fmr_confirm=<token>, handled by plugins/former/index.php within
        // the actual page/theme - NOT a bare /xhr/plugins/former/ response,
        // which has no site header/footer/tag-manager snippet of its own.
        'confirm_page_slug' => 'VARCHAR(255) NULL',
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
