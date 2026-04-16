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
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/superadmin/superadmin.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>assets/css/common/dashboard_wrapper.css">

    <script>
        // Shared Navigation Logic (UI Only - Overwritten by content scripts)
        window.switchTab = function(e, tabId) {
            document.querySelectorAll('.sidebar-menu li').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.pd-content, .dd-content, .hd-content, .content-section').forEach(c => c.classList.remove('active'));

            if (e && e.currentTarget && e.currentTarget.tagName === 'LI') {
                e.currentTarget.classList.add('active');
            } else {
                const sideItem = document.querySelector(`.sidebar-menu li[data-page="${tabId}"]`);
                if (sideItem) sideItem.classList.add('active');
            }

            const targetTab = document.getElementById(`tab-${tabId}`);
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
    </script>

    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-hand-holding-medical logo-icon"></i>
                <h2>MedScape <span><?php echo ucfirst($role); ?></span></h2>
            </div>
            <ul class="sidebar-menu">
                <?php foreach ($menuItems as $item): ?>
                    <li data-page="<?php echo $item['id']; ?>" class="<?php echo $item['active'] ? 'active' : ''; ?>"
                        onclick="<?php echo isset($item['onclick']) ? $item['onclick'] : "switchTab(event, '{$item['id']}')"; ?>">
                        <i class="<?php echo $item['icon']; ?>"></i> <span><?php echo $item['label']; ?></span>
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
                <div class="user-profile">
                    <span class="user-name"><?php echo htmlspecialchars($userName); ?></span>
                    <img src="<?php echo $basePath . ($userPhoto ?: 'assets/img/default.jpeg'); ?>" alt="Profile"
                        class="avatar" onerror="this.src='<?php echo $basePath; ?>assets/img/default.jpeg'">
                </div>
            </header>

            <div class="dashboard-content-area">
                <?php include $contentFile; ?>
            </div>
        </main>
    </div>

    <?php
}
?>