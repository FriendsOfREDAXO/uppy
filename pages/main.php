<?php

use Uppy\Provider\UppyTokenProvider;

$tokenValue = UppyTokenProvider::getToken();
$tokenParam = UppyTokenProvider::getTokenParameterKey();

$rex_file_category = rex_request('rex_file_category', 'int', -1);
$PERMALL = rex::getUser()->getComplexPerm('media')->hasCategoryPerm(0);
if (!$PERMALL && !rex::getUser()->getComplexPerm('media')->hasCategoryPerm($rex_file_category)) {
    $rex_file_category = 0;
}
$cats_sel = new rex_media_category_select();
$cats_sel->setStyle('class="form-control"');
$cats_sel->setSize(1);
$cats_sel->setName('rex_file_category');
$cats_sel->setId('rex-mediapool-category');
$cats_sel->addOption(rex_i18n::msg('pool_kats_no'), '0');
$cats_sel->setSelected($rex_file_category);

$content = <<<EOT

            <form id="fileupload" method="POST" enctype="multipart/form-data">
                <fieldset>
                    <dl class="rex-form-group form-group">
                        <dt>
                            <label for="rex-mediapool-title">Titel</label>
                        </dt>
                        <dd>
                            <input class="form-control" type="text" name="ftitle" value="" id="rex-mediapool-title">
                        </dd>
                    </dl>
                    <dl class="rex-form-group form-group">
                        <dt>
                            <label for="rex-mediapool-category"><?php echo rex_i18n::msg('pool_file_category'); ?></label>
                        </dt>
                        <dd>
                            {$cats_sel->get()}
                        </dd>
                    </dl>
                    <dl class="rex-form-group form-group">
                        <dt>
                            <label for="rex-mediapool-category"><?php echo rex_i18n::msg('pool_file_category'); ?></label>
                        </dt>
                        <dd><div class="DashboardContainer"></div></dd>
                    </dl>
                </fieldset>
            </form>


<script>

const uppy = Uppy.Core({
  debug: true,
  autoProceed: false,
  locale: Uppy.locales.de_DE,
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
  target: '.DashboardContainer',
  replaceTargetContent: true,
  showProgressDetails: true,
  // note: 'Images and video only, 2–3 files, up to 1 MB',
  height: 470,
  width: '100%',
  metaFields: [
    { id: 'name', name: 'Name', placeholder: 'file name' },
    { id: 'caption', name: 'Caption', placeholder: 'describe what the image is about' }
  ],
  browserBackButtonClose: true
})
// .use(Uppy.GoogleDrive, { target: Uppy.Dashboard, serverUrl: 'https://companion.uppy.io' })
// .use(Uppy.Dropbox, { target: Uppy.Dashboard, serverUrl: 'https://companion.uppy.io' })
// .use(Uppy.Instagram, { target: Uppy.Dashboard, serverUrl: 'https://companion.uppy.io' })
.use(Uppy.Webcam, { target: Uppy.Dashboard })
.use(Uppy.XHRUpload, { 
    endpoint: 'index.php?uppy=1&$tokenParam=$tokenValue',
    formData: true,
    fieldName: 'rex_uppy_file'
})

// uppy.addFile({
//   name: 'bildschirmfoto_2019-12-09_um_19.08.41_1.png', // file name
//   type: 'image/jpeg', // file type
//   // data: blob, // file blob
//   source: '/media/', // optional, determines the source of the file, for example, Instagram
//   isRemote: false // optional, set to true if actual file is not in the browser, but on some remote server, for example, when using companion in combination with Instagram
// })


uppy.on('upload-success', (file, body) => {
    console.log(file);
    console.log(body.body);
    console.log(body.body.dirname);
    console.log(body.body.filename);
    console.log(body.body.pathinfo);
})

uppy.on('complete', result => {
  console.log('successful files:', result.successful)
  console.log('failed files:', result.failed)
})


</script>


EOT;


$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('uppy_main_title') , false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
