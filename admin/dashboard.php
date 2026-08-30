<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

auth_start_session();
auth_require_login();

$db = getDbConnection();
$paperCount = $db->query("SELECT COUNT(*) c FROM paper_rates WHERE is_active = 1")->fetch()['c'];
$orderCount = $db->query("SELECT COUNT(*) c FROM orders")->fetch()['c'];
$recentOrders = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 5")->fetchAll();

$activePage = 'dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="admin-card">
    <h2>Overview</h2>
    <p><?= (int) $paperCount ?> active paper rate(s) · <?= (int) $orderCount ?> saved order(s)</p>
    <p style="color:#616e7c;font-size:0.85rem;">
        Use the navigation above to edit any pricing value. Changes take effect
        immediately - the calculator always reads live from the database.
    </p>
</div>

<div class="admin-card">
    <h2>Recent Orders</h2>
    <?php if (!$recentOrders): ?>
        <p style="color:#7b8794;">No orders saved yet.</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr><th>Job</th><th>Qty</th><th>Printing</th><th>Lamination</th><th>Amount Payable</th><th>Date</th></tr>
            </thead>
            <tbody>
            <?php foreach ($recentOrders as $o): ?>
                <tr>
                    <td><?= htmlspecialchars($o['job_name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) $o['quantity'] ?></td>
                    <td><?= htmlspecialchars($o['printing_type'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($o['lamination_type'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td>₹<?= number_format($o['amount_payable'], 2) ?></td>
                    <td><?= htmlspecialchars($o['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
