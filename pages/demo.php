<?php

/**
 * Uppy Demo Seite
 */

$package = rex_addon::get('uppy');

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
        <li><a href="#tab_docs" data-toggle="tab" aria-expanded="false">Doku & API</a></li>
    </ul>
    <div class="tab-content">
        
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
        </div>

        <!-- Tab 4: Restrictions -->
        <div class="tab-pane" id="tab_restricted">
            <h3>Einschränkungen</h3>
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

        <!-- Tab 6: Docs -->
        <div class="tab-pane" id="tab_docs">
            <h3>Verfügbare Data-Attribute</h3>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Attribut</th>
                        <th>Beschreibung</th>
                        <th>Standard</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>data-widget="uppy"</code></td>
                        <td>Aktiviert das Standard Dashboard Widget</td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td><code>class="uppy-upload-widget"</code></td>
                        <td>Aktiviert das Custom Widget (Minimal UI)</td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td><code>data-category-id</code></td>
                        <td>ID der Mediapool-Kategorie für Uploads</td>
                        <td>0</td>
                    </tr>
                    <tr>
                        <td><code>data-max-files</code></td>
                        <td>Maximale Anzahl an Dateien</td>
                        <td>10</td>
                    </tr>
                    <tr>
                        <td><code>data-max-filesize</code></td>
                        <td>Maximale Dateigröße in Megabyte</td>
                        <td>200</td>
                    </tr>
                    <tr>
                        <td><code>data-allowed-types</code></td>
                        <td>Erlaubte Dateitypen (MIME-Types oder Extensions), kommagetrennt</td>
                        <td>(alle)</td>
                    </tr>
                    <tr>
                        <td><code>data-enable-image-editor</code></td>
                        <td>Aktiviert den Bild-Editor (true/false)</td>
                        <td>false</td>
                    </tr>
                    <tr>
                        <td><code>data-lang</code></td>
                        <td>Sprache des Widgets (de_DE, en_US)</td>
                        <td>de_DE</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
';

$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
