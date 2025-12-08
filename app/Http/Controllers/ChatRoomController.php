<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatRoomController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $roleId = $user->role_id;

        $query = ChatRoom::query()->orderByDesc('updated_at');

        // 1. Phân quyền Admin: xem tất cả phòng chát
        if ($roleId == 1 || $roleId == 3 || $roleId == 6 ) {
            $query->with(['customer', 'staff', 'lastMessage']);

            // 2. Phân quyền Khách hàng: Chỉ xem các phòng mà mình là customer
        } elseif ($roleId == 4) {
            $query->with(['staff', 'lastMessage'])
                ->where('customer_id', $user->id);

            // 3. Phân quyền Nhân viên (Staff): Xem các phòng đã được gán cho mình HOẶC đang ở trạng thái pending
        } elseif ($roleId == 6) {
            $query->with(['customer', 'lastMessage'])
                ->where(function ($q) use ($user) {
                    $q->where('staff_id', $user->id) 
                        ->orWhere('status', 'pending'); 
                });
        } else {
            return response()->json([], 200);
        }

        $rooms = $query->get();

        return response()->json($rooms);
    }

    // Customer mở room với 1 staff (hoặc staff mở với 1 customer)
    public function openRoom()
    {
        $user = Auth::user();

        if ($user->role_id == 4) {
            $customerId = $user->id;
            $staffId = null;
        } else {
            return response()->json([
                'message' => 'Chỉ Khách hàng (Customer) mới có thể mở phòng chat.'
            ], 403);
        }

        $room = ChatRoom::firstOrCreate(
            [
                'customer_id' => $customerId,
                'staff_id'    => $staffId,
            ]
        );

        $room->load(['customer', 'staff', 'lastMessage']);

        return response()->json($room);
    }

    public function joinRoom(Request $request, $roomId)
    {
        $staffId = $request->input('staff_id');
        // Tìm phòng chat
        $room = ChatRoom::find($roomId);
        if (!$room) {
            return response()->json([
                'message' => "Phòng chat với ID $roomId không tồn tại."
            ], 404);
        }

        if ($room->staff_id != $staffId) {
            $room->staff_id = $staffId;
            $room->status = 'active';
            $room->save();
        }

        $room->load(['customer', 'staff', 'lastMessage']);

        return response()->json($room);
    }

    public function show(ChatRoom $room)
    {
        $user = Auth::user();

        // Chỉ cho phép xem room nếu là 1 trong 2 người
        if ($room->customer_id !== $user->id && $room->staff_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $room->load(['customer', 'staff', 'lastMessage']);

        return response()->json($room);
    }
}
