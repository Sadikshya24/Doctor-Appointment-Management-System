<?php
session_start();
require_once 'includes/core/db.php';
$stmt = $pdo->query("SELECT h.id, u.name, h.location FROM hospitals h JOIN users u ON h.user_id = u.id");
$hospitalsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php $pageTitle = 'MedScape - Login';
require_once 'includes/layout/header.php'; ?>
<div class="auth-wrapper">
    <div class="auth-card">

        <div class="auth-header">
            <a href="index.php" class="back-link" style="position: absolute; top: 20px; left: 20px; text-decoration: none; color: var(--text-muted); font-size: 0.9rem; display: flex; align-items: center; gap: 5px;">
                <i class="fas fa-arrow-left"></i> Home
            </a>
            <h2>MedScape</h2>
            <p>Manage your doctor appointments easily</p>
        </div>


        <div class="auth-tabs">
            <button class="auth-tab active" data-target="login">Sign In</button>
            <button class="auth-tab" data-target="signup">Sign Up</button>
        </div>

        <!-- LOGIN FORM -->
        <div class="auth-form-container active" id="login">
            <form action="logic/auth/auth.php" method="POST">
                <input type="hidden" name="action" value="login">

                <div class="input-field">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Email"
                        pattern="[A-Za-z0-9]+@(gmail\.com|outlook\.com|yahoo\.com|hotmail\.com|yopmail\.com)"
                        required />
                </div>
                <div class="input-field password-field">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Password" required />
                    <i class="fas fa-eye toggle-password" title="Toggle password visibility"></i>
                </div>

                <div class="role-selector">
                    <p>Login as :</p>
                    <div class="radio-group">
                        <label><input type="radio" name="role" value="patient" checked><span>Patient</span></label>
                        <label><input type="radio" name="role" value="doctor"><span>Doctor</span></label>
                        <label><input type="radio" name="role" value="hospital"><span>Hospital</span></label>
                        <label><input type="radio" name="role" value="superadmin"><span>Admin</span></label>
                    </div>
                </div>

                <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>

                <button type="submit" class="btn btn-with-arrow">Login <i class="fas fa-chevron-right nav-arrow"></i></button>


            </form>
        </div>

        <!-- SIGNUP FORM -->
        <div class="auth-form-container" id="signup">
            <form action="logic/auth/auth.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="register">

                <div class="input-field">
                    <i class="fas fa-user"></i>
                    <input type="text" name="name" placeholder="Full Name" required />
                </div>
                <div class="input-field">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Email"
                        pattern="[A-Za-z0-9]+@(gmail\.com|outlook\.com|yahoo\.com|hotmail\.com|yopmail\.com)"
                        required />
                </div>
                <div class="input-field">
                    <i class="fas fa-phone"></i>
                    <input type="tel" name="phone" placeholder="Phone" pattern="9[0-9]{9}" required />
                </div>
                <div class="input-field password-field">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Password"
                        pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$"
                        title="At least 8 characters, with uppercase, lowercase, number and special char" required />
                    <i class="fas fa-eye toggle-password" title="Toggle password visibility"></i>
                </div>
                <p style="font-size: 0.72rem; color: var(--text-muted); margin: -10px 0 10px 5px; line-height: 1.2;">
                    <i class="fas fa-info-circle"></i> 8+ chars: A-Z, a-z, 0-9 & symbols.
                </p>

                <div class="role-selector">
                    <p>Register as :</p>
                    <div class="radio-group">
                        <label><input type="radio" name="role" value="patient" checked><span>Patient</span></label>
                        <label><input type="radio" name="role" value="doctor"><span>Doctor</span></label>
                    </div>
                </div>

                <div id="doctor-fields" style="display: none; width: 100%;">
                    <div class="input-field">
                        <i class="fas fa-building"></i>
                        <select name="hospital_id" id="hospital_id">
                            <option value="" disabled selected>Select Affiliate Hospital</option>
                            <?php foreach ($hospitalsList as $h): ?>
                                <option value="<?php echo $h['id']; ?>">
                                    <?php echo htmlspecialchars($h['name'] . ' - ' . $h['location']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-field">
                        <i class="fas fa-id-card"></i>
                        <input type="text" name="nmc_number" id="nmc_number" placeholder="NMC Number"
                            pattern="[0-9]{3,6}" title="Numeric and 3-6 digits only" />
                    </div>
                    <div class="input-field" style="height: auto; padding: 12px 15px; display: block;">
                        <label
                            style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; margin-bottom: 6px; display: block;">Upload
                            CV (PDF/DOC)</label>
                        <input type="file" name="cv_file" id="cv_file" accept=".pdf,.doc,.docx"
                            style="font-size: 0.9rem;" />
                    </div>
                </div>

                <button type="submit" class="btn btn-with-arrow">Sign Up <i class="fas fa-chevron-right nav-arrow"></i></button>


            </form>
        </div>

    </div>
</div>

<?php require_once 'includes/layout/footer.php'; ?>