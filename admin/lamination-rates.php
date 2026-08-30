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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    try {
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
        $baseCost = filter_var($_POST['base_cost'] ?? null, FILTER_VALIDATE_FLOAT);
        $glossy = filter_var($_POST['glossy_divisor'] ?? null, FILTER_VALIDATE_FLOAT);
        $matte  = filter_var($_POST['matte_divisor'] ?? null, FILTER_VALIDATE_FLOAT);

        if ($id === false || $id === null
            || $baseCost === false || $baseCost === null || $baseCost < 0
            || $glossy === false || $glossy === null || $glossy <= 0
            || $matte === false || $matte === null || $matte <= 0) {
            throw new InvalidArgumentException('Please enter valid numbers (divisors must be greater than 0).');
        }

        $old = $db->prepare("SELECT * FROM lamination_rates WHERE id = ?");
        $old->execute([$id]);
        $oldRow = $old->fetch();

        $stmt = $db->prepare(
            "UPDATE lamination_rates SET base_cost = ?, glossy_divisor = ?, matte_divisor = ? WHERE id = ?"
        );
        $stmt->execute([$baseCost, $glossy, $matte, $id]);

        log_rate_change($db, 'lamination_rates', $id, 'base_cost', $oldRow['base_cost'], $baseCost, $_SESSION['admin_username']);
        log_rate_change($db, 'lamination_rates', $id, 'glossy_divisor', $oldRow['glossy_divisor'], $glossy, $_SESSION['admin_username']);
        log_rate_change($db, 'lamination_rates', $id, 'matte_divisor', $oldRow['matte_divisor'], $matte, $_SESSION['admin_username']);

        $flash = 'Lamination rates updated.';
        $flashType = 'success';
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$rows = $db->query("SELECT * FROM lamination_rates ORDER BY paper_size")->fetchAll();

$activePage = 'lamination';
require __DIR__ . '/includes/header.php';
?>
<div class="admin-card">
    <h2>Lamination Rates</h2>
    <?php if ($flash): ?>
        <div class="flash-<?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php foreach ($rows as $r): ?>
    <form method="post" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;border-bottom:1px solid #eef0f2;padding-bottom:14px;margin-bottom:14px;">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
        <div style="min-width:70px;"><strong><?= htmlspecialchars($r['paper_size'], ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div>
            <label>Base Cost (₹)</label>
            <input type="number" step="0.01" min="0" name="base_cost" value="<?= htmlspecialchars($r['base_cost'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label>Glossy Divisor</label>
            <input type="number" step="0.01" min="0.01" name="glossy_divisor" value="<?= htmlspecialchars($r['glossy_divisor'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div>
            <label>Matte Divisor</label>
            <input type="number" step="0.01" min="0.01" name="matte_divisor" value="<?= htmlspecialchars($r['matte_divisor'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <button type="submit" class="btn-primary" style="width:auto;padding:9px 16px;">Save</button>
    </form>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
