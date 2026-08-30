<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gumming Sheet Price Calculator</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="page">
    <header class="page-header">
        <h1>Gumming Sheet Price Calculator</h1>
        <p>Get an instant price for your gumming sheet order.</p>
    </header>

    <div class="calc-layout">
        <form id="calc-form" class="card">
            <h2>Job Information</h2>

            <label for="job_name">Job Name <span class="req">*</span></label>
            <input type="text" id="job_name" name="job_name" required placeholder="e.g. Diwali Sticker Batch">

            <h2>Paper</h2>

            <label for="paper_id">Paper Quality &amp; Size <span class="req">*</span></label>
            <select id="paper_id" name="paper_id" required>
                <option value="">Loading options…</option>
            </select>

            <label for="quantity">Quantity (sheets) <span class="req">*</span></label>
            <input type="number" id="quantity" name="quantity" min="100" step="1" required placeholder="Minimum 100">
            <small id="qty-error" class="error-text"></small>

            <label for="printing_type">Printing</label>
            <select id="printing_type" name="printing_type">
                <option value="single_side">Single Side</option>
            </select>

            <label for="lamination_type">Lamination</label>
            <select id="lamination_type" name="lamination_type">
                <option value="none">No Lamination</option>
                <option value="single_glossy">Single Side Glossy</option>
                <option value="single_matte">Single Side Matte</option>
            </select>

            <button type="submit" id="calc-btn" class="btn-primary">Calculate Price</button>
            <div id="form-error" class="error-text"></div>
        </form>

        <div class="card" id="breakdown-card">
            <h2>Price Breakdown</h2>
            <div id="breakdown-empty" class="empty-state">
                Fill the form and click <strong>Calculate Price</strong> to see your quote.
            </div>
            <table id="breakdown-table" class="breakdown-table hidden">
                <tbody>
                    <tr><td>Paper Cost</td><td id="b-paper">-</td></tr>
                    <tr><td>Printing Cost</td><td id="b-printing">-</td></tr>
                    <tr><td>Lamination Cost</td><td id="b-lamination">-</td></tr>
                    <tr class="subtotal"><td>Base Cost</td><td id="b-base">-</td></tr>
                    <tr><td id="b-margin-label">Margin</td><td id="b-margin">-</td></tr>
                    <tr class="subtotal"><td>Subtotal</td><td id="b-subtotal">-</td></tr>
                    <tr><td id="b-gst-label">GST</td><td id="b-gst">-</td></tr>
                    <tr><td>Delivery</td><td id="b-delivery">-</td></tr>
                    <tr class="subtotal"><td>Total</td><td id="b-total">-</td></tr>
                    <tr class="payable"><td>Amount Payable</td><td id="b-payable">-</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="../assets/js/calculator.js"></script>
</body>
</html>
