<?php
/**
 * Shared Dashboard Layout Component
 * Provides Sidebar and Topbar for Patient, Doctor, and Hospital roles.
 */
function renderDashboardLayout($role, $userName, $userPhoto, $menuItems, $contentFile)
{
    global $pdo;
    $basePath = '../';
    ?>
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/common/dashboard_wrapper.css">

    <script>
        // Mobile Sidebar Toggle
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('active');
            document.querySelector('.sidebar-overlay').classList.toggle('active');
        }

        // Shared Navigation Logic
        window.switchTab = function(e, tabId) {
            // Close mobile sidebar if open
            const sidebar = document.querySelector('.sidebar');
            if (sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                document.querySelector('.sidebar-overlay').classList.remove('active');
            }
            
            document.querySelectorAll('.sidebar-menu li').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.pd-content, .dd-content, .hd-content, .ad-content, .content-section, .page-section').forEach(c => c.classList.remove('active'));

            let label = 'Portal';
            if (e && e.currentTarget && e.currentTarget.tagName === 'LI') {
                e.currentTarget.classList.add('active');
                label = e.currentTarget.querySelector('span').textContent;
            } else {
                const sideItem = document.querySelector(`.sidebar-menu li[data-page="${tabId}"]`);
                if (sideItem) {
                    sideItem.classList.add('active');
                    label = sideItem.querySelector('span').textContent;
                }
            }

            const breadcrumbCurrent = document.getElementById('breadcrumb-current');
            if (breadcrumbCurrent) breadcrumbCurrent.textContent = label;

            const targetTab = document.getElementById(`tab-${tabId}`) || document.getElementById(tabId);
            if (targetTab) {
                targetTab.classList.add('active');
                window.activePage = tabId;
            }
        };

        function generateTimeSlots(startTime, endTime) {
            const slots = [];
            if (!startTime || !endTime) return slots;
            let startParts = startTime.split(':');
            let endParts = endTime.split(':');
            let sDate = new Date(); sDate.setHours(startParts[0], startParts[1] || 0, 0);
            let eDate = new Date(); eDate.setHours(endParts[0], endParts[1] || 0, 0);
            while (sDate < eDate) {
                let hh = sDate.getHours().toString().padStart(2, '0');
                let mm = sDate.getMinutes().toString().padStart(2, '0');
                slots.push(`${hh}:${mm}`);
                sDate.setMinutes(sDate.getMinutes() + 30);
            }
            return slots;
        }

        function escapeQuotes(str) {
            if (!str) return '';
            return str.replace(/'/g, "\\'").replace(/"/g, '\\"');
        }

        // --- Notification Logic ---
        async function fetchNotifications() {
            try {
                const res = await fetch('<?php echo $basePath; ?>logic/common/notifications.php?action=get');
                const result = await res.json();
                if (result.status === 'success') {
                    renderNotifications(result.data);
                }
            } catch (e) { console.error('Notif fetch error', e); }
        }

        function renderNotifications(notifs) {
            const list = document.getElementById('notifList');
            const badge = document.getElementById('notifBadge');
            if (!list || !badge) return;

            const unreadCount = notifs.filter(n => n.is_read == 0).length;
            if (unreadCount > 0) {
                badge.innerText = unreadCount;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }

            if (notifs.length === 0) {
                list.innerHTML = '<div class="notif-empty">No notifications yet</div>';
                return;
            }

            list.innerHTML = notifs.map(n => `
                <div class="notif-item ${n.is_read == 0 ? 'unread' : ''}" onclick="markNotifRead(${n.id})">
                    <h5>${n.title}</h5>
                    <p>${n.message}</p>
                    <span class="notif-time">${getTimeAgo(n.created_at)}</span>
                </div>
            `).join('');
        }

        async function markNotifRead(id) {
            const fd = new FormData();
            fd.append('id', id);
            await fetch('<?php echo $basePath; ?>logic/common/notifications.php?action=mark_read', { method: 'POST', body: fd });
            fetchNotifications();
        }

        async function markAllNotifRead() {
            await fetch('<?php echo $basePath; ?>logic/common/notifications.php?action=mark_read', { method: 'POST' });
            fetchNotifications();
        }

        function getTimeAgo(date) {
            const seconds = Math.floor((new Date() - new Date(date)) / 1000);
            if (seconds < 60) return "just now";
            const minutes = Math.floor(seconds / 60);
            if (minutes < 60) return minutes + "m ago";
            const hours = Math.floor(minutes / 60);
            if (hours < 24) return hours + "h ago";
            return new Date(date).toLocaleDateString();
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Initial load and periodic refresh
            fetchNotifications();
            setInterval(fetchNotifications, 60000); // Refresh every minute
        });
    </script>

    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-hand-holding-medical logo-icon"></i>
                <h2>MedScape <span><?php echo ucfirst($role); ?></span></h2>
                <button class="mobile-close" onclick="toggleSidebar()">&times;</button>
            </div>
            <ul class="sidebar-menu">
                <?php foreach ($menuItems as $item): ?>
                    <li data-page="<?php echo $item['id']; ?>" class="<?php echo $item['active'] ? 'active' : ''; ?>"
                        onclick="<?php echo isset($item['onclick']) ? $item['onclick'] : "switchTab(event, '{$item['id']}')"; ?>">
                        <span><?php echo $item['label']; ?></span>
                        <i class="fas fa-chevron-right nav-arrow"></i>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="sidebar-footer">
                <a href="<?php echo $basePath; ?>logic/auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="top-bar">
                <div class="top-bar-left">
                    <button class="hamburger-menu" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="breadcrumb">
                        <span id="breadcrumb-parent">Dashboard</span> 
                        <i class="fas fa-chevron-right separator"></i> 
                        <span id="breadcrumb-current"><?php echo ucfirst($role); ?> Portal</span>
                    </div>
                </div>
                
                <div style="display: flex; align-items: center; gap: 10px;">
                    <!-- Notification Bell -->
                    <div class="notification-wrapper">
                        <div class="notification-bell" id="notifBell">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge" id="notifBadge">0</span>
                        </div>
                        <div class="notification-dropdown" id="notifDropdown">
                            <div class="notif-header">
                                <h4>Notifications</h4>
                                <button onclick="markAllNotifRead()">Mark all as read</button>
                            </div>
                            <div class="notif-list" id="notifList">
                                <div class="notif-empty">No new notifications</div>
                            </div>
                        </div>
                    </div>
                <div class="user-profile-wrapper">
                    <div class="user-profile" id="profileToggle">
                        <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                        <img src="<?php echo $basePath . ($userPhoto ?: 'assets/img/default.jpeg'); ?>" alt="Profile"
                            class="avatar" onerror="this.src='<?php echo $basePath; ?>assets/img/default.jpeg'">
                        <i class="fas fa-chevron-down dropdown-icon"></i>
                    </div>
                    <div class="profile-dropdown" id="profileDropdown">
                        <div class="dropdown-header" style="padding: 10px 14px; border-bottom: 1px solid var(--border-color); margin-bottom: 5px;">
                            <div style="font-weight: 700; color: var(--text-main); font-size: 0.9rem;"><?php echo htmlspecialchars($userName); ?></div>
                            <div style="color: var(--text-muted); font-size: 0.8rem;"><?php echo ucfirst($role); ?></div>
                        </div>
                        <?php 
                            $profileTab = ($role === 'doctor') ? 'settings' : 'profile';
                        ?>
                        <a href="#" onclick="switchTab(event, '<?php echo $profileTab; ?>'); return false;">
                            <i class="fas fa-user-circle"></i> Edit Profile
                        </a>
                        <a href="<?php echo $basePath; ?>reset_password.php">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="<?php echo $basePath; ?>logic/auth/logout.php" class="logout-link">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </header>

            <?php if (isset($_SESSION['is_verified']) && $_SESSION['is_verified'] == 0): ?>
                <div class="verification-banner" style="background: #fff3cd; color: #856404; padding: 15px 25px; margin: 20px; border-radius: 10px; border-left: 5px solid #ffeeba; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <span><strong>Verify your email!</strong> Please check your inbox to confirm your email address. Some features may be restricted until verified.</span>
                    </div>
                    <form action="<?php echo $basePath; ?>resend_verification.php" method="POST" style="margin: 0;">
                        <button type="submit" style="background: #856404; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; font-weight: 600; transition: all 0.3s ease;">Resend Email</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="dashboard-content-area">
                <?php include $contentFile; ?>
            </div>
        </main>
    </div>

    <?php
}
?>