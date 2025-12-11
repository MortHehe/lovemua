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

// FOLDER UPLOAD
$uploadDir = "../uploads/mua_packages/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// ====== DELETE PACKAGE ======
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Delete images from folder
    $img_query = mysqli_query($conn, "SELECT filename FROM package_images WHERE package_id='$id'");
    while ($row = mysqli_fetch_assoc($img_query)) {
        @unlink($uploadDir . $row['filename']);
    }
    
    // Delete from database
    mysqli_query($conn, "DELETE FROM package_images WHERE package_id='$id'");
    mysqli_query($conn, "DELETE FROM packages WHERE id='$id'");
    
    $_SESSION['message'] = "Package berhasil dihapus!";
    header("Location: packages.php");
    exit;
}

// ====== DELETE SINGLE IMAGE ======
if (isset($_GET['delete_image'])) {
    $img_id = (int)$_GET['delete_image'];
    $package_id = (int)$_GET['package_id'];
    
    $data = mysqli_query($conn, "SELECT filename FROM package_images WHERE id='$img_id'")->fetch_assoc();
    if ($data) {
        @unlink($uploadDir . $data['filename']);
        mysqli_query($conn, "DELETE FROM package_images WHERE id='$img_id'");
        $_SESSION['message'] = "Image berhasil dihapus!";
    }
    
    header("Location: packages.php?edit=$package_id");
    exit;
}

// ====== ADD PACKAGE ======
if (isset($_POST['add_package'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $category_id = (int)$_POST['category_id'];
    $mua_id = (int)$_POST['mua_id'];
    $price = (int)$_POST['price'];
    $duration = (int)$_POST['duration'];
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    $stmt = $conn->prepare("INSERT INTO packages (mua_id, category_id, name, price, duration_hours, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisiis", $mua_id, $category_id, $name, $price, $duration, $description);
    $stmt->execute();
    
    $new_package_id = $conn->insert_id;
    
    // Upload multiple images
    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['name'] as $key => $filename) {
            $tmp = $_FILES['images']['tmp_name'][$key];
            if (!$tmp) continue;
            
            $cleanName = time() . "_" . str_replace(" ", "_", basename($filename));
            
            if (move_uploaded_file($tmp, $uploadDir . $cleanName)) {
                mysqli_query($conn, "INSERT INTO package_images (package_id, filename) VALUES ('$new_package_id', '$cleanName')");
            }
        }
    }
    
    $_SESSION['message'] = "Package berhasil ditambahkan!";
    header("Location: packages.php");
    exit;
}

// ====== UPDATE PACKAGE ======
if (isset($_POST['update_package'])) {
    $id = (int)$_POST['package_id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $price = (int)$_POST['price'];
    $duration = (int)$_POST['duration'];
    $category_id = (int)$_POST['category_id'];
    $mua_id = (int)$_POST['mua_id'];
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    $stmt = $conn->prepare("UPDATE packages SET name=?, price=?, duration_hours=?, category_id=?, mua_id=?, description=? WHERE id=?");
    $stmt->bind_param("siiissi", $name, $price, $duration, $category_id, $mua_id, $description, $id);
    $stmt->execute();
    
    // Upload new images
    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
            if (!$tmp) continue;
            
            $filename = time() . "_" . basename($_FILES['images']['name'][$i]);
            $dest = $uploadDir . $filename;
            
            if (move_uploaded_file($tmp, $dest)) {
                mysqli_query($conn, "INSERT INTO package_images (package_id, filename) VALUES ('$id', '$filename')");
            }
        }
    }
    
    $_SESSION['message'] = "Package berhasil diupdate!";
    header("Location: packages.php");
    exit;
}

// ====== FILTERS ======
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$filter_mua = isset($_GET['mua']) ? (int)$_GET['mua'] : 0;

$where_conditions = [];
if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE '%$search%' OR p.description LIKE '%$search%')";
}
if ($filter_category > 0) {
    $where_conditions[] = "p.category_id = $filter_category";
}
if ($filter_mua > 0) {
    $where_conditions[] = "p.mua_id = $filter_mua";
}

$where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

