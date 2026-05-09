# FeeCollection

FeeCollection is a Laravel package for fee workflows:

- register upcoming payments
- create invoices and receipts
- create manual credit notes
- split payments
- detect overdue payments and generate due invoices
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
```

### Notes

- `account_statements.number` stores numeric value only.
- Prefix/suffix are added on retrieval via `formatted_number`.
- If `pdf.enabled = true`, generated PDF path is saved in `account_statements.document`.
- `auto_invoice_on_receipt` may be overridden per receipt call.
- Credit notes are always manual. Splitting a payment never creates a credit note automatically.

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

Disable receipt-driven invoice generation per call:

```php
$user->createReceipt(1000, 'Initial credit', now(), autoInvoice: false);
```

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

If the original payment already had an invoice, create the credit note manually:

```php
$invoice = $payment->invoice;

$children = $payment->split([
    ['amount' => 500, 'due_date' => now()->addMonth()],
    ['amount' => 500, 'due_date' => now()->addMonths(2)],
]);

$invoice->createCreditNote('Customer requested installment split', now());
```

### 4) Create a credit note

```php
$payment = $user->registerPayment(1000, now()->addDay());
$invoice = $payment->createInvoice('Original invoice', now());

$creditNote = $invoice->createCreditNote('Invoice cancelled', now());
```

Credit notes:

- can only be created from invoices
- reference the original invoice through `reference_id`
- use a negative `amount`
- move the original invoice status to `credited`

### 5) Void an invoice

```php
$payment = $user->registerPayment(1000, now()->addDay());
$invoice = $payment->createInvoice('Draft invoice', now());

$invoice->void('Created by mistake');
```

Voided invoices are excluded from balance recalculation. Use this only for invoices that should be killed internally before customer settlement.

### 6) Detect overdue payments

```php
if ($payment->isOverdue()) {
    // notify the customer
}

$overduePayments = $user->overduePayments();
```

An overdue payment has a due date before today, still has remaining amount, and has no linked receipt.

### 7) Generate invoices due today

```php
$invoices = $user->generateDueInvoices();
```

Scheduler example:

```php
use App\Models\User;

$schedule->call(function () {
    User::each(fn (User $user) => $user->generateDueInvoices());
})->daily();
```

### 8) Events

The package dispatches these events after successful changes:

- `Emad\FeeCollection\Events\InvoiceCreated`
- `Emad\FeeCollection\Events\ReceiptCreated`
- `Emad\FeeCollection\Events\CreditNoteCreated`
- `Emad\FeeCollection\Events\PaymentOverdue`
- `Emad\FeeCollection\Events\PaymentSplit`
- `Emad\FeeCollection\Events\InvoiceVoided`

### 9) Statement status

Statements include a `status` field for filtering/reporting:

- `issued`
- `paid`
- `overdue`
- `credited`
- `voided`

```php
$paidStatements = $user->accountStatements()->where('status', 'paid')->get();
$creditedStatements = $user->accountStatements()->where('status', 'credited')->get();
```

Status is a reporting helper. Balances are still calculated from statement debit/credit values, excluding voided invoices.

### 10) List statements and check wallet balance

```php
$statements = $user->accountStatements()->orderByDesc('date')->get();
$balance = $user->balance();
```

### 11) Generate PDF manually

```php
$statement = $user->accountStatements()->latest('id')->first();
$pdf = $statement->toPdf();

return $pdf->download($statement->formatted_number . '.pdf');
```

## What Gets Stored

- `account_statements.number`: numeric sequence (no prefix/suffix)
- `account_statements.document`: stored PDF relative path (when enabled)
- `account_statements.reference_id`: original invoice for credit notes
- `account_statements.status`: reporting status
- `account_statements.voided_at`: timestamp for voided invoices
- `account_statements.void_reason`: reason for voiding
- `wallet_transactions.balance`: current wallet balance for the payable

## License

MIT
