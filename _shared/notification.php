<script type="text/html" class="ew-js-template" data-name="notificationsDropdown" data-method="appendTo" data-target="#ew-navbar-end" data-seq="9">
    <li class="nav-item dropdown me-2">
        <!-- Notification Bell -->
        <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-bell"></i>
            <span class="position-absolute top-0 start-50 badge rounded-pill bg-danger" id="notificationBadge" style="display: none;">
                0
            </span>
        </a>
        <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end" aria-labelledby="notificationDropdown">
            <div class="d-flex justify-content-between align-items-center px-3 py-2">
                <span class="text-bold">Notifications</span>
                <button class="btn btn-xs btn-link text-sm" onclick="markAllNotificationsRead(event)">Mark all read</button>
            </div>
            <div class="dropdown-divider"></div>
            <div id="notificationList" style="max-height: 300px; overflow-y: auto;">
                <div class="d-flex justify-content-center py-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="dropdown-divider"></div>
            <a href="#" onclick="openNotificationsCenter(event)" class="dropdown-item dropdown-footer text-center">See All Notifications</a>
        </div>
    </li>
</script>

<script>
    // FIMS serves the notification API itself (app/routes/notifications.php, schema uac). Other apps that
    // include this shared bell without defining the FIMS Config() keep the UAC hub default.
    <?php if (function_exists('PHPMaker2024\FIMS\Config')) : ?>
    window.NOTIFY_API_BASE = window.NOTIFY_API_BASE || <?= json_encode(preg_replace('~/api$~', '', rtrim((string) \PHPMaker2024\FIMS\Config('API.FIMS_URL'), '/')) . '/api/notifications') ?>;
    <?php endif; ?>
    // Use window assignment to avoid re-declaration conflicts across pages
    if (typeof window.NotificationManager === 'undefined') {
        window.NotificationManager = {
            updateInterval: null,
            lastUpdate: null,
            centerBuilt: false,
            centerFilter: 'all',
            centerData: [],

            // Base URL of the notification API (the hub). An app overrides it before this script runs, e.g.
            //   window.NOTIFY_API_BASE = '/api/notifications';   (FIMS: app/routes/notifications.php)
            // The default keeps the original behaviour: the UAC hub.
            NOTIFY_API: window.NOTIFY_API_BASE || '/UAC/api/NotificationManagers',

            getJwtToken() {
                return (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) ? ew.API_JWT_TOKEN : '';
            },

            init() {
                this.updateNotifications();
                this.startAutoUpdate();
                this.setupEventListeners();
            },

            setupEventListeners() {
                // Listen for new notifications from MQTT
                document.addEventListener('appNotification', () => this.updateNotifications());

                // Handle visibility change
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        this.updateNotifications();
                        this.startAutoUpdate();
                    } else {
                        this.stopAutoUpdate();
                    }
                });
            },

            startAutoUpdate() {
                if (!this.updateInterval) {
                    this.updateInterval = setInterval(() => this.updateNotifications(), 30000);
                }
            },

            stopAutoUpdate() {
                if (this.updateInterval) {
                    clearInterval(this.updateInterval);
                    this.updateInterval = null;
                }
            },

            async updateNotifications() {
                try {
                    const response = await fetch(`${this.NOTIFY_API}/recent`, {
                        credentials: 'include',
                        headers: {
                            'X-Authorization': 'Bearer ' + this.getJwtToken(),
                            'Cache-Control': 'no-cache'
                        }
                    });

                    if (!response.ok) throw new Error('Network response was not ok');

                    const result = await response.json();
                    if (!result.success) throw new Error(result.message || 'Error fetching notifications');

                    this.updateUI(result.data);
                    this.lastUpdate = new Date();

                } catch (error) {
                    console.error('Error updating notifications:', error);
                    this.showErrorState();
                }
            },

            updateUI(notifications) {
                const notificationList = document.getElementById('notificationList');
                const badge = document.getElementById('notificationBadge');

                if (!notificationList || !badge) return;

                // Update badge
                const unreadCount = notifications.filter(n => !n.read_by_user).length;
                badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                badge.style.display = unreadCount > 0 ? 'block' : 'none';

                // Show max 10 notifications in dropdown
                const displayNotifications = notifications.slice(0, 10);

                if (displayNotifications.length === 0) {
                    notificationList.innerHTML = `
                        <div class="dropdown-item text-center text-muted py-3">
                            <i class="fas fa-bell-slash mb-2"></i>
                            <p class="mb-0">No notifications</p>
                        </div>
                    `;
                    return;
                }

                notificationList.innerHTML = displayNotifications.map(notification => `
                    <div class="dropdown-item ${notification.read_by_user ? 'fw-light' : 'fw-semibold'}"
                        data-notification-id="${this.esc(notification.id)}"
                        data-notification-link="${this.esc(notification.link || '#')}"
                        role="button"
                        style="cursor:pointer; font-size:.825rem;">
                        <div class="d-flex align-items-center">
                            <div class="me-2">
                                <i class="fas fa-${this.getIcon(notification.type)} ${this.getIconColor(notification.type)}"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="text-sm ${notification.read_by_user ? 'text-muted' : 'text-dark'}">${this.esc(notification.subject)}</div>
                                <div class="text-xs text-truncate" style="max-width: 200px;">${this.esc(notification.body || '')}</div>
                                <div class="text-xs text-muted d-flex justify-content-between align-items-center mt-1">
                                    <span>${notification.time_ago || ''}</span>
                                    <span class="badge bg-${this.getSourceColor(notification.from_system)}" style="font-size:.65rem;">${this.getSourceLabel(notification.from_system)}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');

                // Add click event listeners to notification items
                notificationList.querySelectorAll('.dropdown-item[data-notification-id]').forEach(item => {
                    item.addEventListener('click', async (e) => {
                        e.preventDefault();
                        const notificationId = item.dataset.notificationId;
                        const link = item.dataset.notificationLink;
                        await this.markAsRead(e, notificationId);
                        this.go(link);
                    });
                });
            },

            async markAsRead(event, notificationId) {
                try {
                    const response = await fetch(`${this.NOTIFY_API}/mark-read`, {
                        method: 'POST',
                        credentials: 'include',
                        headers: {
                            'X-Authorization': 'Bearer ' + this.getJwtToken(),
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ id: notificationId })
                    });

                    if (!response.ok) throw new Error('Network response was not ok');

                    const result = await response.json();
                    if (!result.success) throw new Error('Failed to mark notification as read');

                    this.updateNotifications();

                } catch (error) {
                    console.error('Error marking notification as read:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to mark notification as read',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            },

            async markAllRead(event) {
                event.preventDefault();
                event.stopPropagation();

                try {
                    const response = await fetch(`${this.NOTIFY_API}/mark-all-read`, {
                        method: 'POST',
                        credentials: 'include',
                        headers: { 'X-Authorization': 'Bearer ' + this.getJwtToken() }
                    });

                    if (!response.ok) throw new Error('Network response was not ok');

                    const result = await response.json();
                    if (!result.success) throw new Error('Failed to mark all notifications as read');

                    this.updateNotifications();

                } catch (error) {
                    console.error('Error marking all notifications as read:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to mark all notifications as read',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                }
            },

            showErrorState() {
                const notificationList = document.getElementById('notificationList');
                if (!notificationList) return;
                notificationList.innerHTML = `
                    <div class="dropdown-item text-center text-danger py-3">
                        <i class="fas fa-exclamation-circle mb-2"></i>
                        <p class="mb-0">Error loading notifications</p>
                    </div>
                `;
            },

            getIcon(type) {
                switch (type) {
                    case 'system':     return 'cog';
                    case 'user':       return 'user';
                    case 'userlevel':  return 'users';
                    case 'office':     return 'building';
                    case 'usergroup':  return 'user-friends';
                    case 'all':        return 'globe';
                    default:           return 'bell';
                }
            },

            getIconColor(type) {
                switch (type) {
                    case 'system':     return 'text-info';
                    case 'user':       return 'text-success';
                    case 'userlevel':  return 'text-primary';
                    case 'office':     return 'text-danger';
                    case 'usergroup':  return 'text-teal';
                    case 'all':        return 'text-purple';
                    default:           return 'text-warning';
                }
            },

            getSourceColor(source) {
                switch (source?.toLowerCase()) {
                    case 'uac':              return 'primary';
                    case 'fmd':              return 'warning';
                    case 'treasury':         return 'teal';
                    case 'accounting-gad':   return 'info';
                    case 'accounting-bapd':  return 'indigo';
                    case 'accounting-pcds':  return 'purple';
                    case 'accounting-dcd':   return 'secondary';
                    case 'system':           return 'dark';
                    default:                 return 'secondary';
                }
            },

            getSourceLabel(source) {
                switch (source?.toLowerCase()) {
                    case 'uac':              return 'UAC';
                    case 'fmd':              return 'FMD';
                    case 'treasury':         return 'Treasury';
                    case 'accounting-gad':   return 'Acctg-GAD';
                    case 'accounting-bapd':  return 'Acctg-BAPD';
                    case 'accounting-pcds':  return 'Acctg-PCDS';
                    case 'accounting-dcd':   return 'Acctg-DCD';
                    case 'system':           return 'System';
                    default:                 return source || 'System';
                }
            },

            // ── "My Notifications" full-screen center (opened from "See All") ──
            // Self-contained overlay appended to <body> once; reads the same UAC
            // API the bell uses, just with a larger limit for a full list.
            // Only relative, same-origin links are followed (plan §7/§10). Returns true when it navigated.
            go(link) {
                if (!link || link === '#' || link === 'null') return false;
                if (link.charAt(0) !== '/' || link.startsWith('//')) return false;
                window.location.href = link;
                return true;
            },

            esc(s) {
                return String(s == null ? '' : s).replace(/[&<>"']/g, c =>
                    ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
            },

            openCenter(event) {
                if (event) { event.preventDefault(); event.stopPropagation(); }
                this.buildCenter();
                this.centerFilter = 'all';
                this.syncTabs();
                document.getElementById('mynOverlay').classList.add('show');
                this.fetchCenter();
            },

            closeCenter() {
                const o = document.getElementById('mynOverlay');
                if (o) o.classList.remove('show');
            },

            syncTabs() {
                document.querySelectorAll('#mynOverlay .myn-tab').forEach(t =>
                    t.classList.toggle('active', t.dataset.filter === this.centerFilter));
            },

            buildCenter() {
                if (this.centerBuilt) return;

                const style = document.createElement('style');
                style.textContent = `
                    .myn-overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:20000;display:none;align-items:flex-start;justify-content:center;padding:5vh 16px;}
                    .myn-overlay.show{display:flex;}
                    .myn-card{background:#fff;width:100%;max-width:640px;max-height:88vh;border-radius:14px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.35);}
                    .myn-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;background:linear-gradient(135deg,#0d47a1,#1976d2);color:#fff;}
                    .myn-title{font-weight:700;font-size:1rem;display:flex;align-items:center;gap:8px;}
                    .myn-actions{display:flex;align-items:center;gap:8px;}
                    .myn-btn{background:rgba(255,255,255,.15);color:#fff;border:none;border-radius:6px;padding:6px 10px;font-size:.78rem;cursor:pointer;display:inline-flex;align-items:center;gap:6px;}
                    .myn-btn:hover{background:rgba(255,255,255,.28);}
                    .myn-close{background:transparent;border:none;color:#fff;font-size:1.6rem;line-height:1;cursor:pointer;padding:0 4px;}
                    .myn-filter{display:flex;gap:6px;padding:10px 16px;border-bottom:1px solid #eef2f7;}
                    .myn-tab{border:1px solid #d0d9e8;background:#fff;color:#475569;border-radius:20px;padding:4px 14px;font-size:.78rem;cursor:pointer;}
                    .myn-tab.active{background:#0d47a1;color:#fff;border-color:#0d47a1;}
                    .myn-list{overflow-y:auto;padding:4px 0;}
                    .myn-item{display:flex;gap:12px;padding:12px 18px;border-bottom:1px solid #f1f5f9;cursor:pointer;}
                    .myn-item:hover{background:#f8fafc;}
                    .myn-item.unread{background:#eff6ff;}
                    .myn-item.unread:hover{background:#e0efff;}
                    .myn-ic{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#f1f5f9;flex:0 0 auto;}
                    .myn-body{flex:1;min-width:0;}
                    .myn-subj{font-weight:600;font-size:.88rem;color:#0f172a;}
                    .myn-item.read .myn-subj{font-weight:500;color:#64748b;}
                    .myn-text{font-size:.8rem;color:#64748b;margin-top:2px;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;}
                    .myn-meta{display:flex;justify-content:space-between;align-items:center;margin-top:6px;font-size:.7rem;color:#94a3b8;gap:8px;}
                    .myn-badge{padding:2px 8px;border-radius:20px;font-size:.65rem;font-weight:700;background:#e2e8f0;color:#475569;white-space:nowrap;}
                    .myn-empty,.myn-loading{padding:44px 18px;text-align:center;color:#94a3b8;font-size:.9rem;}
                `;
                document.head.appendChild(style);

                const overlay = document.createElement('div');
                overlay.className = 'myn-overlay';
                overlay.id = 'mynOverlay';
                overlay.innerHTML = `
                    <div class="myn-card" role="dialog" aria-modal="true" aria-label="My Notifications">
                        <div class="myn-head">
                            <div class="myn-title"><i class="fas fa-bell"></i> My Notifications</div>
                            <div class="myn-actions">
                                <button class="myn-btn" id="mynMarkAll"><i class="fas fa-check-double"></i> Mark all read</button>
                                <button class="myn-close" id="mynClose" aria-label="Close">&times;</button>
                            </div>
                        </div>
                        <div class="myn-filter">
                            <button class="myn-tab active" data-filter="all">All</button>
                            <button class="myn-tab" data-filter="unread">Unread</button>
                        </div>
                        <div class="myn-list" id="mynList"></div>
                    </div>`;
                document.body.appendChild(overlay);

                overlay.addEventListener('click', e => { if (e.target === overlay) this.closeCenter(); });
                overlay.querySelector('#mynClose').addEventListener('click', () => this.closeCenter());
                overlay.querySelector('#mynMarkAll').addEventListener('click', async (e) => {
                    await this.markAllRead(e);
                    this.fetchCenter();
                });
                overlay.querySelectorAll('.myn-tab').forEach(tab => {
                    tab.addEventListener('click', () => {
                        this.centerFilter = tab.dataset.filter;
                        this.syncTabs();
                        this.renderCenter(this.centerData || []);
                    });
                });
                document.addEventListener('keydown', e => {
                    if (e.key === 'Escape') this.closeCenter();
                });

                this.centerBuilt = true;
            },

            async fetchCenter() {
                const list = document.getElementById('mynList');
                if (list) list.innerHTML = `<div class="myn-loading"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>`;
                try {
                    const response = await fetch(`${this.NOTIFY_API}/recent?limit=200`, {
                        credentials: 'include',
                        headers: { 'X-Authorization': 'Bearer ' + this.getJwtToken(), 'Cache-Control': 'no-cache' }
                    });
                    if (!response.ok) throw new Error('Network response was not ok');
                    const result = await response.json();
                    if (!result.success) throw new Error(result.message || 'Error fetching notifications');
                    this.centerData = result.data || [];
                    this.renderCenter(this.centerData);
                } catch (error) {
                    console.error('Error loading notifications center:', error);
                    if (list) list.innerHTML = `<div class="myn-empty text-danger"><i class="fas fa-exclamation-circle mb-2"></i><div>Failed to load notifications</div></div>`;
                }
            },

            renderCenter(notifications) {
                const list = document.getElementById('mynList');
                if (!list) return;
                let items = notifications || [];
                if (this.centerFilter === 'unread') items = items.filter(n => !n.read_by_user);
                if (!items.length) {
                    list.innerHTML = `<div class="myn-empty"><i class="fas fa-bell-slash fa-lg mb-2"></i><div>No ${this.centerFilter === 'unread' ? 'unread ' : ''}notifications</div></div>`;
                    return;
                }
                list.innerHTML = items.map(n => `
                    <div class="myn-item ${n.read_by_user ? 'read' : 'unread'}" data-id="${this.esc(n.id)}" data-link="${this.esc(n.link || '#')}">
                        <div class="myn-ic"><i class="fas fa-${this.getIcon(n.type)} ${this.getIconColor(n.type)}"></i></div>
                        <div class="myn-body">
                            <div class="myn-subj">${this.esc(n.subject)}</div>
                            <div class="myn-text">${this.esc(n.body || '')}</div>
                            <div class="myn-meta">
                                <span>${this.esc(n.time_ago || '')}</span>
                                <span class="myn-badge">${this.esc(this.getSourceLabel(n.from_system))}</span>
                            </div>
                        </div>
                    </div>`).join('');
                list.querySelectorAll('.myn-item').forEach(el => {
                    el.addEventListener('click', async () => {
                        const id = el.dataset.id;
                        const link = el.dataset.link;
                        await this.markAsRead(null, id);
                        if (this.go(link)) {
                            /* navigated */
                        } else {
                            this.fetchCenter();
                        }
                    });
                });
            }
        };
    }

    // Initialize when document is ready
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize NotificationManager (correct name, no trailing 's')
        window.NotificationManager.init();

        // Theme observer for app icons
        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-bs-theme') {
                    updateAppIcons();
                }
            });
        });

        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['data-bs-theme']
        });

        function updateAppIcons() {
            const isDarkMode = document.documentElement.getAttribute('data-bs-theme') === 'dark';
            document.querySelectorAll('.app-icon').forEach(icon => {
                const src = icon.getAttribute('src');
                if (isDarkMode) {
                    icon.setAttribute('src', src.replace('.png', '-light.png'));
                } else {
                    icon.setAttribute('src', src.replace('-light.png', '.png'));
                }
            });
        }
    });

    // Global helper called from the "Mark all read" button in the template
    function markAllNotificationsRead(event) {
        window.NotificationManager.markAllRead(event);
    }

    // Global helper called from the "See All Notifications" footer link
    function openNotificationsCenter(event) {
        window.NotificationManager.openCenter(event);
    }
</script>