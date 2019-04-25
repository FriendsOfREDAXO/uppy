<?php
/**
 * User: joachimdoerr
 * Date: 2019-04-04
 * Time: 18:53
 */

namespace Uppy\Handler;

use rex;
use rex_addon;
use rex_extension;
use rex_extension_point;
use rex_path;
use rex_request;
use rex_response;

class UppyUploadHandler
{
    const MEDIA_PATH = 'media/';
    const MEDIA_TYPE_PATH = '';

    /**
     * @author Joachim Doerr
     */
    public static function uploadFile()
    {
        if (!function_exists('rex_mediapool_saveMedia')) {
            if (rex_addon::exists('mediapool') && rex_addon::get('mediapool')->isAvailable()) {
                require_once rex_addon::get('cke5')->getPath('../mediapool/functions/function_rex_mediapool.php');
            }
        }



//        $basePath = "uppy";
//        $_file = $_FILES['rex_uppy_file'];
//        $name = $_file['name'];
//        $file = rex_path::frontend("$basePath/$name");
//        move_uploaded_file($_file['tmp_name'], $file);
//
//        $result = json_encode(array(
//            'dirname' => pathinfo($file, PATHINFO_DIRNAME),
//            'filename' => basename($name),
//            'pathinfo' => pathinfo($file)
//        ));
//
//        rex_response::sendContent($result, 'application/json');
//        die;


        $_file = $_FILES['rex_uppy_file'];

        if ($_file['name'] != '' && rex_mediapool_isAllowedMediaType($_file['name'])) {

            $userLogin = (is_object(rex::getUser())) ? rex::getUser()->getValue('login') : null;

            $mediaCategory = rex_request::get('media_category', 'int', 0);
            $return = rex_mediapool_saveMedia($_file, $mediaCategory, ['title'=>''], $userLogin);

            if ($return['ok'] == 1) {
                rex_extension::registerPoint(new rex_extension_point('MEDIA_ADDED', '', $return));
            }
            $file = rex_path::frontend(self::MEDIA_PATH . $return['filename']);

            $statusCode = 201;
            $response = [
                'dirname' => pathinfo($file, PATHINFO_DIRNAME),
                'filename' => basename($file),
                'pathinfo' => pathinfo($file),
                'uploaded' => 1,
                'error' => null,
                'url' => $file
            ];

        } else {

            $statusCode = 500;
            $response = [
                'dirname' => null,
                'filename' => null,
                'pathinfo' => null,
                'uploaded' => [
                    'number'    => 500,
                    'message'   => 'Internal server error. The uploaded file was failed',
                ],
                'error' => 'internal_server_error',
                'url' => null,
            ];
        }

        rex_response::cleanOutputBuffers();
        rex_response::setHeader('status', $statusCode);
        rex_response::sendContent(json_encode($response), 'application/json');
        exit;

    }

}