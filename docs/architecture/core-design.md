# CodeVerse ERP v2: Core Design

Status: approved v2 (2026-10-02). This document turns the principles in [CLAUDE.md](../../CLAUDE.md) into concrete module boundaries, schemas and flows. When code and this document disagree, fix one of them in the same change.

## 1. Decisions

| # | Decision | Notes |
|---|----------|-------|
| D1 | `Core` and `Accounting` are always installed and cannot be disabled. | Every module that has financial documents `requires` Accounting. |
| D2 | Posting a document calls Accounting's posting action **directly, inside the same DB transaction**. | If the journal entry fails, the document is not posted. Events are only for optional reactions (EgyptTax, Notifications, …). |
| D3 | One legal entity per installation, with multiple **branches**. | The branch is a dimension on documents and journal lines. There is no multi-company. |
| D4 | Multi-currency in the schema from day one. | Documents carry `currency_id` and `exchange_rate`. The ledger is kept in the base currency, plus the amount in the foreign currency. |
| D5 | Partners (customers and suppliers) live in `Core`. | One `partners` table with role flags. |
| D6 | Weighted-average cost (WAC) is kept per product across the whole company, not per warehouse. | Transfers don't change cost. FIFO comes later through valuation layers (§9.6). |
| D7 | The document number is assigned at posting time, not when the draft is created. | No gaps from deleted drafts. |
| D8 | A posted document is immutable. Corrections are made by reversal or by a return document. | Same rule applies to journal entries. |
| D9 | MySQL from Phase 1. | Accounting (Phase 2) already depends on real row locks and exact decimals (§11.4). |
| D10 | POS posts one journal entry per closed shift. A credit sale to a known customer gets its own entry per receipt. | Stock moves and the ETA E-Receipt stay per receipt (§7.1). |
| D11 | Line discounts and document discounts are both supported. The document discount is distributed across lines in proportion to their net amounts and stored on each line. | ETA needs final per-line values (§5.1). |
| D12 | Credit-limit check is a setting: `block` / `warn` / `off`. The default is `warn`. | `sales.credit_limit.override` lets a user post past a `block` (§5.2). |
| D13 | Cost centers are optional. An account can be flagged `requires_cost_center` (off by default). | Implemented after v1.0 (§15). |
| D14 | Product names are translatable. Arabic is required, English is optional, and the UI falls back to Arabic. | Same fallback for all translatable master data (§12). |
| D15 | Custom fields and attachments are deferred until after Sales. | §4.3 |
| D16 | Milestone **v1.0** (first sellable release) = Core + Accounting + Products + Inventory + Purchases + Sales + POS. | Advanced accounting comes after it (§15). |

## 2. Module map

```
Core ─────────────┐
  ▲               │
Accounting ◄──────┤   (always on)
  ▲               │
Products ─────────┘
  ▲
Inventory
  ▲         ▲
Purchases  Sales ◄── POS ◄── Restaurant
              ▲
        Manufacturing (requires Inventory, Products)

Optional listeners: EgyptTax (requires Sales), Notifications
```

| Module | Owns |
|--------|------|
| **Core** | users, roles/permissions, branches, settings, currencies & exchange rates, numbering sequences, partners, audit log, installed-modules registry. Later: custom-field definitions, attachments (after Sales), approval workflows (after v1.0). |
| **Accounting** | chart of accounts, journal entries/lines, fiscal years/periods, lock dates, account mappings, taxes, cost centers, receipt/payment vouchers, reconciliation, financial reports |
| **Products** | products, categories, units and per-product unit conversions, barcodes, product types/tracking mode |
| **Inventory** | warehouses, stock moves, stock balances, product costs (valuation), batches, serials, adjustments, transfers, opening stock |
| **Purchases** | purchase invoices, purchase returns (orders and receipts later) |
| **Sales** | sales invoices, sales returns, price lists (v1 "sales segments") (quotations/orders later) |
| **POS** | registers, cashier shifts, POS receipts |
| **EgyptTax** | ETA credentials, item codes (GS1/EGS), E-Receipt and E-Invoice submissions |

