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

// ------ CREATE (TAMBAH) ------
if (isset($_POST['add_category'])) {
    $name = trim($_POST['name']);

    if (!empty($name)) {
        $stmt = $conn->prepare("INSERT INTO packages_categories (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Category berhasil ditambahkan!";
        } else {
            $_SESSION['error'] = "Error: " . $stmt->error;
        }
        
        header("Location: packages_categories.php");
        exit;
    }
}

// ------ UPDATE (EDIT) ------
if (isset($_POST['update_category'])) {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name']);

    if (!empty($name)) {
        $stmt = $conn->prepare("UPDATE packages_categories SET name = ? WHERE id = ?");
        $stmt->bind_param("si", $name, $id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Category berhasil diupdate!";
        } else {
            $_SESSION['error'] = "Error: " . $stmt->error;
        }
        
        header("Location: packages_categories.php");
        exit;
    }
}

// ------ DELETE (HAPUS) ------
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if category has packages
    $check_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM packages WHERE category_id = $id");
    $check_result = mysqli_fetch_assoc($check_query);
    
    if ($check_result['total'] > 0) {
        $_SESSION['error'] = "Cannot delete! This category has {$check_result['total']} package(s) associated with it.";
    } else {
        $stmt = $conn->prepare("DELETE FROM packages_categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Category berhasil dihapus!";
        }
    }

    header("Location: packages_categories.php");
    exit;
}

// ------ SEARCH ------
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where_clause = !empty($search) ? "WHERE pc.name LIKE '%$search%'" : "";

// GET DATA CATEGORIES with package count
$categories = $conn->query("SELECT pc.*, 
                           (SELECT COUNT(*) FROM packages WHERE category_id = pc.id) as total_packages
                           FROM packages_categories pc
                           $where_clause
                           ORDER BY pc.id DESC");

// Get category for edit
$edit_category = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_result = mysqli_query($conn, "SELECT * FROM packages_categories WHERE id = $edit_id");
    $edit_category = mysqli_fetch_assoc($edit_result);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Package Categories Management - LoveMUA Admin</title>
    <link rel="stylesheet" href="assets/css/packages-categories.css">
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
            <a href="mua.php" class="nav-item">
                <i class="fas fa-user-tie"></i>
                <span>MUAs</span>
            </a>
            <a href="packages_categories.php" class="nav-item active">
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
                <h1>Package Categories</h1>
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
                    <h2>Manage Package Categories</h2>
                    <p>Organize your packages by categories for better management</p>
                </div>
                <button class="btn-primary" onclick="toggleForm()">
                    <i class="fas fa-plus"></i> Add New Category
                </button>
            </div>

            <!-- Form Add/Edit Category -->
            <div class="form-container" id="formContainer" style="display: <?= $edit_category ? 'block' : 'none' ?>;">
                <div class="form-card">
                    <div class="form-header">
                        <h3>
                            <i class="fas fa-<?= $edit_category ? 'edit' : 'plus-circle' ?>"></i>
                            <?= $edit_category ? 'Edit Category' : 'Add New Category' ?>
                        </h3>
                        <button class="btn-close" onclick="toggleForm()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form method="POST" class="category-form">
                        <?php if ($edit_category): ?>
                        <input type="hidden" name="id" value="<?= $edit_category['id'] ?>">
                        <?php endif; ?>

                        <div class="form-group">
                            <label>
                                <i class="fas fa-tag"></i> Category Name
                            </label>
                            <input type="text" name="name" required
                                placeholder="Enter category name (e.g. Wedding Makeup)"
                                value="<?= $edit_category ? htmlspecialchars($edit_category['name']) : '' ?>">
                            <small class="form-hint">Choose a descriptive name for this package category</small>
                        </div>

                        <div class="form-actions">
                            <?php if ($edit_category): ?>
                            <button type="submit" name="update_category" class="btn-primary">
                                <i class="fas fa-save"></i> Update Category
                            </button>
                            <a href="packages_categories.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <?php else: ?>
                            <button type="submit" name="add_category" class="btn-primary">
                                <i class="fas fa-plus"></i> Add Category
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
                        <input type="text" name="search" placeholder="Search categories..."
                            value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-search">Search</button>
                    </div>
                </form>
                <?php if (!empty($search)): ?>
                <a href="packages_categories.php" class="btn-clear">
                    <i class="fas fa-times"></i> Clear Search
                </a>
                <?php endif; ?>
            </div>

            <!-- Categories Table/Grid -->
            <?php if (mysqli_num_rows($categories) > 0): ?>
            <div class="categories-grid">
                <?php while ($row = $categories->fetch_assoc()): ?>
                <div class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-layer-group"></i>
                    </div>
                    <div class="category-content">
                        <h3><?= htmlspecialchars($row['name']) ?></h3>
                        <div class="category-stats">
                            <span class="stat-badge">
                                <i class="fas fa-box"></i>
                                <?= $row['total_packages'] ?> Package<?= $row['total_packages'] != 1 ? 's' : '' ?>
                            </span>
                            <span class="category-id">ID: <?= $row['id'] ?></span>
                        </div>
                    </div>
                    <div class="category-actions">
                        <a href="?edit=<?= $row['id'] ?>" class="btn-action edit" title="Edit Category">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button
                            onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars($row['name']) ?>', <?= $row['total_packages'] ?>)"
                            class="btn-action delete" title="Delete Category">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-layer-group"></i>
                <h3>No Categories Found</h3>
                <p>
                    <?= !empty($search) ? 'No categories match your search criteria.' : 'Start by adding your first package category!' ?>
                </p>
                <?php if (!empty($search)): ?>
                <a href="packages_categories.php" class="btn-primary">
                    <i class="fas fa-list"></i> View All Categories
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
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
    function confirmDelete(id, name, packageCount) {
        let message = `Are you sure you want to delete category "${name}"?`;

        if (packageCount > 0) {
            message +=
                `\n\n⚠️ WARNING: This category has ${packageCount} package(s) associated with it.\nYou must reassign or delete those packages first!`;
            alert(message);
            return false;
        } else {
            message += '\n\nThis action cannot be undone!';
        }

        if (confirm(message)) {
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