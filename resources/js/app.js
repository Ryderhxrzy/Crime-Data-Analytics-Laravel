import './bootstrap';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Initialize Pusher client for Reverb (Reverb emulates Pusher protocol)
window.Pusher = Pusher;

// Initialize Laravel Echo for Reverb
// Config is passed from server via window.reverbConfig (set in blade template)
const config = window.reverbConfig || {};
const reverbKey = config.key || 'jyfymj6zqd8jx44rcwsh'; // Fallback if not set
const reverbHost = config.host || window.location.hostname;
const reverbPort = config.port || 8080;
const reverbScheme = config.scheme || 'http';

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: reverbKey,
    wsHost: reverbHost,
    wsPort: reverbPort,
    wssPort: reverbScheme === 'https' ? 443 : reverbPort,
    forceTLS: reverbScheme === 'https',
    encrypted: reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
});

console.log('✅ Real-Time Reverb Connected');
