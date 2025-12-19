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

Uppy kann einfach über `data`-Attribute in einem `hidden` Input-Feld aktiviert werden. Das Widget kümmert sich um die Darstellung und das Speichern der Dateinamen.

```html
<input type="hidden" 
       name="REX_INPUT_VALUE[1]" 
       value="REX_VALUE[1]"
       data-widget="uppy" 
       data-category-id="1"
       data-max-files="10"
       data-max-filesize="200"
       data-allowed-types="image/*,application/pdf">
```

**Verfügbare Attribute:**
- `data-widget="uppy"`: Aktiviert das Widget
- `data-category-id="1"`: Ziel-Kategorie ID im Mediapool
- `data-max-files="5"`: Maximale Anzahl Dateien
- `data-allowed-types="image/*"`: Erlaubte Typen (MIME-Types oder Extensions)
- `data-enable-image-editor="true"`: Image Editor aktivieren
- `data-lang="de_DE"`: Sprache erzwingen (optional)

### YForm Integration

In YForm können Sie das Uppy-Widget einfach über das Feld "Attribute" konfigurieren. Hierzu wird ein JSON-Objekt verwendet.

**Beispiel 1: Standard Dashboard**
```json
{
    "data-widget": "uppy",
    "data-category-id": "1",
    "data-max-files": "10"
}
```

**Beispiel 2: Custom Widget (Minimal UI)**
```json
{
    "class": "uppy-upload-widget",
    "data-category-id": "1",
    "data-max-files": "5",
    "data-allowed-types": "image/*"
}
```

### Demo Seite

Eine ausführliche Demo mit Live-Beispielen und Quellcode finden Sie im Backend unter **Uppy → Demo**.

## Technische Details

### Metadaten & Mehrsprachigkeit
Das AddOn unterstützt automatisch alle im System definierten Metainfo-Felder (`med_...`). 
Mehrsprachige Felder werden erkannt und können direkt im Upload-Dialog für alle Sprachen gepflegt werden. Die Speicherung erfolgt als JSON-String in der Datenbank.

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
MIT
