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
    'convert_timeout' => (int) env('DOCUMENT_CONVERT_TIMEOUT', 180),
    'page_timeout' => 25,
];