`Products` is separate from `Inventory` because services businesses sell items without tracking stock.

## 3. Cross-module communication

### 3.1 Calls (hard dependencies)
- A module calls the public actions of modules it lists in `module.json` `requires`. For example, `Sales` calls `Inventory\Actions\IssueStock` and `Accounting\Actions\PostJournalEntry`.
- Public actions live in `Modules/<Name>/app/Actions` and take **DTOs**, not request arrays.
- An action meant to be called by other modules is marked with the `#[ModuleApi]` attribute. Anything else in a module is internal.

### 3.2 Two kinds of actions
- **Use-case actions** are invoked by a user, through web or API (e.g. `PostSalesInvoice`):
  - They authorize the acting user.
  - They open the DB transaction.
  - They fire the domain events.
- **Module API actions** are invoked by other modules (e.g. `IssueStock`, `PostJournalEntry`):
  - They do **not** check user permissions; the calling use case already did.
  - They do **not** open their own transaction. They assert that one is active (`DB::transactionLevel() > 0`), so a partial write can never escape.

### 3.3 Events (optional reactions)
- Events are dispatched **after commit** (`ShouldDispatchAfterCommit`), so a listener never sees a document that was rolled back.
- Listeners that talk to outside services (ETA submission, SMS, e-mail) are queued jobs.
- A module that fires an event must work identically when nobody listens.
- Event names are past tense and carry IDs, not models: `SalesInvoicePosted(int $invoiceId)`.

### 3.4 Transactions and lock order
The use-case action holds the transaction. Locks are taken in this fixed order to avoid deadlocks:

1. The document row itself (`lockForUpdate`, to prevent a double post).
2. Stock rows: `product_costs`, then `stock_balances`, each sorted by `product_id` and then `warehouse_id`.
3. Partner-related rows (credit-limit check) if needed.
4. Number sequences, **last**, because they are the hottest rows.

## 4. Core module

### 4.1 Tables

| Table | Key columns |
|-------|-------------|
| `branches` | name (translatable), code, address, phone, is_active |
| `branch_user` | user_id, branch_id (the branches a user may work in), is_default |
| `settings` | module, key, value (json), branch_id nullable (per-branch override). Read through a typed `Settings` service with defaults declared per module. |
| `currencies` | code (ISO 4217), name (translatable), symbol, decimal_places, is_active. Base currency is a setting and cannot change after the first posted journal entry. |
| `exchange_rates` | currency_id, date, rate `decimal(18,6)` = base units per 1 foreign unit |
| `sequences` | key (e.g. `sales.invoice`), branch_id nullable, prefix pattern (`INV-{branch}-{yyyy}-`), padding, next_number, reset (`never`/`yearly`), current_period |
| `partners` | type (`person`/`company`), name, is_customer, is_supplier, tax_number, national_id, commercial_register, phone, email, address, credit_limit `decimal(18,4)` nullable, payment_term_days, branch_id nullable, is_active |
| `custom_field_definitions` | *After Sales.* entity (e.g. `sales.invoice`, `core.partner`), key, type (text/number/date/select/bool), options json, label (translatable), required, sort |
| `attachments` | *After Sales.* morph (attachable), path, disk, original_name, mime, size, uploaded_by |
| `audit_logs` | user_id, event, morph (auditable), old_values json, new_values json, ip, created_at |
| `installed_modules` | name, version, enabled, installed_at, upgraded_at (see §11) |

Users, roles and permissions use `spatie/laravel-permission`.

### 4.2 Numbering
- `Core\Actions\NextNumber::for('sales.invoice', $branchId, $date)` locks the sequence row, formats the number, increments the counter and returns the number.
- It is called while posting, as the last lock (§3.4).
- Sequences are configurable from the UI. Each module seeds defaults for its own keys.

### 4.3 Custom fields (after Sales)
- Deferred, together with attachments, until Sales is done. When they arrive, a migration adds the `custom_fields` column to each table that supports them.
- Definitions live in Core. Values live in a `custom_fields` JSON column on each owning table.
- The owning module renders and validates them through a shared Core helper.
- If MySQL needs to report on a field, add a generated column then. No EAV.

