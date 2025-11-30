<?php

namespace App\Http\Controllers;

use App\Models\QuantityImport;
use App\Models\RequestImport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequestImportController extends Controller
{
    public function index()
    {
        $requestImports = RequestImport::with('user', 'quantityImports.product')->get();
        return response()->json($requestImports, 200);
    }

    // Lấy ra sản phẩm bị thiếu (sử dụng bản dịch đầu tiên của product_translations để lấy tên)
    public function show($id)
    {
        $requestImport = RequestImport::with([
            'user', 
            'quantityImports.product.product_translations',
            'deliveries.quantityDeliveries'
        ])->find($id);

        if (!$requestImport) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }

        $missingItems = [];
        foreach ($requestImport->quantityImports as $qi) {
            $requested = (int)$qi->quantity;
            $received = 0;

            // tổng received_qty từ tất cả deliveries cho sản phẩm này
            foreach ($requestImport->deliveries as $delivery) {
                foreach ($delivery->quantityDeliveries as $qd) {
                    if ($qd->product_id === $qi->product_id) {
                        $received += (int)($qd->received_qty ?? 0);
                    }
                }
            }

            $missing = max(0, $requested - $received);
            if ($missing > 0) {
                $product = $qi->product;
                $translation = null;
                if ($product && $product->product_translations && $product->product_translations->isNotEmpty()) {
                    $translation = $product->product_translations->first();
                }

                $missingItems[] = [
                    'product_id'    => $qi->product_id,
                    'product'       => $product ? [
                        'id' => $product->id,
                        'name' => $translation->name ?? null,
                    ] : null,
                    'requested_qty' => $requested,
                    'received_qty'  => $received,
                    'missing_qty'   => $missing,
                ];
            }
        }

        return response()->json([
            'request_import' => $requestImport,
            'user' => $requestImport->user ? [
                'id' => $requestImport->user->id,
                'name' => $requestImport->user->name,
                'email' => $requestImport->user->email,
            ] : null,
            'missing_items'  => $missingItems,
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'note' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $date = Carbon::now();
        $status = 'pending';
        $note = $request->input('note', null);

        DB::beginTransaction();
        try {
            $requestImport = RequestImport::create([
                'user_id' => $user->id,
                'date' => $date,
                'status' => $status,
                'note' => $note,
            ]);

            $items = $request->input('items', []);
            $rows = [];
            foreach ($items as $it) {
                $rows[] = [
                    'request_import_id' => $requestImport->id,
                    'product_id' => $it['product_id'],
                    'quantity' => $it['quantity'],
                ];
            }

            QuantityImport::insert($rows);

            DB::commit();

            return response()->json([
                'message' => 'Tạo yêu cầu nhập thành công',
                'request_import' => $requestImport,
                'items' => $rows,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Tạo yêu cầu nhập thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $requestImport = RequestImport::find($id);
        if (!$requestImport) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }

        $request->validate([
            'note' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $requestImport->note = $request->input('note', $requestImport->note);
            $requestImport->updated_at = Carbon::now();
            $requestImport->save();

            // Replace existing quantity items with provided ones
            $requestImport->quantityImports()->delete();

            $items = $request->input('items', []);
            $now = Carbon::now();
            $rows = [];
            foreach ($items as $it) {
                $rows[] = [
                    'request_import_id' => $requestImport->id,
                    'product_id' => $it['product_id'],
                    'quantity' => $it['quantity'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                QuantityImport::insert($rows);
            }

            DB::commit();

            return response()->json([
                'message' => 'Cập nhật yêu cầu nhập thành công',
                'request_import' => $requestImport->load('quantityImports.product'),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Cập nhật yêu cầu nhập thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $requestImport = RequestImport::find($id);
        if (!$requestImport) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }

        $request->validate([
            'status' => 'required|string|in:pending,processing,shipped,completed,cancelled,supplemented,partially',
        ]);

        try {
            $requestImport->status = $request->input('status');
            $requestImport->updated_at = Carbon::now();
            $requestImport->save();

            return response()->json([
                'message' => 'Cập nhật trạng thái yêu cầu nhập thành công',
                'request_import' => $requestImport,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Cập nhật trạng thái yêu cầu nhập thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function destroy($id)
    {
        $requestImport = RequestImport::find($id);
        if (!$requestImport) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }

        try {
            $requestImport->quantityImports()->delete();
            $requestImport->delete();

            return response()->json(['message' => 'Yêu cầu nhập đã được xóa'], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Xóa yêu cầu nhập thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
