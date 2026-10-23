# Gumming Sheet Price Calculator

PHP + MySQL based project — customer-facing calculator aur admin panel ke saath.

## Key Highlights

- **Tech stack:** PHP (PDO), MySQL, HTML/CSS, vanilla JS
- **No hardcoded pricing** — sab rates MySQL se aate hain. Admin panel se rates change karo aur calculator turant update ho jata hai.
- **Pricing logic:** Paper cost, Printing (block-based), Lamination, Margin, GST, Delivery — sab `includes/Calculator.php` mein ek jagah.
- **Verified test cases:** 1000, 1500, 2050, 2051 quantities ke printing costs match karte hain.

## Default Admin Login

- **Username:** `admin`
- **Password:** `admin123`