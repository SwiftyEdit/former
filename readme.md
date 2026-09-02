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
  a hidden field (e.g. for tracking values like `gclid`/UTM parameters that an external
  site-wide script fills in by matching the field's name), plus a plain text/explanation
  block with no input (e.g. for longer instructions inside the form)
- Optional free-text CSS class(es) per field, appended to that field's wrapper `<div>` - for
  layout (e.g. a grid/utility class to place two fields side by side) or emphasis (e.g.
  highlighting one field), independent of whether the site's theme is Bootstrap-based or not
- Optional per-form template-set: swap the markup of the whole form (or individual field
  types within it) for a custom look, selected per form under Settings → Appearance. Sets
  live in `data/themes/<slug>/`, outside the plugin's own update path - see
  `data/themes/README.md`
- Configurable per form: store submissions in the database and/or send them by email to
  selected recipients (or both)
- A dedicated **Submissions** tab lists stored submissions across all forms at once, with a
  filter dropdown to narrow it to one form (the per-form "Submissions" button in the form
  list leads into the same tab, preselected to that form)
- Multiple recipients can be defined in the plugin settings; a checkbox per form controls
  who gets notified
- Captcha: simple math captcha (default) or Google reCAPTCHA v2, switchable in the plugin
  settings; the captcha can also be turned off per form (e.g. for forms only shown to
  logged-in users)
- File uploads are stored in their own directory (`plugins/former/uploads/<form_id>/`) and
  are not added to the central media library, since submitters are anonymous
- Submission happens via HTMX without a page reload
- Fires a `former:submitted` browser event on success, for the theme (or a GTM snippet) to
  hook conversion tracking into - see below
- Optionally, per form: auto-attach the logged-in user (user ID, username, e-mail), the
  visitor's IP address/referrer/browser, and/or the page the form was submitted from and
  when, to every submission - see below
- Lightweight consent-proof logging: a checkbox field can be flagged to record its exact
  wording, timestamp and IP whenever it's ticked, independent of the auto-attached data above
  - see below
- Optional e-mail double-opt-in per form: a submission is held "pending" until the visitor
  clicks a confirmation link sent to their own address - see below
- Built-in "Help" tab in the plugin's own backend UI (`Formulare | Einstellungen | Hilfe`),
  documented per language under `docs/<lang>/` - same pattern as `plugins/paddle-pay`

## Auto-attached data (logged-in user / user data / page info)

Three checkboxes in a form's settings ("Auto-attached data") add a fixed, non-configurable
set of extra data points on top of the form's own fields - into all three places a
submission can end up: the submissions list, the notification mail, and the
`former:submitted` event below. All default to off.

- **Include logged-in user**: `user_id`, `user_nick`, `user_mail` from the visitor's own
  session (the site-wide login shared with the shop/profile area, not just backend admins).
  Adds nothing if the visitor isn't logged in.
- **Include user data**: `ip_address`, `referrer` (the `Referer` header of the submit
  request - note this is whatever page the visitor was on *when they submitted*, not
  necessarily the original ad/landing page they arrived from if they navigated the site
  first), and `browser` (the `User-Agent` header).
- **Include page information**: `page_url`, the site-relative slug (`$swifty_slug`) of the
  page the form was embedded on - captured server-side into a hidden field when the form is
  rendered, not read from the submit request's own `Referer` - and `submitted_at`, the
  submission timestamp.

There is deliberately no built-in privacy-notice text for these - use a "Text / explanation"
field in the form itself if you want to inform submitters, and make sure your own privacy
policy covers what you configure here. Former only collects and forwards what a form is
explicitly opted into.

## Consent-proof logging and e-mail double-opt-in

For cases where you need to show that the submitter really is who they claim (a newsletter
sign-up, a survey participant), two independent mechanisms are available - see
`docs/<lang>/confirmation.md` for the full admin-facing walkthrough.

- **Consent-proof logging**: any "Checkbox (consent)" field can have "Record as consent
  proof" turned on. When checked at submit time, the field's exact label text, a timestamp
  and the sender's IP are added to the submission's `meta.consent_log` (and shown as their
  own section in the submissions list / notification mail) - independent of the auto-attached
  data checkboxes above, and without any e-mail round-trip.
- **E-mail confirmation (double opt-in)**: turned on per form under Settings → "Bestätigung
  per E-Mail". A submission is stored as pending (forces "store in database" on) and a
  confirmation mail is sent to the address from the form's chosen e-mail field. The
  notification mail to `mail_recipients` and the `former:submitted` event below are **not**
  fired at the original submit for such a form - both are deferred until the visitor clicks
  through the confirmation link and then clicks "confirm" there (deliberately not
  auto-confirmed just by opening the link, so an e-mail security scanner pre-fetching it in
  transit can't count as a confirmation). The link (`fmr_confirm_link()`) points at the actual
  page the form was embedded on - `https://.../that-page/?fmr_confirm=<token>` - not the bare
  `/xhr/plugins/former/` endpoint: `plugins/former/index.php` (i.e. the shortcode itself, via
  `fmr_handle_confirm_request()`) notices `?fmr_confirm=...`/the confirm button's POST and
  renders the prompt/result there in place of the normal form, so it's a normal page load with
  the site's own theme (header, footer, any site-wide GTM snippet) - not a bare, unstyled
  response.

## Frontend event: `former:submitted`

On a successful submission, former dispatches a `CustomEvent` on `document` (bubbles, so it
can also be caught on a form's own wrapper element). It carries the form id/name, the
submission id (`null` if "store in database" is off for that form), the sanitized field
values that were just submitted, and the auto-attached data described above (`meta`, empty
object if no checkbox is on) - i.e. the visitor's own data, handed back to their own
browser. Former makes no external network calls itself; what happens with the event,
including forwarding any of this to a third party like Google or Salesforce, is entirely up
to the theme.

```js
document.addEventListener('former:submitted', function (e) {
  console.log(e.detail);
  // { form_id: 3, form_name: "Contact", submission_id: 42,
  //   data: { name: "...", email: "...", message: "..." },
  //   meta: { user_id: 7, user_nick: "jane", user_mail: "jane@example.com" } }

  // Example: Google Ads conversion tracking
  if (typeof gtag === 'function') {
    gtag('event', 'conversion', { send_to: 'AW-XXXXXXX/YYYYYYY' });
  }

  // Example: push to a GTM/GA4 dataLayer
  window.dataLayer = window.dataLayer || [];
  window.dataLayer.push({ event: 'former_submitted', formId: e.detail.form_id, ...e.detail.data });

  // Example: Salesforce Web-to-Lead, only for a specific form
  if (e.detail.form_id === 3) {
    fetch('https://webto.salesforce.com/servlet/servlet.WebToLead?encoding=UTF-8', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ oid: 'YOUR_OID', email: e.detail.data.email }),
    });
  }
});
```

## Note on activation

The shortcode renders the form even if the plugin is not activated. Submitting
(`/xhr/plugins/former/`) only works once the plugin has been activated under Addons.

## License

GPL-3.0 — see [license.txt](license.txt).
