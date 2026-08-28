---
title: Former - Automatisch mitgesendete Daten
description: Angemeldeten Benutzer, Benutzerdaten bzw. Seiten-Informationen mitschicken
btn: Automatisch mitgesendete Daten
group: addons
priority: 400
---

# Automatisch mitgesendete Daten

In den Formular-Einstellungen gibt es drei Checkboxen unter **„Automatisch mitgesendete
Daten"**, die zusätzlich zu den eigentlichen Formularfeldern in die Einsendungsliste, die
Benachrichtigungs-Mail und das `former:submitted`-JavaScript-Event (siehe die
Entwickler-Seite in dieser Hilfe) aufgenommen werden. Standardmäßig sind alle drei deaktiviert.

- **Angemeldeten Benutzer mitschicken**: User-ID, Benutzername und E-Mail-Adresse des
  angemeldeten Besuchers (sofern er angemeldet ist - bei Gästen wird nichts ergänzt).
- **Benutzerdaten mitschicken**: die IP-Adresse des Absenders, die Referrer-URL beim
  Absenden (i. d. R. die Seite, auf der das Formular liegt - nicht zwingend die
  ursprüngliche Anzeige/Quelle, falls der Besucher vorher schon auf der Website unterwegs
  war) sowie der Browser (User-Agent) des Absenders.
- **Seiten-Informationen mitschicken**: die Seiten-URL, auf der das Formular eingebunden
  war, sowie der Zeitpunkt der Absendung.

## Datenschutz

Diese Daten sind personenbezogen. Ob und wie ihr sie einsetzt (z. B. Weiterleitung an
Google Ads oder Salesforce über das Theme, siehe die Entwickler-Seite), und ob ihr Besucher
darüber informiert, liegt in eurer eigenen Verantwortung als Betreiber - z. B. über einen
**„Text / Erklärung"**-Block direkt im Formular oder eure Datenschutzerklärung.
