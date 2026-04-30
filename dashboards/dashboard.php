<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$userName = htmlspecialchars($_SESSION['name']);
$userRole = htmlspecialchars(ucfirst($_SESSION['role']));
$userEmail = htmlspecialchars($_SESSION['email']);
?>
<?php
$isDashboard = true;
require_once '../includes/core/db.php';

$stmt = $pdo->prepare("SELECT profile_photo FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userPhoto = $stmt->fetchColumn() ?: 'assets/img/default.jpeg';

$pageTitle = 'Dashboard - MedScape';
require_once '../includes/layout/header.php';
?>
<link rel="stylesheet" href="../assets/css/superadmin/superadmin.css">
</head>

<body>

    <div class="dashboard-container">
        <div class="header">
            <h1>MedScape</h1>
            <div class="header-actions">
                <?php if ($_SESSION['role'] !== 'superadmin'): ?>
                    <img src="../<?php echo htmlspecialchars($userPhoto); ?>" alt="Profile" class="header-profile-img"
                        onerror="this.src='../assets/img/default.jpeg'">
                <?php endif; ?>
                <a href="../logic/auth/logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <div class="welcome-message">
            Welcome back, <strong>
                <?php echo $userName; ?>
            </strong>!
        </div>

        <div class="user-info">
            <p><i class="fas fa-envelope"></i> <strong>Email:</strong>
                <?php echo $userEmail; ?>
            </p>
            <p><i class="fas fa-user-tag"></i> <strong>Account Type:</strong> <span class="role-badge">
                    <?php echo $userRole; ?>
                </span></p>
        </div>

        <!-- Role-specific content can go here -->
        <?php if ($userRole === 'Superadmin'): ?>
            <div class="superadmin-section"
                style="margin-top: 40px; padding-top: 30px; border-top: 2px solid var(--border-color);">
                <h3><i class="fas fa-hospital"></i> Hospital Management</h3>

                <div class="admin-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px;">
                    <!-- ADD HOSPITAL FORM -->
                    <div class="admin-card"
                        style="background: var(--bg-card); padding: 20px; border-radius: 12px; border: 1px solid var(--border-color);">
                        <h4>Add New Hospital</h4>
                        <form id="addHospitalForm" style="margin-top: 15px;">
                            <input type="hidden" name="action" value="add_hospital">
                            <div class="input-field"
                                style="margin-bottom: 10px; border: 1px solid var(--border-color); padding: 5px 15px; border-radius: 8px;">
                                <input type="text" name="name" placeholder="Hospital Name" required
                                    style="width: 100%; border: none; padding: 10px; background: transparent; color: var(--text-main);">
                            </div>
                            <div class="input-field"
                                style="margin-bottom: 10px; border: 1px solid var(--border-color); padding: 5px 15px; border-radius: 8px;">
                                <input type="email" name="email" placeholder="Email Address" required
                                    style="width: 100%; border: none; padding: 10px; background: transparent; color: var(--text-main);">
                            </div>
                            <div class="input-field"
                                style="margin-bottom: 10px; border: 1px solid var(--border-color); padding: 5px 15px; border-radius: 8px; display: flex; align-items: center;">
                                <input type="password" name="password" placeholder="Password" required
                                    style="flex: 1; border: none; padding: 10px; background: transparent; color: var(--text-main); outline: none;">
                                <i class="fas fa-eye toggle-password" style="cursor: pointer; color: var(--text-muted);" title="Toggle password visibility"></i>
                            </div>
                            <div class="input-field"
                                style="margin-bottom: 20px; border: 1px solid var(--border-color); padding: 5px 15px; border-radius: 8px;">
                                <input type="text" name="location" placeholder="Location" required
                                    style="width: 100%; border: none; padding: 10px; background: transparent; color: var(--text-main);">
                            </div>
                            <button type="submit" class="btn btn-admin"
                                style="width: 100%; padding: 12px; background: var(--primary-color); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Register
                                Hospital</button>
                        </form>
                    </div>

                    <!-- HOSPITAL LIST -->
                    <div class="admin-card"
                        style="background: var(--bg-card); padding: 20px; border-radius: 12px; border: 1px solid var(--border-color);">
                        <h4>Existing Hospitals</h4>
                        <div id="hospitalList" style="margin-top: 15px; max-height: 400px; overflow-y: auto;">
                            <?php
                            $hospitals = $pdo->query("SELECT h.id, u.name, h.location FROM hospitals h JOIN users u ON h.user_id = u.id")->fetchAll();
                            if (empty($hospitals)) {
                                echo "<p style='color: var(--text-muted);'>No hospitals registered yet.</p>";
                            } else {
                                foreach ($hospitals as $h) {
                                    echo "
                                    <div class='hospital-item' style='display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid var(--border-color);'>
                                        <div>
                                            <div style='font-weight: 600; color: var(--text-main);'>" . htmlspecialchars($h['name']) . "</div>
                                            <div style='font-size: 0.85em; color: var(--text-muted);'>" . htmlspecialchars($h['location']) . "</div>
                                        </div>
                                        <button onclick='deleteHospital(" . $h['id'] . ")' style='background: #ef4444; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; font-size: 0.85em;'>Delete</button>
                                    </div>";
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <script>
                    document.getElementById('addHospitalForm').addEventListener('submit', async (e) => {
                        e.preventDefault();
                        const formData = new FormData(e.target);
                        const response = await fetch('../logic/superadmin/actions.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            showToast(result.success, 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast(result.error, 'error');
                        }
                    });

                    async function deleteHospital(id) {
                        const confirmed = await showConfirm({
                            title: 'Delete Hospital?',
                            message: 'Are you sure you want to delete this hospital? This will also delete the hospital login account.',
                            confirmText: 'Delete',
                            type: 'danger'
                        });
                        if (!confirmed) return;
                        const formData = new FormData();
                        formData.append('action', 'delete_hospital');
                        formData.append('id', id);
                        const response = await fetch('../logic/superadmin/actions.php', {
                            method: 'POST',
                            body: formData
                        });
                        const result = await response.json();
                        if (result.success) {
                            showToast(result.success, 'success');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showToast(result.error, 'error');
                        }
                    }
                </script>
            </div>
        <?php else: ?>
            <script>window.location.href = '../' + "<?php echo strtolower($userRole); ?>" + '/dashboard.php';</script>
        <?php endif; ?>

    </div>

    <?php require_once '../includes/layout/footer.php'; ?>