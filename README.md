<p align="center">
    <img src="https://3mad0o.github.io/fee-collection-documentation/logo.svg" alt="FeeCollection logo" width="96" height="96">
</p>

<h1 align="center">FeeCollection</h1>

<p align="center">
    Laravel fee workflow package for scheduled payments, invoices, receipts, credit notes, statement history, wallet balances, and optional PDF documents.
</p>

FeeCollection is a Laravel package for fee workflows:

- register upcoming payments
- create invoices and receipts
- create manual credit notes
- split payments
- detect overdue payments and generate due invoices
- keep account statements recalculated
- track one wallet balance row per payable model
- optionally generate/store invoice/receipt PDFs

See the documentation below, or browse the full documentation site:

https://3mad0o.github.io/fee-collection-documentation/

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Getting Started](#getting-started)
- [Configuration](#configuration)
- [Core Concepts](#core-concepts)
- [Usage Examples](#usage-examples)
- [PDF Documents](#pdf-documents)
- [Events](#events)
- [API Quick Reference](#api-quick-reference)
- [What Gets Stored](#what-gets-stored)
- [License](#license)

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

## Getting Started

### Setup Model

Add `UseFeeable` trait to any Eloquent model (for example `User`):

```php
use Emad\FeeCollection\Traits\UseFeeable;

class User extends Model
{
    use UseFeeable;
}
```

After this, the model can register payments, create receipts, generate due invoices, query statements, and read its wallet balance.

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

## Core Concepts

FeeCollection is built around payable models, upcoming payments, account statements, and wallet balances.

### Payable Models

Any Eloquent model using `UseFeeable` can own fee workflows. Typical examples include `User`, `Student`, `Customer`, and `Tenant`.

The trait adds methods for payment registration, statement access, wallet balance checks, overdue detection, and due invoice generation.

### Upcoming Payments

An upcoming payment represents a scheduled amount due on a future date.

Upcoming payments can be:

- registered from a payable model
- invoiced manually
- receipted manually
- split into child payments
- detected as overdue
- invoiced automatically when due

### Account Statements

Account statements represent financial documents and history entries such as invoices, receipts, and credit notes.

Statements include a `status` field for reporting and filtering.

### Wallet Balance

The package tracks one wallet balance row per payable model in `wallet_transactions`.

```php
$balance = $user->balance();
```

### Credit Notes

Credit notes are created from invoices. A credit note:

- references the original invoice through `reference_id`
- uses a negative `amount`
- changes the original invoice status to `credited`

Credit notes are not created automatically during payment splitting.

### Voided Invoices

A voided invoice is excluded from balance recalculation.

Use voiding only for invoices that should be killed internally before customer settlement.

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

### 8) Statement status

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

### 9) List statements and check wallet balance

```php
$statements = $user->accountStatements()->orderByDesc('date')->get();
$balance = $user->balance();
```

## Events

The package dispatches these events after successful changes:

- `Emad\FeeCollection\Events\InvoiceCreated`
- `Emad\FeeCollection\Events\ReceiptCreated`
- `Emad\FeeCollection\Events\CreditNoteCreated`
- `Emad\FeeCollection\Events\PaymentOverdue`
- `Emad\FeeCollection\Events\PaymentSplit`
- `Emad\FeeCollection\Events\InvoiceVoided`

Common uses include customer notifications, internal audit logs, reporting updates, accounting exports, and webhook dispatching.

## PDF Documents

FeeCollection can generate and store PDF documents for invoices, receipts, and credit notes.

Install DomPDF support:

```bash
composer require barryvdh/laravel-dompdf
```

PDF generation is controlled by `config/fee_collection.php`:

```php
'pdf' => [
    'enabled' => env('FEE_COLLECTION_PDF_ENABLED', true),
    'paper' => 'a4',
    'orientation' => 'portrait',
    'disk' => env('FEE_COLLECTION_PDF_DISK', 'public'),
    'path' => env('FEE_COLLECTION_PDF_PATH', 'fee-collection/documents'),
],
```

Useful environment variables:

```text
FEE_COLLECTION_PDF_ENABLED=true
FEE_COLLECTION_PDF_DISK=public
FEE_COLLECTION_PDF_PATH=fee-collection/documents
```

Configure the Blade views used for generated documents:

```php
'invoice_view' => 'fee-collection::pdf.invoice',
'receipt_view' => 'fee-collection::pdf.receipt',
'credit_note_view' => 'fee-collection::pdf.invoice',
```

### Generate PDF manually

```php
$statement = $user->accountStatements()->latest('id')->first();
$pdf = $statement->toPdf();

return $pdf->download($statement->formatted_number . '.pdf');
```

When PDF generation is enabled, the generated PDF path is saved in `account_statements.document`.

## API Quick Reference

### Payable model methods

```php
$user->createReceipt(1000, 'Initial credit', now());
$user->createReceipt(1000, 'Initial credit', now(), autoInvoice: false);

$payment = $user->registerPayment(100, now()->addDays(10));

$overduePayments = $user->overduePayments();
$invoices = $user->generateDueInvoices();
$statements = $user->accountStatements()->orderByDesc('date')->get();
$balance = $user->balance();
```

### Upcoming payment methods

```php
$invoice = $payment->createInvoice('Original invoice', now());
$payment->createReceipt('Test Receipt', now());

$children = $payment->split([
    ['amount' => 500, 'due_date' => now()->addMonth()],
    ['amount' => 500, 'due_date' => now()->addMonths(2)],
]);

if ($payment->isOverdue()) {
    // notify the customer
}
```

### Statement methods

```php
$creditNote = $invoice->createCreditNote('Invoice cancelled', now());
$invoice->void('Created by mistake');
$pdf = $statement->toPdf();
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
