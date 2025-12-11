<?php
session_start();
include '../includes/db.php';

// CEK LOGIN
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// CEK ROLE ADMIN
if ($_SESSION['role'] !== "admin") {
    header("Location: ../index.php");
    exit;
}

// ======================================================
// DELETE MUA
// ======================================================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Get photo filename first
    $photo_query = mysqli_query($conn, "SELECT photo FROM mua WHERE id = $id");
    $photo_data = mysqli_fetch_assoc($photo_query);
    
    // Delete photo file
    if (!empty($photo_data['photo'])) {
        $photo_path = "assets/images/mua/" . $photo_data['photo'];
        if (file_exists($photo_path)) {
            unlink($photo_path);
        }
    }
    
    // Delete from database
    mysqli_query($conn, "DELETE FROM mua WHERE id = $id");
    $_SESSION['message'] = "MUA successfully removed!";
    header("Location: mua.php");
    exit;
}

// ======================================================
// UPDATE MUA
// ======================================================
if (isset($_POST['update_mua'])) {
    $id = (int)$_POST['mua_id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $bio = mysqli_real_escape_string($conn, $_POST['bio']);
    
    // Handle photo upload
    $photo_name = $_POST['old_photo'];
    
    if (!empty($_FILES['photo']['name'])) {
        // Delete old photo
        if (!empty($photo_name)) {
            $old_path = "assets/images/mua/" . $photo_name;
            if (file_exists($old_path)) {
                unlink($old_path);
            }
        }
        
        $target_dir = "assets/images/mua/";
        $photo_name = time() . "_" . basename($_FILES["photo"]["name"]);
        $target_file = $target_dir . $photo_name;
        move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file);
    }
    
    $stmt = $conn->prepare("UPDATE mua SET name=?, phone=?, email=?, bio=?, photo=? WHERE id=?");
    $stmt->bind_param("sssssi", $name, $phone, $email, $bio, $photo_name, $id);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "MUA successfully updated!";
    }
    
    header("Location: mua.php");
    exit;
}

