<?php
return [
    'invoice_prefix' => 'I-',
    'invoice_suffix' => '',
    'receipt_prefix' => 'R-',
    'receipt_suffix' => '',
    'credit_note_prefix' => 'CN-',
    'credit_note_suffix' => '',
    'auto_invoice_on_receipt' => true,
    'invoice_view' => 'fee-collection::pdf.invoice',
    'receipt_view' => 'fee-collection::pdf.receipt',
    'credit_note_view' => 'fee-collection::pdf.invoice',
    'pdf' => [
        'enabled' => env('FEE_COLLECTION_PDF_ENABLED', true),
        'paper' => 'a4',
        'orientation' => 'portrait',
        'disk' => env('FEE_COLLECTION_PDF_DISK', 'public'),
        'path' => env('FEE_COLLECTION_PDF_PATH', 'fee-collection/documents'),
    ],
];
