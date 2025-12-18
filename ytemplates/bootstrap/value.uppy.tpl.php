<?php
/**
 * @var rex_yform_value_uppy_uploader $this
 */

$addon = rex_addon::get('uppy');

// Eindeutige Widget-ID
$widgetId = 'uppy-yform-' . uniqid();

// Werte aus der Konfiguration
$name = $this->getFieldName();
$label = $this->getLabel();
$categoryId = (int) ($this->getElement('category_id') ?: rex_config::get('uppy', 'category_id', 0));
$maxFiles = (int) ($this->getElement('max_files') ?: rex_config::get('uppy', 'max_files', 10));
$maxFilesize = (int) ($this->getElement('max_filesize') ?: rex_config::get('uppy', 'max_filesize', 200));
$allowedTypes = $this->getElement('allowed_types') ?: rex_config::get('uppy', 'allowed_types', 'image/*,application/pdf');
$enableWebcam = (bool) ($this->getElement('enable_webcam') ?: rex_config::get('uppy', 'enable_webcam', false));
$value = $this->getValue();

// Notice-Text
$notice = 'Max. ' . $maxFiles . ' Datei(en), je max. ' . $maxFilesize . ' MB';

?>
<div class="form-group yform-element <?= $this->getWarningClass() ?>">
    <?php if ($label): ?>
        <label class="control-label" for="<?= $widgetId ?>">
            <?= rex_escape($label) ?>
        </label>
    <?php endif; ?>
    
    <div class="yform-element-value">
        <input type="file"
               name="<?= rex_escape($name) ?>"
               id="<?= $widgetId ?>"
               data-widget="uppy"
               data-category-id="<?= $categoryId ?>"
               data-max-files="<?= $maxFiles ?>"
               data-max-filesize="<?= $maxFilesize ?>"
               data-allowed-types="<?= rex_escape($allowedTypes) ?>"
               data-enable-chunks="<?= rex_config::get('uppy', 'enable_chunks', true) ? 'true' : 'false' ?>"
               data-chunk-size="<?= rex_config::get('uppy', 'chunk_size', 5) ?>"
               data-enable-webcam="<?= $enableWebcam ? 'true' : 'false' ?>"
        />
        
        <!-- Hidden Input für aktuellen Wert -->
        <input type="hidden" 
               name="<?= rex_escape($name) ?>" 
               id="<?= $widgetId ?>_value" 
               value="<?= rex_escape($value) ?>" 
        />
        
        <?php if ($notice): ?>
            <p class="help-block"><?= $notice ?></p>
        <?php endif; ?>
        
        <?php if ($value): ?>
            <div class="uppy-current-files" style="margin-top: 15px;">
                <strong>Aktuelle Dateien:</strong>
                <div style="margin-top: 10px;">
                    <?php
                    $files = array_filter(array_map('trim', explode(',', $value)));
                    foreach ($files as $filename):
                        $media = rex_media::get($filename);
                        if ($media):
                    ?>
                        <div class="uppy-file-item" style="display: inline-block; margin: 5px 10px 5px 0; padding: 5px 10px; background: #f5f5f5; border-radius: 3px;">
                            <?php if ($media->isImage() && rex_addon::get('media_manager')->isAvailable()): ?>
                                <img src="<?= rex_url::frontend('index.php?rex_media_type=rex_media_small&rex_media_file=' . urlencode($filename)) ?>" 
                                     alt="" 
                                     style="max-width: 50px; max-height: 50px; vertical-align: middle; margin-right: 5px;" 
                                />
                            <?php else: ?>
                                <i class="fa fa-file" style="margin-right: 5px;"></i>
                            <?php endif; ?>
                            <span><?= rex_escape($filename) ?></span>
                            <a href="#" 
                               class="uppy-remove-file" 
                               data-filename="<?= rex_escape($filename) ?>" 
                               data-target="<?= $widgetId ?>_value"
                               style="margin-left: 10px; color: #d9534f;"
                               title="Datei entfernen">
                                <i class="fa fa-times"></i>
                            </a>
                        </div>
                    <?php
                        endif;
                    endforeach;
                    ?>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Datei entfernen
                $('.uppy-remove-file').on('click', function(e) {
                    e.preventDefault();
                    var filename = $(this).data('filename');
                    var targetId = $(this).data('target');
                    var $input = $('#' + targetId);
                    
                    // Aus kommaseparierter Liste entfernen
                    var files = $input.val().split(',').map(function(f) { return f.trim(); });
                    files = files.filter(function(f) { return f && f !== filename; });
                    $input.val(files.join(','));
                    
                    // Element ausblenden
                    $(this).closest('.uppy-file-item').fadeOut(300, function() {
                        $(this).remove();
                    });
                });
            });
            </script>
        <?php endif; ?>
    </div>
    
    <?php if ($this->getWarningMessages()): ?>
        <span class="help-block">
            <?= implode('<br>', $this->getWarningMessages()) ?>
        </span>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Nach Upload: Dateinamen zum Hidden Input hinzufügen
    $(document).on('uppyUploadSuccess', function(e, data) {
        if (data.widgetId === '<?= $widgetId ?>') {
            var $input = $('#<?= $widgetId ?>_value');
            var currentValue = $input.val();
            var files = currentValue ? currentValue.split(',') : [];
            
            // Neue Datei hinzufügen
            if (data.filename && files.indexOf(data.filename) === -1) {
                files.push(data.filename);
                $input.val(files.join(','));
            }
        }
    });
});
</script>
