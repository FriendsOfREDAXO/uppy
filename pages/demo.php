<?php

/**
 * Uppy Image Editor Demo Seite
 */

$package = rex_addon::get('uppy');


$content = '
<div class="row">
    <div class="col-lg-12">
        <div class="panel panel-default">
            <header class="panel-heading">
                <div class="panel-title">Image Editor Demo</div>
            </header>
            <div class="panel-body">
                <p class="lead">Diese Seite demonstriert die optionalen Features des Uppy Addons:</p>
                
                <h3>Image Editor - Bildbearbeitung mit festen Seitenverhältnissen</h3>
                <p>Der Image Editor ermöglicht das Zuschneiden von Bildern mit verschiedenen Seitenverhältnissen:</p>
                <ul>
                    <li><strong>1:1</strong> - Perfekt für Avatare, Profilbilder, Thumbnails</li>
                    <li><strong>16:9</strong> - Standard für Header-Bilder, Hero-Images</li>
                    <li><strong>4:3</strong> - Klassisches Fotoformat</li>
                    <li><strong>3:2</strong> - Übliches DSLR-Format</li>
                    <li><strong>Free</strong> - Freies Zuschneiden ohne Beschränkung</li>
                </ul>
';

// Hinweis zur Bildbearbeitung
$content .= '
                <div class="alert alert-info">
                    <i class="rex-icon fa-info-circle"></i> 
                    <strong>Bildbearbeitung per Feld:</strong> 
                    Die folgenden Upload-Felder haben <code>data-enable-image-editor="true"</code> gesetzt.
                    Bei Einzel-Uploads öffnet sich der Editor automatisch, bei Multi-Uploads über den Edit-Button.
                </div>
';

