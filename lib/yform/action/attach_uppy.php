<?php
class rex_yform_action_attach_uppy extends rex_yform_action_abstract
{
    public function executeAction() :void
    {
        $extension = $this->getElement(3);
        $uploaded_files = explode(",",$this->params['value_pool']['sql'][$this->getElement(2)]);
        $email_attachments = &$this->params['value_pool']['email_attachments'];

        foreach ($uploaded_files as $file) {
            if ($extension && pathinfo($file, PATHINFO_EXTENSION) !== $extension) {
                continue;
            }
            $email_attachments[] = [$file, rex_path::media($file), rex_path::media($file)];
        }
        

    }
    public function getDescription() :string
    {
        return 'action|attach_uppy|uppyfieldname|[pdf]';
    }
}
