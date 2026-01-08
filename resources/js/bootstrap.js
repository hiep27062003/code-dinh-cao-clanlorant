/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    // KEY lấy từ file .env của bạn (REVERB_APP_KEY)
    key: 'bsmrlae6c5b2ccqzf6uk', 
    
    // Cấu hình Host/Port cứng
    wsHost: '127.0.0.1',
    wsPort: 8080,
    wssPort: 8080,
    
    // Tắt bảo mật SSL (quan trọng khi chạy localhost)
    forceTLS: false,
    encrypted: false,
    
    // Tắt thống kê để đỡ báo lỗi rác
    disableStats: true,
    
    // Chỉ dùng WebSocket
    enabledTransports: ['ws'],
});

console.log('--- Echo Configured: Connecting to Reverb at 127.0.0.1:8080 ---');

window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('Pusher connected successfully!');
});

window.Echo.connector.pusher.connection.bind('error', (err) => {
    console.error('Pusher connection error:', err);
});



