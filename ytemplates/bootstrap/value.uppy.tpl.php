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
$categoryId = $this->getElement('category_id') ?: rex_config::get('uppy', 'category_id', 1);
$maxFiles = $this->getElement('max_files') ?: rex_config::get('uppy', 'max_files', 10);
$maxFilesize = $this->getElement('max_filesize') ?: rex_config::get('uppy', 'max_filesize', 10);
$allowedTypes = $this->getElement('allowed_types') ?: rex_config::get('uppy', 'allowed_types', '*');
$enableWebcam = $this->getElement('enable_webcam') !== '' ? (bool)$this->getElement('enable_webcam') : rex_config::get('uppy', 'enable_webcam', false);
$enableImageEditor = $this->getElement('enable_image_editor') !== '' ? (bool)$this->getElement('enable_image_editor') : rex_config::get('uppy', 'enable_image_editor', false);

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
        data-max-files="<?= (int)$maxFiles ?>"
        data-max-filesize="<?= (int)$maxFilesize ?>"
        data-allowed-types="<?= rex_escape($allowedTypes) ?>"
        data-enable-webcam="<?= $enableWebcam ? '1' : '0' ?>"
        data-enable-image-editor="<?= $enableImageEditor ? '1' : '0' ?>"
    />
    
    <?php if ($notice = $this->getElement('notice')): ?>
    <p class="help-block"><?= rex_escape($notice) ?></p>
    <?php endif; ?>
</div>
