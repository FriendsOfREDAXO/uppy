# Uppy im Frontend verwenden

## Problem
Wenn Uppy im Frontend verwendet wird, werden die hochgeladenen Dateien zwar im Mediapool gespeichert, aber die Liste im Frontend bleibt leer und zeigt die Warnung:
```
Upload success event but invalid response body
```

## Ursache
Die Response vom Upload-Handler wird nicht korrekt verarbeitet. Dies kann mehrere Gründe haben:
1. Fehlende oder falsche API-Token-Authentifizierung
2. Zusätzlicher Output vor der JSON-Response (Warnings, Notices)
3. Falsche Response-Struktur

## Lösung

### 1. API-Token korrekt setzen

Der API-Token muss sowohl in der Session als auch als data-Attribut gesetzt werden:

```php
<?php
// WICHTIG: EINMALIG am Anfang des Templates/Moduls ausführen
rex_set_session('uppy_token', rex_config::get('uppy', 'api_token'));
$uppy = rex_addon::get('uppy');
$apiToken = rex_config::get('uppy', 'api_token');
?>

<!-- CSS im <head> -->
<link rel="stylesheet" href="<?= $uppy->getAssetsUrl('dist/uppy-frontend-bundle.css') ?>">

<!-- HTML-Element für Uppy -->
<input 
    type="hidden" 
    class="uppy-upload-widget"
    name="my_upload_field" 
    value=""
    data-widget="uppy" 
    data-api-token="<?= htmlspecialchars($apiToken) ?>"
    data-category-id="0"
    data-max-files="5"
    data-max-filesize="10"
    data-allowed-types="image/jpeg,image/png,application/pdf"
>

<!-- JavaScript am Ende des <body> -->
<script src="<?= $uppy->getAssetsUrl('dist/uppy-custom-widget-bundle.js') ?>"></script>
<script>
// Widget initialisieren
document.addEventListener('DOMContentLoaded', function() {
    const widget = document.querySelector('.uppy-upload-widget');
    if (widget && !widget.dataset.uppyInitialized) {
        new UppyCustomWidget.UppyCustomWidget(widget);
    }
});
</script>
```

### 2. Debug-Modus aktivieren

Um zu sehen, was genau schiefgeht, aktivieren Sie den Debug-Modus in den Uppy-Einstellungen:

1. Backend → AddOns → Uppy → Einstellungen
2. "Debug-Logging aktivieren" → Ja
3. Speichern

Dann schauen Sie in:
- **Browser Console** (F12): Zeigt die Response-Struktur
- **REDAXO Log** (System → Logdateien): Zeigt die PHP-seitige Response

### 3. Häufige Fehlerquellen

#### Fehler: "Unauthorized access"
- API-Token ist nicht gesetzt oder falsch
- Session wurde nicht gestartet
- Token stimmt nicht mit dem im Backend konfigurierten überein

Lösung:
```php
// Prüfen ob Token korrekt ist
$configToken = rex_config::get('uppy', 'api_token');
$sessionToken = rex_session('uppy_token', 'string', '');

if (!$configToken) {
    echo 'FEHLER: API-Token ist nicht konfiguriert!';
    echo 'Bitte im Backend unter AddOns → Uppy → Einstellungen einen Token generieren.';
}

if ($configToken !== $sessionToken) {
    echo 'FEHLER: Session-Token stimmt nicht überein!';
    rex_set_session('uppy_token', $configToken);
}
```

#### Fehler: "Upload success event but invalid response body"
- Die Response hat nicht die erwartete Struktur `{success: true, data: {filename: "..."}}`
- Es gibt zusätzlichen Output vor der JSON-Response

Lösung:
```php
// In der boot.php oder im Template sicherstellen, dass kein Output vor der JSON-Response kommt
// Keine echo, print_r, var_dump, etc. vor dem Upload

// Alternativ: Error Reporting im Frontend reduzieren
if (!rex::isBackend()) {
    error_reporting(E_ERROR | E_PARSE);
}
```

### 4. Alternative: Uppy ohne Token (nur für YCom-Login)

