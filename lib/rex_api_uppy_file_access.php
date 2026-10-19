<?php

declare(strict_types=1);

use FriendsOfRedaxo\Uppy\Security;

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

        $filename = basename(str_replace(["\0", '\\'], ['', '/'], $filename));

        if ('' === $filename || str_starts_with($filename, '.')) {
            rex_response::setStatus(rex_response::HTTP_BAD_REQUEST);
            rex_response::sendJson(['error' => 'invalid_parameters']);
            exit;
        }

        // Ordner mit denselben Regeln wie beim Upload auflösen (nur freigegebene Basisordner,
        // keine REDAXO-Systemordner, kein Verlassen per ..)
        try {
            $realTargetDir = Security::resolveCustomDirectory($uploadDir, false);
        } catch (rex_api_exception) {
            rex_response::setStatus(rex_response::HTTP_NOT_FOUND);
            rex_response::sendJson(['error' => 'directory_not_found']);
            exit;
        }

        $filePath = $realTargetDir . DIRECTORY_SEPARATOR . $filename;
        $realFile = realpath($filePath);
        if (false === $realFile || !Security::isInside($realFile, $realTargetDir) || !is_file($realFile) || !is_readable($realFile)) {
            rex_response::setStatus(rex_response::HTTP_NOT_FOUND);
            rex_response::sendJson(['error' => 'file_not_found']);
            exit;
        }

        $mime = rex_file::mimeType($filePath);
        if (!is_string($mime) || '' === $mime) {
            $mime = 'application/octet-stream';
        }

        // Inline nur für Bilder, Videos, Audio und PDF – alles andere immer als Download
        $inlineAllowed = 1 === preg_match('#^(image/(jpeg|png|gif|webp|avif)|video/|audio/|application/pdf$)#', $mime);
        $disposition = ('download' === $mode || !$inlineAllowed) ? 'attachment' : 'inline';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($filePath));
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace(['"', "\r", "\n"], '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, no-cache, must-revalidate');

        readfile($filePath);
        exit;
    }
}
