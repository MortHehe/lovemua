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

// ====== DELETE USER ======
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Prevent deleting current admin
    if ($delete_id == $_SESSION['user_id']) {
        header("Location: users.php?error=self_delete");
        exit;
    }
    
    // Check if user has bookings
    $check_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE user_id = $delete_id")->fetch_assoc();
    
    if ($check_bookings['count'] > 0) {
        header("Location: users.php?error=has_bookings");
        exit;
    }
    
    // Delete user reviews first
    $conn->query("DELETE FROM review WHERE user_id = $delete_id");
    
    // Delete user
    $delete_query = "DELETE FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt, "i", $delete_id);
    
    if (mysqli_stmt_execute($stmt)) {
        header("Location: users.php?success=deleted");
        exit;
    }
}

// ====== TOGGLE USER STATUS (Ban/Unban) ======
if (isset($_GET['toggle_status'])) {
    $user_id = (int)$_GET['toggle_status'];
    $current_status = isset($_GET['current']) ? $_GET['current'] : 'active';
    $new_status = $current_status == 'active' ? 'banned' : 'active';
    
    // Note: You need to add 'status' column to users table
    // ALTER TABLE users ADD COLUMN status ENUM('active', 'banned') DEFAULT 'active';
    
    header("Location: users.php");
    exit;
}

// ====== FILTERS ======
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_role = isset($_GET['role']) ? mysqli_real_escape_string($conn, $_GET['role']) : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE '%$search%' OR email LIKE '%$search%' OR phone LIKE '%$search%')";
}

if (!empty($filter_role)) {
    $where_conditions[] = "role = '$filter_role'";
}

$where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

