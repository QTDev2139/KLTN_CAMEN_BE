<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Tạo URL thanh toán VNPay
     * POST /api/payment/vnpay
     */
    public function vnpay_payment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10000',
            'order_id' => 'required|string',
            'order_info' => 'nullable|string',
        ]);

        // Cấu hình VNPay
        $vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html";
        $vnp_Returnurl = "https://nonsignificantly-spiflicated-xiao.ngrok-free.dev/api/payment/vnpay/callback";
        $vnp_TmnCode = "ZIGCWE3V";
        $vnp_HashSecret = "CWPZ26DKIC1VFQXK5NWGW8MRECDJP8SW";

        // Dữ liệu từ request
        $vnp_TxnRef = $request->order_id;
        $vnp_OrderInfo = $request->order_info ?? 'Thanh toán đơn hàng #' . $vnp_TxnRef;
        $vnp_OrderType = 'billpayment';
        $vnp_Amount = $request->amount * 100;
        $vnp_Locale = $request->locale ?? 'vn';
        $vnp_IpAddr = $request->ip();

        // Tạo mảng dữ liệu gửi sang VNPay
        $inputData = [
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef,
        ];

        // Sắp xếp và tạo query string
        ksort($inputData);
        $query = "";
        $hashdata = "";

        foreach ($inputData as $key => $value) {
            $hashdata .= ($hashdata ? '&' : '') . urlencode($key) . "=" . urlencode($value);
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        // Tạo secure hash
        $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
        $vnp_Url = $vnp_Url . "?" . $query . 'vnp_SecureHash=' . $vnpSecureHash;

        return response()->json([
            'message' => 'Tạo URL thanh toán thành công',
            'data' => [
                'payment_url' => $vnp_Url
            ]
        ], 200);
    }

    /**
     * Callback từ VNPay sau khi thanh toán
     * GET /api/payment/vnpay/callback
     */
    public function vnpay_callback(Request $request)
    {
        $frontend = rtrim(env('FRONTEND_URL', 'http://localhost:3001'), '/');
        $vnp_HashSecret = "CWPZ26DKIC1VFQXK5NWGW8MRECDJP8SW";

        // 1) Lấy tất cả params từ VNPay
        $inputData = $request->all();

        // 2) Lấy SecureHash từ VNPay gửi về
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';

        // 3) Xóa SecureHash khỏi mảng data để tính hash
        unset($inputData['vnp_SecureHash']);
        unset($inputData['vnp_SecureHashType']);

        // 4) Sort params theo thứ tự alphabet
        ksort($inputData);
        $hashdata = '';
        foreach ($inputData as $key => $value) {
            if ($value !== '' && $value !== null) {
                $hashdata .= ($hashdata ? '&' : '') . urlencode($key) . '=' . urlencode($value);
            }
        }

        // 6) Tính secure hash từ phía server
        $secureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);

        // 7) Log để debug
        Log::info('VNPay callback verify', [
            'hashdata' => $hashdata,
            'expected_hash' => $secureHash,
            'received_hash' => $vnp_SecureHash,
            'match' => $secureHash === $vnp_SecureHash,
        ]);

        // 8) So sánh hash
        if ($secureHash !== $vnp_SecureHash) {
            Log::error('VNPay callback: Invalid signature');
            return redirect()->away($frontend . '/payment-callback?status=error&message=' . urlencode('Chữ ký không hợp lệ'));
        }

        // 9) Xác thực thành công, xử lý kết quả
        $vnp_ResponseCode = $request->input('vnp_ResponseCode');
        $orderCode = $request->input('vnp_TxnRef');
        $amount = ((int)$request->input('vnp_Amount', 0)) / 100;
        $transactionNo = $request->input('vnp_TransactionNo');

        if ($vnp_ResponseCode === '00') {
            // Thanh toán thành công
            $order = Order::where('code', $orderCode)->first();

            if ($order) {
                $order->update([
                    'payment_status' => 'paid',
                    'transaction_code' => $transactionNo, // ✅ Lưu mã giao dịch VNPay
                    'payment_method' => 'vnpay',
                ]);
            }

            Log::info('VNPay payment success', [
                'order_id' => $orderCode,
                'amount' => $amount,
                'transaction_no' => $transactionNo
            ]);

            $cart = Cart::where('user_id', $order->user_id)
                ->orderByDesc('id')
                ->first();

            if ($cart) {
                $cart->cartitems()->delete();
                $cart->update(['is_active' => false]);
            }

            return redirect()->away($frontend . '/payment-callback?status=success&order_id=' . $orderCode);
        } else {
            // Thanh toán thất bại
            $order = Order::where('code', $orderCode)->first();

            if ($order) {
                $order->update([
                    'payment_status' => 'failed',
                ]);
            }

            Log::warning('VNPay payment failed', [
                'order_id' => $orderCode,
                'response_code' => $vnp_ResponseCode
            ]);

            return redirect()->away($frontend . '/payment-callback?status=failed&order_id=' . $orderCode . '&code=' . $vnp_ResponseCode);
        }
    }

    /**
     * Kiểm tra trạng thái giao dịch VNPay
     * GET /api/payment/vnpay/status/{order_id}
     */
    public function vnpay_status($order_id)
    {
        $order = Order::where('code', $order_id)->first();

        if (!$order) {
            return response()->json([
                'message' => 'Không tìm thấy đơn hàng'
            ], 404);
        }

        return response()->json([
            'message' => 'Lấy trạng thái giao dịch thành công',
            'data' => [
                'order_id' => $order->code,
                'status' => $order->status,
                'payment_status' => $order->payment_status, // ✅ Trả về payment_status
                'payment_method' => $order->payment_method,
                'transaction_code' => $order->transaction_code, // ✅ Trả về mã giao dịch
                'amount' => $order->grand_total,
            ]
        ], 200);
    }
}
