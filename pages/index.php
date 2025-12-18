<?php

$package = rex_addon::get('uppy');

echo rex_view::title($package->i18n('uppy_title'));

// Subpages einbinden
rex_be_controller::includeCurrentPageSubPath();
