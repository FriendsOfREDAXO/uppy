<?php

$content = <<<EOT


<div class="DashboardContainer"></div>
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
  target: '.DashboardContainer',
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
.use(Uppy.GoogleDrive, { target: Uppy.Dashboard, serverUrl: 'https://companion.uppy.io' })
.use(Uppy.Dropbox, { target: Uppy.Dashboard, serverUrl: 'https://companion.uppy.io' })
.use(Uppy.Instagram, { target: Uppy.Dashboard, serverUrl: 'https://companion.uppy.io' })
.use(Uppy.Webcam, { target: Uppy.Dashboard })
.use(Uppy.XHRUpload, { 
    endpoint: 'index.php?uppy=1',
    formData: true,
    fieldName: 'rex_uppy_file'
})

uppy.addFile({
  name: 'image.jpg', // file name
  type: 'image/jpeg', // file type
  // data: blob, // file blob
  source: '/media/', // optional, determines the source of the file, for example, Instagram
  isRemote: false // optional, set to true if actual file is not in the browser, but on some remote server, for example, when using companion in combination with Instagram
})


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
$fragment->setVar('title', $this->i18n('main_title'), false);
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
