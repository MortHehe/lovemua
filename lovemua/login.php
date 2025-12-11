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

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $pass  = trim($_POST['password']);

    // CARI EMAIL
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // COCOKKAN PASSWORD
        if (password_verify($pass, $user['password'])) {

            // SET SESSION BENAR
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];

            // ARAHKAN SESUAI ROLE
            if ($user['role'] === "admin") {
                header("Location: admin/dashboard.php");
                exit;
            } else {
                header("Location: index.php");
                exit;
            }

        } else {
            $msg = "Wrong password! Please try again.";
            $msg_type = "error";
        }
    } else {
        $msg = "Email not found! Please make sure your email is correct.";
        $msg_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LoveMUA</title>
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
                <h2 class="auth-title">Welcome Back!</h2>
                <p class="auth-description">Login to continue your beauty journey</p>

                <!-- Alert Message -->
                <?php if ($msg != ""): ?>
                <div class="auth-alert <?= $msg_type ?>">
                    <i class="fas fa-<?= $msg_type === 'error' ? 'exclamation-circle' : 'check-circle' ?>"></i>
                    <span><?= htmlspecialchars($msg) ?></span>
                </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" id="loginForm">
                    <!-- Email Field -->
                    <div class="form-group">
                        <label for="email" class="form-label">
                            <i class="fas fa-envelope"></i>
                            Email Address
                        </label>
                        <div class="form-input-wrapper">
                            <input type="email" id="email" name="email" class="form-input"
                                placeholder="Enter your email" required autofocus
                                value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                            <i class="fas fa-envelope input-icon"></i>
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
                                placeholder="Enter your password" required>
                            <i class="fas fa-lock input-icon"></i>
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" name="login" class="btn-submit" id="submitBtn">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Login Now</span>
                    </button>
                </form>
            </div>

            <!-- Auth Footer -->
            <div class="auth-footer">
                <p>
                    Don't have an account?
                    <a href="regist.php" class="auth-link">Register here</a>
                </p>
            </div>
        </div>
    </div>

    <script>
    // Toggle Password Visibility
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');

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

    // Auto-hide alert after 5 seconds
    const alert = document.querySelector('.auth-alert');
    if (alert) {
        setTimeout(() => {
            alert.style.animation = 'slideUp 0.3s ease reverse';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    }
    </script>
</body>

</html>