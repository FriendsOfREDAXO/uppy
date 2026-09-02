<?php
/**
 * YForm Template: Uppy File Upload Widget
 *
 * Verfügbare Variablen:
 * @var rex_yform_value_uppy_uploader $this
 */

// Authentifizierung: Token in Session schreiben für Frontend-Uploads
if (!rex::isBackend()) {
    \FriendsOfRedaxo\Uppy\Utils::ensureApiSession();
}

// Konfiguration aus YForm-Feld
$fieldName = $this->getName();
$fieldValue = $this->getValue();
$fieldId = 'yform-uppy-' . $fieldName;

// max_files/max_filesize/... bleiben leer statt 0, wenn im YForm-Feld nichts eingetragen ist -
// Field::render() greift dann selbst auf die globalen uppy-Einstellungen zurück.
$options = [];
foreach (['category_id', 'upload_folder', 'max_files', 'max_filesize', 'allowed_types', 'enable_webcam', 'enable_image_editor', 'allow_mediapool', 'show_file_access', 'file_access_mode'] as $key) {
    $elementValue = $this->getElement($key);
    if ($elementValue !== '' && $elementValue !== null) {
        $options[$key] = $elementValue;
    }
}
$options['id'] = $fieldId;

$fieldHtml = \FriendsOfRedaxo\Uppy\Field::render($this->getFieldName(), $options, $fieldValue);
?>
<div class="form-group">
    <label class="control-label" for="<?= $fieldId ?>"><?= rex_escape($this->getLabel()) ?></label>

    <?= $fieldHtml ?>

    <?php if ($notice = $this->getElement('notice')): ?>
    <p class="help-block"><?= rex_escape($notice) ?></p>
    <?php endif; ?>
</div>
