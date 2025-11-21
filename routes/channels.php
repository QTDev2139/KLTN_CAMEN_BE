<?php

// routes/channels.php

use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat-room.{roomId}', function (User $user, int $roomId) {
    // Nếu user là admin thì cho phép subscribe tất cả room
    if (($user->role->name ?? null) === 'admin' || $user->role_id === 1) {
        return true;
    }

    return ChatRoom::where('id', $roomId)
        ->where(function ($q) use ($user) {
            $q->where('customer_id', $user->id)
              ->orWhere('staff_id', $user->id);
        })
        ->exists();
});