$content .= '
                <h4>Beispiel 1: Avatar Upload (1:1)</h4>
                <p>Perfekt für Benutzerprofile - erzwingt quadratisches Format:</p>
                <div class="form-group">
                    <input 
                        type="hidden" 
                        name="avatar_demo" 
                        id="avatar_demo"
                        data-widget="uppy"
                        data-max-files="1"
                        data-max-filesize="10"
                        data-allowed-types="image/*"
                        data-category-id="0"
                        data-enable-image-editor="true"
                    />
                </div>
                
                <hr>
                
                <h4>Beispiel 2: Header-Bild Upload (16:9)</h4>
                <p>Ideal für Website-Header und Hero-Bereiche:</p>
                <div class="form-group">
                    <input 
                        type="hidden" 
                        name="header_demo" 
                        id="header_demo"
                        data-widget="uppy"
                        data-max-files="1"
                        data-max-filesize="20"
                        data-allowed-types="image/*"
                        data-category-id="0"
                        data-enable-image-editor="true"
                    />
                </div>
                
                <hr>
                
                <h4>Beispiel 3: Mehrfach-Upload mit freiem Zuschnitt</h4>
                <p>Flexible Bildbearbeitung für allgemeine Zwecke:</p>
                <div class="form-group">
                    <input 
                        type="hidden" 
                        name="multi_demo" 
                        id="multi_demo"
                        data-widget="uppy"
                        data-max-files="5"
                        data-max-filesize="15"
                        data-allowed-types="image/*"
                        data-category-id="0"
                        data-enable-image-editor="true"
                    />
                </div>
                
                <hr>
                
                <h3>Webcam Integration</h3>
                <p>Mit aktiviertem Webcam-Plugin können Nutzer direkt Fotos aufnehmen:</p>
                <div class="alert alert-info">
                    <strong>Hinweis:</strong> Aktivieren Sie <code>enable_webcam</code> in den 
                    <a href="index.php?page=uppy/settings">Einstellungen</a>.
                </div>
                
                <hr>
                
                <h3>Konfiguration & Debug</h3>
                <p>Aktuelle Einstellungen:</p>
                <table class="table table-striped">
                    <tbody>
                        <tr>
                            <th>Image Editor:</th>
                            <td><span class="label label-info">Per Feld aktiviert</span> (data-enable-image-editor="true")</td>
                        </tr>
                        <tr>
                            <th>Seitenverhältnisse:</th>
                            <td><code>1:1, 16:9, 4:3, 3:2, free</code></td>
                        </tr>
                        <tr>
                            <th>Webcam aktiviert:</th>
                            <td>' . (rex_config::get('uppy', 'enable_webcam', false) ? '<span class="label label-success">Ja</span>' : '<span class="label label-default">Nein</span>') . '</td>
                        </tr>
                        <tr>
                            <th>Bildgröße (Resize):</th>
                            <td>' . rex_config::get('uppy', 'resize_width', 2000) . ' x ' . rex_config::get('uppy', 'resize_height', 2000) . ' px</td>
                        </tr>
                        <tr>
                            <th>JPEG Qualität:</th>
                            <td>' . rex_config::get('uppy', 'resize_quality', 85) . '%</td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="alert alert-warning">
                    <h4>Wichtige Hinweise zum Image Editor:</h4>
                    <ul class="list-unstyled">
                        <li>✓ Funktioniert nur mit Bildern (image/*)</li>
                        <li>✓ Nutzer können drehen, spiegeln, zoomen und zuschneiden</li>
                        <li>✓ Basiert auf Cropper.js (MIT License)</li>
                        <li>✓ Client-seitige Bearbeitung (kein Server-Upload von Original)</li>
                        <li>✓ Kombinierbar mit automatischem Resize</li>
                    </ul>
                </div>
                
                <h3>Audio Recording</h3>
                <div class="alert alert-danger">
                    <strong>Nicht verfügbar:</strong> Das @uppy/audio Plugin ist derzeit nur für Uppy 4.x verfügbar. 
                    Eine Version für Uppy 5.0 ist noch nicht erschienen. Wir beobachten die Entwicklung und werden 
                    das Feature ergänzen, sobald es verfügbar ist.
                </div>

                <hr>

                <h3>Custom Widget (Standalone)</h3>
                <p>Das Custom Widget bietet eine alternative, schlanke Darstellung ohne das große Dashboard-Modal als Standard-Ansicht. Es eignet sich besonders für Formulare, bei denen der Upload dezent integriert werden soll.</p>
                
                <div class="form-group">
                    <label>Beispiel Custom Widget:</label>
                    <input 
                        type="text" 
                        class="form-control uppy-upload-widget" 
                        name="custom_widget_demo" 
                        value=""
                        data-max-files="5"
                        data-allowed-types="image/*,application/pdf"
                    />
                </div>

                <h4>Frontend Integration</h4>
                <p>Um das Custom Widget (oder das Standard Dashboard) im Frontend zu nutzen, müssen die Assets manuell eingebunden werden:</p>
                
                <pre><code>&lt;!-- CSS einbinden --&gt;
&lt;link rel="stylesheet" href="/assets/addons/uppy/uppy-core.min.css"&gt;
&lt;link rel="stylesheet" href="/assets/addons/uppy/uppy-dashboard.min.css"&gt;
&lt;link rel="stylesheet" href="/assets/addons/uppy/uppy-custom-widget.css"&gt;

&lt;!-- JS einbinden --&gt;
&lt;script src="/assets/addons/uppy/uppy-frontend-bundle.js"&gt;&lt;/script&gt;

&lt;!-- Widget HTML --&gt;
&lt;input type="text" 
       class="form-control uppy-upload-widget" 
       name="upload" 
       data-api-token="OPTIONAL_API_TOKEN"&gt;</code></pre>

                <div class="alert alert-info">
                    <strong>Hinweis:</strong> Für den Upload im Frontend wird ggf. ein API-Token benötigt, falls der Zugriff nicht öffentlich erlaubt ist.
                </div>
            </div>
        </div>
    </div>
</div>
';

echo $content;

?>
<script>
// Minimaler Debug: Prüfe ob Uppy geladen wurde
jQuery(document).ready(function() {
    if (!window.UPPY_BUNDLE_LOADED) {
        console.error('Uppy Bundle wurde nicht geladen!');
    }
});
</script>
<?php

