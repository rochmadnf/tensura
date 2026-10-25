<?php

namespace App\Services\LetterTemplate;

use PhpOffice\PhpWord\TemplateProcessor;

class LetterTemplateProcessor extends TemplateProcessor
{
    public function __destruct()
    {
        parent::__destruct();
        if (isset($this->tempDocumentFilename) && is_file($this->tempDocumentFilename)) {
            unlink($this->tempDocumentFilename);
        }
    }
}
