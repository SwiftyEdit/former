---
title: Former - Bestätigung & Einwilligungs-Nachweis
description: Double-Opt-In per E-Mail sowie leichtgewichtiges Protokollieren von Consent-Checkboxen
btn: Bestätigung & Einwilligung
group: addons
priority: 350
---

# Bestätigung, dass der Absender das Formular wirklich selbst abgeschickt hat

Für Fälle wie eine Newsletter-Anmeldung oder eine Umfrage-Teilnahme gibt es zwei
unabhängig voneinander nutzbare Bausteine: ein leichtgewichtiges Einwilligungs-Protokoll
ohne Mail-Umweg, und ein vollständiges Double-Opt-In per E-Mail.

## Einwilligungs-Nachweis (leichtgewichtig)

Jedes **Checkbox (Zustimmung)**-Feld im Formular-Editor hat die Option **„Als
Einwilligungs-Nachweis protokollieren"**. Ist sie aktiv und wird die Checkbox beim Absenden
angehakt, speichert Former zusätzlich zur Einsendung: den genauen Text des Feld-Labels zum
Zeitpunkt der Absendung, Datum/Uhrzeit sowie die IP-Adresse des Absenders. Das erscheint als
eigener Block **„Einwilligungs-Nachweis"** in der Einsendungsliste und in der
Benachrichtigungs-Mail.

Das ist unabhängig von den Checkboxen unter „Automatisch mitgesendete Daten" (siehe die
entsprechende Seite in dieser Hilfe) - jene protokollieren IP/Zeitpunkt für die *gesamte*
Einsendung, dieser Nachweis ist gezielt an eine einzelne Zustimmungs-Checkbox samt ihrem
genauen Wortlaut gebunden. Reicht z. B. für eine Umfrage, bei der belegt werden soll, dass
der Teilnehmer selbst zugestimmt hat - ganz ohne E-Mail-Bestätigung.

## Bestätigung per E-Mail (Double-Opt-In)

Für Fälle, die eine echte Bestätigung der E-Mail-Adresse brauchen (klassisch:
Newsletter-Anmeldung), gibt es unter **Einstellungen → Bestätigung per E-Mail
(Double-Opt-In)** pro Formular:

- **Bestätigung per E-Mail erforderlich**: schaltet den Ablauf ein. Erzwingt automatisch
  „In Datenbank speichern", da eine noch nicht bestätigte Einsendung sonst nirgends
  gespeichert werden könnte.
- **Feld mit der zu bestätigenden E-Mail-Adresse**: welches E-Mail-Feld des Formulars die
  Zieladresse liefert. „Automatisch" nimmt das erste E-Mail-Feld des Formulars.
- **Betreff/Text der Bestätigungs-Mail**: mit den Platzhaltern `{confirm_link}` und
  `{form_name}`.
- **Meldung nach erfolgreicher Bestätigung**.
- **Bestätigungslink gültig für (Stunden)**: Standard 48 Stunden.

### Ablauf

1. Die Einsendung wird beim Absenden als „ausstehend" gespeichert (kein Eintrag im
   Bereich „Einsendungen" wird übersprungen - sie erscheint dort sofort mit dem Hinweis
   „Bestätigung ausstehend").
2. Statt der normalen Erfolgsmeldung sieht der Besucher einen Hinweis, seine E-Mails zu
   prüfen - inklusive eines „Erneut senden"-Buttons, falls die Mail nicht ankommt.
3. Der Bestätigungslink führt zurück auf genau die Seite, auf der das Formular eingebettet
   ist (dort, wo der Shortcode `[plugin=former]...[/plugin]` steht) - nicht auf eine technische
   Adresse. Dort erscheint statt des Formulars ein **Bestätigen**-Button. Bewusst kein
   automatisches Bestätigen beim bloßen Öffnen des Links: E-Mail-Sicherheitsscanner rufen
   Links in Mails teils automatisch vorab auf, was sonst fälschlich als Bestätigung durch den
   Empfänger gezählt würde.
4. Erst mit dem Klick auf „Bestätigen" werden die Benachrichtigungs-Mail an die unter
   „Empfänger" ausgewählten Adressen verschickt und das `former:submitted`-JavaScript-Event
   ausgelöst (siehe die Entwickler-Seite in dieser Hilfe) - nicht schon beim ursprünglichen
   Absenden.

Da der Bestätigungs-Schritt innerhalb der normalen Seite (samt Theme, Header/Footer und
einem eventuell eingebundenen Google-Tag-Manager-Snippet) läuft, funktionieren dort
eingebundene Tracking-Snippets ganz normal mit.

### Manuell bestätigen

In der Einsendungsliste gibt es bei einer noch ausstehenden Einsendung zusätzlich den Button
**„Als bestätigt markieren"** - für Sonderfälle wie eine telefonisch bestätigte Anmeldung.
Löst denselben Effekt aus wie ein echter Klick auf den Bestätigungslink.
