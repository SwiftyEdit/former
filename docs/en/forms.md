---
title: Former - Forms & Fields
description: Creating a form and adding fields to it
btn: Forms & Fields
group: addons
priority: 200
---

# Creating a form

1. In the form list, click **+ New form**.
2. On the left, under **Settings**: name, description, status (active/inactive), whether
   submissions are stored in the database and/or e-mailed to selected recipients, the
   success/error message, and the submit button label.
3. On the right, add fields via the field palette buttons (text, textarea, e-mail, number,
   select list, radio buttons, checkbox, file upload, a hidden field, plus a plain
   text/explanation block with no input). Fields can be reordered via drag & drop.
4. Recipients for the e-mail notification are managed centrally under the plugin's
   **Settings** tab, then picked per form via checkbox.

## Styling individual fields

Every visible field has a **CSS class(es)** input (the hidden field type is the exception -
it has no wrapper for a class to attach to). Its value is appended to (not a replacement for)
the field wrapper's existing classes - useful for placing two fields side by side (with your
own theme's grid/utility classes) or highlighting a single field. Enter multiple classes
separated by spaces, same as any HTML `class` attribute.

## Hidden field

A normal field with no visible input (`<input type="hidden">`) - useful e.g. when moving an
existing form to Former that needs to carry tracking values like `gclid` or UTM parameters,
which an already-present site-wide script (Google Ads/GTM, a Salesforce snippet, etc.) fills
in automatically by matching the field's name - the **field name** must exactly match what
that script expects (e.g. `gclid` or `UTM_Source__c`). Both `name` and `id` of the rendered
`<input>` are exactly that field name (no `fmr-` prefix like the other field types get), so a
script matching on either attribute finds it. The optional **default value** is usually left
empty, except for a genuinely fixed value (e.g. a form identifier) that no external script
sets. There's deliberately no "required" option here - a hidden field whose external script
doesn't run (or run in time) should never be able to block the whole form. Like any other
field, the submitted value shows up in the submissions list, the notification mail, and the
`former:submitted` event.

## Template sets: restyling the whole form

If a CSS class isn't enough - e.g. the form needs a fundamentally different structure (its
own grid, normal instead of floating labels, a fully custom look) - pick a **Template set**
per form under **Settings → Appearance**. Sets are subfolders under
`plugins/former/data/themes/`; see the README in that folder for the exact layout and a
worked example. A set survives Former and SwiftyEdit updates untouched, since `data/` is
excluded from both - custom sets are entirely an installation's own, never shipped with or
committed to the plugin.

## Submissions

The dedicated **Submissions** tab (between Forms and Settings) shows submissions from every
form together, as long as "store in database" is enabled for the form in question. A filter
at the top narrows the list to **All forms** or a single form - the "Submissions" button in
the form list leads into the same tab, just with that form preselected. It lists every
submitted field value, attached files, and any auto-attached data (see the corresponding page
in this help); the unfiltered view additionally shows a badge naming which form each
submission belongs to.
