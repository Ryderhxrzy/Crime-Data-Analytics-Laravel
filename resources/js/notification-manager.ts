// Notification Component for Crime Management System
interface NotificationData {
    title: string;
    incidentData: {
        incident_title: string;
        category_name?: string;
        location?: string;
        id?: number;
        incident_date?: string;
        status?: string;
        clearance_status?: string;
    };
    eventType: string;
}

class NotificationManager {
    private static activeNotifications: Map<string, Notification> = new Map();

    static showIncidentNotification(title: string, incidentData: NotificationData['incidentData'], eventType: string): void {
        console.log(`🔔 NotificationManager.showIncidentNotification called (ID: ${incidentData.id}, Event: ${eventType})`);
        // Request permission if not granted
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    this.createDesktopNotification(title, incidentData, eventType);
                }
            });
        } else if ('Notification' in window && Notification.permission === 'granted') {
            this.createDesktopNotification(title, incidentData, eventType);
        }
    }

    private static createDesktopNotification(title: string, incidentData: NotificationData['incidentData'], eventType: string): void {
        try {
            const icon = this.getNotificationIcon(eventType);
            const dynamicLink = this.getDynamicLink(incidentData.id);
            
            // Build comprehensive notification body
            const body = this.buildNotificationBody(incidentData, eventType);
            
            const notification = new Notification(title, {
                body: body,
                icon: '/images/alertara.png',
                badge: '/images/alertara.png',
                tag: `crime-incident-${incidentData.id}-${eventType}`,
                requireInteraction: true,
                silent: false
            });
            
            // Auto-close after 8 seconds (increased from 5)
            setTimeout(() => {
                notification.close();
                this.activeNotifications.delete(`crime-incident-${incidentData.id}-${eventType}`);
            }, 8000);
            
            // Handle notification click with dynamic link
            notification.onclick = () => {
                // Open the incident in new tab or focus current window
                if (incidentData.id) {
                    window.open(dynamicLink, '_blank');
                } else {
                    window.focus();
                }
                notification.close();
                this.activeNotifications.delete(`crime-incident-${incidentData.id}-${eventType}`);
            };
            
            // Store reference for cleanup
            this.activeNotifications.set(`crime-incident-${incidentData.id}-${eventType}`, notification);
        } catch (error) {
            console.error('Error creating desktop notification:', error);
        }
    }

    private static buildNotificationBody(incidentData: NotificationData['incidentData'], eventType: string): string {
        const parts = [];
        
        // Main incident info
        parts.push(`📌 ${incidentData.incident_title}`);
        
        // Category and location
        if (incidentData.category_name || incidentData.location) {
            parts.push(`📍 ${incidentData.category_name || 'Unknown'} • ${incidentData.location || 'Unknown Location'}`);
        }
        
        // Status information
        if (incidentData.status || incidentData.clearance_status) {
            const status = incidentData.status === 'under_investigation' ? 'Under Investigation' : 
                          incidentData.status || 'Unknown';
            const clearance = incidentData.clearance_status === 'uncleared' ? 'Uncleared' : 
                            incidentData.clearance_status || 'Unknown';
            parts.push(`🏷️ Status: ${status} • Clearance: ${clearance}`);
        }
        
        // Date if available
        if (incidentData.incident_date) {
            const date = new Date(incidentData.incident_date).toLocaleDateString();
            parts.push(`📅 ${date}`);
        }
        
        // Action indicator
        const actionText = eventType === 'created' ? '🆕 New Report' : 
                          eventType === 'updated' ? '🔄 Updated' : 
                          eventType === 'deleted' ? '🗑️ Removed' : 'ℹ️ Notice';
        parts.push(actionText);
        
        return parts.join('\n');
    }

    private static getDynamicLink(incidentId?: number): string {
        // Get the base URL based on environment
        const isLocal = window.location.hostname === 'localhost' || 
                       window.location.hostname === '127.0.0.1' ||
                       window.location.hostname.includes('.local');
        
        const baseUrl = isLocal 
            ? `${window.location.protocol}//${window.location.host}`
            : 'https://crime-analytics.alertaraqc.com'; // Replace with actual production URL
        
        // Return link to specific incident or general crimes page
        return incidentId 
            ? `${baseUrl}/crimes#${incidentId}`
            : `${baseUrl}/crimes`;
    }

    private static getNotificationIcon(eventType: string): string {
        switch (eventType) {
            case 'created': return '✅';
            case 'updated': return '🔄';
            case 'deleted': return '🗑️';
            default: return 'ℹ️';
        }
    }
}

// Export for global access
if (typeof window !== 'undefined') {
    (window as any).NotificationManager = NotificationManager;
}
