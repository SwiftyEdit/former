---
title: Former - Auto-attached Data
description: Including the logged-in user, or IP address & referrer
btn: Auto-attached Data
group: addons
priority: 400
---

# Auto-attached data

A form's settings include two checkboxes under **"Auto-attached data"** that add extra
information on top of the form's own fields - into the submissions list, the notification
mail, and the `former:submitted` JavaScript event (see the developer page in this help).
Both are off by default.

- **Include logged-in user**: user ID, username and e-mail of the logged-in visitor (nothing
  is added for anonymous/guest submissions).
- **Include IP address & referrer**: the sender's IP address and the referrer URL at submit
  time (usually just the page the form lives on - not necessarily the original ad/landing
  source if the visitor already navigated the site beforehand).

## Privacy

This is personal data. Whether and how you use it (e.g. forwarding it to Google Ads or
Salesforce via the theme, see the developer page), and whether you inform visitors about it,
is your own responsibility as the site operator - e.g. via a **"Text / explanation"** block
right inside the form, or your privacy policy.
