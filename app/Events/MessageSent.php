<?php

namespace App\Events;

use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomId;
    public $sender;
    public $receiver;
    public $message;
    public $mediaPath;

    public function __construct($roomId, $sender, $receiver, $message, $mediaPath)
    {
        $this->roomId = $roomId;
        $this->sender = $sender;
        $this->receiver = $receiver;
        $this->message = $message;
        $this->mediaPath = $mediaPath;
    }

    public function broadcastOn()
    {
        // Kênh private: chat.{ID_CỦA_KHÁCH_HÀNG}
        return new PrivateChannel('chat.' . $this->roomId);
    }

    // QUAN TRỌNG: Bỏ comment hàm này để định danh sự kiện
    public function broadcastAs()
    {
        return 'MessageSent';
    }

    public function broadcastWith()
    {
        return [
            'roomId' => $this->roomId,
            'sender' => [
                'id' => $this->sender->id,
                'name' => $this->sender->username,
                'username' => $this->sender->username,
                'profile_picture' => $this->sender->profile_picture
            ],
            'receiver' => [
                'id' => $this->receiver->id,
                'name' => $this->receiver->username,
                'username' => $this->receiver->username,
                'profile_picture' => $this->receiver->profile_picture
            ],
            'message' => $this->message,
            'media_path' => $this->mediaPath,
            'created_at' => now(), // Thêm thời gian để hiển thị realtime
        ];
    }
}