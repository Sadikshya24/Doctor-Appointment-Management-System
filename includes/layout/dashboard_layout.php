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
                        <i class="fas fa-exclamation-triangle" style="font-size: 1.2rem;"></i>
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