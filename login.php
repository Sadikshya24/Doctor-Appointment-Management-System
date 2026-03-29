<?php
require_once 'includes/db.php';
$stmt = $pdo->query("SELECT h.id, u.name, h.location FROM hospitals h JOIN users u ON h.user_id = u.id");
$hospitalsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php $pageTitle = 'MedScape - Login';
require_once 'includes/header.php'; ?>
<div class="auth-wrapper">
    <div class="auth-card">

        <div class="auth-header">
            <h2>MedScape</h2>
            <p>Manage your doctor appointments securely</p>
        </div>


        <div class="auth-tabs">
            <button class="auth-tab active" data-target="login">Sign In</button>
            <button class="auth-tab" data-target="signup">Sign Up</button>
        </div>

        <!-- LOGIN FORM -->
        <div class="auth-form-container active" id="login">
            <form action="actions/auth.php" method="POST">
                <input type="hidden" name="action" value="login">

                <div class="input-field">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Email Address" required />
                </div>
                <div class="input-field">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Password" required />
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

                <button type="submit" class="btn">Login</button>


            </form>
        </div>

        <!-- SIGNUP FORM -->
        <div class="auth-form-container" id="signup">
            <form action="actions/auth.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="register">

                <div class="input-field">
                    <i class="fas fa-user"></i>
                    <input type="text" name="name" placeholder="Full Name" required />
                </div>
                <div class="input-field">
                    <i class="fas fa-envelope"></i>
                    <input type="email" name="email" placeholder="Email Address" required />
                </div>
                <div class="input-field">
                    <i class="fas fa-phone"></i>
                    <input type="tel" name="phone" placeholder="Phone Number (10 digits)" pattern="[0-9]{10}"
                        title="Please enter exactly 10 digits" required />
                </div>
                <div class="input-field">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" placeholder="Password" required />
                </div>

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
                        <select name="hospital_id" id="hospital_id"
                            style="width: 100%; border: none; background: transparent; outline: none; padding: 15px 15px 15px 45px; font-weight: 500; font-family: inherit; color: var(--text-main);">
                             <option value="" disabled selected style="background: var(--bg-card); color: var(--text-muted);">Select Hospital</option>
                            <?php foreach ($hospitalsList as $h): ?>
                                <option value="<?php echo $h['id']; ?>">
                                    <?php echo htmlspecialchars($h['name'] . ' - ' . $h['location']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-field">
                        <i class="fas fa-id-card"></i>
                        <input type="text" name="nmc_number" id="nmc_number" placeholder="NMC Number" />
                    </div>
                    <div class="input-field" style="padding: 10px 15px; height: auto;">
                        <label style="font-size: 0.9em; color: #666; margin-bottom: 5px; display: block;">Upload CV
                            (PDF/DOC)</label>
                        <input type="file" name="cv_file" id="cv_file" accept=".pdf,.doc,.docx" />
                    </div>
                </div>

                <button type="submit" class="btn">Sign Up</button>


            </form>
        </div>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>