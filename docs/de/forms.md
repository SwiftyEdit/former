---
title: Former - Formulare & Felder
description: Ein Formular anlegen und mit Feldern bestücken
btn: Formulare & Felder
group: addons
priority: 200
---

# Ein Formular anlegen

1. In der Formular-Übersicht auf **+ Neues Formular** klicken.
2. Links unter **Einstellungen**: Name, Beschreibung, Status (aktiv/inaktiv), ob Einsendungen
   in der Datenbank gespeichert und/oder per E-Mail an ausgewählte Empfänger verschickt werden
   sollen, Erfolgs-/Fehlermeldung und Beschriftung des Absenden-Buttons.
3. Rechts über die Buttons in der Feld-Palette Felder hinzufügen (Text, Textarea, E-Mail,
   Zahl, Auswahlliste, Radiobuttons, Checkbox, Datei-Upload, ein verstecktes Feld, sowie ein
   reiner Text-/Erklärungsblock ohne Eingabe). Die Felder lassen sich per Drag & Drop in die
   gewünschte Reihenfolge bringen.
4. Empfänger für die E-Mail-Benachrichtigung werden zentral unter **Einstellungen** im
   Plugin-Tab verwaltet und dann pro Formular per Checkbox ausgewählt.

## Aussehen einzelner Felder anpassen

Jedes sichtbare Feld hat ein Eingabefeld **CSS-Klasse(n)** (verstecktes Feld ausgenommen, das
hat keinen Wrapper, an den sich eine Klasse anhängen ließe). Der Inhalt wird an die
vorhandenen Klassen des Feld-Wrappers angehängt (ersetzt sie nicht) - damit lassen sich z. B.
zwei Felder nebeneinander anordnen (mit den Grid-Klassen des eigenen Themes) oder ein
einzelnes Feld hervorheben. Mehrere Klassen werden wie gewohnt durch Leerzeichen getrennt
eingegeben.

## Verstecktes Feld

Ein normales Feld ohne sichtbare Eingabe (`<input type="hidden">`) - nützlich, um z. B. beim
Umzug eines bestehenden Formulars nach Former Tracking-Werte wie `gclid` oder UTM-Parameter
mitzuschicken, die ein bereits vorhandenes, seitenweites Skript (Google Ads/GTM,
Salesforce-Snippet o. ä.) anhand des Feldnamens automatisch befüllt - dafür muss der
**Feldname** exakt dem Namen entsprechen, den dieses Skript erwartet (z. B. `gclid` oder
`UTM_Source__c`). Sowohl `name` als auch `id` des gerenderten `<input>` entsprechen exakt
diesem Feldnamen (kein `fmr-`-Präfix wie bei den anderen Feldtypen) - ein Skript, das per
`id` oder `name` sucht, findet das Feld also gleichermaßen. Der optionale **Standardwert**
ist meist leer, außer für einen fest codierten Wert (z. B. eine Formular-Kennung), der nicht
extern gesetzt wird. "Pflichtfeld" gibt es hier bewusst nicht - ein verstecktes Feld, dessen
externes Skript nicht (rechtzeitig) läuft, dürfte niemals das ganze Formular blockieren. Wie
jedes andere Feld erscheint der übermittelte Wert in der Einsendungsliste, der
Benachrichtigungs-Mail und im `former:submitted`-Event.

## Template-Sets: das ganze Formular anders gestalten

Reicht eine CSS-Klasse nicht aus - z. B. weil ein Formular grundsätzlich anders aufgebaut
sein soll (eigenes Grid, normale statt Floating Labels, ein komplett eigenes Design) - lässt
sich unter **Einstellungen → Darstellung → Template-Set** pro Formular ein eigenes Set
auswählen. Sets werden als Unterordner unter `plugins/former/data/themes/` angelegt; die
genaue Anleitung mit Beispiel steht in der README in diesem Ordner. Ein Set übersteht Former-
und SwiftyEdit-Updates unverändert, weil `data/` davon grundsätzlich ausgenommen ist - eigene
Sets sind reine Installationssache und werden nicht mit dem Plugin ausgeliefert oder
committet.

## Einsendungen

Der eigene Tab **Einsendungen** (zwischen Formulare und Einstellungen) zeigt die Einsendungen
aller Formulare zusammen an, sofern „In Datenbank speichern" für das jeweilige Formular
aktiviert ist. Ein Filter oben schränkt die Liste auf **Alle Formulare** oder ein einzelnes
Formular ein - der „Einsendungen"-Button in der Formular-Übersicht führt in denselben Tab,
nur direkt mit diesem Formular vorausgewählt. Dort werden alle übermittelten Feldwerte,
angehängte Dateien sowie ggf. automatisch mitgesendete Daten (siehe die entsprechende Seite in
dieser Hilfe) aufgelistet; in der ungefilterten Ansicht zeigt ein Badge zusätzlich, zu welchem
Formular eine Einsendung gehört.
