<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StatisticsService
{
    /**
     * Tính toán % tăng trưởng
     */
    private static function calculatePercentage($current, $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 1 : 0; // 100% hoặc 0%
        }
        return round(($current - $previous) / $previous, 4);
    }

    /**
     * Lấy khoảng ngày trước đó dựa trên số ngày chênh lệch
     */
    private static function getPreviousDateRange(string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        $daysDifference = $start->diffInDays($end) + 1;
        $previousEnd = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays($daysDifference - 1);

        return [
            'startDate' => $previousStart->format('Y-m-d'),
            'endDate' => $previousEnd->format('Y-m-d'),
        ];
    }

    /**
     * Doanh số năm hiện tại vs năm trước
     */
    private static function getCurrentYearSales(?string $startDate = null, ?string $endDate = null): array
    {
        $currentYear = Carbon::now()->year;

        if (!$startDate && !$endDate) {
            $startDate = $currentYear . '-01-01';
            $endDate = $currentYear . '-12-31';
        }

        $currentYearRevenue = Order::getTotalRevenueByRange($startDate, $endDate);
        
        $previousRange = self::getPreviousDateRange($startDate, $endDate);
        $previousYearRevenue = Order::getTotalRevenueByRange($previousRange['startDate'], $previousRange['endDate']);

        $percentage = self::calculatePercentage($currentYearRevenue, $previousYearRevenue);
        $isPositiveTrend = $percentage >= 0;

        return [
            'value' => (float)$currentYearRevenue,
            'comparison' => $percentage,
            'comparisonText' => 'vs năm ngoài',
            'isPositiveTrend' => $isPositiveTrend,
        ];
    }

    /**
     * Doanh thu đã thanh toán
     */
    private static function getPaidRevenue(?string $startDate = null, ?string $endDate = null): array
    {
        if (!$startDate && !$endDate) {
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        $currentRevenue = Order::getPaidRevenueByRange($startDate, $endDate);
        
        $previousRange = self::getPreviousDateRange($startDate, $endDate);
        $previousRevenue = Order::getPaidRevenueByRange($previousRange['startDate'], $previousRange['endDate']);

        $percentage = self::calculatePercentage($currentRevenue, $previousRevenue);
        $isPositiveTrend = $percentage >= 0;

        return [
            'value' => (float)$currentRevenue,
            'comparison' => $percentage,
            'comparisonText' => 'vs tháng trước',
            'isPositiveTrend' => $isPositiveTrend,
        ];
    }

    /**
     * Doanh thu chưa thanh toán
     */
    private static function getUnpaidRevenue(?string $startDate = null, ?string $endDate = null): array
    {
        if (!$startDate && !$endDate) {
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        $currentRevenue = Order::getUnpaidRevenueByRange($startDate, $endDate);
        
        $previousRange = self::getPreviousDateRange($startDate, $endDate);
        $previousRevenue = Order::getUnpaidRevenueByRange($previousRange['startDate'], $previousRange['endDate']);

        $percentage = self::calculatePercentage($currentRevenue, $previousRevenue);
        $isPositiveTrend = $percentage <= 0; // Giảm là tốt

        return [
            'value' => (float)$currentRevenue,
            'comparison' => $percentage,
            'comparisonText' => 'vs tháng trước',
            'isPositiveTrend' => $isPositiveTrend,
        ];
    }

    /**
     * Tổng đơn hàng
     */
    private static function getTotalOrders(?string $startDate = null, ?string $endDate = null): array
    {
        if (!$startDate && !$endDate) {
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        $currentOrders = Order::getTotalOrdersByRange($startDate, $endDate);
        
        $previousRange = self::getPreviousDateRange($startDate, $endDate);
        $previousOrders = Order::getTotalOrdersByRange($previousRange['startDate'], $previousRange['endDate']);

        $percentage = self::calculatePercentage($currentOrders, $previousOrders);
        $isPositiveTrend = $percentage >= 0;

        return [
            'value' => (int)$currentOrders,
            'comparison' => $percentage,
            'comparisonText' => 'vs tháng trước',
            'isPositiveTrend' => $isPositiveTrend,
        ];
    }

    /**
     * Đơn hàng chờ xử lý
     */
    private static function getPendingOrders(?string $startDate = null, ?string $endDate = null): array
    {
        if (!$startDate && !$endDate) {
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        $currentPending = Order::getPendingOrdersByRange($startDate, $endDate);
        
        $previousRange = self::getPreviousDateRange($startDate, $endDate);
        $previousPending = Order::getPendingOrdersByRange($previousRange['startDate'], $previousRange['endDate']);

        $percentage = self::calculatePercentage($currentPending, $previousPending);
        $isPositiveTrend = $percentage <= 0; // Giảm là tốt

        return [
            'value' => (int)$currentPending,
            'comparison' => $percentage,
            'comparisonText' => 'vs tháng trước',
            'isPositiveTrend' => $isPositiveTrend,
        ];
    }

    /**
     * Biểu đồ Cơ cấu Thanh toán (Donut Chart)
     */
    private static function getDonutChartPaymentStatus(?string $startDate = null, ?string $endDate = null): array
    {
        if (!$startDate && !$endDate) {
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        $paidRevenue = Order::getPaidRevenueByRange($startDate, $endDate);
        $unpaidRevenue = Order::getUnpaidRevenueByRange($startDate, $endDate);
        $totalRevenue = $paidRevenue + $unpaidRevenue;

        if ($totalRevenue == 0) {
            return [
                'labels' => ['Đã thanh toán', 'Chưa thanh toán'],
                'values' => [0, 0],
                'colors' => ['#4CAF50', '#F44336'],
            ];
        }

        $paidPercentage = round(($paidRevenue / $totalRevenue) * 100);
        $unpaidPercentage = 100 - $paidPercentage;

        return [
            'labels' => ['Đã thanh toán', 'Chưa thanh toán'],
            'values' => [$paidPercentage, $unpaidPercentage],
            'colors' => ['#4CAF50', '#F44336'],
        ];
    }

    /**
     * Biểu đồ Funnel Trạng thái Đơn hàng
     */
    private static function getFunnelChartOrderFlow(?string $startDate = null, ?string $endDate = null): array
    {
        if (!$startDate && !$endDate) {
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        $totalOrders = Order::getTotalOrdersByRange($startDate, $endDate);
        $pendingOrders = Order::query()->filterByDateRange($startDate, $endDate)->where('status', 'pending')->count();
        $shippingOrders = Order::query()->filterByDateRange($startDate, $endDate)->where('status', 'shipping')->count();
        $completedOrders = Order::query()->filterByDateRange($startDate, $endDate)->where('status', 'completed')->count();
        $cancelledOrders = Order::query()->filterByDateRange($startDate, $endDate)->where('status', 'cancelled')->count();

        return [
            'steps' => [
                ['label' => 'Tổng Đơn', 'value' => $totalOrders, 'status_key' => 'total'],
                ['label' => 'Chờ Xử Lý', 'value' => $pendingOrders, 'status_key' => 'pending'],
                ['label' => 'Đang Giao', 'value' => $shippingOrders, 'status_key' => 'shipping'],
                ['label' => 'Đã Giao', 'value' => $completedOrders, 'status_key' => 'completed'],
                ['label' => 'Đã Hủy', 'value' => $cancelledOrders, 'status_key' => 'cancelled'],
            ],
        ];
    }

    /**
     * Biểu đồ Line Chart Doanh thu và Đơn hàng theo Tháng
     */
    private static function getLineChartMonthlyTrend(string $year): array
    {
        $monthlyData = Order::query()
            ->whereYear('created_at', $year)
            ->where('payment_status', 'paid')
            ->selectRaw('MONTH(created_at) as month')
            ->selectRaw('SUM(grand_total) as paid_revenue')
            ->selectRaw('COUNT(*) as total_orders')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $revenueData = array_fill(0, 12, 0);
        $ordersData = array_fill(0, 12, 0);

        foreach ($monthlyData as $item) {
            $monthIndex = $item->month - 1;
            $revenueData[$monthIndex] = (float)$item->paid_revenue;
            $ordersData[$monthIndex] = (int)$item->total_orders;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Doanh thu đã thanh toán (VND)',
                    'data' => $revenueData,
                    'borderColor' => '#2196F3',
                    'backgroundColor' => 'rgba(33, 150, 243, 0.1)',
                ],
                [
                    'label' => 'Tổng đơn hàng',
                    'data' => $ordersData,
                    'borderColor' => '#FF9800',
                    'backgroundColor' => 'rgba(255, 152, 0, 0.1)',
                ],
            ],
        ];
    }

    /**
     * Biểu đồ Bar Chart Top 5 Sản phẩm
     */
    private static function getBarChartTopProducts(?string $startDate = null, ?string $endDate = null): array
    {
        if (!$startDate && !$endDate) {
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
        }

        $topProducts = OrderItem::query()
            ->join('orders', 'orderitems.order_id', '=', 'orders.id')
            ->join('products', 'orderitems.product_id', '=', 'products.id')
            ->join('product_translations', 'products.id', '=', 'product_translations.product_id')
            ->whereBetween('orders.created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay(),
            ])
            ->where('orders.status', 'completed')
            ->where('product_translations.language_id', 1)
            ->selectRaw('product_translations.name as product_name')
            ->selectRaw('SUM(orderitems.qty) as total_quantity')
            ->selectRaw('SUM(orderitems.subtotal) as total_revenue')
            ->groupBy('products.id', 'product_translations.name')
            ->orderByDesc('total_quantity')
            ->limit(8)
            ->get();

        $labels = [];
        $quantities = [];
        $revenues = [];

        foreach ($topProducts as $product) {
            $labels[] = $product->product_name;
            $quantities[] = (int)$product->total_quantity;
            $revenues[] = (float)$product->total_revenue;
        }

        return [
            'labels' => $labels,
            'data' => [
                [
                    'label' => 'Lượt bán',
                    'values' => $quantities,
                    'backgroundColor' => '#4CAF50',
                ],
                [
                    'label' => 'Tổng tiền (VND)',
                    'values' => $revenues,
                    'backgroundColor' => '#2196F3',
                ],
            ],
        ];
    }

    /**
     * Lấy dữ liệu overview (5 box KPI)
     */
    public static function getOverviewData(?string $startDate = null, ?string $endDate = null): array
    {
        return [
            'current_year_sales' => self::getCurrentYearSales($startDate, $endDate),
            'paid_revenue' => self::getPaidRevenue($startDate, $endDate),
            'unpaid_revenue' => self::getUnpaidRevenue($startDate, $endDate),
            'total_orders' => self::getTotalOrders($startDate, $endDate),
            'pending_orders' => self::getPendingOrders($startDate, $endDate),
        ];
    }

    /**
     * Lấy toàn bộ dữ liệu Dashboard
     */
    public static function getDashboardData(?string $startDate = null, ?string $endDate = null, string $year = null): array
    {
        if (!$year) {
            $year = date('Y');
        }

        $kpiBoxes = [
            [
                'id' => 'current_year_sales',
                ...self::getCurrentYearSales($startDate, $endDate),
            ],
            [
                'id' => 'paid_revenue',
                ...self::getPaidRevenue($startDate, $endDate),
            ],
            [
                'id' => 'unpaid_revenue',
                ...self::getUnpaidRevenue($startDate, $endDate),
            ],
            [
                'id' => 'total_orders',
                ...self::getTotalOrders($startDate, $endDate),
            ],
            [
                'id' => 'pending_orders',
                ...self::getPendingOrders($startDate, $endDate),
            ],
        ];

        return [
            'kpi_boxes' => $kpiBoxes,
            'donut_chart_payment_status' => self::getDonutChartPaymentStatus($startDate, $endDate),
            'funnel_chart_order_flow' => self::getFunnelChartOrderFlow($startDate, $endDate),
            'line_chart_monthly_trend' => self::getLineChartMonthlyTrend($year),
            'bar_chart_top_products' => self::getBarChartTopProducts($startDate, $endDate),
        ];
    }
}