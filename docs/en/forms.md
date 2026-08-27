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
   select list, radio buttons, checkbox, file upload, plus a plain text/explanation block
   with no input). Fields can be reordered via drag & drop.
4. Recipients for the e-mail notification are managed centrally under the plugin's
   **Settings** tab, then picked per form via checkbox.

## Styling individual fields

Every field has a **CSS class(es)** input. Its value is appended to (not a replacement for)
the field wrapper's existing classes - useful for placing two fields side by side (with your
own theme's grid/utility classes) or highlighting a single field. Enter multiple classes
separated by spaces, same as any HTML `class` attribute.

## Template sets: restyling the whole form

If a CSS class isn't enough - e.g. the form needs a fundamentally different structure (its
own grid, normal instead of floating labels, a fully custom look) - pick a **Template set**
per form under **Settings → Appearance**. Sets are subfolders under
`plugins/former/data/themes/`; see the README in that folder for the exact layout and a
worked example. A set survives Former and SwiftyEdit updates untouched, since `data/` is
excluded from both - custom sets are entirely an installation's own, never shipped with or
committed to the plugin.

## Submissions

Every form has its own **Submissions** view (reachable via the button in the form list),
as long as "store in database" is enabled for it. It lists every submitted field value,
attached files, and any auto-attached data (see the corresponding page in this help).
