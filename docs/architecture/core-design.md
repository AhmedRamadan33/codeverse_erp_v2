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

**Isolation.** Under InnoDB's default REPEATABLE READ, a plain `SELECT` reads the snapshot taken at the transaction's first read, even after a lock was waited for, so two postings that queued on the same lock both acted on the old value (`ConcurrentPostingTest` found it twice: the last unit sold twice, and two credit sales passing the same credit limit). Two rules:
- The connection runs at **READ COMMITTED** (`config/database.php`), so a read made after taking a lock sees every posting committed before it. This is what makes "lock the customer, then read their ledger balance" correct without locking ledger rows.
- Rows the posting itself updates from what it read (stock balances, available and batch quantities, reconciliation residuals) are still read with `lockForUpdate`, so the value and the lock come from the same read.

## 4. Core module

### 4.1 Tables

| Table | Key columns |
|-------|-------------|
| `branches` | name (translatable), code, address, phone, is_active |
| `branch_user` | user_id, branch_id (the branches a user may work in), is_default |
| `settings` | module, key, value (json), branch_id nullable (per-branch override). Read through a typed `Settings` service with defaults declared per module. |
| `currencies` | code (ISO 4217), name (translatable), symbol, decimal_places, is_active. Base currency is a setting and cannot change after the first posted journal entry. |
| `exchange_rates` | currency_id, date, rate `decimal(18,6)` = base units per 1 foreign unit |
| `sequences` | key (e.g. `sales.invoice`), branch_id nullable (null = shared default), prefix pattern (`INV-{branch}-{yyyy}-`), padding, reset (`never`/`yearly`/`monthly`) |
| `sequence_counters` | sequence_id, period (`''` / `YYYY` / `YYYY-MM`), next_number. One counter per period, so a document dated in a previous year keeps that year's numbering. |
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

Implemented once in `AccountingPricingDocumentTotals` (a module API), shared by purchases and sales. Each document line stores gross, discounts, net, tax and total, plus the tax rate, as computed when saved.

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
- **v1.0:** one tax per document line, stored with its rate. Several taxes per line (e.g. VAT 14% plus table tax) come with EgyptTax, through a line-taxes table.

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

**Tables** (module `POS`, requires Sales):

