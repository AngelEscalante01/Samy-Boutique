<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class ReportDataService
{
    public function __construct(
        private SalesAnalyticsService $sales,
        private ReportAnalyticsService $analytics,
    ) {}

    public function build(string $from, string $to): array
    {
        $start = Carbon::createFromFormat('!Y-m-d', $from)->startOfDay();
        $end = Carbon::createFromFormat('!Y-m-d', $to)->endOfDay();
        $sales = $this->sales->buildRangeReport($start, $end);
        $analytics = $this->analytics->forRange($start, $end);

        return [
            'filters' => ['from' => $from, 'to' => $to],
            'salesSummary' => $sales['summary'],
            'dailySummary' => $sales['daily'],
            'topCategories' => $analytics['top_categories']->all(),
            'topProducts' => $analytics['top_products']->all(),
            'topCustomers' => $analytics['top_customers']->all(),
            'nearLoyaltyCustomers' => $analytics['near_loyalty_customers']->all(),
        ];
    }
}
