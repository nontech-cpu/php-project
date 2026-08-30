<?php
/**
 * Calculator
 *
 * All pricing business logic lives here, and nowhere else.
 * Every number used is fetched from the database - there are
 * no hardcoded rates in this file.
 */
class Calculator
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Fetch a single active paper rate row by id. */
    public function getPaperRate(int $paperId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM paper_rates WHERE id = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$paperId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Fetch the active printing rate configuration. */
    public function getPrintingRate(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM printing_rates WHERE is_active = 1 ORDER BY id DESC LIMIT 1"
        );
        return $stmt->fetch();
    }

    /** Fetch the lamination rate row for a given paper size. */
    public function getLaminationRate(string $paperSize): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM lamination_rates WHERE paper_size = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$paperSize]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Fetch all calculator_settings as an associative array. */
    public function getSettings(): array
    {
        $stmt = $this->db->query("SELECT setting_key, setting_value FROM calculator_settings");
        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[$row['setting_key']] = (float) $row['setting_value'];
        }
        return $settings;
    }

    /** Paper Cost = quantity x rate per sheet */
    public function calculatePaperCost(float $ratePerSheet, int $quantity): float
    {
        return $ratePerSheet * $quantity;
    }

    /**
     * Printing cost.
     * <= threshold  -> flat first_block_rate
     * >  threshold  -> first_block_rate + CEIL((qty - threshold) / 1000) * next_block_rate
     */
    public function calculatePrintingCost(int $quantity, array $printingRate): float
    {
        $threshold  = (int) $printingRate['threshold'];
        $firstBlock = (float) $printingRate['first_block_rate'];
        $nextBlock  = (float) $printingRate['next_block_rate'];

        if ($quantity <= $threshold) {
            return $firstBlock;
        }

        $remaining = $quantity - $threshold;
        $additionalBlocks = (int) ceil($remaining / 1000);

        return $firstBlock + ($additionalBlocks * $nextBlock);
    }

    /**
     * Lamination cost.
     * none   -> 0
     * glossy -> (base_cost / glossy_divisor) * quantity
     * matte  -> (base_cost / matte_divisor)  * quantity
     */
    public function calculateLaminationCost(string $laminationType, int $quantity, ?array $laminationRate): float
    {
        if ($laminationType === 'none' || $laminationRate === null) {
            return 0.0;
        }

        $baseCost = (float) $laminationRate['base_cost'];

        if ($laminationType === 'single_glossy') {
            $divisor = (float) $laminationRate['glossy_divisor'];
        } elseif ($laminationType === 'single_matte') {
            $divisor = (float) $laminationRate['matte_divisor'];
        } else {
            return 0.0;
        }

        if ($divisor <= 0) {
            return 0.0;
        }

        return ($baseCost / $divisor) * $quantity;
    }

    /**
     * Paper weight for delivery calculation.
     * area (sq inches) -> sq meters (x 0.00064516) -> kg using GSM.
     * Returns total weight in kg for the given quantity.
     */
    public function calculateWeightKg(string $paperSize, int $gsm, int $quantity): float
    {
        // paperSize like "20x30"
        [$w, $h] = array_map('floatval', explode('x', strtolower($paperSize)));
        $areaSqInches = $w * $h;
        $areaSqMeters = $areaSqInches * 0.00064516;
        $weightPerSheetKg = ($areaSqMeters * $gsm) / 1000;
        return $weightPerSheetKg * $quantity;
    }

    /** Delivery charge = MAX(fixed charge, weight x rate per kg) */
    public function calculateDeliveryCharge(float $weightKg, float $ratePerKg, float $fixedCharge): float
    {
        $weightBased = $weightKg * $ratePerKg;
        return max($fixedCharge, $weightBased);
    }

    /**
     * Full calculation pipeline. This is the single source of truth used
     * by both the AJAX preview endpoint and the final order submission -
     * the server always recalculates from the database, never trusting
     * any price sent from the browser.
     *
     * @throws InvalidArgumentException on bad input
     */
    public function calculate(int $paperId, int $quantity, string $printingType, string $laminationType, float $discount = 0): array
    {
        $settings = $this->getSettings();
        $minQty = (int) ($settings['minimum_quantity'] ?? 100);

        if ($quantity < $minQty) {
            throw new InvalidArgumentException("Quantity must be at least {$minQty} sheets.");
        }

        $paper = $this->getPaperRate($paperId);
        if (!$paper) {
            throw new InvalidArgumentException('Invalid or inactive paper selection.');
        }

        if ($printingType !== 'single_side') {
            throw new InvalidArgumentException('Invalid printing type.');
        }

        if (!in_array($laminationType, ['none', 'single_glossy', 'single_matte'], true)) {
            throw new InvalidArgumentException('Invalid lamination type.');
        }

        $printingRate    = $this->getPrintingRate();
        $laminationRate  = $this->getLaminationRate($paper['paper_size']);

        $paperCost      = $this->calculatePaperCost((float) $paper['rate_per_sheet'], $quantity);
        $printingCost   = $this->calculatePrintingCost($quantity, $printingRate);
        $laminationCost = $this->calculateLaminationCost($laminationType, $quantity, $laminationRate);

        $baseCost = $paperCost + $printingCost + $laminationCost;

        $marginPercent = $settings['margin_percent'] ?? 0;
        $marginAmount  = $baseCost * ($marginPercent / 100);
        $afterMargin   = $baseCost + $marginAmount;

        $gstPercent = $settings['gst_percent'] ?? 0;
        $gstAmount  = $afterMargin * ($gstPercent / 100);
        $totalWithGst = $afterMargin + $gstAmount;

        $weightKg = $this->calculateWeightKg($paper['paper_size'], (int) $paper['gsm'], $quantity);
        $deliveryCharge = $this->calculateDeliveryCharge(
            $weightKg,
            $settings['delivery_rate_per_kg'] ?? 0,
            $settings['delivery_fixed_amount'] ?? 0
        );

        $totalBeforeDiscount = $totalWithGst + $deliveryCharge;
        $finalAmount = $totalBeforeDiscount - $discount;
        $amountPayable = (int) ceil($finalAmount);

        return [
            'paper' => [
                'id' => $paper['id'],
                'size' => $paper['paper_size'],
                'quality' => $paper['paper_quality'],
                'gsm' => $paper['gsm'],
                'rate_per_sheet' => (float) $paper['rate_per_sheet'],
            ],
            'quantity' => $quantity,
            'printing_type' => $printingType,
            'lamination_type' => $laminationType,
            'breakdown' => [
                'paper_cost' => round($paperCost, 2),
                'printing_cost' => round($printingCost, 2),
                'lamination_cost' => round($laminationCost, 2),
                'base_cost' => round($baseCost, 2),
                'margin_percent' => $marginPercent,
                'margin_amount' => round($marginAmount, 2),
                'subtotal_after_margin' => round($afterMargin, 2),
                'gst_percent' => $gstPercent,
                'gst_amount' => round($gstAmount, 2),
                'total_with_gst' => round($totalWithGst, 2),
                'weight_kg' => round($weightKg, 4),
                'delivery_charge' => round($deliveryCharge, 2),
                'total_before_discount' => round($totalBeforeDiscount, 2),
                'discount' => round($discount, 2),
                'final_amount' => round($finalAmount, 2),
                'amount_payable' => $amountPayable,
            ],
        ];
    }
}
