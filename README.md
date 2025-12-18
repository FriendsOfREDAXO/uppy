# Uppy Uploader für REDAXO
Multiuploader für REDAXO basierend auf [Uppy 5.0](https://uppy.io/).

![Screenshot](https://github.com/FriendsOfREDAXO/uppy/blob/assets/uppy_screen2.png?raw=true)

## Features

- ✅ **Moderne UI**: Responsive File-Upload-Oberfläche mit Drag & Drop
- ✅ **Chunk-Upload**: Unterstützung für sehr große Dateien durch Aufteilung in kleine Pakete (umgeht PHP `upload_max_filesize`)
- ✅ **Client-seitige Bildoptimierung**: Automatisches Resizing und EXIF-Korrektur vor dem Upload
- ✅ **Image Editor**: Integrierte Bildbearbeitung (Zuschneiden, Drehen, Spiegeln) mit festen Seitenverhältnissen
- ✅ **Webcam-Integration**: Direkte Foto-Aufnahme im Browser
- ✅ **Metadaten-Verwaltung**: Automatische Erkennung und Editor für MetaInfo-Felder (inkl. Mehrsprachigkeit)
- ✅ **REDAXO Integration**: Volle Unterstützung für `REX_MEDIA`, `REX_MEDIALIST` und YForm
- ✅ **Dark Theme**: Automatische Erkennung und manuelle Umschaltung
- ✅ **Lokaler Build**: Keine externen CDN-Abhängigkeiten (DSGVO-konform)

## Voraussetzungen

- REDAXO >= 5.17.1
- PHP >= 8.1

## Installation

1. AddOn über den REDAXO-Installer herunterladen und installieren.
2. AddOn aktivieren.

## Konfiguration

Die globalen Einstellungen finden Sie unter **Uppy → Einstellungen**:

- **Erlaubte Dateitypen**: Definieren Sie die zulässigen MIME-Types.
  - *Neu:* Nutzen Sie den Button **"Dateitypen auswählen"**, um bequem aus einer Liste gängiger Formate (Bilder, Dokumente, Video, Audio) zu wählen.
- **Maximale Dateigröße**: Obergrenze pro Datei in MB.
- **Maximale Anzahl Dateien**: Limit pro Upload-Vorgang.
- **Standard-Kategorie**: Zielkategorie im Mediapool (falls keine spezifische gewählt wird).
- **Chunk-Upload**: Ermöglicht den Upload von Dateien, die größer sind als das PHP-Limit (`upload_max_filesize`).
- **Bildoptimierung**: Aktiviert das client-seitige Verkleinern von Bildern (spart Bandbreite und Server-Ressourcen).
- **Image Editor**: Aktiviert die Bildbearbeitungswerkzeuge.

## Verwendung

### Im Backend (Module / AddOns)

Uppy kann einfach über `data`-Attribute in jedem File-Input aktiviert werden:

```html
<input type="file" 
       data-widget="uppy" 
       data-category-id="1"
       data-max-files="10"
       data-max-filesize="200"
       data-allowed-types="image/*,application/pdf">
```

**Verfügbare Attribute:**
- `data-widget="uppy"`: Aktiviert das Widget
- `data-category-id="1"`: Ziel-Kategorie ID
- `data-max-files="5"`: Maximale Anzahl Dateien
- `data-allowed-types="image/*"`: Erlaubte Typen
- `data-enable-image-editor="true"`: Image Editor aktivieren

### Mediapool Widgets

Das AddOn klinkt sich automatisch in die Standard-Widgets ein:

- **REX_MEDIA**: Öffnet Uppy im Popup. Nach Upload "Übernehmen" klicken.
- **REX_MEDIALIST**: Ermöglicht den Upload mehrerer Dateien und deren Übernahme in die Liste ("Alle übernehmen").

### YForm Integration

Nutzen Sie den Feldtyp `uppy_uploader` (falls verfügbar) oder ein Textfeld mit Custom-Attributen.

```php
$yform->setValueField('text', [
    'name' => 'dateien',
    'label' => 'Dateien',
    'attributes' => [
        'data-widget' => 'uppy',
        'data-max-files' => 5
    ]
]);
```

## Technische Details

### Chunk Upload
Der Chunk-Upload teilt große Dateien in kleine Blöcke (Standard: 5MB) und sendet diese sequenziell an den Server. Dies verhindert Timeouts und Memory-Limits bei großen Uploads.

### SVG Support
SVG-Dateien werden automatisch vom client-seitigen Resizing ausgeschlossen, um eine Rasterisierung (Umwandlung in PNG) zu verhindern. Sie bleiben als Vektorgrafiken erhalten.

### Build
Das Projekt nutzt `esbuild` für das Bundling der Assets.
```bash
npm install
npm run build
```

## Lizenz

MIT License

---
**Friends Of REDAXO**
[GitHub](https://github.com/FriendsOfREDAXO/uppy)
