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
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'update_rate') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            $newRate = filter_var($_POST['rate_per_sheet'] ?? null, FILTER_VALIDATE_FLOAT);

            if ($id === false || $id === null || $newRate === false || $newRate === null || $newRate < 0) {
                throw new InvalidArgumentException('Please enter a valid non-negative rate.');
            }

            $old = $db->prepare("SELECT rate_per_sheet FROM paper_rates WHERE id = ?");
            $old->execute([$id]);
            $oldRate = $old->fetchColumn();

            $stmt = $db->prepare("UPDATE paper_rates SET rate_per_sheet = ? WHERE id = ?");
            $stmt->execute([$newRate, $id]);

            log_rate_change($db, 'paper_rates', $id, 'rate_per_sheet', $oldRate, $newRate, $_SESSION['admin_username']);
            $flash = 'Rate updated successfully.';
            $flashType = 'success';

        } elseif ($action === 'toggle_active') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
            if ($id === false || $id === null) {
                throw new InvalidArgumentException('Invalid record.');
            }
            $stmt = $db->prepare("UPDATE paper_rates SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$id]);
            $flash = 'Status updated.';
            $flashType = 'success';

        } elseif ($action === 'add_paper') {
            $size = trim($_POST['paper_size'] ?? '');
            $quality = trim($_POST['paper_quality'] ?? '');
            $gsm = filter_var($_POST['gsm'] ?? null, FILTER_VALIDATE_INT);
            $rate = filter_var($_POST['rate_per_sheet'] ?? null, FILTER_VALIDATE_FLOAT);

            if ($size === '' || $quality === '' || !$gsm || $rate === false || $rate === null || $rate < 0) {
                throw new InvalidArgumentException('All fields are required and rate/GSM must be valid numbers.');
            }

            $stmt = $db->prepare(
                "INSERT INTO paper_rates (paper_size, paper_quality, gsm, rate_per_sheet) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$size, $quality, $gsm, $rate]);
            $flash = 'New paper option added.';
            $flashType = 'success';
        }
    } catch (Throwable $e) {
        $flash = $e->getMessage();
        $flashType = 'error';
    }
}

$papers = $db->query("SELECT * FROM paper_rates ORDER BY is_active DESC, paper_size, paper_quality")->fetchAll();

$activePage = 'paper';
require __DIR__ . '/includes/header.php';
?>
<div class="admin-card">
    <h2>Paper Rates</h2>
    <?php if ($flash): ?>
        <div class="flash-<?= $flashType ?>"><?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <table class="admin-table">
        <thead>
            <tr><th>Size</th><th>Quality</th><th>GSM</th><th>Rate / Sheet</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($papers as $p): ?>
            <tr>
                <td><?= htmlspecialchars($p['paper_size'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($p['paper_quality'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= (int) $p['gsm'] ?></td>
                <td>
                    <form method="post" style="display:flex;gap:6px;align-items:center;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_rate">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        ₹<input type="number" step="0.01" min="0" name="rate_per_sheet" value="<?= htmlspecialchars($p['rate_per_sheet'], ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn-primary" style="width:auto;padding:6px 12px;margin:0;font-size:0.8rem;">Save</button>
                    </form>
                </td>
                <td><?= $p['is_active'] ? 'Active' : 'Inactive' ?></td>
                <td>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_active">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <button type="submit" class="btn-primary" style="width:auto;padding:6px 12px;margin:0;font-size:0.8rem;background:#616e7c;">
                            <?= $p['is_active'] ? 'Deactivate' : 'Activate' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="admin-card">
    <h2>Add New Paper Option</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_paper">
        <label>Size (e.g. 20x30)</label>
        <input type="text" name="paper_size" required>
        <label>Quality (e.g. Normal / Fasson)</label>
        <input type="text" name="paper_quality" required>
        <label>GSM</label>
        <input type="number" name="gsm" value="80" required>
        <label>Rate per sheet (₹)</label>
        <input type="number" step="0.01" min="0" name="rate_per_sheet" required>
        <button type="submit" class="btn-primary">Add Paper Option</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
