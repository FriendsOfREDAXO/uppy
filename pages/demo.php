<?php

/**
 * Uppy Demo Seite
 */

$package = rex_addon::get('uppy');

// Signatur für Demo erstellen
$signedParams = [
    'category_id' => 0,
    'allowed_types' => 'image/jpeg,image/png',
    'max_filesize' => 0.5 // 0.5 MB = 500 KB
];
$signature = FriendsOfRedaxo\Uppy\Signature::create($signedParams);

// Fragment für Tabs erstellen
$fragment = new rex_fragment();
$fragment->setVar('title', 'Uppy Widget Demo', false);

// Tab-Inhalt vorbereiten
$content = '
<div class="nav-tabs-custom">
    <ul class="nav nav-tabs">
        <li class="active"><a href="#tab_standard" data-toggle="tab" aria-expanded="true">Standard</a></li>
        <li><a href="#tab_custom" data-toggle="tab" aria-expanded="false">Custom Widget</a></li>
        <li><a href="#tab_editor" data-toggle="tab" aria-expanded="false">Image Editor</a></li>
        <li><a href="#tab_restricted" data-toggle="tab" aria-expanded="false">Restriktionen</a></li>
        <li><a href="#tab_yform" data-toggle="tab" aria-expanded="false">YForm</a></li>
    </ul>
    <div class="tab-content" style="padding-top: 20px;">
        
        <!-- Tab 1: Standard -->
        <div class="tab-pane active" id="tab_standard">
            <h3>Standard Dashboard</h3>
            <p>Das Standard-Widget zeigt das volle Uppy Dashboard direkt an. Es eignet sich für Bereiche, wo viel Platz zur Verfügung steht.</p>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Beispiel:</label>
                        <input 
                            type="hidden" 
                            name="demo_standard" 
                            value=""
                            data-widget="uppy"
                            data-category-id="0"
                        />
                    </div>
                </div>
                <div class="col-md-6">
                    <h4>Code</h4>
                    <pre><code class="language-html">&lt;input 
    type="hidden" 
    name="rex_input_value" 
    value=""
    data-widget="uppy"
    data-category-id="0" 
/&gt;</code></pre>
                </div>
            </div>
        </div>

        <!-- Tab 2: Custom Widget -->
        <div class="tab-pane" id="tab_custom">
            <h3>Custom Widget (Minimal UI)</h3>
            <p>Eine minimalistische Darstellung als Liste. Das Dashboard öffnet sich erst beim Klick auf "Dateien hinzufügen". Ideal für YForm oder schmale Spalten.</p>
            
            <!-- Custom Widget JS/CSS laden -->
            <script src="' . $package->getAssetsUrl('dist/uppy-custom-widget-bundle.js') . '"></script>
            <link rel="stylesheet" href="' . $package->getAssetsUrl('css/uppy-custom-widget.css') . '">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Beispiel:</label>
                        <input 
                            type="hidden" 
                            class="uppy-upload-widget"
                            name="demo_custom" 
                            value=""
                            data-category-id="0"
                            data-max-files="5"
                        />
                    </div>
                </div>
                <div class="col-md-6">
                    <h4>Code</h4>
                    <pre><code class="language-html">&lt;input 
    type="hidden" 
    class="uppy-upload-widget"
    name="rex_input_value" 
    value=""
    data-category-id="0"
    data-max-files="5" 
/&gt;</code></pre>
                </div>
            </div>

            <hr>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Beispiel mit Optionen (Nur Bilder, Max 2, Editor):</label>
                        <input 
                            type="hidden" 
                            class="uppy-upload-widget"
                            name="demo_custom_opts" 
                            value=""
                            data-category-id="0"
                            data-max-files="2"
                            data-allowed-types="image/*"
                            data-enable-image-editor="true"
                        />
                    </div>
                </div>
                <div class="col-md-6">
                    <h4>Code mit Optionen</h4>
                    <pre><code class="language-html">&lt;input 
    type="hidden" 
    class="uppy-upload-widget"
    name="rex_input_value_opts" 
    value=""
    data-category-id="0"
    data-max-files="2"
    data-allowed-types="image/*"
    data-enable-image-editor="true"
