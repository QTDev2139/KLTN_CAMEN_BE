<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\QuantityDelivery;
use App\Models\Product; // added
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryController extends Controller
{
    public function show($id)
    {
        $delivery = Delivery::with('user', 'quantityDeliveries.product', 'requestImport')
        ->where('request_import_id', $id)
        ->first();

        if (!$delivery) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }
        return response()->json($delivery, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'note' => 'nullable|string|max:1000',
            'request_import_id' => 'required|integer',
            'quantity_imports' => 'required|array|min:1',
            'quantity_imports.*.product_id' => 'required|integer',
            'quantity_imports.*.quantity' => 'required|integer|min:1',
            'quantity_imports.*.sent_qty' => 'required|integer|min:1',
        ]);

        $user = Auth::user();
        $date = Carbon::now();
        $delivery_number = 'DEL-' . now()->format('YmdHis') . strtoupper(Str::random(4));
        $note = $request->input('note', null);

        DB::beginTransaction();
        try {

            $delivery = Delivery::create([
                'user_id' => $user->id,
                'date' => $date,
                'request_import_id' => $request->input('request_import_id'),
                'delivery_number' => $delivery_number,
                'note' => $note,
            ]);

            $items = $request->input('quantity_imports', []);
            $rows = [];
            foreach ($items as $it) {
                $rows[] = [
                    'delivery_id' => $delivery->id,
                    'product_id' => $it['product_id'],
                    'quantity' => $it['quantity'],
                    'sent_qty' => $it['sent_qty'],
                ];
            }

            QuantityDelivery::insert($rows);

            DB::commit();

            return response()->json([
                'message' => 'Gửi yêu cầu giao hàng thành công',
                'delivery' => $delivery,
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
        $delivery = Delivery::find($id);
        if (!$delivery) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }

        $request->validate([
            'note' => 'nullable|string|max:1000',
            'quantity_deliveries' => 'required|array|min:1',
            'quantity_deliveries.*.product_id' => 'required|integer',
            'quantity_deliveries.*.quantity' => 'required|integer|min:0',
            'quantity_deliveries.*.sent_qty' => 'required|integer|min:0',
            'quantity_deliveries.*.received_qty' => 'required|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            $delivery->note = $request->input('note', $delivery->note);
            $delivery->updated_at = Carbon::now();
            $delivery->save();

            $delivery->quantityDeliveries()->delete();

            $items = $request->input('quantity_deliveries', []);
            $now = Carbon::now();
            $rows = [];
            $hasShortage = false;

            foreach ($items as $it) {
                $sent = (int)($it['sent_qty'] ?? 0);
                $received = (int)($it['received_qty'] ?? 0);
                $shortage = max(0, $sent - $received);

                if ($shortage > 0) {
                    $hasShortage = true;
                }

                $rows[] = [
                    'delivery_id'    => $delivery->id,
                    'product_id'     => $it['product_id'],
                    'quantity'       => $it['quantity'] ?? 0,
                    'sent_qty'       => $sent,
                    'received_qty'   => $received,
                    'shortage_qty'   => $shortage,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ];

                // Update product stock: increase by received qty
                $product = Product::find($it['product_id']);
                if ($product) {
                    // assume product has 'stock_quantity' column
                    $increment = max(0, $received);
                    if ($increment > 0) {
                        $product->increment('stock_quantity', $increment);
                    }
                }
            }

            if (!empty($rows)) {
                QuantityDelivery::insert($rows);
            }

            // Update related request_import flags/status if exists
            $requestImport = $delivery->requestImport;
            if ($requestImport) {
                $requestImport->has_shortage = $hasShortage ? 1 : 0;
                $requestImport->status = $hasShortage ? 'partially' : 'completed';
                $requestImport->updated_at = Carbon::now();
                $requestImport->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Cập nhật yêu cầu giao thành công',
                'delivery' => $delivery->load('quantityDeliveries.product', 'requestImport'),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Cập nhật yêu cầu giao thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateProductMissed(Request $request, $id)
    {
        $delivery = Delivery::where('request_import_id', $id)->first();
        if (!$delivery) {
            return response()->json(['message' => 'Không tìm thấy'], 404);
        }

        $request->validate([
            'note' => 'nullable|string|max:1000',
            'quantity_deliveries' => 'required|array|min:1',
            'quantity_deliveries.*.product_id' => 'required|integer',
            'quantity_deliveries.*.received_qty' => 'required|integer|min:0',
        ]);

        DB::beginTransaction();
        try {
            // update note if provided
            $delivery->note = $request->input('note', $delivery->note);
            $delivery->updated_at = Carbon::now();
            $delivery->save();

            $items = $request->input('quantity_deliveries', []);
            $now = Carbon::now();

            foreach ($items as $it) {
                $productId = $it['product_id'];
                $received = (int)($it['received_qty'] ?? 0);
                if ($received <= 0) {
                    continue;
                }

                // find existing quantity delivery row
                $qd = QuantityDelivery::where('delivery_id', $delivery->id)
                    ->where('product_id', $productId)
                    ->first();

                if (!$qd) {
                    continue;
                }

                // update received_qty and shortage_qty
                $currentReceived = (int)($qd->received_qty ?? 0);
                $currentShortage = (int)($qd->shortage_qty ?? 0);

                $qd->received_qty = $currentReceived + $received;
                $qd->shortage_qty = max(0, $currentShortage - $received);
                $qd->updated_at = $now;
                $qd->save();

                // update product stock by received qty
                $product = Product::find($productId);
                if ($product && $received > 0) {
                    $product->increment('stock_quantity', $received);
                }
            }

            // recompute hasShortage based on remaining shortage_qty
            $hasShortage = $delivery->quantityDeliveries()->where('shortage_qty', '>', 0)->exists();

            $requestImport = $delivery->requestImport;
            if ($requestImport) {
                $requestImport->has_shortage = $hasShortage ? 1 : 0;
                $requestImport->status = $hasShortage ? 'partially' : 'completed';
                $requestImport->updated_at = Carbon::now();
                $requestImport->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Cập nhật thiếu hàng thành công',
                'delivery' => $delivery->load('quantityDeliveries.product', 'requestImport'),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Cập nhật thiếu hàng thất bại',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
