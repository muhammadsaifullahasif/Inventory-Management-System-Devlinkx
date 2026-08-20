# Inventory Management System — Complete Documentation

## Overview

Comprehensive **Laravel-based Inventory Management System** for e-commerce businesses. End-to-end management of products, orders, purchases, warehousing, and financial accounting, with deep **eBay marketplace integration**. Single-tenant application (no multi-company/org scoping).

---

## Key Features & Modules

### 1. Inventory Management
- Product catalog with SKU, barcode, pricing, descriptions (`ProductController`, `Product`, `ProductMeta`)
- Product bundles — composite products with automatic stock calculations (`ProductBundleComponent`)
- Multi-warehouse/rack storage tracking (`Warehouse`, `Rack`)
- Barcode generation and bulk label printing (picqer/php-barcode-generator)
- Bulk import/export via Excel (`ProductsImport`, `PurchaseImport`, Maatwebsite Excel)

### 2. Warehouse & Storage
- Multiple warehouse locations
- Granular rack/bin level tracking
- Weighted average cost inventory valuation (`ProductStock`, `InventoryAccountingService`)

### 3. Purchase & Supplier Management
- Purchase order creation and tracking (`Purchase`, `PurchaseItem`)
- Supplier profiles with payables integration (`Supplier`)
- Partial/full receiving workflows
- Freight and customs duty tracking for imports

### 4. Sales Channel Integration
- **eBay only** — architecture (`SalesChannel`, `SalesChannelProduct`) is named generically to allow future channels (Amazon, Shopify, etc.) but no other channel is implemented today. All OAuth, sync, and finance code is eBay-specific.
- Full eBay integration with OAuth2 authentication
- Product listing management, eBay Platform Notifications for webhooks
- **Dedicated per-channel listings** *(added 2026-08-13/14, missing from original draft)* — each product can now have a distinct listing `title`, `description`, `regular_price`, `sale_price` per connected sales channel (stored on the `sales_channel_product` pivot, model `SalesChannelProduct`), instead of one shared title/description across all channels. Fields can be prefilled from eBay via `GET /ebay/item-by-sku/{id}`.
- **Buffered Inventory Sync engine** *(missing from original draft)* — `app/Services/Inventory/` (`InventorySyncService`, `VisibleStockCalculator`, `SyncDecision`, `SyncResult`) + `InventorySyncController` + `InventorySyncLog` model. Calculates "visible" sellable stock per channel (buffer/overselling protection), supports manual force-sync, per-product/channel sync toggles, and logs every sync attempt. Control surface at `/api/inventory-sync/*` (status, preview, sync, queue, force, syncChannel, logs, stats, settings).
- **eBay Finance sync** *(missing from original draft)* — `EbayFinanceSyncService`, `EbayFinanceTransaction` model, `SyncEbayFinances` job/command (hourly). Reconciles eBay fees, shipping label charges, and ad charges against orders (`orders` table has `ebay_finance_summary` fields).

### 5. Order Management
- Complete order lifecycle (pending → paid → shipped → delivered)
- **Order Returns module** *(missing from original draft)* — separate from eBay's own Return API. `OrderReturn`/`OrderReturnItem` models, `OrderReturnController`, `order_returns`/`order_return_items` tables. Approve/decline/mark-received/close workflow. Rule: **return = restock, refund alone ≠ restock** (business rule captured in return-vs-refund logic).
- FedEx shipping integration for labels and tracking (see §6 below for full feature list — goes well beyond tracking)
- Shipment deadline alerts

### 6. FedEx Shipping — full feature set *(original draft undersold this)*
`FedexService` + `ShippingService` (carrier-agnostic wrapper, only FedEx implemented today):
- OAuth2 client-credentials token flow, sandbox/production switching, auto token refresh (`shipping:refresh-tokens`, every 30 min)
- **Address validation** — classifies BUSINESS/RESIDENTIAL/MIXED/UNKNOWN
- **Rate shopping** — `POST /orders/shipping-rates`, `GET /orders/{id}/rate-info`
- **Label generation** — single and multi-package shipments (`POST /orders/{id}/generate-label`, `.../generate-multi-labels`), returns tracking number + base64 label
- **Label cancellation/voiding** — `POST /orders/{id}/cancel-label`
- **End-of-day shipment closing** — `POST /orders/close-fedex-shipments`
- **Tracking status polling** — used by scheduled delivery-status checks (every 2h)

### 7. Financial Accounting Module
- Double-entry bookkeeping, hierarchical Chart of Accounts
- Vendor bills and payment tracking (`BillService`, `PaymentService`)
- Automatic journal entries (`JournalService`, `InventoryAccountingService`) for COGS on shipment, sales revenue, purchase duties/freight
- `SalesChannelAccountService` auto-creates Chart-of-Accounts entries per sales channel
- Reports: trial balance, ledger, inventory valuation, sales, purchases, plus **23 distinct export classes** (`app/Exports/`) including expense report, supplier ledger, bank summary, COGS (2-sheet), gross profit, comparison report, returns/refunds export, unmatched SKUs, and more.

