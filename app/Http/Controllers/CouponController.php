<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCouponRequest;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CouponController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $coupons = Coupon::with([
            'user:id,name'
        ])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($coupons);
    }
    public function getActiveCoupons()
    {
        $coupons = Coupon::query()
            ->where('is_active', true)
            ->where('state', 'approved')
            ->orderBy('created_at', 'desc')
            ->where('end_date', '>=', now())
            ->get();

        return response()->json($coupons);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $coupon = Coupon::with(['user:id,name'])->findOrFail($id);
        return response()->json($coupon);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCouponRequest $request)
    {
        $user = Auth::user();
        $data = $request->validated();
        $existingCoupon = Coupon::where('code', $data['code'])->first();
        if ($existingCoupon) {
            return response()->json(['message' => 'Mã coupon đã tồn tại'], 422);
        }
        if ($data['end_date'] < $data['start_date']) {
            return response()->json(['message' => 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu'], 422);
        }
        $data['user_id'] = $user->id;
        Coupon::create($data);

        return response()->json(['message' => 'Tạo coupon thành công'], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    
    public function updateStatus(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->state = $request->input('state');
        $coupon->reason_end = $request->input('reason_end');
        $coupon->is_active = 1;
        $coupon->save();

        return response()->json(['message' => 'Duyệt thành công'], 200);
    }

    public function updateActive(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->is_active = $request->input('is_active');
        $coupon->reason_end = $request->input('reason_end');
        $coupon->save();

        return response()->json(['message' => 'Cập nhật trạng thái hoạt động thành công'], 200);
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json([
            'message' => 'Xóa coupon thành công',
        ], 200);
    }
}
