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
use Uppy\Provider\UppyTokenProvider;
use Whoops\Exception\ErrorException;

class UppyUploadHandler
{
    const MEDIA_PATH = 'media/';
    const MEDIA_TYPE_PATH = '';

    /**
     * @param $filename
     * @return mixed|string
     * @author Joachim Doerr
     */
    private static function getFilename($filename)
    {
        $pathInfo = pathinfo($filename);
        if (file_exists($filename)) {
            $cnt = 1;
            while (file_exists($pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_' . $cnt . '.' . $pathInfo['extension'])) {
                ++$cnt;
            }
            return $pathInfo['filename'] . '_' . $cnt . '.' . $pathInfo['extension'];
        }
        return $pathInfo['basename'];
    }

    /**
     * @author Joachim Doerr
     */
    public static function uploadFile()
    {
        $file = '';

        if (!function_exists('rex_mediapool_saveMedia')) {
            if (rex_addon::exists('mediapool') && rex_addon::get('mediapool')->isAvailable()) {
                require_once rex_addon::get('cke5')->getPath('../mediapool/functions/function_rex_mediapool.php');
            }
        }

        // TODO load uppy profile
        // TODO use profile path
        // TODO use given path


        try {
            $_file = $_FILES['rex_uppy_file'];

            if (!UppyTokenProvider::isTokenValid()) {
                throw new \ErrorException('csrf_token_invalid', 401);
            }
            if (!isset($_file['name']) || is_null($_file)) {
                throw new \ErrorException('internal_server_error', 500);
            }

            if (!is_null($path = rex_request::get('data_path', 'string', null))) {

                // TODO replace with uppy profile it case of profile is available
                $overwrite = rex_request::get('overwrite', 'bool', false);
                $rename = rex_request::get('rename', 'bool', true);

                $dstFile = rex_path::data($path . $_file['name']);
                $dstPathInfo = pathinfo($dstFile);

                if (!$overwrite && file_exists($dstFile) && !$rename) {
                    throw new ErrorException('file_already_exist', 406);
                }
                if (!$overwrite && $rename) {
                    $dstFile = $dstPathInfo['dirname'] . '/' . self::getFilename($dstFile);
                }
                if (!file_exists($dstPathInfo['dirname'])) {
                    mkdir($dstPathInfo['dirname'], 0755, true);
                }
                if (!move_uploaded_file($_file['tmp_name'], $dstFile)) {
                    throw new ErrorException('file_save_failed', 500);
                }
                $file = $dstFile;

            } else {
                if (rex_mediapool_isAllowedMediaType($_file['name'])) {

                    $userLogin = (is_object(rex::getUser())) ? rex::getUser()->getValue('login') : null;
                    $mediaCategory = rex_request::get('media_category', 'int', 0);
                    $return = rex_mediapool_saveMedia($_file, $mediaCategory, ['title' => ''], $userLogin);

                    if ($return['ok'] == 1) {
                        rex_extension::registerPoint(new rex_extension_point('MEDIA_ADDED', '', $return));
                    }
                    $file = rex_path::frontend(self::MEDIA_PATH . $return['filename']);

                } else {
                    throw new \ErrorException('internal_server_error', 500);
                }
            }

            $statusCode = 201;
            $response = [
                'dirname' => pathinfo($file, PATHINFO_DIRNAME),
                'filename' => basename($file),
                'pathinfo' => pathinfo($file),
                'uploaded' => true,
                'error' => null,
                'url' => $file,
            ];

        } catch (\ErrorException $e) {
            \rex_logger::logException($e);

            switch ($e->getCode()) {
                default:
                case 500:
                    $statusCode = 500;
                    $errorMessage = 'Internal server error. The uploaded file was failed';
                    break;
                case 401:
                    $statusCode = 401;
                    $errorMessage = 'Csrf token is invalid. The upload was failed';
                    break;
                case 406:
                    $statusCode = 406;
                    $errorMessage = 'File already exist. The upload was failed';
                    break;
            }

            $response = [
                'dirname' => null,
                'filename' => null,
                'pathinfo' => null,
                'uploaded' => false,
                'error' => [
                    'number' => $statusCode,
                    'message' => $errorMessage,
                    'exception_message' => $e->getMessage()
                ],
                'url' => null,
            ];
        }

        // execute callback
        if (!is_null($callback = rex_request::get('callback', 'string', null))) {
            if (is_callable($callback, true)) {
                $response['callback_result'] = $callback(array('file' => $file, 'status' => $statusCode, 'response' => $response, 'token' => rex_request::get(UppyTokenProvider::getTokenParameterKey(), 'string', null), 'callback_param' => rex_request::get('callback_param', 'array', null)));
            }
        }

        rex_response::cleanOutputBuffers();
        rex_response::setHeader('status', $statusCode);
        rex_response::sendContent(json_encode($response), 'application/json');
        exit;
    }
}