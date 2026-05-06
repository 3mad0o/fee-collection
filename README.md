# FeeCollection

FeeCollection is a Laravel package for fee workflows:

- register upcoming payments
- create invoices and receipts
- split payments
- keep account statements recalculated
- track one wallet balance row per payable model
- optionally generate/store invoice/receipt PDFs

## Requirements

- PHP 8.3+
- Laravel 13+

## Installation

Install package in your Laravel app:

```bash
composer require 3mad/fee-collection
```

If your app does not auto-discover providers, register:

```php
Emad\FeeCollection\Providers\FeeCollectionServiceProvider::class
```

For PDF generation support (optional):

```bash
composer require barryvdh/laravel-dompdf
```

## Publish Package Files

Publish config:

```bash
php artisan vendor:publish --provider="Emad\FeeCollection\Providers\FeeCollectionServiceProvider" --tag=config
```

Publish default PDF blade views:

```bash
php artisan vendor:publish --provider="Emad\FeeCollection\Providers\FeeCollectionServiceProvider" --tag=views
```

Published views path:

`resources/views/vendor/fee-collection/pdf`

## Migrations

Run migrations:

```bash
php artisan migrate
```

This creates:

- `upcoming_payments`
- `account_statements`
- `account_statement_upcoming_payments`
- `wallet_transactions` (single row per walletable with current balance)

## Setup Model

Add `UseFeeable` trait to any Eloquent model (for example `User`):

```php
use Emad\FeeCollection\Traits\UseFeeable;

class User extends Model
{
    use UseFeeable;
}
```

## Configuration

File: `config/fee_collection.php`

```php
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
```

### Notes

- `account_statements.number` stores numeric value only.
- Prefix/suffix are added on retrieval via `formatted_number`.
- If `pdf.enabled = true`, generated PDF path is saved in `account_statements.document`.

## Usage Examples

### 1) Create receipt, then register payments (wallet consumption flow)

```php
$user = User::factory()->create();

$user->createReceipt(1000, 'Initial credit', now());

$user->registerPayment(100, now()->addDays(1));
$user->registerPayment(100, now()->addDays(2));
$user->registerPayment(100, now()->addDays(3));
```

If wallet balance is enough, registered payments can be invoiced automatically.

### 2) Manual invoice and receipt on one upcoming payment

```php
$payment = $user->registerPayment(100, now()->addDays(10));
$payment->createInvoice('Test Invoice', now());
$payment->createReceipt('Test Receipt', now());
```

### 3) Split an upcoming payment

```php
$payment = $user->registerPayment(1000, now()->addDays(1));

$children = $payment->split([
    ['amount' => 100, 'due_date' => now()->addDays(2)],
    ['amount' => 100, 'due_date' => now()->addDays(3)],
    ['amount' => 800, 'due_date' => now()->addDays(4)],
]);
```

### 4) List statements and check wallet balance

```php
$statements = $user->accountStatements()->orderByDesc('date')->get();
$balance = $user->balance();
```

### 5) Generate PDF manually

```php
$statement = $user->accountStatements()->latest('id')->first();
$pdf = $statement->toPdf();

return $pdf->download($statement->formatted_number . '.pdf');
```

## What Gets Stored

- `account_statements.number`: numeric sequence (no prefix/suffix)
- `account_statements.document`: stored PDF relative path (when enabled)
- `wallet_transactions.balance`: current wallet balance for the payable

## License

MIT
