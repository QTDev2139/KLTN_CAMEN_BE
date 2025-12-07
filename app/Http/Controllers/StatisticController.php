<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatisticController extends Controller
{
    /**
     * Lấy dữ liệu overview dashboard
     * 
     * @param Request $request (startDate, endDate)
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
}