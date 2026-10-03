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
- DB: MySQL 8 (dev, tests and CI). SQLite is not supported: no real row locks and inexact decimals.

## Architecture principles (agreed)
1. **Each module owns its data.** Each module owns its models, migrations, routes, views, permissions and tests. Modules may depend on each other: a module may **read** another module's tables/models and add **foreign keys** to them (declare the dependency in `module.json` `requires`). But only the owning module **writes** to its tables: other modules change its data by calling its actions or firing events it listens to (e.g. Sales calls Inventory's `issueStock`, never updates stock tables directly). This keeps each module's business rules (costing, batches, locking, accounting entries) in one place.
2. **Calls vs events.** A module may call actions of modules it `requires` (hard dependency, e.g. Sales → Inventory). `Core` and `Accounting` are always installed, so posting a document calls Accounting's posting action directly inside the same DB transaction (never via a listener). For optional reactions, it fires events (dispatched after commit) and lets other modules listen (e.g. Sales fires `SalesInvoicePosted`; EgyptTax and Notifications listen). Disabling a module must not break modules that don't require it.
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
13. **Country tax compliance is an optional module.** Planned `EgyptTax` module: E-Receipt (port from v1), E-Invoice with digital signing (new), and item codes (GS1/EGS) shared by both. Enabled only for ETA-registered Egyptian customers. Other countries get their own module the same way (e.g. Saudi ZATCA).

## Legacy project (v1): read-only reference
Path: `f:/Projects/BackEnd/laravel/codeverse/erp/codeverse_erp` (Laravel 10). Do not modify it. Use it to understand business rules, not as code to copy.

Worth porting (adapted to the new structure):
- Unit-of-measure conversion: `app/Traits/Stock.php`
- Egyptian tax authority (ETA) integration: `app/Utils/ElectronicInvoice.php`. Despite the name, it implements only the **E-Receipt** (B2C POS receipts: `receiptType "S"`, `/api/v1/receiptsubmissions`, auth via client_id/secret + POS serial, no digital signature). It does **not** implement the B2B **E-Invoice**, which needs document signing (USB token/HSM).
- Translations: `resources/lang/ar`, `resources/lang/en`
- AdminLTE dashboard theme: `public/theme/dashboard` (look and feel only; v2 uses AdminLTE 4 from npm)
- Business behavior reference: `app/Services/*` (Sell, Purchase, Stock, CashRegister, Manufacturing), POS, restaurant tables/kitchen, cashier shifts, sales segments (price lists), reports in `app/Http/Controllers/Dashboard/ReportController.php`

Not carried over: Cartona integration, client-specific Firebase files, v1 migrations.

Known v1 weaknesses to avoid: single `transactions` god-table, `enable_*` columns in `settings` instead of real modules, float money, no accounting ledger, cost overwritten with last purchase price, missing permission checks, GET delete routes, unauthenticated API endpoints, no row locking on stock updates, 2000+ line controllers, no tests.

## Current status / next step
- Project skeleton is ready (Laravel + Livewire + Sanctum + modules + empty `Core` module).
- Core design document (approved): [docs/architecture/core-design.md](docs/architecture/core-design.md). Decisions are in §1, phases in §15.
- Milestone **v1.0** (first sellable release) = Core + Accounting + Products + Inventory + Purchases + Sales + POS. Custom fields/attachments, advanced accounting (full reconciliation, cost centers, exchange differences), EgyptTax, Restaurant and Manufacturing come after it.
- Phase 1 (`Core`) is done on branch `phase-1-core`: module registry and `erp:install`/`erp:upgrade`, settings, branches, currencies, sequences, permissions, partners, audit log, AdminLTE 4 layout with all Core admin screens, Sanctum API.
- Phase 2 (`Accounting`) is done on branch `phase-2-accounting` (based on `phase-1-core`): chart of accounts with Egyptian template, `PostJournalEntry`/`ReverseJournalEntry`, `AccountResolver` mappings, fiscal years, lock date, year-end closing, taxes, payment methods, manual entries, receipt/payment/expense vouchers with allocation (`Reconciler`), trial balance, general ledger, partner statement.
- Phase 3 (`Products` + `Inventory`) is done on branch `phase-3-inventory`: products with per-product units and barcodes (`UnitConverter`, `ProductLookup`, `ProductUsage`), warehouses, `StockEngine` with weighted-average costing, `ReceiveStock`/`IssueStock`/`TransferStock`/`ReverseStock`/`PostDeferredValuation`, batches (FEFO) and serials, stock adjustments (incl. opening stock), transfers, stock on hand, item ledger, `inventory:check`.
- Phase 4 (`Purchases`) is done on branch `phase-4-purchases`: purchase invoices (line + document discounts via `DocumentTotals`, GRNI / purchases expense, input tax, supplier with due date, stock received at net cost) and returns against invoices (average cost out, price difference, auto-matched with the invoice), `products::picker` component.
- **Next:** Phase 5 (`Sales`).
- Patterns to follow in new modules: use-case actions authorize the actor themselves (`Gate::forUser($actor)`) and are shared by Livewire and API controllers; module API actions are marked `#[ModuleApi]` and call `TransactionGuard::assertActive()`; validation rules live next to the action (`rules()`); each module registers menu items in its provider and permissions in `config/permissions.php`; tests use `RefreshDatabase` on MySQL over an installation seeded once per run (`TestsSupportInstalledErpSeeder`, `TestsConcernsInstallsErp` gives `$this->admin` and `$this->branch`); only tests that need an uninstalled database use `DatabaseMigrations` with `$seed`/`$seeder` = false.

## Commands
- `php artisan test`: runs app tests and `Modules/*/tests`
- `php artisan module:list`, `php artisan module:make <Name>`
- `composer run dev`: local dev server
