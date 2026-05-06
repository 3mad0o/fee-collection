<?php
return [
    'invoice_prefix' => 'I-',
    'invoice_suffix' => '',
    'receipt_prefix' => 'R-',
    'receipt_suffix' => '',
    'invoice_view' => 'fee-collection::pdf.invoice',
    'receipt_view' => 'fee-collection::pdf.receipt',
    'pdf' => [
        'enabled' => env('FEE_COLLECTION_PDF_ENABLED', true),
        'paper' => 'a4',
        'orientation' => 'portrait',
        'disk' => env('FEE_COLLECTION_PDF_DISK', 'public'),
        'path' => env('FEE_COLLECTION_PDF_PATH', 'fee-collection/documents'),
    ],
];
