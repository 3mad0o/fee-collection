# Changelog

All notable changes to `3mad/fee-collection` are documented in this file.

## [1.1.0] - 2026-05-09

### Added

- Manual credit notes for issued invoices.
- Credit note statement type with `reference_id` back to the original invoice.
- Invoice voiding with `voided_at`, `void_reason`, and `voided` status.
- Statement `status` field for reporting: `issued`, `paid`, `overdue`, `credited`, and `voided`.
- Overdue payment detection through `isOverdue()` and `overduePayments()`.
- Due invoice generation through `generateDueInvoices()`.
- Lifecycle events for invoice creation, receipt creation, credit note creation, overdue detection, payment splitting, and invoice voiding.
- Configurable credit note number prefix/suffix and view.
- `auto_invoice_on_receipt` config with per-call override.
- Feature tests for the v1.1 accounting flows.

### Changed

- Splitting a payment never creates a credit note automatically. Developers must explicitly call `createCreditNote()`.
- Voided invoices are excluded from balance recalculation.
- Receipt amounts and credit note amounts are stored as negative `amount` values while still using the existing `credit` column for balance calculation.

## [1.0.0] - 2026-05-06

### Added

- Upcoming payment registration, split support, and settlement flow.
- Invoice and receipt creation through account statements.
- Wallet balance table (single row per walletable).
- Statement balance recalculation service.
- Optional PDF generation for invoice/receipt statements.
- Configurable invoice/receipt blade templates.
- Config publishing and view publishing support.
- Interfaces for core services and service-container bindings.
- Package README documentation.

### Changed

- Statement `number` is stored as numeric value only.
- Prefix/suffix are now display-time formatting (`formatted_number`).
- Improved receipt/invoice relation checks using `exists()` queries.
