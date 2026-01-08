@extends('layouts.admin.main')

@section('content')

{{-- 1. HÀM PHP XỬ LÝ ẢNH (Giữ nguyên để fix lỗi ảnh) --}}
@php
    function getAvatarUrl($path) {
        if (!$path) return 'https://img.icons8.com/color/48/user.png';
        if (Str::startsWith($path, 'http')) return $path;
        
        // Nếu trong DB lưu "avatars/..." thì thêm storage/
        if (Str::startsWith($path, 'avatars/')) return asset('storage/' . $path);
        
        return asset($path);
    }
    $currentAdminAvatar = getAvatarUrl(Auth::user()->avatar);
@endphp

<style>
    /* === 1. FIX MÀU CHỮ CÁC Ô NHẬP LIỆU === */
    #chat-input, #searchUser {
        color: #000 !important; /* Chữ khi gõ màu đen */
        background-color: #fff !important;
        font-weight: 500;
    }
    
    /* Màu chữ placeholder (chữ gợi ý mờ) cho đậm hơn */
    ::placeholder {
        color: #666 !important; 
        opacity: 1;
    }

    /* === 2. GIAO DIỆN CHAT === */
    .chat-container { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); height: 85vh; display: flex; overflow: hidden; font-family: sans-serif; }
    
    /* SIDEBAR */
    .chat-sidebar { width: 300px; background: #f8f9fa; border-right: 1px solid #e0e0e0; display: flex; flex-direction: column; }
    
    .search-box { padding: 15px; border-bottom: 1px solid #eee; }
    .search-box input { width: 100%; padding: 10px 15px; border-radius: 20px; border: 1px solid #ccc; outline: none; }
    
    .user-list { flex: 1; overflow-y: auto; }
    .user-item { display: flex; align-items: center; padding: 12px 15px; cursor: pointer; transition: 0.2s; border-bottom: 1px solid #f0f0f0; }
    .user-item:hover { background: #e9ecef; }
    
    /* Khi active thì nền cam, chữ trắng */
    .user-item.active { background: #ef9f00; color: #fff; }
    .user-item.active .user-name { color: #fff !important; }
    .user-item.active .sub-text { color: #f0f0f0 !important; }

    /* Ảnh đại diện */
    .avatar-img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; margin-right: 12px; border: 1px solid #ccc; background: white; }

    /* KHUNG CHAT CHÍNH */
    .chat-main { flex: 1; display: flex; flex-direction: column; background: #fff; }
    .chat-header { padding: 12px 20px; border-bottom: 1px solid #eee; display: flex; align-items: center; background: #fff; }
    .chat-header img { width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; object-fit: cover; }
    
    /* === 3. FIX MÀU CHỮ TRẠNG THÁI (Đang hoạt động / Online) === */
    .status-text {
        font-size: 12px;
        color: #000 !important; /* Đổi màu xanh thành ĐEN */
        font-weight: 500;
        display: flex;
        align-items: center;
    }
    .status-dot {
        height: 8px; width: 8px; background-color: #28a745; /* Chấm xanh giữ nguyên cho đẹp */
        border-radius: 50%; display: inline-block; margin-right: 5px;
    }

    /* Tin nhắn */
    .messages-area { flex: 1; overflow-y: auto; padding: 20px; background: #f9f9f9; display: flex; flex-direction: column; gap: 10px; }
    .message-row { display: flex; align-items: flex-end; margin-bottom: 10px; }
    .message-row.right { flex-direction: row-reverse; }
    .msg-avatar { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; margin: 0 8px; border: 1px solid #ddd; }
    .msg-bubble { max-width: 70%; padding: 10px 14px; border-radius: 15px; font-size: 14px; line-height: 1.4; position: relative; }
    .message-row.left .msg-bubble { background: #fff; border: 1px solid #ddd; color: #000; border-bottom-left-radius: 2px; }
    .message-row.right .msg-bubble { background: #ef9f00; color: #fff; border-bottom-right-radius: 2px; }
    .msg-time { display: block; font-size: 10px; margin-top: 4px; opacity: 0.7; }
    .message-row.right .msg-time { text-align: right; color: #eee; }

    /* Footer Input */
    .chat-footer { padding: 15px; border-top: 1px solid #eee; display: flex; align-items: center; gap: 10px; background: #fff; }
    .chat-footer input { flex: 1; padding: 12px; border-radius: 25px; border: 1px solid #ccc; outline: none; }
    .btn-send { background: #ef9f00; color: white; border: none; padding: 10px 20px; border-radius: 25px; cursor: pointer; font-weight: bold; }
</style>

<div class="container-fluid p-3">
    <div class="chat-container">
        <div class="chat-sidebar">
            <div class="search-box">
                <input type="text" id="searchUser" placeholder="Tìm kiếm khách hàng...">
            </div>
            <div class="user-list">
                @foreach ($users as $user)
                @php $avatar = getAvatarUrl($user->avatar); @endphp
                
                <div class="user-item" 
                     onclick="selectUser(this)"
                     data-id="{{ $user->id }}"
                     data-name="{{ $user->username }}"
                     data-avatar="{{ $avatar }}">
                     
                    <img src="{{ $avatar }}" class="avatar-img" onerror="this.src='https://img.icons8.com/color/48/user.png'">
                    <div>
                        <div class="user-name" style="font-weight:bold; color:#000;">{{ $user->username }}</div>
                        <div class="sub-text" style="font-size:12px; color:#000;">Online</div> </div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="chat-main">
            <div class="chat-header">
                <img id="current-avatar" src="https://img.icons8.com/color/48/user.png">
                <div>
                    <strong id="current-name" style="font-size:16px; color:#000;">Chọn người dùng</strong>
                    <div class="status-text">
                        <span class="status-dot"></span> Đang hoạt động
                    </div>
                </div>
            </div>

            <div id="chat-box" class="messages-area">
                <div style="text-align:center; margin-top:50px; color:#999;">
                    <img src="https://img.icons8.com/clouds/100/000000/chat.png" width="100"><br>
                    Chưa có tin nhắn nào được chọn
                </div>
            </div>

            <form id="chat-form" class="chat-footer">
                <input type="hidden" id="receiver-id">
                <input type="file" id="file-upload" style="display:none">
                <button type="button" onclick="document.getElementById('file-upload').click()" style="background:none;border:none;font-size:20px;">📎</button>
                
                <input type="text" id="chat-input" placeholder="Nhập tin nhắn..." autocomplete="off">
                
                <button type="submit" class="btn-send">Gửi</button>
            </form>
        </div>
    </div>
</div>

@vite('resources/js/app.js')

<script type="module">
    // CẤU HÌNH
    const Config = {
        adminId: @json(Auth::id()),
        adminAvatar: "{{ $currentAdminAvatar }}",
        defaultAvatar: "https://img.icons8.com/color/48/user.png",
        urls: {
            send: '{{ route('chat.send-message') }}',
            history: '{{ route('chat.getDataChatAdmin') }}',
            base: '{{ asset('') }}' 
        },
        csrf: '{{ csrf_token() }}'
    };

    let selectedUserAvatar = Config.defaultAvatar;
    let currentChannel = null;

    // 1. CHỌN USER
    window.selectUser = function(element) {
        document.querySelectorAll('.user-item').forEach(el => el.classList.remove('active'));
        element.classList.add('active');

        const uid = element.getAttribute('data-id');
        const uname = element.getAttribute('data-name');
        const uavatar = element.getAttribute('data-avatar');

        selectedUserAvatar = uavatar;
        document.getElementById('receiver-id').value = uid;
        document.getElementById('current-name').innerText = uname;
        
        const headerImg = document.getElementById('current-avatar');
        headerImg.src = uavatar;
        headerImg.onerror = function() { this.src = Config.defaultAvatar; };

        loadMessages(uid);
        listenPusher(uid);
    }

    // 2. TẢI TIN NHẮN
    async function loadMessages(userId) {
        const box = document.getElementById('chat-box');
        box.innerHTML = '<div style="text-align:center;padding:20px;color:#000;">Đang tải...</div>';

        try {
            const res = await axios.post(Config.urls.history, { userId: userId, _token: Config.csrf });
            box.innerHTML = '';
            const messages = res.data.data;
            
            if (messages && messages.length > 0) {
                messages.forEach(msg => appendMessage(msg));
            } else {
                box.innerHTML = '<div style="text-align:center;padding:20px;color:#999">Chưa có tin nhắn</div>';
            }
            scrollToBottom();
        } catch (e) { console.error(e); }
    }

    // 3. VẼ TIN NHẮN
    function appendMessage(data) {
        const box = document.getElementById('chat-box');
        const isMe = data.sender_id == Config.adminId;
        const side = isMe ? 'right' : 'left';
        const avatarUrl = isMe ? Config.adminAvatar : selectedUserAvatar;

        let mediaHtml = '';
        if (data.media_path) {
            const src = data.media_path.startsWith('blob:') ? data.media_path : Config.urls.base + data.media_path;
            mediaHtml = `<div style="margin-top:5px;"><img src="${src}" style="max-width:150px;border-radius:8px;"></div>`;
        }

        const time = new Date(data.created_at).toLocaleTimeString('vi-VN', {hour:'2-digit', minute:'2-digit'});

        const html = `
            <div class="message-row ${side}">
                <img src="${avatarUrl}" class="msg-avatar" onerror="this.src='${Config.defaultAvatar}'">
                <div class="msg-bubble">
                    <div>${data.message || ''}</div>
                    ${mediaHtml}
                    <span class="msg-time">${time}</span>
                </div>
            </div>
        `;

        if (box.innerText.includes('Chưa có tin nhắn')) box.innerHTML = '';
        box.insertAdjacentHTML('beforeend', html);
        scrollToBottom();
    }

    // 4. GỬI TIN
    document.getElementById('chat-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = document.getElementById('chat-input');
        const fileIn = document.getElementById('file-upload');
        const rid = document.getElementById('receiver-id').value;

        if (!rid) { alert("Vui lòng chọn khách hàng!"); return; }
        if (!input.value.trim() && !fileIn.files[0]) return;

        appendMessage({
            message: input.value,
            media_path: fileIn.files[0] ? URL.createObjectURL(fileIn.files[0]) : null,
            sender_id: Config.adminId,
            created_at: new Date()
        });

        const msg = input.value;
        const file = fileIn.files[0];
        input.value = '';
        fileIn.value = '';

        const fd = new FormData();
        fd.append('message', msg);
        fd.append('receiver_id', rid);
        if (file) fd.append('file', file);

        try {
            await axios.post(Config.urls.send, fd, {
                headers: { 'Content-Type': 'multipart/form-data', 'X-CSRF-TOKEN': Config.csrf }
            });
        } catch (e) { console.error(e); }
    });

    function listenPusher(uid) {
        if (currentChannel) Echo.leave(currentChannel.name);
        currentChannel = Echo.private(`chat.${uid}`);
        currentChannel.listen('.MessageSent', (e) => {
            if (e.sender.id != Config.adminId) appendMessage(e);
        });
    }

    function scrollToBottom() {
        const box = document.getElementById('chat-box');
        setTimeout(() => box.scrollTop = box.scrollHeight, 50);
    }

    // Tìm kiếm (Màu đen)
    document.getElementById('searchUser').addEventListener('keyup', function() {
        const val = this.value.toLowerCase();
        document.querySelectorAll('.user-item').forEach(el => {
            const name = el.getAttribute('data-name').toLowerCase();
            el.style.display = name.includes(val) ? 'flex' : 'none';
        });
    });
</script>
@endsection