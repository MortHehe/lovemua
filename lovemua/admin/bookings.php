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

// ====== UPDATE BOOKING STATUS ======
if (isset($_GET['update_status'])) {
    $id = (int)$_GET['id'];
    $status = $_GET['update_status'];
    
    $allowed_status = ['pending', 'confirmed', 'completed', 'cancelled'];
    
    if (in_array($status, $allowed_status)) {
        $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        
        // Update payment status jika booking completed
        if ($status == 'completed') {
            $conn->query("UPDATE payments SET status = 'paid' WHERE booking_id = '$id'");
        } elseif ($status == 'cancelled') {
            $conn->query("UPDATE payments SET status = 'failed' WHERE booking_id = '$id'");
        }
        
        $_SESSION['message'] = "Booking status successfully updated to " . ucfirst($status) . "!";
    }
    
    header("Location: bookings.php");
    exit;
}

// ====== DELETE BOOKING ======
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Delete related records
    $conn->query("DELETE FROM invoice WHERE booking_id = '$id'");
    $conn->query("DELETE FROM payments WHERE booking_id = '$id'");
    $conn->query("DELETE FROM review WHERE booking_id = '$id'");
    $conn->query("DELETE FROM bookings WHERE id = '$id'");
    
    $_SESSION['message'] = "Booking successfully deleted!";
    header("Location: bookings.php");
    exit;
}

// ====== FILTERS ======
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE '%$search%' OR m.name LIKE '%$search%' OR p.name LIKE '%$search%')";
}

if (!empty($filter_status) && $filter_status != 'all') {
    $where_conditions[] = "b.status = '$filter_status'";
}

if (!empty($filter_date)) {
    $where_conditions[] = "DATE(b.start_time) = '$filter_date'";
}

$where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

