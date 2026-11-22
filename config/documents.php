<?php

return [
    // auto: render watermarked pages on the server when Poppler/LibreOffice are installed,
    //       otherwise render in the learner's browser.
    // server: server rendering only (fails closed if converters are missing).
    // browser: always render in the browser (no converters required).
    'renderer' => env('DOCUMENT_RENDERER', 'auto'),
    'pdfinfo' => env('DOCUMENT_PDFINFO', 'pdfinfo'),
    'pdftoppm' => env('DOCUMENT_PDFTOPPM', 'pdftoppm'),
    'libreoffice' => env('DOCUMENT_LIBREOFFICE', 'soffice'),
    'max_pages' => 200,
    'page_pixels' => 1800,
    // Conversions run in the queue worker, whose jobs must finish within 120 seconds.
    'conversion' => env('DOCUMENT_CONVERSION', 'queue'),
    'convert_timeout' => (int) env('DOCUMENT_CONVERT_TIMEOUT', 100),
    'page_timeout' => 25,
];
