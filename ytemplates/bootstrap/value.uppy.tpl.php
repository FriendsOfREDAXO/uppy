<?php
$class       = $this->getElement('required') ? 'form-is-required ' : '';
$class_group = trim('form-group  ' . $class . $this->getWarningClass());
$category = 0;
if ($this->getElement('category') != '') {
    $category = (int) ($this->getElement('category'));
}
?>

<div class="<?php echo $class_group; ?>" id="<?php echo $this->getHTMLId(); ?>">

    <?php if (!empty($this->getElement('label'))): ?><label for="<?= $this->getFieldName() ?>"><?= $this->getElement('label') ?></label><?php endif; ?>
    <input type="hidden" id="<?= $this->getFieldId() ?>" name="<?= $this->getFieldName() ?>" value="<?= $this->getValue() ?>"/>

    <div class="uppy_container" id="uppy_<?= $this->getFieldId() ?>" data-field="<?= $this->getFieldId() ?>"></div>
    <script>

        const uppy = Uppy.Core({
            debug: true,
            autoProceed: false,
            restrictions: {
                // maxFileSize: 1000000,
                maxNumberOfFiles: 3,
                minNumberOfFiles: 1
                // , allowedFileTypes: ['image/*', 'video/*']
            }
        })
            .use(Uppy.Dashboard, {
                trigger: '.UppyModalOpenerBtn',
                inline: true,
                target: '.uppy_container',
                replaceTargetContent: true,
                showProgressDetails: true,
                // note: 'Images and video only, 2–3 files, up to 1 MB',
                height: 470,
                metaFields: [
                    { id: 'name', name: 'Name', placeholder: 'file name' },
                    { id: 'caption', name: 'Caption', placeholder: 'describe what the image is about' }
                ],
                browserBackButtonClose: true
            })
            //.use(Uppy.GoogleDrive, { target: Uppy.Dashboard, serverUrl: 'https://companion.uppy.io' })
            //.use(Uppy.Dropbox, { target: Uppy.Dashboard, serverUrl: 'https://companion.uppy.io' })
            //.use(Uppy.Instagram, { target: Uppy.Dashboard, serverUrl: 'https://companion.uppy.io' })
            //.use(Uppy.Webcam, { target: Uppy.Dashboard })
            .use(Uppy.XHRUpload, {
                endpoint: 'index.php?uppy=1&media_category=<?= $category; ?>',
                formData: true,
                fieldName: 'rex_uppy_file'
            })

        uppy.on('upload-success', (file, body) => {
            console.log(file);
            console.log(body.body);
            console.log(body.body.dirname);
            console.log(body.body.filename);
            console.log(body.body.pathinfo);
            let element_input = $('#<?= $this->getFieldId() ?>'),
                val = element_input.val();
            val = (val !== '') ? body.body.filename + ',' + val : body.body.filename;
            element_input.val(val);
        })

        uppy.on('complete', result => {
            console.log('successful files:', result.successful)
            console.log('failed files:', result.failed)
        })


    </script>


</div>
