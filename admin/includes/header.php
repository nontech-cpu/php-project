<?php
// Expects $activePage to be set by the including file for nav highlighting.
$activePage = $activePage ?? '';
function navClass(string $page, string $active): string {
    return $page === $active ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Gumming Sheet Calculator</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<nav class="admin-nav">
    <div>
        <a href="dashboard.php" class="<?= navClass('dashboard', $activePage) ?>">Dashboard</a>
        <a href="paper-rates.php" class="<?= navClass('paper', $activePage) ?>">Paper Rates</a>
        <a href="printing-rates.php" class="<?= navClass('printing', $activePage) ?>">Printing Rates</a>
        <a href="lamination-rates.php" class="<?= navClass('lamination', $activePage) ?>">Lamination Rates</a>
        <a href="settings.php" class="<?= navClass('settings', $activePage) ?>">Settings</a>
    </div>
    <div>
        <span style="color:#9aa5b1;font-size:0.85rem;margin-right:12px;"><?= htmlspecialchars($_SESSION['admin_username'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        <a href="logout.php">Log Out</a>
    </div>
</nav>
<div class="admin-wrap">
