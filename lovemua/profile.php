<?php
session_start();
// HARUS LOGIN
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// Include DB
require_once 'includes/db.php';
// Ambil ID user login
$user_id = $_SESSION['user_id'];
// Ambil data user dari database
$query = "SELECT * FROM users WHERE id = '$user_id'";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);
// Jika submit update
if (isset($_POST['update_profile'])) {
    $name  = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    
    $update = "UPDATE users SET name='$name', email='$email', phone='$phone' WHERE id='$user_id'";
    mysqli_query($conn, $update);
    
    // UPDATE SESSION SUPAYA NAMA DI NAVBAR IKUT BERUBAH
    $_SESSION['name'] = $name;
    $_SESSION['email'] = $email;
    
    // Refresh supaya update langsung terlihat
    header("Location: profile.php?success=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - LoveMUA</title>
    <link rel="stylesheet" href="assets/css/profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="profile-container">
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>

        <div class="profile-header">
            <h2>
                <div class="profile-icon">
                    <i class="fas fa-user"></i>
                </div>
                My Profile
            </h2>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="success">Profile updated successfully!</div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Name</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['name']); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <div class="input-wrapper">
                    <i class="fas fa-envelope input-icon"></i>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Phone</label>
                <div class="input-wrapper">
                    <i class="fas fa-phone input-icon"></i>
                    <input type="text"
                        name="phone"
                        value="<?= htmlspecialchars($user['phone']); ?>"
                        required
                        maxlength="20"
                        oninput="this.value = this.value.replace(/(?!^\+)[^0-9]/g, '')">
                </div>
            </div>
            
            <button type="submit" name="update_profile" class="btn-save">Save Changes</button>
        </form>
    </div>
</body>
</html>