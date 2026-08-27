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
   Zahl, Auswahlliste, Radiobuttons, Checkbox, Datei-Upload, sowie ein reiner
   Text-/Erklärungsblock ohne Eingabe). Die Felder lassen sich per Drag & Drop in die
   gewünschte Reihenfolge bringen.
4. Empfänger für die E-Mail-Benachrichtigung werden zentral unter **Einstellungen** im
   Plugin-Tab verwaltet und dann pro Formular per Checkbox ausgewählt.

## Aussehen einzelner Felder anpassen

Jedes Feld hat ein Eingabefeld **CSS-Klasse(n)**. Der Inhalt wird an die vorhandenen Klassen
des Feld-Wrappers angehängt (ersetzt sie nicht) - damit lassen sich z. B. zwei Felder
nebeneinander anordnen (mit den Grid-Klassen des eigenen Themes) oder ein einzelnes Feld
hervorheben. Mehrere Klassen werden wie gewohnt durch Leerzeichen getrennt eingegeben.

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

Jedes Formular hat einen eigenen **Einsendungen**-Bereich (erreichbar über den Button in der
Formular-Übersicht), sofern „In Datenbank speichern" aktiviert ist. Dort werden alle
übermittelten Feldwerte, angehängte Dateien sowie ggf. automatisch mitgesendete Daten (siehe
die entsprechende Seite in dieser Hilfe) aufgelistet.
