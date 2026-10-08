# Sales and fulfillment application design

## Purpose
Replace repeated entry across the Excel sales record, summaries, receipts, and delivery sheets with one order record. Support Thai text, THB, desktop and mobile screens, and separate retail and wholesale prices.

## Main screens
1. Dashboard: sales this month, unpaid orders, orders waiting for dispatch, and retail/wholesale breakdown.
2. Orders: searchable list with customer, salesperson, channel, payment status, fulfillment status, and total. Create or duplicate an order.
3. Customers: name, phone, billing and delivery addresses, tax ID, default retail/wholesale channel, and optional wholesale price list.
4. Products and pricing: SKU, name, selling unit, retail price, wholesale price lists, active status, and price history.
5. Fulfillment: confirmed orders, packing list, paid and free quantities, dispatch details, and delivery confirmation.
6. Payments and documents: payments, outstanding balances, printable receipts, tax invoices, and delivery notes.
7. Reports: sales by period, product, salesperson, and channel; paid quantities and free quantities; Excel export.
8. Settings: company details, document numbering, users and permissions, tax settings, and price-list configuration.

## Order screen
Header: customer, salesperson, order date, retail/wholesale channel, price list, billing address, and delivery address.
Lines: SKU, product, unit, quantity, free quantity, unit price, discount, and line total.
Footer: subtotal, discounts, tax-exclusive amount, VAT, grand total, amount paid, and outstanding balance.
Actions: save draft, confirm order, record payment, print documents, and open packing list.
Show the selected channel and price source beside every line. Recalculate draft lines when the customer/channel changes, with a visible preview before saving.

## Retail and wholesale pricing
Store retail and wholesale prices separately; wholesale is not a discount calculated from retail. Reject an active product price configuration where the two prices are equal. Require a valid price for the selected channel before an order can be confirmed.

Support wholesale price lists because the workbook already distinguishes Hosp, OTC/Doz, and OTC/Pcs. Each product may have a price in each list. Customers can have a default wholesale list; retail customers use the retail list.

Initial mapping proposal, requiring business verification before live import:
- Price -> retail
- Hosp -> wholesale hospital
- OTC/Doz -> wholesale OTC dozen tier
- OTC/Pcs -> wholesale OTC piece tier

The OTC/Doz label does not establish whether its amounts are per piece or per dozen. Confirm the selling unit and any minimum quantity before using this list. Do not silently multiply or divide these values.

Examples from the Receipt sheet:
| SKU | Product | Retail / Price | Hosp | OTC/Doz | OTC/Pcs |
| --- | --- | ---: | ---: | ---: | ---: |
| Pro001 | Profine berry box | 1125 | 570 | 570 | 570 |
| Pro002 | Profine berry sachet | 120 | 90 | 90 | 90 |
| Inno01 | Innoage foam 50 ml | 180 | 116 | 130.15 | 137 |
| Inno02 | Innoage toothpaste 120 g | 180 | 116 | 130.15 | 137 |
| Inno03 | Innoage mouthwash 500 ml | 220 | 155 | 161.5 | 170 |
| Inno04 | Innoage suction toothbrush | 100 | 54 | 71.25 | 75 |
| Inno05 | Innoage Slimmax toothbrush | 75 | missing | missing | missing |

Keep missing prices unset. Historical transaction prices do not establish a customer's pricing channel and must remain unchanged during import.

Copy the selected price and its source onto each order line. Later catalog edits must not change confirmed orders or issued documents. Record manager overrides with the original price, replacement price, reason, actor, and timestamp. Preserve historical orders even if their prices fail today's pricing rules.

## Workflow
Customer selected -> draft order -> pricing and address validation -> confirmed order -> packing -> dispatched -> delivered.
Payment status is independent: unpaid, partially paid, paid, or refunded. This supports wholesale orders shipped on credit. Store due dates for credit orders.
Generate documents from the saved order data without retyping customer details or totals. Issue document numbers atomically. Keep issued documents immutable and link corrections or cancellations to the original.
Free boxes and sachets are separate order lines or quantities with zero selling price. Include them in packing totals. Promotion rules can propose free items for staff review.

## Calculations
Use decimal currency arithmetic. Make tax rate and tax-inclusive/exclusive price mode configurable. The workbook contains a 7% VAT calculation; this is an import observation, not a verification of current tax rules.
For tax-inclusive prices: gross = paid quantity × unit price − applicable discount; net = gross / (1 + tax rate); tax = gross − net. Define one consistent rounding policy for lines and documents before production.

## Data model
- Product: ID, SKU, name, unit, active flag.
- PriceList: ID, name, retail/wholesale channel, currency, tax mode.
- ProductPrice: product, price list, decimal amount, effective period.
- Customer: contact details, tax ID, default channel and price list.
- Address: customer, billing/delivery type, address fields.
- Salesperson: code, name, contact, active flag.
- Order: number, customer, salesperson, channel, price list, address snapshots, fulfillment status, totals, due date.
- OrderLine: product, name/unit snapshots, paid/free quantities, price snapshot, discount, tax, total, price source.
- Payment: order, amount, date, method, reference.
- Shipment: order, carrier, tracking reference, dispatched/delivered timestamps.
- Document: order, type, unique number, issue date, immutable content snapshot, cancellation/correction reference.
- AuditEvent: actor, action, record, before/after values, timestamp.

## Permissions
Admin: user management, configuration, pricing, and corrections.
Sales: customers and orders; price override requests.
Finance: payments, balances, and document issuance.
Warehouse: packing, dispatch, and delivery.
Restrict access to salesperson identity and bank account data to authorized users; they are not needed on normal order screens.

## Excel migration
Import product definitions from Receipt, salespeople from Salesman, and historical transactions from sales Record. Preserve codes, phone numbers, and document numbers as text. Preview and flag inconsistent products, missing addresses, duplicate document numbers, and missing prices. Group historical rows into orders only after validating shared document/customer information. Reconcile imported amounts with workbook summaries. Keep the original workbook as the source archive.

## Delivery phases
Phase 1: products with distinct prices, customers, salespeople, orders, payments, printable receipts/delivery notes, and reports.
Phase 2: packing/dispatch tracking, credit due dates, promotions, price approval history, and specialized hospital documents.
Phase 3: inventory by location, lots and expiry dates, purchasing, and integrations if needed.

## Acceptance examples
- Selecting retail for Inno01 loads 180; selecting hospital wholesale loads 116.
- Inno05 cannot be confirmed on wholesale until that price is configured.
- Changing a product price leaves a confirmed order's saved price unchanged.
- Free items appear on the packing list and contribute zero to the selling total.
- A partly paid wholesale order retains its outstanding balance after dispatch.
- Reports filter retail and wholesale independently and reconcile with order totals.

## Implementation boundary
This document is the application design. No application, database, deployment, or verified tax configuration has been implemented yet.
