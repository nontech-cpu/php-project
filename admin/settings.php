<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/history.php';
require_once __DIR__ . '/../config/database.php';

auth_start_session();
auth_require_login();
$db = getDbConnection();

$flash = '';
$flashType = '';

$fields = [
    'margin_percent'        => 'Margin (%)',
    'gst_percent'           => 'GST (%)',
    'delivery_rate_per_kg'  => 'Delivery Rate (₹/kg)',
    'delivery_fixed_amount' => 'Minimum Delivery Charge (₹)',
    'minimum_quantity'      => 'Minimum Quantity (sheets)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    try {
        $updateStmt = $db->prepare("UPDATE calculator_settings SET setting_value = ? WHERE setting_key = ?");
        $selectStmt = $db->prepare("SELECT id, setting_value FROM calculator_settings WHERE setting_key = ?");

        foreach ($fields as $key => $label) {
            $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_FLOAT);
            if ($value === false || $value === null || $value < 0) {
                throw new InvalidArgumentException("Please enter a valid non-negative number for {$label}.");
            }

            $selectStmt->execute([$key]);
            $existing = $selectStmt->fetch();

            $updateStmt->execute([$value, $key]);

            if ($existing) {
                log_rate_change($db, 'calculator_settings', (int) $existing['id'], $key, $existing['setting_value'], $value, $_SESSION['admin_username']);
            }
        }

        $flash = 'Settings updated successfully.';
        $flashType = 'success';
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$settingsRows = $db->query("SELECT setting_key, setting_value FROM calculator_settings")->fetchAll();
$settings = [];
foreach ($settingsRows as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$activePage = 'settings';
require __DIR__ . '/includes/header.php';
?>
<div class="admin-card">
    <h2>General Settings</h2>
    <?php if ($flash): ?>
        <div class="flash-<?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <?php foreach ($fields as $key => $label): ?>
            <label for="<?= $key ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></label>
            <input type="number" step="0.01" min="0" id="<?= $key ?>" name="<?= $key ?>"
                   value="<?= htmlspecialchars($settings[$key] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
        <?php endforeach; ?>
        <button type="submit" class="btn-primary">Save Settings</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
