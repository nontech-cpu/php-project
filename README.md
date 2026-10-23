# Gumming Sheet Price Calculator

A PHP + MySQL based Gumming Sheet Price Calculator with a customer-facing calculator and an admin panel.

## Key Highlights

- **Tech stack:** PHP (PDO), MySQL, HTML/CSS, vanilla JS
- **No hardcoded pricing** — all rates come from MySQL. Change them in the admin panel and the calculator updates instantly.
- **Pricing logic:** Paper cost, Printing (block-based), Lamination, Margin, GST, and Delivery — all handled in one place in `includes/Calculator.php`.
- **Verified test cases:** Printing costs for quantities 1000, 1500, 2050, and 2051 all match expected values.
- **Default admin login:** `admin` / `admin123`