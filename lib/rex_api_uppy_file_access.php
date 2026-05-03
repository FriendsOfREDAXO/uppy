<?php

declare(strict_types=1);

class rex_api_uppy_file_access extends rex_api_function
{
    protected $published = false;

    public function execute(): never
    {
        rex_response::cleanOutputBuffers();

        if (!rex_backend_login::hasSession() || !rex::getUser()) {
            rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
            rex_response::sendJson(['error' => 'forbidden']);
            exit;
        }

        $uploadDir = rex_request('dir', 'string', '');
        $filename = rex_request('file', 'string', '');
        $mode = rex_request('mode', 'string', 'download');

        if ('' === $uploadDir || '' === $filename) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            rex_response::sendJson(['error' => 'missing_parameters']);
            exit;
        }

        $uploadDir = str_replace(['..', '\\'], '', $uploadDir);
        $uploadDir = trim($uploadDir, '/');
        $filename = basename($filename);

        if ('' === $uploadDir || '' === $filename) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            rex_response::sendJson(['error' => 'invalid_parameters']);
            exit;
        }

        $targetDir = rex_path::base($uploadDir);
        $realBase = realpath(rex_path::base());
        $realTargetDir = realpath($targetDir);

        if (false === $realBase || false === $realTargetDir) {
            rex_response::setStatus(rex_response::HTTP_NOT_FOUND);
            rex_response::sendJson(['error' => 'directory_not_found']);
            exit;
        }

        if (0 !== strpos($realTargetDir, $realBase . DIRECTORY_SEPARATOR) && $realTargetDir !== $realBase) {
            rex_response::setStatus(rex_response::HTTP_FORBIDDEN);
            rex_response::sendJson(['error' => 'path_not_allowed']);
            exit;
        }

        $filePath = $realTargetDir . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($filePath) || !is_readable($filePath)) {
            rex_response::setStatus(rex_response::HTTP_NOT_FOUND);
            rex_response::sendJson(['error' => 'file_not_found']);
            exit;
        }

        $mime = rex_file::mimeType($filePath);
        if (!is_string($mime) || '' === $mime) {
            $mime = 'application/octet-stream';
        }

        $disposition = 'download' === $mode ? 'attachment' : 'inline';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($filename) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');

        readfile($filePath);
        exit;
    }
}