// ====== GET BOOKINGS DATA ======
$bookings = $conn->query("
    SELECT 
        b.id,
        b.start_time,
        b.end_time,
        b.status,
        b.location,
        b.notes,
        u.name AS user_name,
        u.email AS user_email,
        u.phone AS user_phone,
        m.name AS mua_name,
        p.name AS package_name,
        p.price AS package_price,
        p.duration_hours,
        (SELECT amount FROM payments WHERE booking_id = b.id LIMIT 1) as payment_amount,
        (SELECT status FROM payments WHERE booking_id = b.id LIMIT 1) as payment_status
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN mua m ON b.mua_id = m.id
    LEFT JOIN packages p ON b.package_id = p.id
    $where_clause
    ORDER BY b.start_time DESC
");

// ====== GET STATISTICS ======
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM bookings
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings Management - LoveMUA Admin</title>
    <link rel="stylesheet" href="assets/css/bookings.css">
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
            <a href="bookings.php" class="nav-item active">
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
                <h1>Bookings Management</h1>
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

        <!-- Statistics Cards -->
        <section class="stats-section">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Total Bookings</p>
                </div>
            </div>

            <div class="stat-card pending">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['pending'] ?></h3>
                    <p>Pending</p>
                </div>
            </div>

            <div class="stat-card confirmed">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['confirmed'] ?></h3>
                    <p>Confirmed</p>
                </div>
            </div>

            <div class="stat-card completed">
                <div class="stat-icon">
                    <i class="fas fa-check-double"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['completed'] ?></h3>
                    <p>Completed</p>
                </div>
            </div>

            <div class="stat-card cancelled">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['cancelled'] ?></h3>
                    <p>Cancelled</p>
                </div>
            </div>
        </section>

        <!-- Page Content -->
        <section class="content-section">
            <div class="content-header">
                <div>
                    <h2>Manage Bookings</h2>
                    <p>View and manage all customer bookings</p>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" class="search-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by user, MUA, or package..."
                            value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-search">Search</button>
                    </div>
                </form>

                <div class="filter-dropdowns">
                    <select name="status" onchange="applyFilter('status', this.value)">
                        <option value="all" <?= $filter_status == 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="confirmed" <?= $filter_status == 'confirmed' ? 'selected' : '' ?>>Confirmed
                        </option>
                        <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>Completed
                        </option>
                        <option value="cancelled" <?= $filter_status == 'cancelled' ? 'selected' : '' ?>>Cancelled
                        </option>
                    </select>

                    <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>"
                        onchange="applyFilter('date', this.value)">
                </div>

                <?php if (!empty($search) || !empty($filter_status) || !empty($filter_date)): ?>
                <a href="bookings.php" class="btn-clear">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
                <?php endif; ?>
            </div>

            <!-- Bookings Table -->
            <div class="table-container">
                <?php if (mysqli_num_rows($bookings) > 0): ?>
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>MUA</th>
                            <th>Package</th>
                            <th>Date & Time</th>
                            <th>Duration</th>
                            <th>Location</th>
                            <th>Notes</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = $bookings->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?= str_pad($booking['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                            <td>
                                <div class="customer-info">
                                    <strong><?= htmlspecialchars($booking['user_name']) ?></strong>
                                    <small><?= htmlspecialchars($booking['user_email']) ?></small>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($booking['mua_name']) ?></td>
                            <td><?= htmlspecialchars($booking['package_name']) ?></td>
                            <td>
                                <div class="datetime-info">
                                    <strong><?= date('d M Y', strtotime($booking['start_time'])) ?></strong>
                                    <small><?= date('H:i', strtotime($booking['start_time'])) ?> -
                                        <?= date('H:i', strtotime($booking['end_time'])) ?></small>
                                </div>
                            </td>
                            <td><?= $booking['duration_hours'] ?> hours</td>
                            <td>
                                <div class="location-info">
                                    <?php if (!empty($booking['location'])): ?>
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?= htmlspecialchars(substr($booking['location'], 0, 30)) ?>
                                    <?= strlen($booking['location']) > 30 ? '...' : '' ?>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="notes-info">
                                    <?php if (!empty($booking['notes'])): ?>
                                    <?= htmlspecialchars(substr($booking['notes'], 0, 40)) ?>
                                    <?= strlen($booking['notes']) > 40 ? '...' : '' ?>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><strong>Rp <?= number_format($booking['payment_amount'], 0, ',', '.') ?></strong></td>

                            <td>
                                <span class="status-badge <?= $booking['status'] ?>">
                                    <?= ucfirst($booking['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($booking['payment_status']): ?>
                                <span class="payment-badge <?= $booking['payment_status'] ?>">
                                    <?= ucfirst($booking['payment_status']) ?>
                                </span>
                                <?php else: ?>
                                <span class="payment-badge pending">No Payment</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($booking['status'] == 'pending'): ?>
                                    <button class="btn-action confirm"
                                        onclick="updateStatus(<?= $booking['id'] ?>, 'confirmed', '<?= htmlspecialchars($booking['user_name']) ?>')">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <?php endif; ?>

                                    <?php if ($booking['status'] == 'confirmed'): ?>
                                    <button class="btn-action complete"
                                        onclick="updateStatus(<?= $booking['id'] ?>, 'completed', '<?= htmlspecialchars($booking['user_name']) ?>')">
                                        <i class="fas fa-check-double"></i>
                                    </button>
                                    <?php endif; ?>

                                    <?php if ($booking['status'] != 'cancelled' && $booking['status'] != 'completed'): ?>
                                    <button class="btn-action cancel"
                                        onclick="updateStatus(<?= $booking['id'] ?>, 'cancelled', '<?= htmlspecialchars($booking['user_name']) ?>')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php endif; ?>

                                    <button class="btn-action delete"
                                        onclick="confirmDelete(<?= $booking['id'] ?>, '<?= htmlspecialchars($booking['user_name']) ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Bookings Found</h3>
                    <p>
                        <?= !empty($search) || !empty($filter_status) || !empty($filter_date) ? 'No bookings match your filters.' : 'No bookings have been made yet.' ?>
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

    // Apply Filter
    function applyFilter(type, value) {
        const url = new URL(window.location.href);
        if (value == 'all' || value == '') {
            url.searchParams.delete(type);
        } else {
            url.searchParams.set(type, value);
        }
        window.location.href = url.toString();
    }

    // Update Booking Status
    function updateStatus(id, status, userName) {
        let message = '';
        if (status == 'confirmed') {
            message = `Confirm booking for ${userName}?`;
        } else if (status == 'completed') {
            message = `Mark booking for ${userName} as completed?`;
        } else if (status == 'cancelled') {
            message = `Cancel booking for ${userName}?\n\nThis action cannot be undone!`;
        }

        if (confirm(message)) {
            window.location.href = `?update_status=${status}&id=${id}`;
        }
    }

    // Confirm Delete
    function confirmDelete(id, userName) {
        if (confirm(
                `Are you sure you want to delete booking for ${userName}?\n\nThis will delete all related payments and invoices!\n\nThis action cannot be undone!`
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