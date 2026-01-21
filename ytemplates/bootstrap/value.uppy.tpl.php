<?php
/**
 * YForm Template: Uppy File Upload Widget
 * 
 * Verfügbare Variablen:
 * @var rex_yform_value_uppy_uploader $this
 */

// Authentifizierung: Token in Session schreiben für Frontend-Uploads
if (!rex::isBackend()) {
    $apiToken = rex_config::get('uppy', 'api_token');
    if ($apiToken) {
        rex_set_session('uppy_token', $apiToken);
    }
}

// Konfiguration aus YForm-Feld
$fieldName = $this->getName();
$fieldValue = $this->getValue();
$fieldId = 'yform-uppy-' . $fieldName;

// Parameter aus YForm-Feld-Definition
$categoryId = $this->getElement('category_id') !== '' ? (int)$this->getElement('category_id') : rex_config::get('uppy', 'category_id', 1);
$uploadFolder = $this->getElement('upload_folder') ?? '';

// Bei max_files: 0 ist gültig (unbegrenzt), daher !== '' prüfen
$maxFiles = $this->getElement('max_files') !== '' ? (int)$this->getElement('max_files') : rex_config::get('uppy', 'max_files', 10);

$maxFilesize = $this->getElement('max_filesize') !== '' ? (int)$this->getElement('max_filesize') : rex_config::get('uppy', 'max_filesize', 200);
$allowedTypes = $this->getElement('allowed_types') !== '' ? $this->getElement('allowed_types') : rex_config::get('uppy', 'allowed_types', '*');
$enableWebcam = $this->getElement('enable_webcam') !== '' ? (bool)$this->getElement('enable_webcam') : rex_config::get('uppy', 'enable_webcam', false);
$enableImageEditor = $this->getElement('enable_image_editor') !== '' ? (bool)$this->getElement('enable_image_editor') : rex_config::get('uppy', 'enable_image_editor', false);
$allowMediapool = $this->getElement('allow_mediapool') !== '' ? (bool)$this->getElement('allow_mediapool') : false;

// Signatur erstellen (für Sicherheit im Frontend)
// WICHTIG: Die Werte müssen exakt denen in den data-Attributen entsprechen
$signature = \FriendsOfRedaxo\Uppy\Signature::create([
    'category_id' => $categoryId,
    'allowed_types' => $allowedTypes,
    'max_filesize' => $maxFilesize, // MB-Wert (wie im data-Attribut)
    'upload_dir' => $uploadFolder
]);

// HTML Output
?>
<div class="form-group">
    <label class="control-label" for="<?= $fieldId ?>"><?= rex_escape($this->getLabel()) ?></label>
    
    <input 
        type="hidden" 
        id="<?= $fieldId ?>" 
        name="<?= $this->getFieldName() ?>" 
        value="<?= rex_escape($fieldValue) ?>"
        class="uppy-upload-widget"
        data-category-id="<?= (int)$categoryId ?>"
        data-upload-dir="<?= rex_escape($uploadFolder) ?>"
        data-max-files="<?= (int)$maxFiles ?>"
        data-max-filesize="<?= (int)$maxFilesize ?>"
        data-allowed-types="<?= rex_escape($allowedTypes) ?>"
        data-enable-webcam="<?= $enableWebcam ? '1' : '0' ?>"
        data-enable-image-editor="<?= $enableImageEditor ? '1' : '0' ?>"
        data-allow-mediapool="<?= $allowMediapool ? 'true' : 'false' ?>"
        data-uppy-signature="<?= $signature ?>"
    />
    
    <?php if ($notice = $this->getElement('notice')): ?>
    <p class="help-block"><?= rex_escape($notice) ?></p>
    <?php endif; ?>
</div>
