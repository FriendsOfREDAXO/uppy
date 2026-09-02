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

### 1. API-Token sicher setzen

**WICHTIG:** Der API-Token darf **niemals** im HTML-Code ausgegeben werden (z.B. als `data-api-token`), da er sonst für jeden Besucher sichtbar ist!
Nutzen Sie stattdessen die PHP-Session - dafür gibt es `Utils::init()`, das den Token
in die Session schreibt **und** die passenden CSS-/JS-Tags ausgibt (siehe
[frontend_usage.md](frontend_usage.md)):

```php
<?php
use FriendsOfRedaxo\Uppy\Utils;

// EINMALIG am Anfang des Templates/Moduls ausführen (vor der HTML-Ausgabe)
echo Utils::init();
?>

<!-- Feld-Markup: keine data-Attribute von Hand pflegen, keine eigene Signatur -->
<?= Utils::field('my_upload_field', [
    'category_id'   => 0,
    'max_files'     => 5,
    'max_filesize'  => 10,
    'allowed_types' => 'image/jpeg,image/png,application/pdf',
]) ?>
```

Das im JS-Bundle enthaltene Custom-Widget initialisiert Felder mit
`class="uppy-upload-widget"` (das `Utils::field()` automatisch setzt) beim Laden der
Seite selbst - ein eigenes Init-`<script>` im Template ist nicht nötig.

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
use FriendsOfRedaxo\Uppy\Utils;

// Prüfen ob Benutzer eingeloggt ist
if (!rex_ycom_auth::getUser()) {
    echo 'Bitte melden Sie sich an, um Dateien hochzuladen.';
    exit;
}

echo Utils::assets(); // kein Token nötig -> ensureApiSession()/init() kann hier entfallen
?>

<?= Utils::field('my_upload_field', [
    'category_id'   => 0,
    'max_files'     => 5,
    'max_filesize'  => 10,
    'allowed_types' => 'image/jpeg,image/png',
]) ?>
```

### 5. Vollständiges Beispiel mit Formular

```php
<?php
use FriendsOfRedaxo\Uppy\Utils;

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
    <?= Utils::assets() ?>
</head>
<body>
    <h1>Dateien hochladen</h1>

    <?php Utils::ensureApiSession(); // Token in die Session schreiben, bevor das Formular ausgegeben wird ?>
    <form method="post">
        <label>Bilder hochladen:</label>
        <?= Utils::field('my_upload_field', [
            'category_id'   => 0,
            'max_files'     => 5,
            'max_filesize'  => 10,
            'allowed_types' => 'image/jpeg,image/png',
        ]) ?>

        <button type="submit" name="submit" value="1">Formular absenden</button>
    </form>
</body>
</html>
```

Kein eigenes Init-`<script>` nötig - `Utils::assets()` lädt das Custom-Widget-Bundle,
das Felder mit `class="uppy-upload-widget"` (von `Utils::field()` gesetzt) selbst
initialisiert.

## Debugging-Checkliste

1. ✅ API-Token ist in den Einstellungen generiert
2. ✅ `Utils::init()` bzw. `Utils::ensureApiSession()` läuft vor der Formular-Ausgabe
3. ✅ Session-Token stimmt mit dem konfigurierten Token überein
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