## 5. Document conventions (shared by all modules)

Every document table (sales invoice, purchase invoice, stock adjustment, receipt voucher, …) has these columns:

```
id, number (null until posted), branch_id, date, status,
partner_id (when applicable), currency_id, exchange_rate decimal(18,6),
subtotal, discount_total, tax_total, total            -- decimal(18,4), document currency
notes,
created_by, posted_by, posted_at, cancelled_by, cancelled_at, cancel_reason,
created_at, updated_at
```

- **Lines** go in a `<document>_lines` table. Each line has `unit_id`, `quantity` (in the document unit), `base_quantity` (in the product's base unit) and `unit_price`. Lines also store the computed `discount`, `tax_amount` and `line_total`, so the printed document never changes after posting.
- **Status** is an enum: `draft → posted → cancelled`.
  - Some documents add steps before posting (`pending_approval`, `approved`).
  - Only drafts can be edited or deleted. A model guard and the actions both enforce this.
- **Posting** (one use-case action per document type):
  1. Authorize.
  2. Lock the document and validate it.
  3. Call stock actions.
  4. Call `PostJournalEntry`.
  5. Assign the number.
  6. Set `posted`.
  7. After commit, fire `<Doc>Posted`.
- **Cancelling** a posted document:
  - It reverses the stock moves and journal entries (new rows that point to the originals). Nothing is deleted.
  - It is allowed only while the date is in an open period and nothing depends on the document, e.g. no payment is reconciled against an invoice.
  - Otherwise the user must issue a return document.
- **Rounding:**
  - Amounts are stored with 4 decimals.
  - Document totals are rounded to the currency's `decimal_places` (EGP = 2).
  - A rounding difference is posted to a mapped `rounding` account.
- **Money in PHP:**
  - Use `brick/math` `BigDecimal` through a custom Eloquent cast.
  - `float` arithmetic on money or quantities is forbidden. Add a PHPStan/Larastan rule or a review checklist item for this.

### 5.1 Discounts
Line amounts are computed in this order. All values are stored on the line.

1. `gross = quantity × unit_price`
2. `line_discount`: a percent or a fixed amount entered on the line.
3. `net_before_doc_discount = gross − line_discount`
4. `document_discount`: the line's share of the document discount (percent or fixed), in proportion to `net_before_doc_discount`.
   - The share is rounded to the currency's decimal places.
   - The rounding remainder goes to the line with the largest net amount (ties: the first such line), so Σ shares = the document discount exactly.
5. `net = net_before_doc_discount − document_discount`
6. Taxes are computed on `net`.

Header `discount_total` = Σ (line_discount + document_discount). Revenue is posted at `net`, so no separate "discount allowed" account is needed. Reports read the discount columns on the lines.

### 5.2 Credit limit
- The setting `sales.credit_limit_mode` is `block` / `warn` / `off`. The default is `warn`.
- The check runs when posting a credit (non-cash) sale for a partner with a `credit_limit`. It fails when the partner's receivable balance (Σ journal lines) plus the new invoice total exceeds the limit.
- `warn`: the UI and API show a warning, and the user must confirm with `confirm_over_limit = true`.
- `block`: posting is refused unless the user has `sales.credit_limit.override`.
- POS credit sales use the same check.

## 6. Accounting engine

### 6.1 Chart of accounts
`accounts` table:

| Column | Description |
|--------|-------------|
| `code` | Unique. Hierarchical codes are allowed, e.g. `1101`. |
| `name` | Translatable. |
| `parent_id` | Parent account in the tree. |
| `is_group` | Group accounts can't receive postings. Only leaf accounts are postable. |
| `type` | `asset` / `liability` / `equity` / `income` / `expense`. Normal balance is derived from it. |
| `subtype` | `cash`, `bank`, `receivable`, `payable`, `inventory`, `tax`, `cogs`, `fixed_asset`, `current_asset`, `current_liability`, `retained_earnings`, … |
| `currency_id` | Nullable. Set it to restrict the account to one currency, e.g. a USD bank account. |
| `requires_partner` | Forced true for `receivable`/`payable`. |
| `requires_cost_center` | Default false. When true, every line on this account must have a `cost_center_id`. Added with cost centers, after v1.0. |
| `is_active` | |
| `is_system` | Seeded accounts referenced by mappings. They can't be deleted, but they can be renamed. |

At installation the user chooses a chart template. The first template is an Egyptian standard chart in Arabic and English.

### 6.2 Journal

`journal_entries`:

| Column | Description |
|--------|-------------|
| `number` | |
| `date` | |
| `branch_id` | |
| `journal_type` | `sales` / `purchases` / `inventory` / `cash` / `bank` / `general` / `opening` / `closing`. Used for numbering and filtering. |
| `description` | |
| `source_type`, `source_id` | Morph to the document that produced the entry. Null for manual entries. |
| `status` | `draft` / `posted`. Only manual entries can be drafts; system entries are created posted. |
| `reversal_of_id` | Nullable. Points to the entry this one reverses. |
| `reversed_by_id` | Nullable. Points to the entry that reverses this one. |
| `posted_by`, `posted_at` | |

`journal_lines`:

| Column | Description |
|--------|-------------|
| `journal_entry_id`, `account_id` | |
| `debit`, `credit` | `decimal(18,4)`, base currency. Both ≥ 0 and exactly one of them > 0. |
| `currency_id`, `amount_currency` | Signed amount in the foreign currency. Null when the line is in base currency. |
| `partner_id` | Required when the account `requires_partner`. |
| `branch_id` | Copied from the header by default. Can differ for inter-branch entries. |
| `cost_center_id` | Nullable. Added with the `cost_centers` table, after v1.0. |
| `due_date` | Nullable. Used for receivable/payable aging. |
| `description` | |
| `source_line_type`, `source_line_id` | Nullable. Traces the line back to a document line. |

Invariants, enforced in `PostJournalEntry` and covered by tests:
- Σ debit = Σ credit, compared in base currency at 4 decimals.
- At least two lines.
- Every account is active and postable (not a group).
- The date is after the lock date and inside an open fiscal period.
- Posted entries and lines are never updated or deleted.

**Balances are always derived from `journal_lines`.** That includes account balances, partner balances (lines on receivable/payable accounts with `partner_id`), the trial balance and the cash box balance. If reports become slow, add a *rebuildable* summary table (e.g. per account, per period). It is never the source of truth.

### 6.3 Posting API
```php
// Modules\Accounting\Actions\PostJournalEntry  (#[ModuleApi])
public function handle(JournalEntryData $data): JournalEntry;

// Modules\Accounting\Actions\ReverseJournalEntry  (#[ModuleApi])
public function handle(JournalEntry $entry, CarbonImmutable $date, string $reason): JournalEntry;
```
`JournalEntryData` contains the date, branch, journal type, description, source and a list of `JournalLineData`. Callers resolve accounts through `AccountResolver` (§6.4). They never use hard-coded account IDs.

### 6.4 Account mapping
Modules need accounts such as "the revenue account for this product". The `account_mappings` table answers that:

```
key          e.g. 'sales.revenue', 'sales.receivable', 'inventory.asset', 'inventory.cogs',
                  'purchases.payable', 'purchases.grni', 'tax.output', 'rounding'
scope_type   null (default) | 'product_category' | 'product' | 'partner' | 'warehouse' | 'branch' | 'tax' | ...
scope_id     nullable
account_id
```

- Each module declares its keys and their default system accounts in `config/accounting.php`. Accounting syncs them into `account_mappings` when the module is enabled.
- `AccountResolver::resolve('sales.revenue', [$product, $product->category, $branch])` returns the most specific match, falling back to the default.
- Core and Products don't need foreign keys to Accounting. For example, a partner-specific receivable account is simply a mapping with `scope_type = partner`.

### 6.5 Periods and closing
- `fiscal_years` (start, end, status) and `fiscal_periods` (monthly, status `open`/`closed`).
- There is also a global `lock_date` setting. A per-user "allow posting before lock date" permission covers adjusting entries.
- Year-end closing is an action. It posts a `closing` entry that moves income and expense balances to `retained_earnings`, and then closes the year.
- **Opening balances** are a `journal_type = opening` entry against the "opening balance equity" mapping. Opening stock is a separate Inventory document (§9.5).

### 6.6 Taxes
- `taxes` table:

  | Column | Description |
  |--------|-------------|
  | `name` | Translatable. |
  | `rate` | `decimal(9,4)` |
  | `type` | `percent` / `fixed` |
  | `scope` | `sales` / `purchases` / `both` |
  | `included_in_price` | bool |
  | `account mappings` | Two mapping keys, scoped to the tax: `tax.output` and `tax.input`. |
  | `is_active` | |

- The `tax_line` link is many-to-many: a document line can carry several taxes, e.g. VAT 14% plus table tax.
- EgyptTax adds ETA tax-type and sub-type codes in its own table that references `taxes`. Accounting knows nothing about ETA.

### 6.7 Receipts, payments and reconciliation
- **Receipt voucher:** customer pays. Dr Cash/Bank, Cr Receivable (partner).
- **Payment voucher:** we pay a supplier. Dr Payable (partner), Cr Cash/Bank. Both are Accounting documents (§5 conventions).
- **Cash boxes and bank accounts** are accounts with subtype `cash`/`bank`. A `payment_methods` table maps a method (cash, card, bank transfer, wallet) to an account.
- **Reconciliation** links debit and credit lines of the same partner and account:
  - Table: `reconciliations(debit_line_id, credit_line_id, amount, amount_currency, created_at)`.
  - From it you get open items, invoice paid/unpaid status and aging.
  - Partner balance is still Σ lines. Reconciliation only says *which* invoices the payments settle.
- **v1.0 scope:** a receipt or payment voucher is allocated to specific invoices or returns of the same partner, fully or partially, when it is posted. Each allocation writes a `reconciliations` row.
- **After v1.0:**
  - Full reconciliation: match any open lines manually, auto-match, and unreconcile.
  - Realized exchange differences on foreign-currency items.
  - Cost centers.

## 7. Posting recipes

All amounts below are in base currency. "Inventory posts" means the entry is made by `Inventory`'s action, which owns valuation; the calling module passes the counter-account key.

| Document | Entry made by the document's module | Entry made by Inventory (stock items only) |
|----------|-------------------------------------|--------------------------------------------|
| **Sales invoice** | Dr Receivable (partner, due_date) = total<br>Cr Revenue (per line, mapped by product/category) = net<br>Cr Tax output (per tax) | Dr COGS / Cr Inventory, at current WAC |
| **Cash sale** | Same as above, but Dr Cash/Bank (by payment method) instead of Receivable. Walk-in customer partner if none. | Same |
| **POS** | See §7.1. | See §7.1. |
| **Sales return** | Dr Sales returns (or Revenue) / Dr Tax output / Cr Receivable or Cash | Dr Inventory / Cr COGS, at the **original issue cost** of the returned lines |
| **Purchase invoice** | Dr GRNI (goods received not invoiced) = net<br>Dr Tax input<br>Cr Payable (partner) = total | Dr Inventory / Cr GRNI, at invoice cost (+ landed costs later). Net GRNI = 0. |
| **Purchase return** | Dr Payable / Cr GRNI / Cr Tax input | Dr GRNI / Cr Inventory, at the original purchase unit cost; any difference vs. WAC goes to `inventory.price_difference` |
| **Stock adjustment** | (none) | Increase: Dr Inventory / Cr Adjustment gain. Decrease: Dr Adjustment loss / Cr Inventory. Both at WAC. |
| **Warehouse transfer** | (none) | No entry if both warehouses map to the same inventory account. Otherwise Dr Inventory(B) / Cr Inventory(A). |
| **Receipt voucher** | Dr Cash/Bank / Cr Receivable (partner) | |
| **Payment voucher** | Dr Payable (partner) / Cr Cash/Bank | |
| **Expense voucher** | Dr Expense / Dr Tax input / Cr Cash/Bank | |

Why GRNI even when the invoice and the receipt are one document? Separate goods receipts can be added later without changing any posting rule.

### 7.1 POS
- **Per receipt:**
  - Stock moves: POS calls `IssueStock` with `deferValuation: true`. The cost is fixed at that moment, but no journal entry is made yet (§9.2).
  - The ETA E-Receipt is submitted for each receipt.
- **Credit sale to a known customer (per receipt):**
  - The receipt posts its own entry at once, like a sales invoice: Dr Receivable (partner).
  - Its stock is issued **without** deferring valuation, so it also gets its own COGS entry.
  - The credit-limit check applies (§5.2).
- **Shift close** (`CloseShift`) posts one entry for all paid receipts of the shift:
  - Dr Cash/Bank per payment method.
  - Cr Revenue per mapped account.
  - Cr Tax output per tax.
  - The cash over/short against the counted amount goes to the mapped `pos.cash_difference` account.
  - In the same transaction, POS calls `Inventory\Actions\PostDeferredValuation` for the shift's receipts. It posts one Dr COGS / Cr Inventory entry from the costs already stored on the moves and stamps them with its `journal_entry_id`.
- **POS returns in an open shift** are netted in the shift entry. Returns after the shift is closed post their own entry.

## 8. Products module

| Table | Key columns |
|-------|-------------|
| `product_categories` | name (translatable), parent_id |
| `units` | name (translatable), symbol |
| `products` | name (translatable: `ar` required, `en` optional), sku, category_id, type (`stockable` / `consumable` / `service`), tracking (`none` / `batch` / `serial`), base_unit_id, sale_price, purchase_price, is_active |
| `product_units` | product_id, unit_id, factor `decimal(18,4)` = base units per this unit, barcode, sale_price nullable, is_default_sale, is_default_purchase |
| `product_barcodes` | product_id, product_unit_id nullable, barcode (unique) |

UoM rules, replacing v1's global `base_unit_is_largest` multiplier:
- The **base unit is the smallest unit** of the product. Every other unit has `factor ≥ 1`. Conversion is always `base_quantity = quantity × factor`, so we never divide.
- Conversions are **per product** (a carton can be 12 for one product and 24 for another).
- Stock, costing and accounting work only in base units. Document lines keep the entered unit for display and printing.

Only `stockable` products create stock moves. `consumable` and `service` products post straight to expense or revenue.

## 9. Inventory module

### 9.1 Tables

| Table | Key columns |
|-------|-------------|
| `warehouses` | name, code, branch_id, is_active |
| `stock_moves` | product_id, warehouse_id, batch_id nullable, quantity `decimal(18,4)` **signed** in base units (+ in / − out), unit_cost, total_cost (signed), type (`purchase`, `sale`, `sale_return`, `purchase_return`, `adjustment`, `transfer_in`, `transfer_out`, `opening`, `production_in`, `production_out`), source morph, source_line morph, reversal_of_id, journal_entry_id nullable (the valuation entry; null while valuation is deferred), date, created_by |
| `stock_balances` | product_id, warehouse_id, batch_id nullable, quantity. Unique (product, warehouse, batch). |
| `product_costs` | product_id (unique), quantity_on_hand, total_value, average_cost |
| `stock_batches` | product_id, batch_number, expiry_date, manufactured_at. Unique (product, batch_number). |
| `stock_serials` | product_id, serial_number, status (`in_stock`/`sold`/`returned`/…), warehouse_id, batch_id |
| `stock_move_serials` | stock_move_id, serial_id |
| `stock_adjustments`, `stock_transfers` (+ lines) | Inventory's own documents (§5 conventions) |

- **`stock_moves` is the source of truth.**
- `stock_balances` and `product_costs` are caches. Only Inventory actions write them, under row locks.
- An artisan command rebuilds both from the moves and reports any drift. A test asserts there is no drift after every flow.

### 9.2 Module API
```php
ReceiveStock::handle(StockReceiptData $data): StockResult   // in-moves + valuation entry
IssueStock::handle(StockIssueData $data): StockResult       // out-moves at cost + valuation entry
TransferStock::handle(StockTransferData $data): StockResult
ReverseStock::handle(Model $source): StockResult            // used by cancellations
PostDeferredValuation::handle(DeferredValuationData $data): ?JournalEntry  // POS shift close
```
- `StockIssueData` and `StockReceiptData` accept `deferValuation` (default `false`).
  - When it is `true`, the moves and their costs are written and the caches are updated, but no journal entry is made.
  - `PostDeferredValuation` later posts one entry for a set of sources and stamps the moves with its `journal_entry_id`. Only POS uses this (§7.1).
- Each call carries the source document, the lines (product, warehouse, base quantity, batch/serials, unit cost for receipts), the counter-account mapping key and the date.
- `StockResult` returns the cost per line, so the caller can store it on its document lines (needed for returns and margin reports).

### 9.3 Weighted-average cost
- On a receipt:
  `new_avg = (value_on_hand + received_qty × unit_cost) / (qty_on_hand + received_qty)`
- If `qty_on_hand ≤ 0` before the receipt, the new average is the receipt's unit cost.
- Issues go out at the current average and don't change it.
- Values are computed with `BigDecimal` and stored with 4 decimals. Any rounding remainder stays in `total_value`, so value = Σ move costs holds exactly.

### 9.4 Negative stock
- The `inventory.allow_negative_stock` setting is off by default and can be set per branch.
- When negative stock is allowed, issues are costed at the last known average. The difference is corrected when stock arrives (and recorded to `inventory.price_difference`).

### 9.5 Batches, expiry and serials
- For batch-tracked products, an issue without an explicit batch picks batches **FEFO** (first expiry, first out).
- Serial-tracked products move with quantity 1 per serial, and serial status is updated by the same action.
- Opening stock is an `opening` adjustment document with unit costs. It posts Dr Inventory / Cr Opening balance equity.

### 9.6 Path to FIFO
- Later, add `stock_valuation_layers` (in-move, remaining_qty, unit_cost). Issues consume layers.
- The costing method becomes a setting (per category, possibly).
- This is why every in-move already stores its own `unit_cost`, and why callers depend only on `StockResult` and never on `product_costs` directly.

## 10. Authorization
- Permission names follow `<module>.<resource>.<action>`, e.g. `sales.invoices.post` or `accounting.entries.view`.
- Each module lists its permissions in `config/permissions.php`. They are synced on install and upgrade.
- Roles are data (created in the UI), except a seeded `super-admin`.
- Use-case actions authorize the actor themselves through policies (`Gate::forUser($actor)->authorize(...)`), so web and API can't forget. Controllers and Livewire may also authorize early, for a fast 403.
- **Branch scoping:** a user sees and creates documents only in the branches assigned in `branch_user`. Branch-scoped models use a global scope, and a `*.all_branches` permission bypasses it.
- There are no state-changing GET routes. Every route has `auth` (web) or `auth:sanctum` (API) middleware.

## 11. Installation, modules and upgrades

### 11.1 Module registry
- Enabled state lives in the database (`installed_modules`), through a custom `nwidart` activator, and is cached. It replaces `modules_statuses.json`, which would otherwise be committed per codebase rather than per installation.
- `module.json` gains `version` and `requires` (module names).
- Each module may provide a `ModuleInstaller` class with these hooks:
  - `install()`: seeds mappings, sequences and settings defaults.
  - `upgrade(string $from, string $to)`.
  - `canDisable(): bool`.

### 11.2 Commands (the web setup wizard calls the same actions)

| Command | What it does |
|---------|--------------|
| `erp:install` | Checks the environment, migrates Core + Accounting, then asks for company info, base currency, chart template, first fiscal year, main branch and admin user. |
| `erp:module:enable {name}` | Checks `requires`, migrates the module, syncs permissions and account mappings, runs `install()`, marks the module enabled. |
| `erp:module:disable {name}` | Refused for Core/Accounting and for any module an enabled module requires. Tables and data are kept. |
| `erp:upgrade` | Maintenance mode → DB backup → migrations of enabled modules in dependency order → `upgrade()` hooks → permission/mapping sync → cache clear. |

### 11.3 Migration rules
- A module's migrations may add foreign keys only to tables of modules it `requires`.
- Migrations are forward-only and append-only once released. There is no editing of released migrations.
- Seeders contain only master data needed to run (units, currencies, chart templates). Demo data is a separate command.

### 11.4 Database
- **MySQL 8 from Phase 1**, for local development, tests and CI.
- SQLite is not supported. It has no real row locks (`lockForUpdate` is a no-op), and its `decimal` columns have numeric affinity, so money can come back as a float.
- Tests use a separate MySQL database (`<db>_testing`) with `RefreshDatabase`.

## 12. i18n and UI
- Each module has `lang/{ar,en}` files. Enums expose `label()` through translations.
- System master data uses translatable JSON columns: account names, units, categories, branches, taxes, product names.
  - Arabic is required and English is optional.
  - Display falls back to Arabic when the current locale's value is missing (`fallback_locale = ar` for translatable attributes).
- The layout switches `dir="rtl"` by locale. The locale is a per-user preference with an installation default.
- The API returns translated labels in the `Accept-Language` locale, and raw codes alongside them.

## 13. Web and API
- Flow: Web (Livewire) and API controllers → Form Request / validation → DTO → **the same use-case action** → API Resource / view.
- API routes are at `/api/v1/<module>/...`, defined in each module's `routes/api.php`, under `auth:sanctum`, with token abilities mirroring permissions.
- Posting endpoints are `POST /api/v1/sales/invoices/{id}/post`, idempotent: posting an already posted document returns 409.

## 14. Testing
- Tests live in each module's `tests/`. Factories exist for all documents.
- Shared assertions:
  - `assertLedgerBalanced()`: every entry balances.
  - `assertStockConsistent()`: caches equal Σ moves.
  - `assertDocumentImmutable()`
- Required flows for each document type: post, cancel/return, posting into a locked period (rejected), concurrent posting of two invoices for the last unit of stock (on MySQL), multi-unit conversions, foreign-currency invoice.

## 15. Implementation order

| Phase | Scope |
|-------|-------|
### Milestone v1.0: first sellable release

| Phase | Scope |
|-------|-------|
| **1. Core** | MySQL setup, DB activator + `installed_modules`, installer command, settings, branches, currencies/rates, sequences, users/roles/permissions, partners, audit log, base layout (RTL, AdminLTE port), API auth |
| **2. Accounting** | Chart + Egyptian template, `PostJournalEntry`/`Reverse`, periods & lock date, account mappings, taxes, manual journal entries, receipt/payment/expense vouchers with invoice allocation (§6.7), trial balance, general ledger, partner statement |
| **3. Products + Inventory** | Units/conversions, moves, WAC, balances, adjustments, transfers, opening stock, batches/serials |
| **4. Purchases** | Invoices and returns end-to-end with stock and accounting |
| **5. Sales** | Invoices and returns, discounts (§5.1), credit limit (§5.2), price lists |
| **6. POS** | Registers, shifts, receipts, shift-level posting (§7.1) |

### After v1.0
- Custom fields and attachments (deferred until after Sales; they can be pulled in right after Phase 5 if needed).
- Advanced accounting: full reconciliation, cost centers, realized exchange differences.
- EgyptTax (E-Receipt port, then E-Invoice), Restaurant, Manufacturing, approval workflows.

## 16. Resolved questions
| Question | Answer |
|----------|--------|
| POS posting granularity | Per shift, except credit sales to a known customer (per receipt). Stock moves and E-Receipt per receipt. D10, §7.1 |
| Product names | Translatable, `ar` required, `en` optional, fallback to Arabic. D14 |
| Discounts | Line and document discounts. The document discount is distributed proportionally and stored per line. D11, §5.1 |
| Credit limit | Setting `block` / `warn` / `off`, default `warn`, override permission. D12, §5.2 |
| Cost centers | Optional, `requires_cost_center` flag per account (default off), after v1.0. D13 |
