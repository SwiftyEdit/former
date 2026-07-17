# Former

Erstellt beliebig viele Formulare per Drag & Drop.

## Einbindung

```
[plugin=former]form_id=3[/plugin]
```

Die Formular-ID steht in der Formular-Liste im Backend (Addons > Former).

## Funktionen

- Beliebig viele Formulare, per Drag & Drop aus Feld-Templates zusammengestellt
- Feldtypen: Text, Textarea, E-Mail, Zahl, Auswahlliste, Radiobuttons, Checkbox, Datei-Upload,
  sowie ein reiner Text-/Erklärungsblock ohne Eingabe (z.B. für längere Hinweistexte im Formular)
- Pro Formular wählbar: Einsendungen in der Datenbank speichern und/oder per E-Mail an
  ausgewählte Empfänger senden (oder beides)
- Mehrere Empfänger lassen sich in den Plugin-Einstellungen hinterlegen; pro Formular
  wird per Checkbox ausgewählt, wer benachrichtigt wird
- Captcha: einfaches Rechen-Captcha (Standard) oder Google reCAPTCHA v2, umschaltbar in
  den Plugin-Einstellungen
- Datei-Uploads landen in einem eigenen Verzeichnis (`plugins/former/uploads/<form_id>/`)
  und werden nicht in die zentrale Medienbibliothek übernommen, da Absender anonym sind
- Absenden erfolgt per HTMX ohne Neuladen der Seite

## Hinweis zur Aktivierung

Der Shortcode rendert das Formular auch, wenn das Plugin nicht aktiviert ist. Das
Absenden (`/xhr/plugins/former/`) funktioniert jedoch erst, nachdem das Plugin unter
Addons aktiviert wurde.

## Lizenz

GPL-3.0 — siehe [license.txt](license.txt).
