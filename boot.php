<?php

/** @var rex_addon $this */

if (rex::isBackend() && rex::getUser()) {
    try {
        rex_view::addCssFile($this->getAssetsUrl('css/uppy.min.css'));
        rex_view::addJsFile($this->getAssetsUrl('js/uppy.min.js'));
    } catch (rex_exception $e) {
        rex_logger::logException($e);
    }
}

// register yform template path
rex_yform::addTemplatePath($this->getPath('ytemplates'));

if (rex_request::get('uppy', 'boolean', false) == 1) {
    \Uppy\Handler\UppyUploadHandler::uploadFile();
}