### 8. Market Research Module *(entirely missing from original draft)*
Automated **eBay competitor price comparison** for your catalog.
- Pulls competitor listings/prices per SKU via eBay's Browse API (`EbayBrowseClient`, `PriceComparisonService`)
- `ProductPriceComparison` model, `CompareProductPriceJob`, console command `price-comparison:run` (scheduled hourly, rotates oldest-compared-first, `--limit=50`)
- UI: `MarketResearchController` — filterable/sortable comparison table + Excel export, at `/market-research`

### 9. User, Role & Permission Management *(entirely missing from original draft)*
Full RBAC built on **Spatie Laravel Permission**, not just data models:
- `UserController`, `RoleController`, `PermissionController` — full CRUD + bulk-delete UIs
- Custom `Permission` model adds a `category` field; permission categories: Orders, Products, Purchases, Warehouses, Sales Channels, Suppliers, Shipping, Accounting, Users & Access
- Every controller in the app is permission-gated (`PermissionMiddleware::using('view users')`, etc.) — this is the authorization backbone for the whole system, not a bolt-on
- No hardcoded "admin"/"superadmin" flag — "admin" is simply a role granted all permissions via the Roles UI
- Seeders: `AccountingPermissionsSeeder`, `BasicSeeder`

### 10. Dashboard & Reporting
- Customizable **per-user** widget-based dashboard (`DashboardSetting` model — settings are per-user, not global company settings)
- Widget toggle/reset endpoints (`/dashboard/widgets`, `/dashboard/widgets/toggle`, `/dashboard/widgets/reset`)
- PDF export (DomPDF), multiple report types

---

## What does NOT exist (explicitly confirmed by codebase audit — don't assume otherwise)

- **No Settings/Configuration module.** No company settings, no email-template settings. Third-party credentials live per-record (`sales_channels`, `shippings` tables) or in `.env`.
- **No notification system.** No `app/Notifications/`, no `app/Mail/`, no `Notification::`/`Mail::`/`->notify()` calls anywhere. `.env` mail driver is `log` only — the app cannot currently send real emails. "Notifications" in this codebase means only eBay's inbound webhooks.
- **No other sales channels.** eBay only; Amazon/Shopify/Walmart/Etsy/WooCommerce — zero code.
- **No 2FA/TOTP.**
- **No multi-tenancy.** No `company_id`/`tenant_id`/`organization_id` anywhere — single-tenant, global data.
- **No low-stock or internal alerting system** for staff (order alerts, stock alerts) — worth flagging as a gap/roadmap item.

---

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | Laravel (PHP ^8.2) |
| Database | MySQL/MariaDB |
| Authentication | **Session-based** (`laravel/ui`, guard `web`) — self-registration disabled (`Auth::routes(['register' => false])`); users created only via admin Users module |
| API tokens | Laravel Sanctum ^4.0 — installed but only guards `GET /api/user`; not used to secure eBay/inventory-sync API routes |
| Authorization | Spatie Laravel Permission ^6.24 (roles + categorized permissions) |
| Frontend | Blade, Bootstrap 5, Tailwind CSS 4, SCSS (`sass`) |
| Build Tool | Vite ^7 |
| PDF Generation | barryvdh/laravel-dompdf ^3.1 |
| Excel/CSV | Maatwebsite Excel ^3.1 |
| Barcode | picqer/php-barcode-generator ^3.2 |
| Queue | Database driver — self-driving worker (scheduled every 5 min: `queue:work --stop-when-empty --max-time=50`), not a persistent daemon |
| Dev tooling | Laravel Pail (log viewer), Pint (formatting), Sail, PHPUnit ^11.5 |

**Auth detail worth noting:** email verification scaffolding exists (`VerificationController`) but is inactive (`MustVerifyEmail` commented out on `User`). Password reset and password re-confirmation are fully wired.

---

## Database Entities

**Core Inventory:** Product, ProductMeta, ProductStock, ProductBundleComponent, Category, Brand, Warehouse, Rack, ProductPriceComparison

**Sales & Orders:** Order, OrderItem, OrderMeta, OrderReturn, OrderReturnItem, SalesChannel, SalesChannelProduct (pivot, holds per-channel listing content), Shipping, InventorySyncLog, EbayFinanceTransaction, EbayImportLog

**Purchases:** Purchase, PurchaseItem, Supplier

**Accounting:** ChartOfAccount, Bill, BillItem, Payment, JournalEntry, JournalEntryLine

**Access Control:** User, Role (Spatie), Permission (custom, with `category`), DashboardSetting

*(Note: actual model classes are singular — e.g. `Order` not `Orders`, `ChartOfAccount` not `ChartOfAccounts`.)*

---

## Architecture

