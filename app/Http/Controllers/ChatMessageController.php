<?php

// app/Http/Controllers/ChatMessageController.php
namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatMessageController extends Controller
{
    public function index(Request $request, ChatRoom $room)
    {
        $user = Auth::user();

        // Chỉ cho phép xem nếu thuộc room
        if ($user->id != 1 && $room->customer_id != $user->id && $room->staff_id != $user->id) {
            return response()->json(['message' => 'Không có quyền xem tin nhắn này'], 403);
        }

        $messages = $room->messages()
            ->with('sender')
            ->orderBy('id', 'asc')
            ->paginate(50);

        return response()->json($messages);
    }

    public function store(Request $request, ChatRoom $room)
    {
        $user = Auth::user();

        if ($room->customer_id != $user->id && $room->staff_id != $user->id) {
            return response()->json(['message' => 'Không có quyền xem tin nhắn này'], 403);
        }

        $data = $request->validate([
            'message' => 'nullable|string|max:5000',
            'images'    => ['nullable', 'array'],
            'images.*'  => ['image', 'max:5120'],
        ]);

        $imagePaths = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $fileName = time() . '_' . $image->getClientOriginalName();
                $path = $image->storeAs('chat_images', $fileName, 'public');
                $imagePaths[] = $path;
            }
        }

        $message = $room->messages()->create([
            'sender_id'   => $user->id,
            'message'     => $data['message'] ?? null,
            'images'      => $imagePaths,
            'read_at'     => null,
        ]);

        // cập nhật last_message_id để list room load nhanh
        $room->update([
            'last_message_id' => $message->id,
        ]);

        $message->load('sender');

        // Bắn event realtime
        broadcast(new MessageSent($message))->toOthers();

        return response()->json($message, 201);
    }

    public function markAsRead(Request $request, ChatRoom $room)
    {
        $user = $request->user();
        if ($user->id === 1) { 
        return response()->json(['message' => 'Xem'], 200);
    }

        if ($room->customer_id != $user->id && $room->staff_id != $user->id) {
            return response()->json(['message' => 'Không có quyền xem tin nhắn này'], 403);
        }

        $room->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $user->id) // chỉ mark tin nhắn của người kia
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'OK']);
    }
}
