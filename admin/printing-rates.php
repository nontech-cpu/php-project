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

$current = $db->query("SELECT * FROM printing_rates WHERE is_active = 1 ORDER BY id DESC LIMIT 1")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    try {
        $firstBlock = filter_var($_POST['first_block_rate'] ?? null, FILTER_VALIDATE_FLOAT);
        $nextBlock  = filter_var($_POST['next_block_rate'] ?? null, FILTER_VALIDATE_FLOAT);
        $threshold  = filter_var($_POST['threshold'] ?? null, FILTER_VALIDATE_INT);

        if ($firstBlock === false || $firstBlock === null || $firstBlock < 0
            || $nextBlock === false || $nextBlock === null || $nextBlock < 0
            || $threshold === false || $threshold === null || $threshold < 1) {
            throw new InvalidArgumentException('Please enter valid, non-negative numbers.');
        }

        $stmt = $db->prepare(
            "UPDATE printing_rates SET first_block_rate = ?, next_block_rate = ?, threshold = ? WHERE id = ?"
        );
        $stmt->execute([$firstBlock, $nextBlock, $threshold, $current['id']]);

        log_rate_change($db, 'printing_rates', $current['id'], 'first_block_rate', $current['first_block_rate'], $firstBlock, $_SESSION['admin_username']);
        log_rate_change($db, 'printing_rates', $current['id'], 'next_block_rate', $current['next_block_rate'], $nextBlock, $_SESSION['admin_username']);
        log_rate_change($db, 'printing_rates', $current['id'], 'threshold', $current['threshold'], $threshold, $_SESSION['admin_username']);

        $flash = 'Printing rates updated successfully.';
        $flashType = 'success';
        $current = $db->query("SELECT * FROM printing_rates WHERE is_active = 1 ORDER BY id DESC LIMIT 1")->fetch();
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$activePage = 'printing';
require __DIR__ . '/includes/header.php';
?>
<div class="admin-card">
    <h2>Printing Rates</h2>
    <?php if ($flash): ?>
        <div class="flash-<?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>
        <label>First Block Rate (₹) - flat charge up to threshold</label>
        <input type="number" step="0.01" min="0" name="first_block_rate" value="<?= htmlspecialchars($current['first_block_rate'], ENT_QUOTES, 'UTF-8') ?>" required>

        <label>Next Block Rate (₹) - per additional 1,000 sheets (rounded up)</label>
        <input type="number" step="0.01" min="0" name="next_block_rate" value="<?= htmlspecialchars($current['next_block_rate'], ENT_QUOTES, 'UTF-8') ?>" required>

        <label>Threshold (sheets)</label>
        <input type="number" step="1" min="1" name="threshold" value="<?= (int) $current['threshold'] ?>" required>

        <button type="submit" class="btn-primary">Save Printing Rates</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
