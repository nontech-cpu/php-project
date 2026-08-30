<?php
/**
 * GET /api/rates.php
 *
 * Returns the currently active paper options so the calculator page
 * never has pricing/options baked into its HTML.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDbConnection();

    $stmt = $db->query(
        "SELECT id, paper_size, paper_quality, gsm, rate_per_sheet
         FROM paper_rates
         WHERE is_active = 1
         ORDER BY paper_size, paper_quality"
    );
    $papers = $stmt->fetchAll();

    $settingsStmt = $db->query("SELECT setting_key, setting_value FROM calculator_settings");
    $settings = [];
    foreach ($settingsStmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = (float) $row['setting_value'];
    }

    echo json_encode([
        'papers' => $papers,
        'lamination_options' => [
            ['value' => 'none', 'label' => 'No Lamination'],
            ['value' => 'single_glossy', 'label' => 'Single Side Glossy'],
            ['value' => 'single_matte', 'label' => 'Single Side Matte'],
        ],
        'printing_options' => [
            ['value' => 'single_side', 'label' => 'Single Side'],
        ],
        'minimum_quantity' => (int) ($settings['minimum_quantity'] ?? 100),
    ]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load rates.']);
}
