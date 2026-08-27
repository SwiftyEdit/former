# Template-Sets

Jedes Formular kann unter **Einstellungen → Darstellung → Template-Set** ein eigenes Set
auswählen. Ein Set ist ein Unterordner hier in `data/themes/`, dessen Name nur aus
Buchstaben, Ziffern, `_` und `-` bestehen darf.

Dieser Ordner (`data/themes/`) gehört *nicht* zum Plugin-Release - er wird beim Bauen der
Release-Zip ausgeschlossen und vom Core-Installer bei Updates grundsätzlich übersprungen
(siehe `.gitignore` und `scripts/build_plugin_release.sh` im Projekt-Root). Ein Former- oder
SwiftyEdit-Update kann eigene Sets hier also nicht überschreiben oder löschen - und umgekehrt
bringt das Plugin selbst auch kein fertiges Set mit. Alles unterhalb von `data/themes/` außer
dieser README ist bewusst von Git ausgeschlossen: Sets sind reine Installationssache, nie Teil
des Plugin-Codes.

## Wie ein Set aufgebaut ist

Ein Set spiegelt die Struktur von `plugins/former/templates/` - **pro Datei optional**. Fehlt
eine Datei im Set, wird automatisch die Standard-Datei aus `templates/` verwendet. Ein Set
muss also nur die Dateien enthalten, die es tatsächlich ändert:

```
data/themes/<slug>/
├── form-wrapper.tpl          (optional)
├── success.tpl                (optional)
└── fields/
    ├── text.tpl                (optional)
    ├── textarea.tpl             (optional)
    ├── email.tpl                (optional)
    ├── number.tpl               (optional)
    ├── select.tpl               (optional)
    ├── radio.tpl                (optional)
    ├── checkbox.tpl             (optional)
    ├── file.tpl                 (optional)
    ├── text_block.tpl           (optional)
    ├── captcha-math.tpl         (optional)
    └── captcha-recaptcha.tpl    (optional)
```

Am einfachsten startet man mit einer Kopie der Datei(en) aus `../../templates/`, die man
ändern möchte, und passt darin nur das an, was gebraucht wird - die Platzhalter-Tokens
(`{label}`, `{value}` usw.) müssen dabei erhalten bleiben, sonst rendert das jeweilige Feld
nicht korrekt.

## Verfügbare Tokens je Datei

- **form-wrapper.tpl**: `{form_id}` `{banner_html}` `{enctype}` `{fields_html}`
  `{captcha_html}` `{hidden_csrf_token}` `{sendtime}` `{submit_label}`
- **success.tpl**: `{form_id}` `{message}` `{tracking_event_json}`
- **fields/text.tpl, email.tpl, textarea.tpl**: `{name}` `{label}` `{placeholder}` `{value}`
  `{required}` `{css_class}` (textarea zusätzlich `{rows}`)
- **fields/number.tpl**: zusätzlich `{min}` `{max}`
- **fields/select.tpl**: `{name}` `{label}` `{required}` `{css_class}` `{options_html}`
- **fields/radio.tpl**: `{label}` `{css_class}` `{options_html}`
- **fields/checkbox.tpl**: `{name}` `{label}` `{required}` `{css_class}` `{checked}`
- **fields/file.tpl**: `{name}` `{label}` `{required}` `{css_class}` `{accept}` `{multiple}`
- **fields/text_block.tpl**: `{css_class}` `{content}`
- **fields/captcha-math.tpl**: `{captcha_label}`
- **fields/captcha-recaptcha.tpl**: `{site_key}`

`{css_class}` ist immer das, was im Formular-Editor pro Feld unter "CSS-Klasse(n)" eingetragen
wurde - unabhängig vom gewählten Set. Ein Set bestimmt die *Struktur* eines Feldtyps (z. B.
Label-Position); die freie Klasse bleibt der Hebel für die einzelne Feld-*Instanz* (z. B.
Breite oder Hervorhebung innerhalb dieser Struktur).

## Beispiel: zweispaltiges Formular mit normalen statt Floating Labels

Ein Set namens z. B. `footer-2col` könnte so aussehen (selbst anzulegen - keines der
Beispiele hier ist Teil der Installation).

`data/themes/footer-2col/form-wrapper.tpl` - Felder in ein Bootstrap-Grid packen:

```html
<div id="fmr-form-{form_id}">
<div class="card shadow-sm p-4">
{banner_html}
<form hx-post="/xhr/plugins/former/" hx-target="#fmr-form-{form_id}" hx-swap="outerHTML" {enctype}>
<input type="hidden" name="form_id" value="{form_id}">
<div class="row g-3">
{fields_html}
</div>
{captcha_html}
<div style="position:absolute;left:-9999px;top:-9999px;" aria-hidden="true">
<input type="text" name="fmr_hp" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="sendtime" value="{sendtime}">
{hidden_csrf_token}
<button type="submit" class="btn btn-primary mt-3">{submit_label}</button>
</form>
</div>
</div>
```

`data/themes/footer-2col/fields/text.tpl` - normales Label statt Floating Label, `{css_class}`
steuert hier die Spaltenbreite (z. B. `col-md-6` für Vorname/Nachname nebeneinander, leer
gelassen fällt auf volle Breite `col-12` zurück). Die Zeilenabstände kommen vom `g-3`-Gutter
des Grids im Wrapper, nicht mehr von einem `mb-3` je Feld:

```html
<div class="col-12 {css_class}">
<label class="form-label" for="fmr-{name}">{label}</label>
<input type="text" name="{name}" id="fmr-{name}" class="form-control" placeholder="{placeholder}" value="{value}" {required}>
</div>
```

Alle anderen Feldtypen in `fields/` folgen demselben Muster (normales Label statt Floating
Label, `col-12 {css_class}` als Wrapper) - so bleibt das Formular auch bei gemischten
Feldtypen durchgängig zweispaltig-fähig. Nur die beiden Captcha-Templates sind unverändert
(fallen auf `templates/` zurück), weil sie außerhalb des Grids stehen und immer volle Breite
brauchen.

Im Formular-Editor braucht dieses Formular dann nur noch: Template-Set = `footer-2col`
wählen, und beim Vorname-/Nachname-Feld `col-md-6` als CSS-Klasse eintragen - alle anderen
Felder (z. B. eine Nachricht als Textarea) bleiben ohne Klasse und damit volle Breite.
