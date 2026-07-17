<?php

/**
 * Registry of available form-field types. A plain PHP array rather than a
 * directory scan, since each type carries its own config schema (e.g.
 * select/radio need an options list, file needs an extension whitelist)
 * that a bare directory listing can't express. Both the admin builder and
 * the frontend renderer (global/functions.php::fmr_render_form()) read
 * this to map a field's field_type to its template file and config UI.
 */
function fmr_field_types(): array {
    return [
        'text' => [
            'label' => 'Text',
            'template' => 'text.tpl',
            'has_options' => false,
            'has_upload' => false,
            'config_fields' => [],
        ],
        'textarea' => [
            'label' => 'Textarea',
            'template' => 'textarea.tpl',
            'has_options' => false,
            'has_upload' => false,
            'config_fields' => ['rows'],
        ],
        'email' => [
            'label' => 'E-Mail',
            'template' => 'email.tpl',
            'has_options' => false,
            'has_upload' => false,
            'config_fields' => [],
        ],
        'number' => [
            'label' => 'Zahl',
            'template' => 'number.tpl',
            'has_options' => false,
            'has_upload' => false,
            'config_fields' => ['min', 'max'],
        ],
        'select' => [
            'label' => 'Auswahlliste',
            'template' => 'select.tpl',
            'has_options' => true,
            'has_upload' => false,
            'config_fields' => ['options'],
        ],
        'radio' => [
            'label' => 'Radiobuttons',
            'template' => 'radio.tpl',
            'has_options' => true,
            'has_upload' => false,
            'config_fields' => ['options'],
        ],
        'checkbox' => [
            'label' => 'Checkbox (Zustimmung)',
            'template' => 'checkbox.tpl',
            'has_options' => false,
            'has_upload' => false,
            'config_fields' => [],
        ],
        'file' => [
            'label' => 'Datei-Upload',
            'template' => 'file.tpl',
            'has_options' => false,
            'has_upload' => true,
            'config_fields' => ['allowed_extensions', 'max_size_mb', 'multiple'],
        ],
        'text_block' => [
            'label' => 'Text / Erklärung',
            'template' => 'text_block.tpl',
            'has_options' => false,
            'has_upload' => false,
            // static: not an input, collects no value - skipped entirely
            // during submission handling (global/xhr.php) and never
            // appears in stored/e-mailed submission data.
            'is_static' => true,
            'config_fields' => ['content'],
        ],
    ];
}
