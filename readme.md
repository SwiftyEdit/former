# Former

Build unlimited forms via drag & drop.

## Usage

```
[plugin=former]form_id=3[/plugin]
```

The form ID is shown in the form list in the backend (Addons > Former).

## Features

- Unlimited forms, assembled via drag & drop from field templates
- Field types: text, textarea, email, number, select, radio buttons, checkbox, file upload,
  plus a plain text/explanation block with no input (e.g. for longer instructions inside the form)
- Configurable per form: store submissions in the database and/or send them by email to
  selected recipients (or both)
- Multiple recipients can be defined in the plugin settings; a checkbox per form controls
  who gets notified
- Captcha: simple math captcha (default) or Google reCAPTCHA v2, switchable in the plugin
  settings
- File uploads are stored in their own directory (`plugins/former/uploads/<form_id>/`) and
  are not added to the central media library, since submitters are anonymous
- Submission happens via HTMX without a page reload

## Note on activation

The shortcode renders the form even if the plugin is not activated. Submitting
(`/xhr/plugins/former/`) only works once the plugin has been activated under Addons.

## License

GPL-3.0 — see [license.txt](license.txt).
