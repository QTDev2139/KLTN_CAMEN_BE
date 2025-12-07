<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatisticController extends Controller
{
    /**
     * Lấy dữ liệu overview dashboard (5 box KPI)
     */
    public function getOverview(Request $request): JsonResponse
    {
        try {
            $startDate = $request->query('startDate');
            $endDate = $request->query('endDate');

            $data = StatisticsService::getOverviewData($startDate, $endDate);

            return response()->json([
                'success' => true,
                'message' => 'Lấy dữ liệu thống kê thành công',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy dữ liệu thống kê',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lấy toàn bộ dữ liệu Dashboard (KPI boxes + Charts)
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        try {
            $year = $request->query('year', date('Y'));
            $startDate = $request->query('startDate');
            $endDate = $request->query('endDate');

            $data = StatisticsService::getDashboardData($startDate, $endDate, $year);

            return response()->json([
                'success' => true,
                'message' => 'Lấy dữ liệu dashboard thành công',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi lấy dữ liệu dashboard',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}