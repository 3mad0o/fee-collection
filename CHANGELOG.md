# Changelog

All notable changes to `3mad/fee-collection` are documented in this file.

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
