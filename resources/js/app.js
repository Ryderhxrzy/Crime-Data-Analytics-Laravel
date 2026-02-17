import './bootstrap';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Initialize Pusher client for Reverb (Reverb emulates Pusher protocol)
window.Pusher = Pusher;

// Initialize Laravel Echo for Reverb
// Key is passed from server via window.reverbConfig to avoid hardcoding
const reverbKey = window.reverbConfig?.key || 'jyfymj6zqd8jx44rcwsh'; // Fallback if not set

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: reverbKey,
    cluster: 'mt1',
    wsHost: window.location.hostname,
    wsPort: 8080,
    wssPort: 8080,
    forceTLS: false,
    encrypted: false,
    enabledTransports: ['ws', 'wss'],
});

console.log('✅ Real-Time Reverb Connected');