// ======================================================
// INSERT DATA MUA
// ======================================================
if (isset($_POST['add_mua'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $bio = mysqli_real_escape_string($conn, $_POST['bio']);

    // HANDLE FOTO UPLOAD
    $photo_name = "";
    
    if (!empty($_FILES['photo']['name'])) {
        $target_dir = "assets/images/mua/";
        $photo_name = time() . "_" . basename($_FILES["photo"]["name"]);
        $target_file = $target_dir . $photo_name;

        if (!move_uploaded_file($_FILES["photo"]["tmp_name"], $target_file)) {
            $_SESSION['error'] = "Failed to upload photo!";
        }
    }

    // INSERT DB
    $stmt = $conn->prepare("INSERT INTO mua (name, phone, email, bio, photo) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $name, $phone, $email, $bio, $photo_name);

    if ($stmt->execute()) {
        $_SESSION['message'] = "MUA successfully added!";
    } else {
        $_SESSION['error'] = "Error: " . $stmt->error;
    }
    
    header("Location: mua.php");
    exit;
}

// ======================================================
// GET LIST DATA MUA
// ======================================================
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where_clause = !empty($search) ? "WHERE name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%'" : "";

$mua_data = $conn->query("SELECT m.*, 
                          (SELECT COUNT(*) FROM packages WHERE mua_id = m.id) as total_packages,
                          (SELECT COUNT(*) FROM bookings WHERE mua_id = m.id) as total_bookings
                          FROM mua m
                          $where_clause
                          ORDER BY id DESC");

// Get MUA for Edit (if edit mode)
$edit_mua = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_result = mysqli_query($conn, "SELECT * FROM mua WHERE id = $edit_id");
    $edit_mua = mysqli_fetch_assoc($edit_result);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MUA Management - LoveMUA Admin</title>
    <link rel="stylesheet" href="assets/css/mua.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2 class="sidebar-logo">Love<span>MUA</span></h2>
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>
            <a href="mua.php" class="nav-item active">
                <i class="fas fa-user-tie"></i>
                <span>MUAs</span>
            </a>
            <a href="packages_categories.php" class="nav-item">
                <i class="fas fa-layer-group"></i>
                <span>Categories</span>
            </a>
            <a href="packages.php" class="nav-item">
                <i class="fas fa-box-open"></i>
                <span>Packages</span>
            </a>
            <a href="bookings.php" class="nav-item">
                <i class="fas fa-calendar-check"></i>
                <span>Bookings</span>
            </a>
            <a href="payments.php" class="nav-item">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>
            <a href="invoice.php" class="nav-item">
                <i class="fas fa-file-invoice"></i>
                <span>Invoices</span>
            </a>
            <a href="review.php" class="nav-item">
                <i class="fas fa-star"></i>
                <span>Reviews</span>
            </a>
            <a href="users.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Users</span>
            </a>
            <div class="nav-divider"></div>
            <a href="../logout.php" class="nav-item logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Navbar -->
        <header class="top-navbar">
            <div class="navbar-left">
                <button class="mobile-toggle" id="mobileToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1>MUA Management</h1>
            </div>
            <div class="navbar-right">
                <div class="admin-profile">
                    <span>Welcome, <strong><?= htmlspecialchars($_SESSION['name']); ?></strong></span>
                    <div class="admin-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </div>
            </div>
        </header>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?= $_SESSION['message'] ?>
        </div>
        <?php unset($_SESSION['message']); endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <?= $_SESSION['error'] ?>
        </div>
        <?php unset($_SESSION['error']); endif; ?>

        <!-- Page Content -->
        <section class="content-section">
            <div class="content-header">
                <div>
                    <h2>Manage MUA data</h2>
                    <p>Add and Manage Your Company's MUA Data</p>
                </div>
                <button class="btn-primary" onclick="toggleForm()">
                    <i class="fas fa-plus"></i> Add new MUAs
                </button>
            </div>

            <!-- Form Add/Edit MUA -->
            <div class="form-container" id="formContainer" style="display: <?= $edit_mua ? 'block' : 'none' ?>;">
                <div class="form-card">
                    <div class="form-header">
                        <h3>
                            <i class="fas fa-user-plus"></i>
                            <?= $edit_mua ? 'Edit MUA' : 'Add new MUAs' ?>
                        </h3>
                        <button class="btn-close" onclick="toggleForm()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="mua-form">
                        <?php if ($edit_mua): ?>
                        <input type="hidden" name="mua_id" value="<?= $edit_mua['id'] ?>">
                        <input type="hidden" name="old_photo" value="<?= $edit_mua['photo'] ?>">
                        <?php endif; ?>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> MUAs Name</label>
                                <input type="text" name="name" required
                                    value="<?= $edit_mua ? htmlspecialchars($edit_mua['name']) : '' ?>">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Number Telephone</label>
                                <input type="text" name="phone" required
                                pattern="\+?[0-9]+" inputmode="numeric" 
                                maxlength="20"
                                oninput="this.value = this.value.replace(/(?!^\+)[^0-9]/g, '')"
                                    value="<?= $edit_mua ? htmlspecialchars($edit_mua['phone']) : '' ?>">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Email</label>
                                <input type="email" name="email" required
                                    value="<?= $edit_mua ? htmlspecialchars($edit_mua['email']) : '' ?>">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-camera"></i> MUAs Photos</label>
                                <input type="file" name="photo" accept="image/*" <?= !$edit_mua ? 'required' : '' ?>>
                                <?php if ($edit_mua && !empty($edit_mua['photo'])): ?>
                                <small class="form-hint">Leave empty to keep current photo</small>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label><i class="fas fa-align-left"></i> Bio / Description</label>
                            <textarea name="bio" rows="4"
                                required><?= $edit_mua ? htmlspecialchars($edit_mua['bio']) : '' ?></textarea>
                        </div>

                        <div class="form-actions">
                            <?php if ($edit_mua): ?>
                            <button type="submit" name="update_mua" class="btn-primary">
                                <i class="fas fa-save"></i> Update MUA
                            </button>
                            <a href="mua.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <?php else: ?>
                            <button type="submit" name="add_mua" class="btn-primary">
                                <i class="fas fa-plus"></i> Add MUA
                            </button>
                            <button type="reset" class="btn-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="search-section">
                <form method="GET" class="search-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search MUA by name, email, or phone..."
                            value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-search">Search</button>
                    </div>
                </form>
                <?php if (!empty($search)): ?>
                <a href="mua.php" class="btn-clear">
                    <i class="fas fa-times"></i> Clear Search
                </a>
                <?php endif; ?>
            </div>

            <!-- MUA Grid -->
            <div class="mua-grid">
                <?php if (mysqli_num_rows($mua_data) > 0): ?>
                <?php while ($row = $mua_data->fetch_assoc()): ?>
                <div class="mua-card">
                    <div class="mua-image">
                        <img src="assets/images/mua/<?= htmlspecialchars($row['photo']) ?>" alt="Foto MUA"
                            onerror="this.src='../assets/images/default-mua.jpg'">
                    </div>
                    <div class="mua-body">
                        <h3><?= htmlspecialchars($row['name']) ?></h3>

                        <div class="mua-info">
                            <div class="info-item">
                                <i class="fas fa-phone"></i>
                                <span>+<?= htmlspecialchars(preg_replace('/[^0-9]/', '', $row['phone'])) ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-envelope"></i>
                                <span><?= htmlspecialchars($row['email']) ?></span>
                            </div>
                        </div>

                        <p class="mua-bio"><?= htmlspecialchars(substr($row['bio'], 0, 100)) ?>...</p>

                        <div class="mua-stats">
                            <div class="stat">
                                <i class="fas fa-box"></i>
                                <span><?= $row['total_packages'] ?> Packages</span>
                            </div>
                            <div class="stat">
                                <i class="fas fa-calendar-check"></i>
                                <span><?= $row['total_bookings'] ?> Bookings</span>
                            </div>
                        </div>

                        <div class="mua-actions">
                            <a href="?edit=<?= $row['id'] ?>" class="btn-action edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name']) ?>')"
                                class="btn-action delete">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-slash"></i>
                    <h3>No MUAs Found</h3>
                    <p>
                        <?= !empty($search) ? 'No MUAs match your search criteria.' : 'Start by adding your first MUA!' ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script>
    // Sidebar Toggle
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileToggle');

    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
    });

    mobileToggle.addEventListener('click', () => {
        sidebar.classList.toggle('mobile-open');
    });

    // Toggle Form
    function toggleForm() {
        const formContainer = document.getElementById('formContainer');
        if (formContainer.style.display === 'none') {
            formContainer.style.display = 'block';
            formContainer.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        } else {
            formContainer.style.display = 'none';
        }
    }

    // Confirm Delete
    function confirmDelete(id, name) {
        if (confirm(
                `Are you sure you want to delete MUA "${name}"?\n\nThis action cannot be undone and will also delete all related packages and bookings!`
                )) {
            window.location.href = `?delete=${id}`;
        }
    }

    // Auto-hide alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
    </script>
</body>

</html>