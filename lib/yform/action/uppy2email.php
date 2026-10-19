<?php

class rex_yform_action_uppy2email extends rex_yform_action_abstract
{
    public function executeAction(): void
    {
        $label_from = $this->getElement(2);

        foreach ($this->params['value_pool']['email'] as $key => $value) {
            if ($label_from === $key) {
                // Konfiguration des Feldes ermitteln (für Upload-Ordner)
                $uploadFolder = '';
                if (isset($this->params['values'])) {
                    foreach ($this->params['values'] as $val) {
                        if ($val->getName() === $key) {
                            $uploadFolder = $val->getElement('upload_folder');
                            break;
                        }
                    }
                }

                // Uppy speichert Dateinamen kommagetrennt
                $files = array_filter(explode(',', $value));
                
                // Custom-Ordner mit denselben Regeln wie beim Upload auflösen
                $targetDir = null;
                if (!empty($uploadFolder)) {
                    try {
                        $targetDir = \FriendsOfRedaxo\Uppy\Security::resolveCustomDirectory((string) $uploadFolder, false);
                    } catch (rex_api_exception) {
                        $files = [];
                    }
                }

                // Im Frontend nur Dateien anhängen, die in dieser Session hochgeladen wurden
                // (der Feldwert kommt vom Browser und ist nicht vertrauenswürdig).
                $checkSession = !rex::isBackend();

                foreach ($files as $filename) {
                    $filename = basename(str_replace(["\0", '\\'], ['', '/'], trim($filename)));
                    if ('' === $filename || str_starts_with($filename, '.')) {
                        continue;
                    }

                    // Pfad ermitteln: Entweder Custom Folder oder Standard Media-Pool
                    if (null !== $targetDir) {
                        $filepath = $targetDir . '/' . $filename;
                    } else {
                        $filepath = rex_path::media($filename);
                    }

                    $realPath = realpath($filepath);
                    if (false === $realPath || !is_file($realPath)) {
                        continue;
                    }
                    if ($checkSession && !\FriendsOfRedaxo\Uppy\Security::wasUploadedInSession(null !== $targetDir ? $realPath : $filename)) {
                        continue;
                    }

                    $this->params['value_pool']['email_attachments'][] = [$filename, $realPath];
                }
                break;
            }
        }
    }

    public function getDescription(): string
    {
        return 'action|uppy2email|fieldname';
    }
}
