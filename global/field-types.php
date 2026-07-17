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
    global $addon_lang;

    return [
        'text' => [
            'label' => $addon_lang['field_type_text'] ?? 'Text',
            'template' => 'text.tpl',
            'has_options' => false,
            'has_upload' => false,
            'config_fields' => [],
        ],
        'textarea' => [
            'label' => $addon_lang['field_type_textarea'] ?? 'Textarea',
            'template' => 'textarea.tpl',
            'has_options' => false,
            'has_upload' => false,
            'config_fields' => ['rows'],
        ],
        'email' => [
            'label' => $addon_lang['field_type_email'] ?? 'E-mail',
            'template' => 'email.tpl',
            'has_options' => false,
            'has_upload' => false,
            'config_fields' => [],
        ],
        'number' => [
            'label' => $addon_lang['field_type_number'] ?? 'Number',
            'template' => 'number.tpl',
            'has_options' => false,
            'has_upload' => false,
            'config_fields' => ['min', 'max'],
        ],
        'select' => [
            'label' => $addon_lang['field_type_select'] ?? 'Select list',
            'template' => 'select.tpl',
            'has_options' => true,
            'has_upload' => false,
            'config_fields' => ['options'],
        ],
        'radio' => [
            'label' => $addon_lang['field_type_radio'] ?? 'Radio buttons',
            'template' => 'radio.tpl',
            'has_options' => true,
            'has_upload' => false,
            'config_fields' => ['options'],
        ],
        'checkbox' => [
            'label' => $addon_lang['field_type_checkbox'] ?? 'Checkbox (consent)',
            'template' => 'checkbox.tpl',
            'has_options' => false,
            'has_upload' => false,
            'config_fields' => [],
        ],
        'file' => [
            'label' => $addon_lang['field_type_file'] ?? 'File upload',
            'template' => 'file.tpl',
            'has_options' => false,
            'has_upload' => true,
            'config_fields' => ['allowed_extensions', 'max_size_mb', 'multiple'],
        ],
        'text_block' => [
            'label' => $addon_lang['field_type_text_block'] ?? 'Text / explanation',
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
