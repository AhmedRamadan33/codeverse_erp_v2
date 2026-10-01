# CodeVerse ERP v2

The user prefers to discuss in Egyptian Arabic. Code, identifiers and comments are in English.

## Goal
A general-purpose, modular, multi-industry ERP (Odoo-like in breadth, not in hosting model) aimed first at the Egyptian/Arab market. Modules are installed per business type (retail, restaurant, manufacturing, services, …) on top of a shared core.

There are **no production customers**. Design things properly; no backward compatibility or data migration from v1 is needed.

## Stack
- Laravel 13, PHP 8.3
- Blade + Livewire 4 for the dashboard
- Sanctum token API under `/api/v1` for the mobile app (API is built alongside the web UI from day one)
- `nwidart/laravel-modules`: every feature area lives in `Modules/<Name>` (`php artisan module:make <Name>`). `Core` is the first module.
- DB: SQLite for now; MySQL is the target.

## Architecture principles (agreed)
1. **Modules are independent.** Each module owns its models, migrations, routes, views, permissions and tests. A module must not reach into another module's tables.
2. **Modules communicate through events**, not direct calls (e.g. Sales fires `InvoicePosted`; Accounting and Inventory listen). Disabling a module must not break the others.
3. **Double-entry accounting is the core.** Every financial document posts a journal entry. Balances are derived from journal lines, never updated by hand.
4. **Money is `decimal`, never `float`.** Use `decimal(18,4)` for amounts and quantities.
5. **One table per document type** (sales invoices, purchase invoices, stock moves, journal entries, …). No single catch-all `transactions` table.
6. **Inventory uses stock moves** with proper costing (weighted average first, FIFO later), plus batch/expiry and serial numbers.
7. **Configurable without code:** custom fields, document numbering sequences, approval workflows.
8. **Single-tenant: one installation per customer.** Not multi-tenant/SaaS; do not add `tenant_id` or tenancy packages. The same codebase is sold to every customer, and each installation enables only the modules that customer's business needs. This requires a clean installer/setup wizard, per-installation module enabling, and safe upgrades (versioned migrations) across many separate installations.
9. **i18n everywhere.** No hardcoded Arabic or English strings in PHP; use translation files. Arabic + English, RTL support.
10. **Business logic lives in services/actions**, not controllers or Livewire components. Controllers stay thin. The web UI and the API share the same actions.
11. **Authorization is enforced server-side** on every route and action (policies/permissions), not only by hiding buttons. No state-changing GET routes.
12. **Tests are required** for business flows (posting, stock, costing, accounting).

## Legacy project (v1): read-only reference
Path: `f:/Projects/BackEnd/laravel/codeverse/erp/codeverse_erp` (Laravel 10). Do not modify it. Use it to understand business rules, not as code to copy.

Worth porting (adapted to the new structure):
- Unit-of-measure conversion: `app/Traits/Stock.php`
- Egyptian e-invoice (ETA) integration: `app/Utils/ElectronicInvoice.php`
- Translations: `resources/lang/ar`, `resources/lang/en`
- AdminLTE dashboard theme: `public/theme/dashboard`
- Business behavior reference: `app/Services/*` (Sell, Purchase, Stock, CashRegister, Manufacturing), POS, restaurant tables/kitchen, cashier shifts, sales segments (price lists), reports in `app/Http/Controllers/Dashboard/ReportController.php`

Not carried over: Cartona integration, client-specific Firebase files, v1 migrations.

Known v1 weaknesses to avoid: single `transactions` god-table, `enable_*` columns in `settings` instead of real modules, float money, no accounting ledger, cost overwritten with last purchase price, missing permission checks, GET delete routes, unauthenticated API endpoints, no row locking on stock updates, 2000+ line controllers, no tests.

## Current status / next step
- Project skeleton is ready (Laravel + Livewire + Sanctum + modules + empty `Core` module).
- **Next:** write the core design document: module boundaries, accounting engine (chart of accounts, journal entries, how each document posts), base schema for documents and inventory, installation/module-enabling and upgrade approach. Then implement `Core` and `Accounting`.

## Commands
- `php artisan test`: runs app tests and `Modules/*/tests`
- `php artisan module:list`, `php artisan module:make <Name>`
- `composer run dev`: local dev server
