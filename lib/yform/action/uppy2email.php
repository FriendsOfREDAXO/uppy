<?php

class rex_yform_action_uppy2email extends rex_yform_action_abstract
{
    public function executeAction(): void
    {
        $label_from = $this->getElement(2);

        foreach ($this->params['value_pool']['email'] as $key => $value) {
            if ($label_from === $key) {
                // Uppy speichert Dateinamen kommagetrennt
                $files = array_filter(explode(',', $value));
                
                foreach ($files as $filename) {
                    $filename = trim($filename);
                    $filepath = rex_path::media($filename);
                    
                    if (file_exists($filepath)) {
                        $this->params['value_pool']['email_attachments'][] = [$filename, $filepath];
                    }
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
