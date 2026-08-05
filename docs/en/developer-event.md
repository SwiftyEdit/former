---
title: Former - For Developers
description: "The former:submitted JavaScript event for conversion tracking"
btn: For Developers
group: addons
priority: 600
---

# For developers: the `former:submitted` event

On a successful submission, former fires a `former:submitted` browser event carrying the
submitted field values (plus the extra data described under "Auto-attached data"), which a
theme can use for e.g. Google Ads conversion tracking, a GTM/dataLayer push, or a Salesforce
call.

Full details, the exact structure of the event's `detail` object, and code examples are in
the plugin's `readme.md` (`plugins/former/readme.md`).
