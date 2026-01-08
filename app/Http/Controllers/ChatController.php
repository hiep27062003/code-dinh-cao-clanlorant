<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Message;
use App\Models\ChatRoom;
use App\Events\MessageSent;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use App\Events\NotificationMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ChatController extends Controller
{
    // public function getChats()
    // {
    //     $userId = Auth::id();

    //     $chats = Message::where('sender_id', $userId)
    //         ->orWhere('receiver_id', $userId)
    //         ->orderBy('created_at', 'desc')
    //         ->get()
    //         ->groupBy(function ($message) use ($userId) {
    //             return $message->sender_id === $userId ? $message->receiver_id : $message->sender_id;
    //         });
    //     return response()->json($chats);
    // }

    // public function getMessages($userId)
    // {
    //     $authId = Auth::id();

    //     $messages = Message::where(function ($query) use ($authId, $userId) {
    //         $query->where('sender_id', $authId)->where('receiver_id', $userId);
    //     })->orWhere(function ($query) use ($authId, $userId) {
    //         $query->where('sender_id', $userId)->where('receiver_id', $authId);
    //     })->orderBy('created_at', 'asc')->get();

    //     return response()->json($messages);
    // }

    // public function sendMessage(Request $request)
    // {
    //     $message = Message::create([
    //         'sender_id' => Auth::id(),
    //         'receiver_id' => $request->receiver_id,
    //         'message' => $request->message,
    //     ]);

    //     Log::info('Phát sự kiện MessageSent:', ['message' => $message]);

    //     broadcast(new \App\Events\MessageSent($message))->toOthers();

    //     return response()->json(['message' => $message]);
    // }

    public function index()
    {
        $users = User::getUser();
        return view('admin.chat', compact('users'));
    }

    public function sendMessage(Request $request)
{
    try {
        // 1. Kiểm tra đăng nhập
        if (!auth()->check()) {
            \Log::warning('⚠️ Người dùng chưa đăng nhập cố gửi tin');
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
        }

        $sender = auth()->user();
        
        \Log::info("=== BẮT ĐẦU GỬI TIN ===");
        \Log::info("Người gửi: ID={$sender->id} | Role={$sender->role}");
        \Log::info("Nội dung tin nhắn: " . ($request->message ?? '(Không có text)'));
        \Log::info("Có file đính kèm: " . ($request->hasFile('file') ? 'Có' : 'Không'));

        $receiver = null;
        $roomId = null;

        // 2. Xác định Receiver và RoomID
        if ($sender->role === 'admin') {
            // --- ADMIN GỬI ---
            if (!$request->receiver_id) {
                return response()->json(['status' => 'error', 'message' => 'Admin phải chọn người nhận'], 400);
            }
            $receiver = User::find($request->receiver_id);
            if ($receiver) {
                $roomId = $receiver->id;
                \Log::info("✅ Admin gửi cho Khách: ID={$receiver->id}");
            }
        } else {
            // --- KHÁCH HÀNG GỬI ---
            // Tìm Admin (Ưu tiên role='admin', fallback ID=1)
            $receiver = User::where('role', 'admin')->first() ?? User::find(1);
            
            if ($receiver) {
                $roomId = $sender->id; // RoomID = ID khách hàng
                \Log::info("✅ Khách gửi cho Admin: ID={$receiver->id}");
            }
        }

        // 3. Kiểm tra Receiver
        if (!$receiver) {
            \Log::error('❌ KHÔNG TÌM THẤY NGƯỜI NHẬN!');
            return response()->json(['status' => 'error', 'message' => 'No receiver found'], 404);
        }

        \Log::info("Room ID: {$roomId}");

        // 4. Xử lý File Upload (QUAN TRỌNG - ĐOẠN NÀY BỊ THIẾU)
        $mediaPath = null;
        $mediaType = null;

        if ($request->hasFile('file')) {
            try {
                $file = $request->file('file');
                
                // Lưu vào thư mục public/storage/chat_media
                $mediaPath = $file->store('chat_media', 'public');
                $mediaType = $file->getMimeType();
                
                \Log::info("✅ Đã lưu file: {$mediaPath}");
            } catch (\Exception $e) {
                \Log::error("❌ Lỗi lưu file: " . $e->getMessage());
            }
        }

        // 5. Lưu vào Database
        $chatMessage = ChatMessage::create([
            'sender_id'   => $sender->id,
            'receiver_id' => $receiver->id,
            'room_id'     => $roomId,
            'message'     => $request->message ?? '',
            'media_type'  => $mediaType,
            'media_path'  => $mediaPath,
        ]);

        \Log::info("✅ ĐÃ LƯU THÀNH CÔNG! Message ID: {$chatMessage->id}");

        // 6. Phát sự kiện Realtime
        try {
            broadcast(new \App\Events\MessageSent(
                $roomId, 
                $sender, 
                $receiver, 
                $chatMessage->message, 
                $mediaPath
            ))->toOthers();
            
            \Log::info("✅ Đã phát sự kiện Realtime cho Room: {$roomId}");
        } catch (\Exception $e) {
            \Log::error("❌ Lỗi Broadcast: " . $e->getMessage());
        }

        // 7. Trả về kết quả
        return response()->json([
            'status' => 'success',
            'message' => 'Message sent successfully',
            'data' => [
                'id' => $chatMessage->id,
                'message' => $chatMessage->message,
                'media_path' => $mediaPath,
                'sender_id' => $sender->id,
                'receiver_id' => $receiver->id,
                'room_id' => $roomId,
                'created_at' => $chatMessage->created_at
            ]
        ]);

    } catch (\Exception $e) {
        \Log::error("❌ LỖI CRASH CONTROLLER: " . $e->getMessage());
        \Log::error("Stack trace: " . $e->getTraceAsString());
        
        return response()->json([
            'status' => 'error',
            'message' => 'Server error',
            'error' => $e->getMessage()
        ], 500);
    }
}
    public function getRoomId(Request $request)
    {
        $loggedInUser = auth()->user(); // Người đang đăng nhập
        $otherUser = User::find($request->user_id); // Người khác từ request

        if (!$otherUser) {
            return response()->json(['status' => 'error', 'message' => 'User not found'], 404);
        }

        // Kiểm tra quyền admin của người dùng
        $isLoggedInUserAdmin = $loggedInUser->isAdmin(); // Hàm isAdmin() bạn cần định nghĩa
        $isOtherUserAdmin = $otherUser->isAdmin(); // Hàm isAdmin() bạn cần định nghĩa

        // Tính toán roomId
        $roomId = $this->calculateRoomId(
            $loggedInUser->id,
            $otherUser->id,
            $isLoggedInUserAdmin,
            $isOtherUserAdmin
        );

        return response()->json([
            'status' => 'success',
            'roomId' => $roomId,
        ]);
    }

    public function getDataChatAdmin(Request $request)
    {
        // Admin đang muốn xem tin nhắn của khách nào?
        $customer = User::find($request->userId); 
    
        if (!$customer) {
            return response()->json(['data' => []]);
        }

        // QUAN TRỌNG: Room ID chính là ID của khách hàng đó (Khớp với lúc gửi)
        $roomId = $customer->id;

        // Lấy toàn bộ tin nhắn trong phòng này
        $messages = ChatMessage::where('room_id', $roomId)
                    ->orderBy('created_at', 'asc') // Tin cũ hiện trước
                    ->get();

        // Trả về dữ liệu chuẩn dạng { data: [...] }
        return response()->json(['data' => $messages]);
    }

    // public function deleteMessage(Request $request)
    // {
    //     $request->validate([
    //         'id' => 'required|exists:messages,id'
    //     ]);

    //     $message = Message::findOrFail($request->id);

    //     // Check if user has permission to delete the message
    //     if ($message->sender_id !== Auth::id()) {
    //         return response()->json(['error' => 'Unauthorized'], 403);
    //     }

    //     // Delete associated file if exists
    //     if ($message->media_path && Storage::disk('public')->exists($message->media_path)) {
    //         Storage::disk('public')->delete($message->media_path);
    //     }

    //     $message->delete();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Message deleted successfully'
    //     ]);
    // }

    public function getDataChatClient()
{
    // 1. Lấy ID user hiện tại
    $userId = auth()->id();

    // 2. Query trực tiếp (Vì bạn chắc chắn RoomID đúng là UserID)
    // Lấy tất cả tin nhắn trong phòng của user này
    $data = ChatMessage::where('room_id', $userId)
            ->orderBy('created_at', 'asc')
            ->get();

    // 3. LUÔN trả về đúng cấu trúc, dù có dữ liệu hay không
    return response()->json([
        'status' => true,
        'data' => $data // Nếu không có tin nhắn, nó sẽ là mảng rỗng []
    ]);
}

        /**
     * Tính toán Room ID dựa trên ID của người dùng.
     *
     * @param int $loggedInUserId
     * @param int $otherUserId
     * @param bool $isLoggedInUserAdmin
     * @param bool $isOtherUserAdmin
     * @return int
     */
    private function calculateRoomId(int $loggedInUserId, int $otherUserId, bool $isLoggedInUserAdmin, bool $isOtherUserAdmin): int
    {
        if ($isLoggedInUserAdmin && $isOtherUserAdmin) {
            return $loggedInUserId > $otherUserId
                ? $loggedInUserId * 100000 + $otherUserId
                : $otherUserId * 100000 + $loggedInUserId;
        }

        return $isLoggedInUserAdmin ? $otherUserId : $loggedInUserId;
    }
}
