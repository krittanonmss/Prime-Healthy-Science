# Prime Healthy Science

A local sales and inventory application built with Laravel 13, PHP 8.3, SQLite, Blade, CSS, and vanilla JavaScript. Designed for a single computer, with no login or user roles. No frontend build is required.

## Features

- **Overview:** sales, cumulative payments, outstanding balances, fulfillment counts, six latest non-cancelled orders, and low-stock alerts.
- **Sales orders:** customer and product search, date/category/fulfillment/payment filters, negotiated prices with reasons, percentage discounts, and gifts identified by SKU and unit.
- **Inventory:** on-hand, reserved, and available quantities; opening balances, receiving, counted adjustments, low-stock thresholds, and movement history. Stock entry has its own page.
- **Products & pricing:** searchable catalog, separate product creation and price-editing pages, unique SKUs, and retail/hospital/pharmacy prices.
- **Customers:** searchable directory with separate creation and editing pages. Each order saves customer details and prices independently of subsequent catalog changes.
- **Sales reports:** filtered totals across all matching orders, category comparisons, balances, and a separate Excel export page. Optional order selections narrow the download.
- **Documents:** printable A4 receipt, tax invoice, and goods receipt with signature lines. Save PDF through the browser print dialog.
- **Navigation:** expandable Products menu with Stock before Products & pricing; collapsible list filters; searchable product and customer selectors.
- **Pagination:** list updates without a full page reload across sales orders, products, customers, reports, and both inventory lists. URLs, Back/Forward, loading states, and retry behavior are supported.

## Requirements

- PHP 8.3+ and Composer.
- PHP extensions required by Laravel, plus PDO SQLite, mbstring, XML/DOM, and ZIP for Excel export.
- Node.js 20+ to run the JavaScript tests. Node is not required to run the application.

## Installation

```bash
git clone https://github.com/krittanonmss/Prime-Healthy-Science.git
cd Prime-Healthy-Science
composer install
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Open **http://127.0.0.1:8000**. The application uses THB and the Asia/Bangkok timezone. Keep `storage/` and `bootstrap/cache/` writable by PHP.

For an existing installation, run `composer install` and `php artisan migrate`; migrations preserve existing data.

### Optional demo catalog

On a fresh, empty installation, run:

```bash
php artisan db:seed
```

The default seeder creates seven workbook-derived products, three fictional customers, and four legacy demonstration orders. Inventory starts uninitialized; set opening balances in Stock before creating stock-managed orders. Do not run this seeder over existing data: it is intended for an empty database.

The additional demonstration records entered in the development workspace are local database data and are not included in the repository. The original source workbook is not needed to run the application.

## First-use workflow

1. Open **Products → Products & pricing → Add product** to create a SKU, name, unit, and prices. Retail price is required; hospital and pharmacy prices are optional and, if set, must differ from retail.
2. Open **Products → Stock → Update stock** and enter physically counted opening quantities and low-stock thresholds.
3. Add customers with their default pricing category.
4. Create a sales order. Different gift SKUs use separate lines with sold quantity zero and a free quantity.
5. Record payments and move the order through packing, dispatch, and delivery.
6. Review Sales reports or open Export report to download the matching orders.

## Inventory rules

| Event | Inventory effect |
| --- | --- |
| Set opening balance | Initializes a product's counted quantity once |
| Receive | Adds the entered quantity |
| Adjust | Sets the final counted balance; cannot fall below reserved stock |
| Confirm a new order | Reserves sold quantities and gifts; rejects insufficient available stock |
| Pack | Keeps the reservation |
| Dispatch | Releases the reservation and deducts on-hand stock once |
| Deliver | No further stock deduction |
| Cancel before dispatch | Releases the reservation; paid orders cannot be cancelled until a refund workflow exists |

**Available = on hand − reserved.** A product is low in stock when available quantity is at or below its threshold. Uninitialized products are identified separately. Document dates are references; stock balances update immediately.

Box and sachet SKUs are counted separately. There is no implicit unpacking or conversion between units. Legacy orders are unmanaged and never deduct inventory retrospectively. Pending, unpaid legacy orders requiring stock control must be cancelled and recreated with correct gift SKUs.

## Reports and documents

- Date filters use the order's sale date, including both endpoints. Received amounts are cumulative payments for the matching orders, not a payment-date ledger.
- Report totals include all matching orders across every page. Cancelled orders are excluded from sales totals and default downloads; an explicit cancelled-status filter can export cancelled documents.
- Excel exports use sheet `sales Record` and the original 18 columns. Each order item is a separate row. Box/sachet gifts use the FOC columns; gifts in other units are annotated in the product description.
- Text cells are written as strings. Discounts, balances, reports, and documents use the saved rounded line amount.
- VAT is a fixed demonstration calculation of 7% inclusive. Printed documents are presentation samples, not official tax documents.

## Verification

```bash
vendor/bin/phpunit
node tests/Frontend/list-navigation.test.cjs
php artisan view:cache
```

Current checks: **31 PHP feature tests / 457 assertions** and **11 JavaScript navigation tests**. Coverage includes pricing snapshots, discounts, customer updates, stock reservations and movements, cancellations, filtered report totals, Excel scope, and all six paginated lists. JavaScript tests cover partial updates, query preservation, stale-request cancellation, retry, and Back/Forward using DOM and response doubles.

PHP tests use a separate test database. JavaScript tests use Node's built-in test runner with no additional dependencies. Direct browser verification was unavailable in the development environment.

## Current boundaries

No authentication, roles, multi-warehouse inventory, lots, expiry tracking, costing, unit conversion, payment ledger, refunds, post-dispatch returns, or shipping integration. Payment records store the cumulative amount received.

Environment settings, SQLite databases, customer records, runtime caches, sessions, logs, Composer dependencies, and the original source workbook are excluded from Git. Install dependencies and configure `.env` locally.