// ====== GET PACKAGES ======
$packages = $conn->query("
    SELECT p.*, 
           m.name AS mua_name, 
           c.name AS category_name,
           (SELECT COUNT(*) FROM package_images WHERE package_id = p.id) as image_count,
           (SELECT filename FROM package_images WHERE package_id = p.id ORDER BY id ASC LIMIT 1) as first_image
    FROM packages p
    LEFT JOIN mua m ON p.mua_id = m.id
    LEFT JOIN packages_categories c ON p.category_id = c.id
    $where_clause
    ORDER BY p.id DESC
");

// ====== GET DATA FOR FILTERS ======
$categories = $conn->query("SELECT * FROM packages_categories ORDER BY name ASC");
$muas = $conn->query("SELECT * FROM mua ORDER BY name ASC");

// ====== GET PACKAGE FOR EDIT ======
$edit_package = null;
$edit_images = [];
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_result = mysqli_query($conn, "SELECT * FROM packages WHERE id = $edit_id");
    $edit_package = mysqli_fetch_assoc($edit_result);
    
    if ($edit_package) {
        $edit_images_result = mysqli_query($conn, "SELECT * FROM package_images WHERE package_id = $edit_id");
        while ($img = mysqli_fetch_assoc($edit_images_result)) {
            $edit_images[] = $img;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packages Management - LoveMUA Admin</title>
    <link rel="stylesheet" href="assets/css/packages.css">
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
            <a href="packages_categories.php" class="nav-item">
                <i class="fas fa-layer-group"></i>
                <span>Categories</span>
            </a>
            <a href="packages.php" class="nav-item active">
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
                <h1>Packages Management</h1>
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
                    <h2>Manage Packages</h2>
                    <p>Create and manage makeup service packages</p>
                </div>
                <button class="btn-primary" onclick="toggleForm()">
                    <i class="fas fa-plus"></i> Add New Package
                </button>
            </div>

            <!-- Form Add/Edit Package -->
            <div class="form-container" id="formContainer" style="display: <?= $edit_package ? 'block' : 'none' ?>;">
                <div class="form-card">
                    <div class="form-header">
                        <h3>
                            <i class="fas fa-<?= $edit_package ? 'edit' : 'plus-circle' ?>"></i>
                            <?= $edit_package ? 'Edit Package' : 'Add New Package' ?>
                        </h3>
                        <button class="btn-close"
                            onclick="<?= $edit_package ? 'window.location.href=\'packages.php\'' : 'toggleForm()' ?>">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="package-form">
                        <?php if ($edit_package): ?>
                        <input type="hidden" name="package_id" value="<?= $edit_package['id'] ?>">
                        <?php endif; ?>

                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-box"></i> Package Name</label>
                                <input type="text" name="name" required placeholder="Enter package name"
                                    value="<?= $edit_package ? htmlspecialchars($edit_package['name']) : '' ?>">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-layer-group"></i> Category</label>
                                <select name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php 
                                    mysqli_data_seek($categories, 0);
                                    while ($cat = $categories->fetch_assoc()): 
                                    ?>
                                    <option value="<?= $cat['id'] ?>"
                                        <?= ($edit_package && $edit_package['category_id'] == $cat['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-user-tie"></i> MUA</label>
                                <select name="mua_id" required>
                                    <option value="">Select MUA</option>
                                    <?php 
                                    mysqli_data_seek($muas, 0);
                                    while ($mua = $muas->fetch_assoc()): 
                                    ?>
                                    <option value="<?= $mua['id'] ?>"
                                        <?= ($edit_package && $edit_package['mua_id'] == $mua['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($mua['name']) ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-tag"></i> Price (Rp)</label>
                                <input type="number" name="price" required placeholder="0"
                                    value="<?= $edit_package ? $edit_package['price'] : '' ?>">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-clock"></i> Duration (Hours)</label>
                                <input type="number" name="duration" required placeholder="0"
                                    value="<?= $edit_package ? $edit_package['duration_hours'] : '' ?>">
                            </div>

                            <div class="form-group">
                                <label><i class="fas fa-images"></i> Package Images</label>
                                <input type="file" name="images[]" multiple accept="image/*"
                                    <?= !$edit_package ? 'required' : '' ?>>
                                <small class="form-hint">You can upload multiple images at once</small>
                            </div>
                        </div>

                        <div class="form-group full-width">
                            <label><i class="fas fa-align-left"></i> Description</label>
                            <textarea name="description" rows="5" required
                                placeholder="Enter package description..."><?= $edit_package ? htmlspecialchars($edit_package['description']) : '' ?></textarea>
                        </div>

                        <?php if ($edit_package && !empty($edit_images)): ?>
                        <div class="form-group full-width">
                            <label><i class="fas fa-image"></i> Current Images</label>
                            <div class="image-gallery">
                                <?php foreach ($edit_images as $img): ?>
                                <div class="gallery-item">
                                    <img src="../uploads/mua_packages/<?= htmlspecialchars($img['filename']) ?>"
                                        alt="Package Image">
                                    <button type="button" class="btn-delete-img"
                                        onclick="confirmDeleteImage(<?= $img['id'] ?>, <?= $edit_package['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-actions">
                            <?php if ($edit_package): ?>
                            <button type="submit" name="update_package" class="btn-primary">
                                <i class="fas fa-save"></i> Update Package
                            </button>
                            <a href="packages.php" class="btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                            <?php else: ?>
                            <button type="submit" name="add_package" class="btn-primary">
                                <i class="fas fa-plus"></i> Add Package
                            </button>
                            <button type="reset" class="btn-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" class="search-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search packages..."
                            value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-search">Search</button>
                    </div>
                </form>

                <div class="filter-dropdowns">
                    <select name="category" onchange="applyFilter('category', this.value)">
                        <option value="0">All Categories</option>
                        <?php 
                        mysqli_data_seek($categories, 0);
                        while ($cat = $categories->fetch_assoc()): 
                        ?>
                        <option value="<?= $cat['id'] ?>" <?= $filter_category == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>

                    <select name="mua" onchange="applyFilter('mua', this.value)">
                        <option value="0">All MUAs</option>
                        <?php 
                        mysqli_data_seek($muas, 0);
                        while ($mua = $muas->fetch_assoc()): 
                        ?>
                        <option value="<?= $mua['id'] ?>" <?= $filter_mua == $mua['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mua['name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <?php if (!empty($search) || $filter_category > 0 || $filter_mua > 0): ?>
                <a href="packages.php" class="btn-clear">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
                <?php endif; ?>
            </div>

            <!-- Packages Grid -->
            <div class="packages-grid">
                <?php if (mysqli_num_rows($packages) > 0): ?>
                <?php while ($pkg = $packages->fetch_assoc()): ?>
                <div class="package-card">
                    <div class="package-image">
                        <?php if ($pkg['first_image']): ?>
                        <img src="../uploads/mua_packages/<?= htmlspecialchars($pkg['first_image']) ?>"
                            alt="<?= htmlspecialchars($pkg['name']) ?>"
                            onerror="this.src='../assets/images/package-placeholder.jpg'">
                        <?php else: ?>
                        <img src="../assets/images/package-placeholder.jpg" alt="No Image">
                        <?php endif; ?>
                        <div class="image-count">
                            <i class="fas fa-images"></i> <?= $pkg['image_count'] ?>
                        </div>
                    </div>

                    <div class="package-body">
                        <div class="package-category"><?= htmlspecialchars($pkg['category_name']) ?></div>
                        <h3><?= htmlspecialchars($pkg['name']) ?></h3>

                        <div class="package-info">
                            <div class="info-item">
                                <i class="fas fa-user-tie"></i>
                                <span><?= htmlspecialchars($pkg['mua_name']) ?></span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-clock"></i>
                                <span><?= $pkg['duration_hours'] ?> Hours</span>
                            </div>
                        </div>

                        <p class="package-description"><?= htmlspecialchars(substr($pkg['description'], 0, 100)) ?>...
                        </p>

                        <div class="package-price">
                            <span class="price-label">Price:</span>
                            <span class="price-value">Rp <?= number_format($pkg['price'], 0, ',', '.') ?></span>
                        </div>

                        <div class="package-actions">
                            <a href="?edit=<?= $pkg['id'] ?>" class="btn-action edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button onclick="confirmDelete(<?= $pkg['id'] ?>, '<?= htmlspecialchars($pkg['name']) ?>')"
                                class="btn-action delete">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>No Packages Found</h3>
                    <p>
                        <?= !empty($search) || $filter_category > 0 || $filter_mua > 0 ? 'No packages match your filters.' : 'Start by adding your first package!' ?>
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

    // Apply Filter
    function applyFilter(type, value) {
        const url = new URL(window.location.href);
        if (value == 0) {
            url.searchParams.delete(type);
        } else {
            url.searchParams.set(type, value);
        }
        window.location.href = url.toString();
    }

    // Confirm Delete Package
    function confirmDelete(id, name) {
        if (confirm(
                `Are you sure you want to delete package "${name}"?\n\nThis will also delete all images and cannot be undone!`
                )) {
            window.location.href = `?delete=${id}`;
        }
    }

    // Confirm Delete Image
    function confirmDeleteImage(imgId, packageId) {
        if (confirm('Are you sure you want to delete this image?')) {
            window.location.href = `?delete_image=${imgId}&package_id=${packageId}`;
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