| Table | Key columns |
|-------|-------------|
| `pos_registers` | code, name (translatable), branch_id, warehouse_id, cash_payment_method_id (the drawer), price_list_id nullable, is_active |
| `pos_shifts` | number, register_id, branch_id, user_id (cashier), status (`open`/`closed`), opened_at, opening_float, closed_at, closed_by, expected_cash, counted_cash, cash_difference, journal_entry_id, valuation_entry_id. At most one open shift per register (unique generated column) and per cashier (checked under lock). |
| `pos_receipts` | number, shift_id, register_id, branch_id, warehouse_id, partner_id, kind (`sale`/`return`), original_receipt_id, date, price_list_id, discount_type/value, subtotal, discount_total, tax_total, total, paid_total, tendered, change, is_credit, due_date, journal_entry_id (credit receipts only), created_by |
| `pos_receipt_lines` | like sales invoice lines, plus `cost`; return lines point to `original_line_id` |
| `pos_receipt_payments` | receipt_id, payment_method_id, amount (always positive; a return's payments are refunds) |

**Rules:**
- POS sells in the base currency only (v1.0).
- A receipt is **posted when it is completed**: no drafts, no edits, no cancellation. A mistake is corrected by a return receipt. A cart that is not completed is not stored.
- Every receipt belongs to the cashier's open shift, and the shift is locked while a receipt is added, so a shift cannot close half-way through a receipt.
- Prices come from the customer's price list, else the register's price list, else the product (`Sales\Pricing\PriceResolver`). Changing a price needs `pos.prices.override`; a discount needs `pos.discounts.give`.
- After commit, POS fires `PosReceiptCompleted`; EgyptTax will listen to it to submit the E-Receipt (per receipt).

**Paid receipt** (walk-in or named customer, payments = total):
- Stock: `IssueStock` with `deferValuation: true`. The cost is fixed and stored on the line, but no entry yet (§9.2).
- No journal entry: it is part of the shift entry.
- Cash tendered above the total is returned as change; only the total is recorded as paid.

**Credit receipt** (named customer, payments < total):
- Posts its own entry at once: Dr payment accounts for what was paid, Dr Receivable (partner, due date from payment terms) for the rest, Cr Revenue, Cr Tax output.
- Stock is issued **without** deferring valuation, so it gets its own COGS entry.
- The credit-limit check applies to the unpaid part (§5.2).
- Its cash still counts in the drawer (expected cash), but not in the shift entry.

**Return receipt** (always against a receipt, recorded in the cashier's current open shift):
- Lines are limited to what is still returnable; amounts are the original line's per-unit net and tax (so discounts are returned pro rata).
- Stock comes back at the original line's cost (`ReceiveStock`).
- Return of a paid receipt: refund payments = total; deferred valuation; netted in the current shift entry (whichever shift the original was in).
- Return of a credit receipt: no refund; posts its own entry at once (Dr Sales returns, Dr Tax output / Cr Receivable) and its own valuation entry.

**Shift close** (`CloseShift`, needs the cashier or `pos.shifts.manage`):
- Expected cash = opening float + cash-drawer payments of all receipts of the shift − cash-drawer refunds.
- The cashier enters the counted cash; the difference (counted − expected) goes to the mapped `pos.cash_difference` account against the drawer's account.
- One entry for the shift's paid receipts and their returns:
  - Dr each payment method's account (payments − refunds; a negative net is a credit).
  - Cr Revenue per mapped account (sales net); Dr Sales returns (returns net).
  - Cr Tax output per tax (sales tax − returns tax).
  - The cash difference line.
- In the same transaction, `PostDeferredValuation` posts one Dr COGS / Cr Inventory entry (netted with returns) for the shift's deferred moves.
- Both entries are dated on the closing day.

**API** (`/api/v1/pos/…`, for the mobile POS; same actions as the selling screen): `setup` (registers, payment methods, the cashier's POS permissions), `products?barcode=|search=` (with prices for the customer or register), `shifts/current`, `POST shifts`, `shifts/{id}`, `POST shifts/{id}/close`, `POST receipts`, `receipts/{id}`, `POST receipts/{id}/returns`. A credit sale over the limit in `warn` mode answers 422 `credit_limit_confirm`; the client resends with `confirm_over_limit: true`.

**After v1.0:** pay-ins/pay-outs and cash handover to a safe during a shift, held (parked) carts, foreign-currency payments, multiple drawers per register.

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

**`ProductUsage` registry.** Products cannot depend on the modules that use products. Those modules (Inventory first) register a check with `ProductUsage`, and Products refuses to change a used product's base unit, type or tracking.

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
ReceiveStock::handle(StockOperationData $op): StockResult                 // in-moves + valuation entry
IssueStock::handle(StockOperationData $op): StockResult                   // out-moves at cost + valuation entry
TransferStock::handle(StockOperationData $op, int $toWarehouseId): StockResult
ReverseStock::handle(Model $source, $date, $reason, $branchId, $user): StockResult  // cancellations
PostDeferredValuation::handle(array<string, int[]> $sources, Model $entrySource, ...): ?JournalEntry  // POS shift close
```
- `StockOperationData` carries `deferValuation` (default `false`).
  - When it is `true`, the moves and their costs are written and the caches are updated, but no journal entry is made.
  - `PostDeferredValuation` later posts one entry for a set of sources and stamps the moves with its `journal_entry_id`. Only POS uses this (§7.1).
- Each call carries the source document, the lines (product, warehouse, base quantity, batch/serials, unit cost for receipts), the counter-account mapping key and the date.
- `StockResult` returns the cost per line, so the caller can store it on its document lines (needed for returns and margin reports).

### 9.3 Weighted-average cost
- On a receipt:
  `new_avg = (value_on_hand + received_qty × unit_cost) / (qty_on_hand + received_qty)`
- If `qty_on_hand ≤ 0` after the receipt, the average stays at the receipt's unit cost.
- An issue takes a proportional share of the stock value: `cost = total_value × qty / qty_on_hand`, rounded to 4 decimals. Issuing everything that is left takes exactly `total_value`.
- So `value = Σ move costs` holds exactly, with no rounding residue (`inventory:check` verifies it).
- Transfers move stock between warehouses at its current value. They don't change the company-wide average.
- Locks: `product_costs` rows, then `stock_balances` rows, in id order. The unbatched balance rows are inserted before locking.

### 9.4 Negative stock
- The `inventory.allow_negative_stock` setting is off by default and can be set per branch.
- When negative stock is allowed, issues beyond the stock on hand are costed at the last known average.
- **Current limitation:** the next receipt simply adds its value, so a cost difference stays in the average. A correction posted to `inventory.price_difference` when stock arrives is planned with FIFO layers (§9.6).
- Batch- and serial-tracked products never go negative.

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
| `erp:install` | Migrates, then asks for company name, base currency, language, main branch and admin user; `--modules=` enables optional modules too (their requirements are added). Accounting installs the chart template from the `accounting.chart_template` setting and opens the current fiscal year. |
| Web wizard `/install` | Same action (`InstallErp`), after checking PHP, extensions, MySQL 8 and writable folders. Until the installation exists every web page redirects to it and runs on file sessions/cache (the session and cache tables do not exist yet); afterwards it answers 404. |
| `erp:module:enable {name}` | Checks `requires`, migrates the module, syncs permissions and account mappings, runs `install()`, marks the module enabled. |
| `erp:module:disable {name}` | Refused for Core/Accounting and for any module an enabled module requires. Tables and data are kept. |
| `erp:upgrade` | Maintenance mode → migrations of enabled modules in dependency order → `upgrade()` hooks → permission/mapping sync → cache clear. **Not built yet:** a database backup before migrating. |

### 11.3 Migration rules
- A module's migrations may add foreign keys only to tables of modules it `requires`.
- Migrations are forward-only and append-only once released. There is no editing of released migrations.
- Seeders contain only master data needed to run (units, currencies, chart templates). Demo data is a separate command.

### 11.4 Database
- **MySQL 8 from Phase 1**, for local development, tests and CI. The connection forces the InnoDB engine, so a server whose default is MyISAM (as some WAMP setups are) still gets transactions, foreign keys and row locks.
- SQLite is not supported. It has no real row locks (`lockForUpdate` is a no-op), and its `decimal` columns have numeric affinity, so money can come back as a float.
- Tests use a separate MySQL database (`<db>_testing`) with `RefreshDatabase`.

## 12. i18n and UI
- Each module has `lang/{ar,en}` files. Enums expose `label()` through translations.
- System master data uses translatable JSON columns: account names, units, categories, branches, taxes, product names.
  - Arabic is required and English is optional.
  - Display falls back to Arabic when the current locale's value is missing (`fallback_locale = ar` for translatable attributes).
- The layout switches `dir="rtl"` by locale. The locale is a per-user preference with an installation default; guests choose it per session and the API follows `Accept-Language`.
- UI kit: AdminLTE 4 (Bootstrap 5, no jQuery) installed from npm and bundled by Vite, with one stylesheet per direction (`app-ltr.css`, `app-rtl.css`). It keeps the v1 look without copying v1's AdminLTE 3 files. All modules share one bundle.
- Each module registers its sidebar items in its service provider (`Menu`); an item shows only when the user has its permission, and every route still authorizes on its own.
- The API returns translated labels in the `Accept-Language` locale, and raw codes alongside them.

## 13. Web and API
- Flow: Web (Livewire) and API controllers → Form Request / validation → DTO → **the same use-case action** → API Resource / view.
- API routes are at `/api/v1/<module>/...`, defined in each module's `routes/api.php`, under `auth:sanctum`, with token abilities mirroring permissions.
- Posting endpoints are `POST /api/v1/sales/invoices/{id}/post`, idempotent: posting an already posted document returns 409.
- Built so far: Core (`auth`, `branches`, `currencies`, `partners`), Sales (`sales/invoices` and `sales/returns`: CRUD of drafts, `post`, `cancel`, `invoices/{id}/returns`), POS (§7.1). Inventory, Purchases and Accounting have no API yet.

## 14. Testing
- Tests live in each module's `tests/`. Factories exist for all documents.
- Shared assertions:
  - `assertLedgerBalanced()`: every entry balances.
  - `assertStockConsistent()`: caches equal Σ moves.
  - `assertDocumentImmutable()`
- Required flows for each document type: post, cancel/return, posting into a locked period (rejected), concurrent posting of two invoices for the last unit of stock (on MySQL), multi-unit conversions, foreign-currency invoice.

## 15. Implementation order

### Milestone v1.0: first sellable release

| Phase | Scope |
|-------|-------|
| **1. Core** | MySQL setup, DB activator + `installed_modules`, installer command, settings, branches, currencies/rates, sequences, users/roles/permissions, partners, audit log, base layout (RTL, AdminLTE 4), admin screens, API auth |
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
