<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class RecommendationService
{
    private const RISK_TYPES = [
        'Low' => ['Fixed Plan', 'Flexi Plan', 'Retirement/Income Plan'],
        'Medium' => ['Flexi Plan', 'Growth Plan', 'Retirement/Income Plan'],
        'High' => ['Growth Plan', 'Equity Plan'],
    ];

    private const HORIZON_TYPES = [
        'Short' => ['Fixed Plan', 'Flexi Plan'],
        'Medium' => ['Flexi Plan', 'Growth Plan'],
        'Long' => ['Growth Plan', 'Equity Plan', 'Retirement/Income Plan'],
    ];

    private const LIQUIDITY_TYPES = [
        'High' => ['Flexi Plan', 'Equity Plan'],
        'Medium' => ['Growth Plan', 'Equity Plan'],
        'Low' => ['Fixed Plan', 'Retirement/Income Plan'],
    ];

    private const HORIZON_MONTHS = [
        'Short' => 12,
        'Medium' => 36,
        'Long' => 60,
    ];

    public static function recommend(array $input, int $age): array
    {
        $banks = Database::connection()
            ->query('SELECT * FROM banks ORDER BY expected_return DESC')
            ->fetchAll();

        $monthly = !empty($input['monthly_contribution']);
        $ageTypes = self::typesForAge($age);

        $ranked = [];
        foreach ($banks as $bank) {
            $type = $bank['plan_type'];
            $score = 0;
            $benefits = [];

            if ($bank['risk'] === $input['risk']) {
                $score++;
                $benefits[] = 'Bank risk level matches your risk tolerance';
            }
            if ($bank['horizon'] === $input['horizon']) {
                $score++;
                $benefits[] = 'Bank horizon matches your time horizon';
            }
            if ($bank['liquidity'] === $input['liquidity']) {
                $score++;
                $benefits[] = 'Bank liquidity matches your preference';
            }
            if (in_array($type, self::RISK_TYPES[$input['risk']] ?? [], true)) {
                $score++;
                $benefits[] = 'Investment type fits risk category';
            }
            if (in_array($type, self::HORIZON_TYPES[$input['horizon']] ?? [], true)) {
                $score++;
                $benefits[] = 'Investment type fits time horizon';
            }
            if (in_array($type, self::LIQUIDITY_TYPES[$input['liquidity']] ?? [], true)) {
                $score++;
                $benefits[] = 'Investment type fits liquidity preference';
            }
            if (($monthly && (int)$bank['allows_monthly'] === 1) || (!$monthly && (int)$bank['allows_monthly'] === 0)) {
                $score++;
                $benefits[] = $monthly ? 'Allows recurring deposits' : 'Works for lump-sum investing';
            }
            if (in_array($type, $ageTypes, true)) {
                $score++;
                $benefits[] = 'Suitable for age profile';
            }

            $ranked[] = [
                'bank_id' => (int)$bank['id'],
                'user_plan_name' => $input['plan_name'],
                'bank_name' => $bank['name'],
                'bank_contact' => $bank['contact_info'],
                'bank_website' => $bank['website'],
                'bank_details' => $bank['details'],
                'plan_type' => $type,
                'expected_return' => (float)$bank['expected_return'],
                'risk' => $bank['risk'],
                'liquidity' => $bank['liquidity'],
                'horizon' => $bank['horizon'],
                'allows_monthly' => (bool)$bank['allows_monthly'],
                'score' => $score,
                'benefits' => $benefits,
                'duration_months' => self::HORIZON_MONTHS[$bank['horizon']] ?? 12,
                'estimated_total_value' => self::estimateTotalValue(
                    (float)$input['investment_amount'],
                    (float)($input['monthly_amount'] ?? 0),
                    (float)$bank['expected_return'],
                    self::HORIZON_MONTHS[$bank['horizon']] ?? 12
                ),
                'estimated_profit' => self::estimateProfit(
                    (float)$input['investment_amount'],
                    (float)($input['monthly_amount'] ?? 0),
                    (float)$bank['expected_return'],
                    self::HORIZON_MONTHS[$bank['horizon']] ?? 12
                ),
                'estimated_monthly_growth' => self::estimateMonthlyGrowth(
                    (float)$input['investment_amount'],
                    (float)($input['monthly_amount'] ?? 0),
                    (float)$bank['expected_return'],
                    self::HORIZON_MONTHS[$bank['horizon']] ?? 12
                ),
            ];
        }

        usort($ranked, fn (array $a, array $b): int => [$b['score'], $b['expected_return']] <=> [$a['score'], $a['expected_return']]);

        return array_slice($ranked, 0, 1);
    }

    private static function typesForAge(int $age): array
    {
        if ($age <= 35) {
            return ['Growth Plan', 'Equity Plan'];
        }

        if ($age <= 55) {
            return ['Flexi Plan', 'Growth Plan', 'Retirement/Income Plan'];
        }

        return ['Fixed Plan', 'Retirement/Income Plan'];
    }

    private static function estimateTotalValue(float $principal, float $monthlyContribution, float $annualRate, int $months): float
    {
        $monthlyRate = $annualRate / 100 / 12;
        $principalFutureValue = $principal * pow(1 + $monthlyRate, $months);
        $contributionFutureValue = 0.0;

        if ($monthlyContribution > 0) {
            if ($monthlyRate == 0.0) {
                $contributionFutureValue = $monthlyContribution * $months;
            } else {
                $contributionFutureValue = $monthlyContribution * ((pow(1 + $monthlyRate, $months) - 1) / $monthlyRate);
            }
        }

        return round($principalFutureValue + $contributionFutureValue, 2);
    }

    private static function estimateProfit(float $principal, float $monthlyContribution, float $annualRate, int $months): float
    {
        $contributed = $principal + ($monthlyContribution * $months);
        return round(self::estimateTotalValue($principal, $monthlyContribution, $annualRate, $months) - $contributed, 2);
    }

    private static function estimateMonthlyGrowth(float $principal, float $monthlyContribution, float $annualRate, int $months): float
    {
        if ($months <= 0) {
            return 0.0;
        }

        return round(self::estimateProfit($principal, $monthlyContribution, $annualRate, $months) / $months, 2);
    }
}