/&gt;</code></pre>
                </div>
            </div>

            <hr>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Beispiel: Signiert (Manipulationssicher)</label>
                        <p class="help-block">Auch das Custom Widget unterstützt signierte Parameter.</p>
                        <input 
                            type="hidden" 
                            class="uppy-upload-widget"
                            name="demo_custom_signed" 
                            value=""
                            data-category-id="'.$signedParams['category_id'].'"
                            data-allowed-types="'.$signedParams['allowed_types'].'"
                            data-max-filesize="'.$signedParams['max_filesize'].'"
                            data-uppy-signature="'.$signature.'"
                        />
                    </div>
                </div>
                <div class="col-md-6">
                    <h4>Code (Signiert)</h4>
                    <pre><code class="language-php">&lt;?php
use FriendsOfRedaxo\Uppy\Signature;

$params = [
    \'category_id\' => 0,
    \'allowed_types\' => \'image/jpeg,image/png\',
    \'max_filesize\' => 0.5 // 0.5 MB
];
$sig = Signature::create($params);
?&gt;</code></pre>
                    <pre><code class="language-html">&lt;input 
    type="hidden" 
    class="uppy-upload-widget"
    name="rex_input_value_signed" 
    value=""
    data-category-id="&lt;?= $params[\'category_id\'] ?&gt;"
    data-allowed-types="&lt;?= $params[\'allowed_types\'] ?&gt;"
    data-max-filesize="&lt;?= $params[\'max_filesize\'] ?&gt;"
    data-uppy-signature="&lt;?= $sig ?&gt;"
/&gt;</code></pre>
                </div>
            </div>
        </div>

        <!-- Tab 3: Image Editor -->
        <div class="tab-pane" id="tab_editor">
            <h3>Bildbearbeitung</h3>
            <p>Mit <code>data-enable-image-editor="true"</code> wird der integrierte Editor aktiviert (Zuschneiden, Drehen, etc.).</p>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Beispiel:</label>
                        <input 
                            type="hidden" 
                            name="demo_editor" 
                            value=""
                            data-widget="uppy"
                            data-category-id="0"
                            data-enable-image-editor="true"
                        />
                    </div>
                </div>
                <div class="col-md-6">
                    <h4>Code</h4>
                    <pre><code class="language-html">&lt;input 
    type="hidden" 
    name="rex_input_value" 
    value=""
    data-widget="uppy"
    data-enable-image-editor="true" 
/&gt;</code></pre>
                </div>
            </div>

            <hr>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Beispiel: Auto-Open (Einzelbild)</label>
                        <p class="help-block">Wenn <code>data-max-files="1"</code> gesetzt ist, öffnet sich der Editor automatisch nach dem Upload.</p>
                        <input 
                            type="hidden" 
                            name="demo_editor_auto" 
                            value=""
                            data-widget="uppy"
                            data-category-id="0"
                            data-enable-image-editor="true"
                            data-max-files="1"
                        />
                    </div>
                </div>
                <div class="col-md-6">
                    <h4>Code</h4>
                    <pre><code class="language-html">&lt;input 
    type="hidden" 
    name="rex_input_value" 
    value=""
    data-widget="uppy"
    data-enable-image-editor="true"
    data-max-files="1"
/&gt;</code></pre>
                </div>
            </div>
        </div>

        <!-- Tab 4: Restrictions -->
        <div class="tab-pane" id="tab_restricted">
            <h3>Einschränkungen & Sicherheit</h3>
            <p>Begrenzung auf bestimmte Dateitypen (z.B. nur Bilder) und Anzahl.</p>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Beispiel (Max 3 Bilder):</label>
                        <input 
                            type="hidden" 
                            name="demo_restricted" 
                            value=""
                            data-widget="uppy"
                            data-category-id="0"
                            data-allowed-types="image/*"
                            data-max-files="3"
                        />
                    </div>
                </div>
                <div class="col-md-6">
                    <h4>Code</h4>
                    <pre><code class="language-html">&lt;input 
    type="hidden" 
    name="rex_input_value" 
    value=""
    data-widget="uppy"
    data-allowed-types="image/*"
    data-max-files="3" 
