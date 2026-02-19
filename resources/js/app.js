import './bootstrap';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Initialize Pusher client for Reverb (Reverb emulates Pusher protocol)
window.Pusher = Pusher;

// Initialize Laravel Echo for Reverb
// Config is passed from server via window.reverbConfig (set in blade template)
const config = window.reverbConfig || {};
const reverbKey = config.key || 'localkey'; // Fallback should match .env REVERB_APP_KEY
const reverbHost = config.host || window.location.hostname;
const reverbPort = config.port || 8080;
const reverbScheme = config.scheme || 'http';

console.log('🔧 Reverb Config:', {
    key: reverbKey,
    host: reverbHost,
    port: reverbPort,
    scheme: reverbScheme,
    wsUrl: `${reverbScheme}://${reverbHost}:${reverbPort}`
});

try {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: reverbKey,
        cluster: 'mt1', // Required by Pusher.js SDK
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbScheme === 'https' ? 443 : reverbPort,
        forceTLS: reverbScheme === 'https',
        encrypted: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
        path: '/reverb', // Add the Reverb path
    });

    console.log('✅ Real-Time Reverb Initialized');

    // Set up connection listeners immediately (don't wait)
    if (window.Echo && window.Echo.connector) {
        const socket = window.Echo.connector.socket;

        console.log('🎯 Setting up WebSocket listeners...');

        // Set up connection listeners
        socket?.on('connect', () => {
            console.log('🔗 WebSocket Connected to Reverb!');
        });
        socket?.on('connect_error', (error) => {
            console.error('❌ WebSocket Connection Error:', error);
            console.error('Error details:', {
                message: error?.message,
                type: error?.type,
                data: error?.data,
            });
        });
        socket?.on('error', (error) => {
            console.error('❌ WebSocket Error:', error);
        });
        socket?.on('disconnect', (reason) => {
            console.warn('⚠️ WebSocket Disconnected:', reason);
        });

        // Check connection status after delays to see progress
        let checkCount = 0;
        const connectionChecker = setInterval(() => {
            checkCount++;
            const status = {
                connected: socket?.connected || false,
                readyState: socket?.io?.engine?.readyState,
                id: socket?.id || 'not-connected',
                transport: socket?.io?.engine?.transport?.name || 'unknown',
            };

            console.log(`📊 WebSocket Status (check ${checkCount}):`, status);

            if (status.connected) {
                console.log('✅ WebSocket is now connected!');
                clearInterval(connectionChecker);
            }

            // Stop checking after 30 seconds if not connected
            if (checkCount > 30) {
                console.error('❌ WebSocket failed to connect after 30 seconds');
                clearInterval(connectionChecker);
            }
        }, 1000);
    }

} catch (error) {
    console.error('❌ Failed to initialize Echo:', error);
    window.Echo = null; // Mark as failed
}
