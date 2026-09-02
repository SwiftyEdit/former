---
title: Former - Confirmation & consent proof
description: E-mail double opt-in, plus lightweight logging of consent checkboxes
btn: Confirmation & consent
group: addons
priority: 350
---

# Confirming that the sender really submitted the form themselves

For cases like a newsletter sign-up or a survey participation, two independent building
blocks are available: a lightweight consent log with no mail round-trip, and a full
e-mail double opt-in.

## Consent proof (lightweight)

Every **Checkbox (consent)** field in the form editor has a **"Record as consent proof"**
option. When it's on and the box is checked at submit time, Former additionally stores with
the submission: the exact wording of the field's label at that moment, the date/time, and
the sender's IP address. This shows up as its own **"Consent proof"** block in the
submissions list and in the notification mail.

This is independent of the "auto-attached data" checkboxes (see the corresponding page in
this help) - those log IP/timestamp for the *whole* submission, while this proof is tied to
one specific consent checkbox and its exact wording. Useful e.g. for a survey where you need
to show the participant themselves agreed - no e-mail confirmation needed.

## E-mail confirmation (double opt-in)

For cases that need a real confirmation of the e-mail address (the classic case: a
newsletter sign-up), each form's **Settings → E-mail confirmation (double opt-in)** offers:

- **Require e-mail confirmation**: turns the flow on. Automatically forces "Store
  submissions in the database" on, since an unconfirmed submission would otherwise have
  nowhere to live.
- **Field holding the address to confirm**: which of the form's e-mail fields supplies the
  target address. "Automatic" uses the form's first e-mail field.
- **Confirmation mail subject/body**: with the `{confirm_link}` and `{form_name}`
  placeholders.
- **Message after successful confirmation**.
- **Confirmation link valid for (hours)**: 48 hours by default.

### Flow

1. The submission is stored as "pending" right at submit time - it shows up in the
   "Submissions" area immediately, tagged "Confirmation pending".
2. Instead of the usual success message, the visitor sees a note to check their e-mail,
   including a "resend" button in case the mail doesn't arrive.
3. The confirmation link leads back to the exact page the form is embedded on (wherever the
   `[plugin=former]...[/plugin]` shortcode sits) - not a technical URL. There, a **Confirm**
   button appears in place of the form. Deliberately not auto-confirmed just by opening the
   link: e-mail security scanners (e.g. Microsoft Defender for Office 365 Safe Links,
   Proofpoint, Mimecast - common in corporate mail setups) often pre-fetch links in incoming
   mail before the recipient ever sees it. If that alone counted as confirmation, someone
   could enter a third party's address and have their mail server "confirm" it without the
   actual owner ever clicking anything - defeating double-opt-in's legal purpose (proving a
   deliberate action by the address's real owner). A scanner fetches the page but never
   submits a form; only an actual click on the button fires the POST that really confirms.
4. Only once "Confirm" is actually clicked are the notification mail (to the addresses
   selected under "Recipients") sent and the `former:submitted` JavaScript event fired (see
   the developer page in this help) - not already at the original submit.

Since the confirm step runs inside the normal page (theme, header/footer, and any Google Tag
Manager snippet embedded there included), tracking snippets on that page work normally.

### Confirming manually

A still-pending submission in the submissions list also gets a **"Mark as confirmed"**
button - for edge cases like a phone-confirmed sign-up. Has the exact same effect as an
actual click on the confirmation link.
