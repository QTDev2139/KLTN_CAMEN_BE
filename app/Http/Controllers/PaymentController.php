<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
                    'transaction_code' => $transactionNo, //  Lưu mã giao dịch VNPay
                    'payment_method' => 'vnpay',
                    'payment_date'      => Carbon::now()->format('YmdHis'),
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

            $cart = Cart::where('user_id', $order->user_id)
                ->orderByDesc('id')
                ->first();

            if ($cart) {
                $cart->cartitems()->delete();
                $cart->update(['is_active' => false]);
            }

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

    /**
     * Hàm hỗ trợ thực hiện hoàn tiền qua VNPay và cập nhật trạng thái đơn hàng.
     * @param Order $order
     * @param float $requestedAmount Số tiền cần hoàn (VND)
     * @param string $transType Loại giao dịch (02: Toàn bộ, 03: Một phần)
     * @param string $reason Lý do hoàn tiền
     * @param string $createBy Người tạo yêu cầu (System hoặc User email/name)
     * @param string $ipAddr IP của người gửi yêu cầu
     * @return array ['success' => bool, 'message' => string, 'payment_status' => string]
     */
    protected function _executeVnPayRefund(
        $order,
        $requestedAmount,
        $transType,
        $reason,
        $createBy,
        $ipAddr
    ) {
        $vnp_Url        = "https://sandbox.vnpayment.vn/merchant_webapi/api/transaction";
        $vnp_TmnCode    = "ZIGCWE3V";
        $vnp_HashSecret = "CWPZ26DKIC1VFQXK5NWGW8MRECDJP8SW";

        // 1. Kiểm tra payment_date
        if (empty($order->payment_date)) {
            return [
                'success' => false,
                'message' => 'Không có payment_date (vnp_PayDate) cho đơn hàng, không thể hoàn tiền.',
                'vnp_code' => '99',
            ];
        }

        // Đảm bảo đúng format yyyyMMddHHmmss
        if (strlen($order->payment_date) === 14 && ctype_digit($order->payment_date)) {
            $transactionDate = $order->payment_date;
        } else {
            $transactionDate = Carbon::parse($order->payment_date)->format('YmdHis');
        }

        // 2. Build inputData theo đúng tài liệu
        $inputData = [
            "vnp_RequestId"       => (string) time(),  
            "vnp_Version"         => "2.1.0",
            "vnp_Command"         => "refund",
            "vnp_TmnCode"         => $vnp_TmnCode,
            "vnp_TransactionType" => $transType,        // '02' hoặc '03'
            "vnp_TxnRef"          => $order->code,
            "vnp_Amount"          => (int) ($requestedAmount * 100),
            "vnp_OrderInfo"       => "Hoàn tiền đơn hàng: {$order->code}. Lý do: {$reason}",
            "vnp_TransactionDate" => $transactionDate,
            "vnp_CreateBy"        => $createBy ?: 'System',
            "vnp_CreateDate"      => date('YmdHis'),
            "vnp_IpAddr"          => $ipAddr ?: '127.0.0.1',
        ];

        // vnp_TransactionNo là TÙY CHỌN → chỉ gửi nếu có
        if (!empty($order->transaction_code)) {
            $inputData["vnp_TransactionNo"] = $order->transaction_code;
        }

        // 3. Tạo checksum theo đúng thứ tự + dấu '|'
        $dataToHashArr = [
            $inputData["vnp_RequestId"],
            $inputData["vnp_Version"],
            $inputData["vnp_Command"],
            $inputData["vnp_TmnCode"],
            $inputData["vnp_TransactionType"],
            $inputData["vnp_TxnRef"],
            $inputData["vnp_Amount"],
            $inputData["vnp_TransactionNo"] ?? "",
            $inputData["vnp_TransactionDate"],
            $inputData["vnp_CreateBy"],
            $inputData["vnp_CreateDate"],
            $inputData["vnp_IpAddr"],
            $inputData["vnp_OrderInfo"],
        ];

        $dataToHash = implode('|', $dataToHashArr);

        $vnpSecureHash = hash_hmac('sha512', $dataToHash, $vnp_HashSecret);
        $inputData['vnp_SecureHash'] = $vnpSecureHash;

        // 4. Gửi request
        $response = $this->sendRefundRequest($vnp_Url, $inputData);

        if (($response['vnp_ResponseCode'] ?? '99') === '00') {
            // Hoàn tiền thành công
            $totalPaid        = $order->grand_total;
            $alreadyRefunded  = $order->refund_amount ?? 0;
            $newRefundAmount  = $alreadyRefunded + $requestedAmount;
            $remainingAmount  = $totalPaid - $newRefundAmount;

            $updateData = [
                'refund_amount'          => $newRefundAmount,
                'refund_transaction_code' => $response['vnp_TransactionNo'] ?? $order->refund_transaction_code,
            ];

            if ($remainingAmount <= 0) {
                $updateData['payment_status'] = 'refunded';
                $updateData['status']         = 'cancelled';
            } else {
                $updateData['payment_status'] = 'partially_refunded';
            }

            $order->update($updateData);

            Log::info('VNPay refund success', [
                'order_id' => $order->code,
                'amount'   => $requestedAmount,
                'refund_trans_code' => $updateData['refund_transaction_code'],
            ]);

            return [
                'success'        => true,
                'message'        => 'Hoàn tiền thành công.',
                'payment_status' => $updateData['payment_status'],
            ];
        } else {
            $order->update(['payment_status' => 'refund_failed']);

            Log::error('VNPay refund failed', [
                'order_id' => $order->code,
                'response' => $response,
            ]);

            return [
                'success'  => false,
                'message'  => 'Hoàn tiền thất bại: ' . ($response['vnp_Message'] ?? 'Lỗi không xác định'),
                'vnp_code' => $response['vnp_ResponseCode'] ?? '99',
            ];
        }
    }

    /**
     * Thực hiện hoàn tiền thủ công qua VNPay (Hoàn tiền toàn bộ hoặc một phần)
     * API dành cho Admin/Nhân viên
     * POST /api/payment/vnpay/manual-refund
     */
    public function vnpay_manual_refund(Request $request)
    {
        // 1. Xác thực và Validate
        if (!Auth::check()) {
            return response()->json(['message' => 'Yêu cầu đăng nhập để thực hiện hoàn tiền.'], 401);
        }
        $user = Auth::user();

        $request->validate([
            'code' => 'required|string',
            'trans_type' => 'required|in:02,03', // 02: Toàn bộ, 03: Một phần
            'amount' => 'required|numeric|min:1000',
            'reason_refund' => 'nullable|string|max:255',
        ]);

        $order = Order::where('code', $request->code)->first();

        // 2. Kiểm tra tính hợp lệ
        if (!$order || $order->payment_status !== 'paid' || !$order->transaction_code) {
            return response()->json(['message' => 'Đơn hàng không hợp lệ để hoàn tiền hoặc chưa thanh toán.'], 400);
        }

        // 3. Tính toán và Kiểm tra logic hoàn tiền
        $totalPaid = $order->grand_total;
        $alreadyRefunded = $order->refund_amount ?? 0;
        $refundableAmount = $totalPaid - $alreadyRefunded;
        $requestedAmount = $request->amount;
        $reason = $request->reason_refund ?? 'Đã hoàn tiền';

        if ($requestedAmount > $refundableAmount) {
            return response()->json(['message' => 'Số tiền hoàn lại vượt quá số tiền còn lại có thể hoàn (còn lại: ' . number_format($refundableAmount) . ' VND).'], 400);
        }
        if ($request->trans_type === '02' && $requestedAmount != $refundableAmount) {
            return response()->json(['message' => 'Yêu cầu hoàn tiền toàn bộ (02) phải hoàn lại đúng số tiền còn lại (' . number_format($refundableAmount) . ' VND).'], 400);
        }

        // 4. Thực thi hoàn tiền
        $result = $this->_executeVnPayRefund(
            $order,
            $requestedAmount,
            $request->trans_type,
            $reason,
            $user->email ?? $user->name, // Người tạo yêu cầu
            $request->ip()
        );

        if ($result['success']) {
            return response()->json([
                'message' => 'Hoàn tiền thành công.',
                'new_payment_status' => $result['payment_status']
            ], 200);
        } else {
            return response()->json([
                'message' => $result['message'],
                'vnp_code' => $result['vnp_code']
            ], 400);
        }
    }

    /**
     * Thực hiện hoàn tiền tự động qua VNPay (Luôn là hoàn toàn bộ số tiền còn lại)
     * Dành cho các sự kiện hệ thống (Ví dụ: Hủy đơn hàng)
     * Có thể gọi nội bộ (ví dụ: $this->vnpay_auto_refund($order->code))
     */
    public function vnpay_auto_refund(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ]);
        $orderCode = $request->code;
        $order = Order::where('code', $orderCode)->first();

        // 1. Kiểm tra tính hợp lệ
        if (!$order || $order->payment_status !== 'paid' || !$order->transaction_code) {
            return ['success' => false, 'message' => 'Đơn hàng không hợp lệ để hoàn tiền tự động hoặc chưa thanh toán.'];
        }

        $totalPaid = $order->grand_total;
        $alreadyRefunded = $order->refund_amount ?? 0;
        $requestedAmount = $totalPaid - $alreadyRefunded; // Luôn lấy số tiền còn lại có thể hoàn

        // 2. Kiểm tra nếu đã hoàn đủ
        if ($requestedAmount <= 0) {
            return ['success' => true, 'message' => 'Đơn hàng đã được hoàn tiền đầy đủ trước đó.'];
        }

        // 3. Thực thi hoàn tiền (TransType: 02 - Hoàn toàn bộ)
        $result = $this->_executeVnPayRefund(
            $order,
            $requestedAmount,
            '02', // Luôn là Hoàn toàn bộ khi tự động
            'Hệ thống hủy đơn hàng',
            'System', // Người tạo yêu cầu là Hệ thống
            '127.0.0.1' // IP có thể là IP tĩnh của Server hoặc 127.0.0.1
        );

        // Update lại số lượng sản phẩm trong kho nếu hoàn tiền thành công
        if ($result['success']) {
            try {
                DB::transaction(function () use ($order) {
                    // Tăng lại stock cho từng order item nếu product có stock_quantity tracked
                    $items = $order->orderItems()->get();
                    foreach ($items as $it) {
                        Product::where('id', $it->product_id)
                            ->whereNotNull('stock_quantity')
                            ->increment('stock_quantity', (int)$it->qty);
                    }

                    // Nếu đơn sử dụng coupon thì giảm used_count (nếu > 0)
                    if ($order->coupon_id) {
                        Coupon::where('id', $order->coupon_id)
                            ->where('used_count', '>', 0)
                            ->decrement('used_count', 1);
                    }
                });

                Log::info('Auto refund: stock and coupon updated', ['order' => $order->code]);
            } catch (\Exception $e) {
                Log::error('Auto refund: failed to update stock/coupon', [
                    'order' => $order->code,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $result;
    }

    protected function sendRefundRequest($url, $data)
    {
        $ch = curl_init();

        $jsonBody = json_encode($data, JSON_UNESCAPED_UNICODE);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonBody),
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);

        curl_close($ch);

        Log::info('VNPay refund HTTP response', [
            'http_code' => $http_code,
            'response'  => $response,
            'curl_err'  => $curl_err,
            'request'   => $data,
        ]);

        if ($response === false) {
            return [
                'vnp_ResponseCode' => '99',
                'vnp_Message'      => 'Lỗi CURL: ' . $curl_err,
            ];
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return [
                'vnp_ResponseCode' => '99',
                'vnp_Message'      => 'Không parse được JSON từ VNPay. HTTP ' . $http_code,
            ];
        }

        return $decoded;
    }
}