/&gt;</code></pre>
                </div>
            </div>

            <hr>

            <h3>Manipulationssicherer Upload (Signiert)</h3>
            <p>Um zu verhindern, dass Nutzer die Einschränkungen im Browser manipulieren (z.B. via Inspektor), können die Parameter serverseitig signiert werden.</p>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Beispiel (Nur JPG/PNG, max 500KB, signiert):</label>
                        <input 
                            type="hidden" 
                            name="demo_signed" 
                            value=""
                            data-widget="uppy"
                            data-category-id="'.$signedParams['category_id'].'"
                            data-allowed-types="'.$signedParams['allowed_types'].'"
                            data-max-filesize="'.$signedParams['max_filesize'].'"
                            data-uppy-signature="'.$signature.'"
                        />
                        <p class="help-block">Versuchen Sie im Inspektor <code>data-max-filesize</code> zu erhöhen - der Upload wird fehlschlagen.</p>
                    </div>
                </div>
                <div class="col-md-6">
                    <h4>PHP Code</h4>
                    <pre><code class="language-php">&lt;?php
use FriendsOfRedaxo\Uppy\Signature;

$params = [
    \'category_id\' => 0,
    \'allowed_types\' => \'image/jpeg,image/png\',
    \'max_filesize\' => 500 * 1024 // 500 KB
];
$sig = Signature::create($params);
?&gt;

&lt;input 
    type="hidden" 
    data-widget="uppy"
    data-category-id="&lt;?= $params[\'category_id\'] ?&gt;"
    data-allowed-types="&lt;?= $params[\'allowed_types\'] ?&gt;"
    data-max-filesize="&lt;?= $params[\'max_filesize\'] ?&gt;"
    data-uppy-signature="&lt;?= $sig ?&gt;"
/&gt;</code></pre>
                </div>
            </div>
        </div>

        <!-- Tab 5: YForm -->
        <div class="tab-pane" id="tab_yform">
            <h3>YForm Integration</h3>
            <p>In YForm können Sie das Uppy-Widget einfach über das Feld "Attribute" konfigurieren. Hierzu wird ein JSON-Objekt verwendet.</p>

            <div class="row">
                <div class="col-md-6">
                    <h4>1. Standard Widget</h4>
                    <p>Nutzen Sie dies für das volle Dashboard.</p>
                    <p><strong>Feld-Konfiguration:</strong></p>
                    <ul>
                        <li>Typ: <code>text</code></li>
                        <li>Name: <code>images</code></li>
                        <li>Label: <code>Bilder</code></li>
                    </ul>
                    <p><strong>Attribute (JSON):</strong></p>
                    <pre><code class="language-json">{
    "data-widget": "uppy",
    "data-category-id": "1",
    "data-max-files": "10"
}</code></pre>
                </div>
                <div class="col-md-6">
                    <h4>2. Custom Widget (Minimal UI)</h4>
                    <p>Nutzen Sie dies für eine kompakte Liste.</p>
                    <p><strong>Feld-Konfiguration:</strong></p>
                    <ul>
                        <li>Typ: <code>text</code></li>
                        <li>Name: <code>documents</code></li>
                        <li>Label: <code>Dokumente</code></li>
                    </ul>
                    <p><strong>Attribute (JSON):</strong></p>
                    <pre><code class="language-json">{
    "class": "uppy-upload-widget",
    "data-category-id": "2",
    "data-max-files": "5",
    "data-allowed-types": "application/pdf"
}</code></pre>
                </div>
            </div>

            <h4>Mit Image Editor</h4>
            <p>Um den Image Editor zu aktivieren, fügen Sie einfach das entsprechende Attribut hinzu:</p>
            <pre><code class="language-json">{
    "data-widget": "uppy",
    "data-enable-image-editor": "true",
    "data-category-id": "1"
}</code></pre>
        </div>
    </div>
</div>
';

$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
