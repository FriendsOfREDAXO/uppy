<?php

/**
 * Uppy Image Editor Demo Seite
 */

$package = rex_addon::get('uppy');

echo rex_view::title($package->i18n('uppy_demo_title'));

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
                
                <div class="alert alert-info">
                    <strong>Hinweis:</strong> Um den Image Editor zu aktivieren, gehen Sie zu 
                    <a href="index.php?page=uppy/settings">Einstellungen</a> und aktivieren Sie 
                    <code>enable_image_editor</code>.
                </div>
                
                <h4>Beispiel 1: Avatar Upload (1:1)</h4>
                <p>Perfekt für Benutzerprofile - erzwingt quadratisches Format:</p>
                <div class="form-group">
                    <input 
                        type="hidden" 
                        name="avatar_demo" 
                        id="avatar_demo"
                        data-widget="uppy"
                        data-api-token="' . rex_config::get('uppy', 'api_token', '') . '"
                        data-max-files="1"
                        data-max-filesize="10"
                        data-allowed-types="image/*"
                        data-category-id="0"
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
                        data-api-token="' . rex_config::get('uppy', 'api_token', '') . '"
                        data-max-files="1"
                        data-max-filesize="20"
                        data-allowed-types="image/*"
                        data-category-id="0"
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
                        data-api-token="' . rex_config::get('uppy', 'api_token', '') . '"
                        data-max-files="5"
                        data-max-filesize="15"
                        data-allowed-types="image/*"
                        data-category-id="0"
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
                
                <h3>Konfiguration</h3>
                <p>Aktuelle Einstellungen:</p>
                <table class="table table-striped">
                    <tbody>
                        <tr>
                            <th>Image Editor aktiviert:</th>
                            <td>' . (rex_config::get('uppy', 'enable_image_editor', false) ? '<span class="label label-success">Ja</span>' : '<span class="label label-default">Nein</span>') . '</td>
                        </tr>
                        <tr>
                            <th>Verfügbare Seitenverhältnisse:</th>
                            <td><code>' . rex_config::get('uppy', 'crop_ratios', '1:1,16:9,4:3,3:2,free') . '</code></td>
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
            </div>
        </div>
    </div>
</div>
';

echo $content;

