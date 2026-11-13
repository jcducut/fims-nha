// /_shared/mqtt-notification.js

var AppNotificationClient = (function() {
    var instance;
    var reconnectTimeout = 2000;
    var maxReconnectAttempts = 5;
    var reconnectCount = 0;
    var client = null;
    var subscriptions = [];

    function createInstance() {
        return {
            initialize: function() {
                // Check for JWT token
                if (!ew.API_JWT_TOKEN) {
                    console.log('No JWT token available, skipping MQTT connection');
                    return;
                }

                // Parse JWT token to get user info
                try {
                    const token = parseJwt(ew.API_JWT_TOKEN);
                    if (!token.values || !token.values.userid) {
                        console.log('Invalid JWT token structure or no user ID found');
                        return;
                    }

                    // Load MQTT client if not already loaded
                    if (typeof mqtt === 'undefined') {
                        var script = document.createElement('script');
                        script.src = '/node_modules/mqtt/dist/mqtt.min.js';
                        script.onload = function() {
                            connectMqtt(token.values);
                        };
                        document.head.appendChild(script);
                    } else {
                        connectMqtt(token.values);
                    }
                } catch (e) {
                    console.error('Error parsing JWT token:', e);
                }
            },

            disconnect: function() {
                if (client && client.connected) {
                    unsubscribeFromAll();
                    client.end();
                }
            }
        };
    }

    function parseJwt(token) {
        try {
            const base64Url = token.split('.')[1];
            const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
            const jsonPayload = decodeURIComponent(atob(base64).split('').map(function(c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
            return JSON.parse(jsonPayload);
        } catch (e) {
            console.error('Error parsing JWT:', e);
            return {};
        }
    }

    function connectMqtt(userInfo) {
        // Clean up existing connection if any
        if (client) {
            client.end(true);
        }

        const clientId = `dict_${userInfo.userid}_${Math.random().toString(16).substr(2, 8)}`;
        
        // MQTT connection options
        const options = {
            clientId: clientId,
            clean: true,
            reconnectPeriod: 0, // We'll handle reconnection manually
            connectTimeout: 30000,
            username: userInfo.username,
            password: ew.API_JWT_TOKEN,
            keepalive: 60,
            rejectUnauthorized: false, // Important for self-signed certificates
            protocol: 'wss', // Explicitly set WebSocket Secure protocol
            protocolVersion: 4,
            protocolId: 'MQTT'
        };

        // Construct the WebSocket URL
        const wsUrl = 'wss://mqtt.itbsstudio.com:2096/mqtt'; // Note the /mqtt path

        try {
            console.log('Attempting MQTT connection...');
            client = mqtt.connect(wsUrl, options);

            client.on('connect', function() {
                console.log('MQTT Connected successfully');
                reconnectCount = 0;
                subscribeToTopics(userInfo);
            });

            client.on('message', function(topic, message) {
                try {
                    const data = JSON.parse(message.toString());
                    const parts = topic.split('/');

                    // app/events/... → real-time UI signals, not user notifications
                    if (parts[0] === 'app' && parts[1] === 'events') {
                        data._eventType = parts[2];
                        data._target    = parts[3];
                        data._topic     = topic;
                        document.dispatchEvent(new CustomEvent('appEvent', { detail: data }));
                        return;
                    }

                    // app/notifications/... → original notification display
                    displayNotification(data);
                } catch (e) {
                    console.error('Error processing notification:', e);
                }
            });

            client.on('error', function(err) {
                console.error('MQTT Error:', err);
                handleReconnect(userInfo);
            });

            client.on('close', function() {
                console.log('MQTT Connection closed');
                handleReconnect(userInfo);
            });

            client.on('offline', function() {
                console.log('MQTT Client went offline');
                handleReconnect(userInfo);
            });

            // Add connection status logging
            client.on('reconnect', function() {
                console.log('MQTT Client trying to reconnect');
            });

            client.on('disconnect', function() {
                console.log('MQTT Client disconnected');
            });

        } catch (e) {
            console.error('MQTT Connection error:', e);
            handleReconnect(userInfo);
        }
    }

    function handleReconnect(userInfo) {
        if (reconnectCount < maxReconnectAttempts) {
            reconnectCount++;
            console.log(`Attempting to reconnect (${reconnectCount}/${maxReconnectAttempts})`);
            setTimeout(function() {
                connectMqtt(userInfo);
            }, reconnectTimeout * reconnectCount);
        } else {
            console.log('Max reconnection attempts reached');
        }
    }

    function subscribeToTopics(userInfo) {
        if (!client || !client.connected) return;

        // Clear existing subscriptions
        unsubscribeFromAll();
        subscriptions = [];

        // Topic scheme = NotificationService::topic(): app/notifications/{type}/{target}
        // (audience types: all | system | user | userlevel | office | usergroup — see
        // _shared/NOTIFICATION-IMPLEMENTATION-PLAN.md §3.2 / §6.1). Polling covers anything not subscribed here.
        subscribe('app/notifications/all/all');
        subscribe(`app/notifications/user/${userInfo.userid}`);

        if (userInfo.userlevel) {
            subscribe(`app/notifications/userlevel/${userInfo.userlevel}`);
        }

        // Users whose supervisor is this user's parent (reports_to_user_id)
        if (userInfo.parentuserid) {
            subscribe(`app/notifications/usergroup/${userInfo.parentuserid}`);
        }

        // Real-time DataTable events (published by backend via NotificationManager::publishEvent)
        subscribe('app/events/#');

        console.log('Subscribed to notification topics for user:', userInfo.username);
    }
    function subscribe(topic) {
        if (client && client.connected) {
            client.subscribe(topic);
            subscriptions.push(topic);
        }
    }

    function unsubscribeFromAll() {
        if (client && client.connected) {
            subscriptions.forEach(topic => client.unsubscribe(topic));
            subscriptions = [];
        }
    }

    function displayNotification(notification) {
        // First update notification badge/counter if exists
        updateNotificationCounter();

        // Then show the notification
        if (Notification.permission === "granted") {
            showNativeNotification(notification);
        } else if (Notification.permission !== "denied") {
            Notification.requestPermission().then(function(permission) {
                if (permission === "granted") {
                    showNativeNotification(notification);
                } else {
                    showFallbackNotification(notification);
                }
            });
        } else {
            showFallbackNotification(notification);
        }

        // Store in localStorage for history (keep last 50)
        storeNotification(notification);

        // Trigger custom event for other parts of the application
        const event = new CustomEvent('appNotification', { detail: notification });
        document.dispatchEvent(event);
    }

    function updateNotificationCounter() {
        // Update notification badge if it exists
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            const count = parseInt(badge.textContent || '0') + 1;
            badge.textContent = count;
            badge.style.display = count > 0 ? 'inline' : 'none';
        }
    }

    function showNativeNotification(notification) {
        const options = {
            body: notification.body,
            icon: ew.PATH_BASE + 'images/notification-icon.png', // Use your project's icon
            tag: notification.id,
            data: notification
        };

        const notify = new Notification(notification.subject, options);
        notify.onclick = function() {
            if (notification.link) {
                window.location.href = notification.link;
            }
            notify.close();
        };
    }

    function showFallbackNotification(notification) {
        // Use SweetAlert2 if available (common in PHPMaker)
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                title: notification.subject,
                text: notification.body,
                icon: 'info',
                showCancelButton: notification.link ? true : false,
                confirmButtonText: notification.link ? 'View' : 'OK',
                cancelButtonText: 'Close'
            }).then((result) => {
                if (result.isConfirmed && notification.link) {
                    window.location.href = notification.link;
                }
            });
        } else if (typeof toastr !== 'undefined') {
            // Fallback to Toastr if SweetAlert2 is not available
            toastr.info(notification.body, notification.subject, {
                timeOut: 5000,
                closeButton: true,
                onclick: function() {
                    if (notification.link) {
                        window.location.href = notification.link;
                    }
                }
            });
        } else {
            // Final fallback to alert
            alert(`${notification.subject}\n${notification.body}`);
        }
    }

    function storeNotification(notification) {
        try {
            let notifications = JSON.parse(localStorage.getItem('appNotifications') || '[]');
            notifications.unshift({
                ...notification,
                receivedAt: new Date().toISOString()
            });
            notifications = notifications.slice(0, 50); // Keep only last 50
            localStorage.setItem('appNotifications', JSON.stringify(notifications));
        } catch (e) {
            console.error('Error storing notification:', e);
        }
    }

    return {
        getInstance: function() {
            if (!instance) {
                instance = createInstance();
            }
            return instance;
        }
    };
})();

// Initialize when document is ready. Prefer jQuery when available,
// but gracefully fall back to native DOM events when it is not.
function initAppNotificationClient() {
    AppNotificationClient.getInstance().initialize();
}

if (window.jQuery && typeof window.jQuery.fn === 'object') {
    window.jQuery(function() {
        initAppNotificationClient();
    });
} else if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAppNotificationClient);
} else {
    initAppNotificationClient();
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    AppNotificationClient.getInstance().disconnect();
});

// Add custom event listener for other scripts
document.addEventListener('appNotification', function(e) {
    // Other scripts can listen for this event
    // console.log('New notification:', e.detail);
});