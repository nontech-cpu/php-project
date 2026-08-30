# Gumming Sheet Price Calculator

A PHP + MySQL price calculator for gumming sheet printing jobs, with a
customer-facing calculator and an admin panel for managing every rate.
**No pricing value is hardcoded anywhere in the code** — everything
comes from MySQL, and changing a rate in the admin panel updates the
calculator immediately with zero code changes.

## Tech stack
PHP (PDO, prepared statements) · MySQL · HTML/CSS · vanilla JavaScript (fetch/AJAX)

## Folder structure
```
/config       -> database.php (PDO connection)
/includes     -> Calculator.php (all pricing logic), auth.php, csrf.php, history.php
/admin        -> login, dashboard, and rate-management pages
/api          -> rates.php (GET options), calculate.php (POST -> authoritative price)
/calculator   -> index.php (customer-facing form)
/assets       -> css/js
database.sql  -> schema + seed data
```

## Setup (local / XAMPP / any LAMP stack)

1. Create the database:
   ```
   mysql -u root -p < database.sql
   ```
2. Copy `.env.example` to `.env` (or just set these as real environment
   variables / edit `config/database.php` defaults) with your DB credentials:
   ```
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=gumming_calculator
   DB_USER=root
   DB_PASS=your_password
   ```
3. Point your web server (Apache/XAMPP, or `php -S localhost:8000`) at the
   project root.
4. Visit:
   - Customer calculator: `/calculator/index.php`
   - Admin panel: `/admin/login.php`

**Default admin login:** username `admin`, password `admin123`
(change this immediately — it's only here so the reviewer can log in).

## How pricing works (all values live in MySQL)

| Step | Formula |
|---|---|
| Paper Cost | quantity × rate_per_sheet |
| Printing Cost | ≤ threshold → flat first_block_rate. Above it: first_block_rate + CEIL((qty − threshold) / 1000) × next_block_rate |
| Lamination Cost | none → 0. Otherwise (base_cost ÷ divisor) × quantity |
| Base Cost | Paper + Printing + Lamination |
| Margin | Base Cost × margin_percent |
| GST | (Base Cost + Margin) × gst_percent |
| Delivery | MAX(fixed_charge, weight_kg × rate_per_kg), weight from area × GSM |
| Amount Payable | CEIL(Base + Margin + GST + Delivery − Discount) |

All of this logic lives in **one place**: `includes/Calculator.php`. Both
the AJAX preview and the final order submission call the exact same
class, so the number the customer sees and the number that gets
recorded are guaranteed to match — and neither trusts anything the
browser sends except the customer's *selections* (paper id, quantity,
printing/lamination type).

## Verified against the spec's worked examples

| Quantity | Expected Printing Cost | Result |
|---|---|---|
| 1,000 | ₹1,540 | ✅ |
| 1,500 | ₹2,020 | ✅ |
| 2,050 | ₹2,020 | ✅ |
| 2,051 | ₹2,500 | ✅ (this is the CEIL-vs-round trap test) |

Run the 6 required test cases from the assessment by entering them into
the calculator, and Test 6 (changing 20×30 Normal from ₹8.50 → ₹10.00 in
the admin panel) by editing the rate on the **Paper Rates** admin page
and re-running a 1,000-sheet order — no code changes needed.

## Security measures implemented
- PDO with prepared statements everywhere (no raw SQL concatenation)
- Session-based admin auth with `password_hash()` / `password_verify()`
- CSRF token on every admin POST form
- Server-side validation of all numeric input (rates, quantity, GSM)
- Final price is **always recalculated server-side** in `api/calculate.php`
  — client-side JS totals are for instant UI feedback only and are never
  trusted or stored
- `htmlspecialchars()` on all database values echoed into HTML
- No credentials committed — `config/database.php` reads from environment
  variables, `.env` is gitignored

## Bonus features included
- Rate history audit log (`rate_history` table + `log_rate_change()`) —
  tracks old value, new value, who changed it, and when, for every rate
  edit in the admin panel
- Add new paper size/quality and activate/deactivate any paper rate,
  from the admin panel
- `POST /api/calculate.php` JSON API matching the spec's example payload
- Responsive layout (single column on mobile/tablet)
- Rates fetched via AJAX (`api/rates.php`) — no pricing config embedded in HTML

## Notes / assumptions
- Paper size is stored as a plain string like `20x30` so weight and
  lamination lookups can share it consistently across tables.
- `discount` is supported in the calculation pipeline (defaults to 0) —
  there's no discount UI in the customer form since the spec didn't
  request one, but the API and Calculator class both accept it.
- Orders are optionally persisted to an `orders` table when the API is
  called with `"save": true`, which the admin dashboard lists.
