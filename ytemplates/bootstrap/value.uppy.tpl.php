<?php
/**
 * @var rex_yform_value_uppy_uploader $this
 */

$addon = rex_addon::get('uppy');

// Werte aus der Konfiguration
$name = $this->getFieldName();
$label = $this->getLabel();
$categoryId = $this->getElement('category_id') ?: rex_config::get('uppy', 'category_id', 0);
$maxFiles = $this->getElement('max_files') ?: rex_config::get('uppy', 'max_files', 10);
$maxFilesize = $this->getElement('max_filesize') ?: rex_config::get('uppy', 'max_filesize', 200);
$allowedTypes = $this->getElement('allowed_types') ?: rex_config::get('uppy', 'allowed_types', 'image/*,application/pdf');
$value = $this->getValue();

// API-Token für Frontend
$apiToken = rex_config::get('uppy', 'api_token', '');

$notice = rex_i18n::msg('uppy_yform_notice', $maxFilesize);

?>
<div class="form-group yform-element">
    <?php if ($label): ?>
        <label class="control-label" for="<?= $this->getFieldId() ?>">
            <?= rex_escape($label) ?>
        </label>
    <?php endif; ?>
    
    <div class="yform-element-value">
        <input type="file"
               name="<?= rex_escape($name) ?>"
               id="<?= $this->getFieldId() ?>"
               value="<?= rex_escape($value) ?>"
               data-widget="uppy"
               data-uppy-category="<?= (int) $categoryId ?>"
               data-uppy-max-files="<?= (int) $maxFiles ?>"
               data-uppy-max-filesize="<?= (int) $maxFilesize ?>"
               data-uppy-allowed-types="<?= rex_escape($allowedTypes) ?>"
               data-uppy-api-token="<?= rex_escape($apiToken) ?>"
               data-uppy-enable-chunks="<?= rex_config::get('uppy', 'enable_chunks', true) ? 'true' : 'false' ?>"
               data-uppy-chunk-size="<?= rex_config::get('uppy', 'chunk_size', 5) ?>"
        />
        
        <?php if ($notice): ?>
            <p class="help-block"><?= rex_escape($notice) ?></p>
        <?php endif; ?>
        
        <?php if ($this->getValue()): ?>
            <div class="uppy-current-files">
                <strong><?= rex_i18n::msg('yform_uppy_current_files') ?>:</strong>
                <?php
                $files = array_filter(explode(',', $this->getValue()));
                foreach ($files as $filename):
                    $media = rex_media::get(trim($filename));
                    if ($media):
                ?>
                    <div class="uppy-file-item">
                        <?php if ($media->isImage()): ?>
                            <img src="<?= rex_url::media($filename) ?>" alt="" style="max-width: 100px; max-height: 100px;" />
                        <?php endif; ?>
                        <span><?= rex_escape($filename) ?></span>
                    </div>
                <?php
                    endif;
                endforeach;
                ?>
            </div>
        <?php endif; ?>
    </div>
</div>