Wenn Sie YCom verwenden und Benutzer eingeloggt sind, funktioniert Uppy auch ohne API-Token:

```php
<?php
// Prüfen ob Benutzer eingeloggt ist
if (!rex_ycom_auth::getUser()) {
    echo 'Bitte melden Sie sich an, um Dateien hochzuladen.';
    exit;
}

$uppy = rex_addon::get('uppy');
?>

<input 
    type="hidden" 
    class="uppy-upload-widget"
    name="my_upload_field" 
    value=""
    data-widget="uppy" 
    data-category-id="0"
    data-max-files="5"
    data-max-filesize="10"
    data-allowed-types="image/jpeg,image/png"
>
```

### 5. Vollständiges Beispiel mit Formular

```php
<?php
// Am Anfang des Templates
rex_set_session('uppy_token', rex_config::get('uppy', 'api_token'));
$uppy = rex_addon::get('uppy');
$apiToken = rex_config::get('uppy', 'api_token');

// Formular-Verarbeitung
if (rex_post('submit', 'string')) {
    $files = rex_post('my_upload_field', 'string');
    
    if ($files) {
        $fileArray = explode(',', $files);
        echo '<p>Hochgeladene Dateien: ' . count($fileArray) . '</p>';
        echo '<ul>';
        foreach ($fileArray as $filename) {
            echo '<li>' . htmlspecialchars($filename) . '</li>';
            
            // Datei aus Mediapool laden
            $media = rex_media::get($filename);
            if ($media) {
                echo '<br><img src="' . $media->getUrl() . '" style="max-width: 200px;">';
            }
        }
        echo '</ul>';
    } else {
        echo '<p>Keine Dateien hochgeladen.</p>';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Uppy Frontend Upload</title>
    <link rel="stylesheet" href="<?= $uppy->getAssetsUrl('dist/uppy-frontend-bundle.css') ?>">
</head>
<body>
    <h1>Dateien hochladen</h1>
    
    <form method="post">
        <label>Bilder hochladen:</label>
        <input 
            type="hidden" 
            class="uppy-upload-widget"
            name="my_upload_field" 
            value=""
            data-widget="uppy" 
            data-api-token="<?= htmlspecialchars($apiToken) ?>"
            data-category-id="0"
            data-max-files="5"
            data-max-filesize="10"
            data-allowed-types="image/jpeg,image/png"
        >
        
        <button type="submit" name="submit" value="1">Formular absenden</button>
    </form>
    
    <script src="<?= $uppy->getAssetsUrl('dist/uppy-custom-widget-bundle.js') ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const widget = document.querySelector('.uppy-upload-widget');
        if (widget && !widget.dataset.uppyInitialized) {
            new UppyCustomWidget.UppyCustomWidget(widget);
        }
    });
    </script>
</body>
</html>
```

## Debugging-Checkliste

1. ✅ API-Token ist in den Einstellungen generiert
2. ✅ `data-api-token` Attribut ist gesetzt
3. ✅ Session-Token ist gesetzt via `rex_set_session()`
4. ✅ Debug-Logging ist aktiviert
5. ✅ Browser Console zeigt Response-Struktur
6. ✅ REDAXO Log zeigt keine PHP-Fehler
7. ✅ Bundles sind aktuell (nach Code-Änderungen neu builden!)

## Bundle neu bauen

Nach Änderungen an den JavaScript-Dateien:

```bash
cd /Users/thomas/redaxo_instances/core/project/public/redaxo/src/addons/uppy
npm run build
```

## Support

Bei weiteren Problemen:
1. Browser Console (F12) öffnen
2. Network-Tab öffnen
3. Upload durchführen
4. Request an `rex-api-call=uppy_uploader` suchen
5. Response-Tab prüfen: Was kommt zurück?
6. Console-Tab prüfen: Was sagt die Debug-Ausgabe?

Die Debug-Ausgabe zeigt:
```javascript
{
    hasBody: true/false,
    body: {...},
    bodySuccess: true/false,
    bodyStatus: "...",
    fullResponse: {...}
}
```

Wenn `bodySuccess` nicht `true` ist, liegt ein Problem mit der Response-Struktur vor.