Service Layer Architecture: **Controllers → Services → Models → Database**

Key services:
- `BillService`, `PaymentService`, `JournalService`, `InventoryAccountingService`, `SalesChannelAccountService` (accounting)
- `EbayService`, `EbayOrderService`, `EbayApiClient`, `EbayBrowseClient`, `EbayFinanceSyncService`, `EbayFinancesApiClient`, `EbayNotificationService`, `EbayPostOrderApiClient`, `EbayXmlBuilder`, `PriceComparisonService` (eBay, under `app/Services/Ebay/`)
- `InventorySyncService`, `VisibleStockCalculator`, `SyncDecision`, `SyncResult` (inventory sync, under `app/Services/Inventory/`)
- `FedexService`, `ShippingService` (shipping)

**Controllers** (30 total) grouped: Auth (6), Users/RBAC (3), Catalog (4, incl. Market Research), Warehousing (2), Purchasing (2), Sales Channels (3), Orders/Shipping (3), Accounting (6), Dashboard/Home (2), base Controller.

**Jobs** (`app/Jobs/`, 6): `SyncEbayOrdersJob`, `ImportEbayListingsJob`, `SyncEbayFinancesJob`, `SyncInventoryToEbayJob`, `UpdateEbayOrderStatusJob`, `CompareProductPriceJob`

---

## Integration Workflows

**eBay Flow:**
```
OAuth2 Auth → Subscribe Notifications → Import Listings → Sync Inventory (buffered, overselling-protected)
     ↓
Receive Order Webhooks → Create Local Orders → Ship → Handle Returns (local OrderReturn workflow + eBay Return API)
     ↓
Sync Finance Transactions (fees/labels/ads, hourly) → Reconcile against Orders
```

**Market Research Flow:**
```
price-comparison:run (hourly, oldest-first) → PriceComparisonService → eBay Browse API
     ↓
ProductPriceComparison records → /market-research review table → Excel export
```

**Accounting Flow:**
```
Purchase (with duties/freight) → Receive Stock → Update Valuation (weighted avg)
     ↓
Customer Order → Ship (COGS entry) → Record Revenue → Reconcile
```

---

## Console Commands (15 total)

| Command | Purpose |
|---|---|
| `ebay:sync-orders` | Poll eBay for orders (scheduled every 15 min) |
| `ebay:sync-listings` | Import eBay listings as products |
| `ebay:refresh-tokens` | Refresh eBay OAuth tokens (every 30 min) |
| `ebay:sync-finances` | Sync eBay finance transactions (hourly) |
| `price-comparison:run` | Market Research price comparison (hourly, `--limit=50`) |
| `check:delivery-status` | Check shipment delivery status (every 2h) |
| `shipping:refresh-tokens` | Refresh FedEx tokens (every 30 min) |
| `queue:release-stale` | Release stuck queue jobs (every 5 min) |
| `SyncInventoryToEbay` | Push inventory changes to eBay |
| `SyncInventoryAccounting` | Reconcile inventory accounting entries |
| `SyncBankBalances` | Sync bank/payment balances |
| `RecalculateOrderTotals` | Data-repair: recompute order totals |
| `BackfillOrderItemCosts` | Data-repair: backfill historical cost data |
| `BackfillOrderItemProducts` | Data-repair: backfill order-item product links |
| `PopulateEbayUserIds` | Data-repair: populate eBay user IDs |
| `ConvertEbayLogsToJson` | Data-repair: migrate legacy eBay logs to JSON |

Order-status reconciliation job (`UpdateEbayOrderStatusJob`) runs twice daily (6am/6pm) to catch cancel/refund/return changes.

---

## API Surface (`routes/api.php`)

Not a general secured third-party REST API — almost entirely eBay webhooks + internal control endpoints:

- `GET /api/user` — only Sanctum-guarded route
- `GET|POST /api/ebay/webhook` — unified eBay notification receiver (routes by `RecipientUserID`); `GET` handles challenge verification
- `GET|POST /api/ebay/webhook/{id}` — legacy per-channel webhook
- `/api/ebay/*` — Returns, Cancellations, Refunds, INR/Inquiries, notification subscriptions, read-only order views
- `/api/orders/*` — local (non-eBay) order cancel/refund/partial-refund
- `/api/inventory-sync/*` — status/preview/sync/queue/force/syncChannel/logs/stats/settings

All routes except `/api/user` are **unauthenticated** (webhook/internal endpoints, not public API keys).

---

## Summary

Production-grade, single-tenant inventory system for e-commerce sellers on eBay, combining inventory management, order fulfillment (with FedEx label generation/rate-shopping, not just tracking), returns handling, competitor price research, and full double-entry accounting in one platform. Backbone is a granular, permission-gated RBAC system covering every module. Notable gaps for future roadmap: no outbound notifications/email, no Settings module, no multi-channel support beyond eBay, no 2FA, no multi-tenancy.
