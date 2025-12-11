<?php
session_start();

// Jika sudah login, redirect ke halaman yang sesuai
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === "admin") {
        header("Location: admin/dashboard.php");
        exit;
    } else {
        header("Location: index.php");
        exit;
    }
}

include 'includes/db.php';

$msg = "";
$msg_type = "";
$success = false;

if (isset($_POST['register'])) {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $phone    = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $role     = "user";

    // Validasi password match
    if ($password !== $confirm_password) {
        $msg = "Password and confirm password do not match!";
        $msg_type = "error";
    } 
    // Validasi panjang password
    else if (strlen($password) < 6) {
        $msg = "Password must be at least 6 characters!";
        $msg_type = "error";
    } 
    else {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // CEK EMAIL TERDAFTAR
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check_result = $check->get_result();

        if ($check_result->num_rows > 0) {
            $msg = "Email already registered! Please use another email.";
            $msg_type = "error";
        } else {
            // SIMPAN USER BARU
            $query = $conn->prepare("
                INSERT INTO users (name, email, password, phone, role)
                VALUES (?, ?, ?, ?, ?)
            ");
            $query->bind_param("sssss", $name, $email, $hashedPassword, $phone, $role);

            if ($query->execute()) {
                // REGISTER BERHASIL
                $success = true;
                $msg = "Account created successfully! You will be redirected to the login page...";
                $msg_type = "success";

                // Redirect ke halaman login setelah 2 detik
                echo "<script>
                        setTimeout(function() {
                            window.location.href = 'login.php';
                        }, 2000);
                      </script>";
            } else {
                $msg = "An error occurred during registration! Please try again.";
                $msg_type = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - LoveMUA</title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <!-- Back to Home Button -->
    <div class="back-home">
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Home</span>
        </a>
    </div>

    <!-- Auth Wrapper -->
    <div class="auth-wrapper">
        <!-- Floating Shapes -->
        <div class="floating-shape shape-1"></div>
        <div class="floating-shape shape-2"></div>
        <div class="floating-shape shape-3"></div>

        <!-- Auth Card -->
        <div class="auth-card">
            <!-- Auth Header -->
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-heart"></i>
                    <h1>Love<span>MUA</span></h1>
                </div>
                <p class="auth-subtitle">Professional Makeup Artist Booking Platform</p>
            </div>

            <!-- Auth Body -->
            <div class="auth-body">
                <?php if (!$success): ?>
                <h2 class="auth-title">Create Your Account</h2>
                <p class="auth-description">Join us to book the best makeup artists</p>
                <?php else: ?>
                <h2 class="auth-title">Registration Successful!</h2>
                <p class="auth-description">Redirecting you to login page...</p>
                <?php endif; ?>

                <!-- Alert Message -->
                <?php if ($msg != ""): ?>
                <div class="auth-alert <?= $msg_type ?>">
                    <i class="fas fa-<?= $msg_type === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
                    <span><?= htmlspecialchars($msg) ?></span>
                </div>
                <?php endif; ?>

                <!-- Register Form -->
                <?php if (!$success): ?>
                <form method="POST" id="registerForm">
                    <!-- Name Field -->
                    <div class="form-group">
                        <label for="name" class="form-label">
                            <i class="fas fa-user"></i>
                            Full Name
                        </label>
                        <div class="form-input-wrapper">
                            <input type="text" id="name" name="name" class="form-input"
                                placeholder="Enter your full name" required autofocus
                                value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                            <i class="fas fa-user input-icon"></i>
                        </div>
                    </div>

                    <!-- Email Field -->
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i>
                            Email Address
                        </label>
                        <div class="form-input-wrapper">
                            <input type="email" id="email" name="email" class="form-input"
                                placeholder="Enter your email" required
                                value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>

                    <!-- Phone Field -->
                    <div class="form-group">
                        <label for="phone" class="form-label">
                            <i class="fas fa-phone"></i>
                            Phone Number
                        </label>
                        <div class="form-input-wrapper">
                            <input type="tel" id="phone" name="phone" class="form-input"
                                placeholder="Enter your phone number" required
                                pattern="\+?[0-9]+" inputmode="numeric" 
                                maxlength="20"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                                value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                            <i class="fas fa-phone input-icon"></i>
                        </div>
                    </div>
                    
                    <!-- Password Field -->
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
                        <div class="form-input-wrapper">
                            <input type="password" id="password" name="password" class="form-input"
                                placeholder="Create a password (min. 6 characters)" required minlength="6">
                            <i class="fas fa-lock input-icon"></i>
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye" id="toggleIcon1"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm Password Field -->
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">
                            <i class="fas fa-lock"></i>
                            Confirm Password
                        </label>
                        <div class="form-input-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                                placeholder="Re-enter your password" required minlength="6">
                            <i class="fas fa-lock input-icon"></i>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye" id="toggleIcon2"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" name="register" class="btn-submit" id="submitBtn">
                        <i class="fas fa-user-plus"></i>
                        <span>Create Account</span>
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Auth Footer -->
            <?php if (!$success): ?>
            <div class="auth-footer">
                <p>
                    Already have an account?
                    <a href="login.php" class="auth-link">Login here</a>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Toggle Password Visibility
    function togglePassword(fieldId) {
        const passwordInput = document.getElementById(fieldId);
        const toggleIcon = fieldId === 'password' ? document.getElementById('toggleIcon1') : document.getElementById(
            'toggleIcon2');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
        }
    }

    // Auto-hide alert after 5 seconds (except success)
    const alert = document.querySelector('.auth-alert');
    if (alert && !alert.classList.contains('success')) {
        setTimeout(() => {
            alert.style.animation = 'slideUp 0.3s ease reverse';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    }

    // Password Match Validation
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');

    if (confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Password tidak cocok!');
            } else {
                confirmPassword.setCustomValidity('');
            }
        });
    }
    </script>
</body>

</html>