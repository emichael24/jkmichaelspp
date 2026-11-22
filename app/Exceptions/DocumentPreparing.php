<?php

namespace App\Exceptions;

use RuntimeException;

/** The queue worker is still converting a document; the reader should retry shortly. */
class DocumentPreparing extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Preparing your document. This can take a minute for large presentations.');
    }
}
