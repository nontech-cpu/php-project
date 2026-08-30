<?php
/**
 * POST /api/calculate.php
 *
 * Accepts:
 * {
 *   "job_name": "My Job",
 *   "paper_id": 1,
 *   "quantity": 1500,
 *   "printing_type": "single_side",
 *   "lamination_type": "single_glossy",
 *   "discount": 0,
 *   "save": false
 * }
 *
 * Always recalculates the price server-side from the database.
 * Nothing about price is ever trusted from the request body -
 * only the *selections* (paper_id, quantity, types) are read.
 * If "save" is true, the recalculated order is stored in `orders`.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Calculator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    // also accept normal form-encoded POST
    $input = $_POST;
}

$jobName        = trim((string) ($input['job_name'] ?? ''));
$paperId        = filter_var($input['paper_id'] ?? null, FILTER_VALIDATE_INT);
$quantity       = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);
$printingType   = (string) ($input['printing_type'] ?? 'single_side');
$laminationType = (string) ($input['lamination_type'] ?? 'none');
$discount       = filter_var($input['discount'] ?? 0, FILTER_VALIDATE_FLOAT) ?: 0.0;
$save           = filter_var($input['save'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($paperId === false || $paperId === null || $quantity === false || $quantity === null) {
    http_response_code(422);
    echo json_encode(['error' => 'paper_id and quantity must be valid numbers.']);
    exit;
}

try {
    $db = getDbConnection();
    $calculator = new Calculator($db);

    $result = $calculator->calculate($paperId, $quantity, $printingType, $laminationType, $discount);

    if ($save) {
        if ($jobName === '') {
            http_response_code(422);
            echo json_encode(['error' => 'job_name is required to save an order.']);
            exit;
        }

        $b = $result['breakdown'];
        $stmt = $db->prepare(
            "INSERT INTO orders
             (job_name, paper_id, quantity, printing_type, lamination_type,
              paper_cost, printing_cost, lamination_cost, base_cost,
              margin_amount, gst_amount, delivery_charge,
              total_before_discount, discount, amount_payable)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $jobName, $paperId, $quantity, $printingType, $laminationType,
            $b['paper_cost'], $b['printing_cost'], $b['lamination_cost'], $b['base_cost'],
            $b['margin_amount'], $b['gst_amount'], $b['delivery_charge'],
            $b['total_before_discount'], $b['discount'], $b['amount_payable'],
        ]);
        $result['order_id'] = (int) $db->lastInsertId();
    }

    echo json_encode($result);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error while calculating price.']);
}