// ====== GET USERS DATA ======
$users = $conn->query("
    SELECT 
        u.*,
        COUNT(DISTINCT b.id) as total_bookings,
        COUNT(DISTINCT r.id) as total_reviews
    FROM users u
    LEFT JOIN bookings b ON u.id = b.user_id
    LEFT JOIN review r ON u.id = r.user_id
    $where_clause
    GROUP BY u.id
    ORDER BY u.id DESC
");

// ====== GET STATISTICS ======
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as total_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins
    FROM users
")->fetch_assoc();

// Get recent registrations (last 7 days)
$recent_registrations = $conn->query("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE DATE(id) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - LoveMUA Admin</title>
    <link rel="stylesheet" href="assets/css/users.css">
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
            <a href="users.php" class="nav-item active">
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
                <h1>User Management</h1>
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

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span>
                <?php
                if ($_GET['success'] == 'deleted') echo 'User deleted successfully!';
                ?>
            </span>
            <button class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <span>
                <?php
                if ($_GET['error'] == 'self_delete') echo 'You cannot delete your own account!';
                if ($_GET['error'] == 'has_bookings') echo 'Cannot delete user with existing bookings!';
                ?>
            </span>
            <button class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <section class="stats-section">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Total Users</p>
                </div>
            </div>

            <div class="stat-card customers">
                <div class="stat-icon">
                    <i class="fas fa-user"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['total_users'] ?></h3>
                    <p>Customers</p>
                </div>
            </div>

            <div class="stat-card admins">
                <div class="stat-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['total_admins'] ?></h3>
                    <p>Administrators</p>
                </div>
            </div>

            <div class="stat-card recent">
                <div class="stat-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $recent_registrations['count'] ?></h3>
                    <p>New (Last 7 Days)</p>
                </div>
            </div>
        </section>

        <!-- Page Content -->
        <section class="content-section">
            <div class="content-header">
                <div>
                    <h2>Manage Users</h2>
                    <p>View and manage all registered users</p>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" class="search-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by name, email, or phone..."
                            value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-search">Search</button>
                    </div>
                </form>

                <div class="filter-dropdowns">
                    <select name="role" onchange="applyFilter()">
                        <option value="">All Roles</option>
                        <option value="user" <?= $filter_role == 'user' ? 'selected' : '' ?>>Customer</option>
                        <option value="admin" <?= $filter_role == 'admin' ? 'selected' : '' ?>>Administrator</option>
                    </select>
                </div>

                <?php if (!empty($search) || !empty($filter_role)): ?>
                <a href="users.php" class="btn-clear">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
                <?php endif; ?>
            </div>

            <!-- Users Table -->
            <div class="table-container">
                <?php if (mysqli_num_rows($users) > 0): ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User Info</th>
                            <th>Contact</th>
                            <th>Role</th>
                            <th>Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($users, 0);
                        while ($user = $users->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><strong>#<?= str_pad($user['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                    </div>
                                    <div class="user-details">
                                        <strong><?= htmlspecialchars($user['name']) ?></strong>
                                        <small><?= htmlspecialchars($user['email']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="contact-info">
                                    <span><i class="fas fa-phone"></i> <?= htmlspecialchars($user['phone']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="role-badge <?= $user['role'] ?>">
                                    <i class="fas fa-<?= $user['role'] == 'admin' ? 'user-shield' : 'user' ?>"></i>
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="activity-info">
                                    <span><i class="fas fa-calendar-check"></i>
                                        <?= $user['total_bookings'] ?> Bookings</span>
                                    <span><i class="fas fa-star"></i> <?= $user['total_reviews'] ?> Reviews</span>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-action view" title="View Details"
                                        onclick="viewUser(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['name'])) ?>', '<?= htmlspecialchars(addslashes($user['email'])) ?>', '<?= htmlspecialchars($user['phone']) ?>', '<?= $user['role'] ?>', <?= $user['total_bookings'] ?>, <?= $user['total_reviews'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button class="btn-action delete" title="Delete User"
                                        onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars(addslashes($user['name'])) ?>', <?= $user['total_bookings'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Users Found</h3>
                    <p>
                        <?= !empty($search) || !empty($filter_role) ? 'No users match your filters.' : 'No users registered yet.' ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- User Detail Modal -->
    <div class="modal" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user"></i> User Details</h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="user-profile">
                    <div class="profile-avatar" id="modalAvatar"></div>
                    <div class="profile-info">
                        <h2 id="modalName"></h2>
                        <p id="modalRole"></p>
                    </div>
                </div>

                <div class="modal-section">
                    <label><i class="fas fa-envelope"></i> Email:</label>
                    <p id="modalEmail"></p>
                </div>

                <div class="modal-section">
                    <label><i class="fas fa-phone"></i> Phone:</label>
                    <p id="modalPhone"></p>
                </div>

                <div class="modal-section">
                    <label><i class="fas fa-chart-bar"></i> Activity Summary:</label>
                    <div class="activity-grid">
                        <div class="activity-item">
                            <i class="fas fa-calendar-check"></i>
                            <strong id="modalBookings">0</strong>
                            <span>Total Bookings</span>
                        </div>
                        <div class="activity-item">
                            <i class="fas fa-star"></i>
                            <strong id="modalReviews">0</strong>
                            <span>Total Reviews</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

    // Apply Filter
    function applyFilter() {
        const role = document.querySelector('select[name="role"]').value;
        const search = document.querySelector('input[name="search"]').value;

        const url = new URL(window.location.href);
        url.searchParams.set('role', role);
        if (search) url.searchParams.set('search', search);

        window.location.href = url.toString();
    }

    // View User Modal
    function viewUser(id, name, email, phone, role, bookings, reviews) {
        document.getElementById('modalAvatar').textContent = name.charAt(0).toUpperCase();
        document.getElementById('modalName').textContent = name;
        document.getElementById('modalRole').innerHTML =
            `<i class="fas fa-${role == 'admin' ? 'user-shield' : 'user'}"></i> ${role.charAt(0).toUpperCase() + role.slice(1)}`;
        document.getElementById('modalEmail').textContent = email;
        document.getElementById('modalPhone').textContent = phone;
        document.getElementById('modalBookings').textContent = bookings;
        document.getElementById('modalReviews').textContent = reviews;

        document.getElementById('userModal').classList.add('show');
    }

    function closeModal() {
        document.getElementById('userModal').classList.remove('show');
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('userModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    // Delete User
    function deleteUser(id, name, bookings) {
        if (bookings > 0) {
            alert(`Cannot delete user "${name}" because they have ${bookings} existing booking(s).`);
            return;
        }

        if (confirm(`Are you sure you want to delete user "${name}"? This action cannot be undone.`)) {
            window.location.href = `users.php?delete=${id}`;
        }
    }

    // Auto-hide alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.animation = 'slideUp 0.3s ease reverse';
            setTimeout(() => {
                alert.remove();
            }, 300);
        });
    }, 5000);
    </script>
</body>

</html>