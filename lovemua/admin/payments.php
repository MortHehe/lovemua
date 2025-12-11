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

// ====== UPDATE PAYMENT STATUS ======
if (isset($_GET['update_status'])) {
    $pid = (int)$_GET['id'];
    $new_status = $_GET['update_status'];
    
    $allowed_status = ['pending', 'paid', 'failed'];
    
    if (in_array($new_status, $allowed_status)) {
        $stmt = $conn->prepare("UPDATE payments SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $pid);
        $stmt->execute();
        
        // Update booking status jika payment paid
        if ($new_status == 'paid') {
            $conn->query("UPDATE bookings SET status = 'completed' WHERE id = (SELECT booking_id FROM payments WHERE id = '$pid')");
        }
        
        $_SESSION['message'] = "Payment status successfully updated to " . ucfirst($new_status) . "!";
    }
    
    header("Location: payments.php");
    exit;
}

// ====== FILTERS ======
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_method = isset($_GET['method']) ? $_GET['method'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE '%$search%' OR m.name LIKE '%$search%' OR pk.name LIKE '%$search%')";
}

if (!empty($filter_status) && $filter_status != 'all') {
    $where_conditions[] = "p.status = '$filter_status'";
}

if (!empty($filter_method) && $filter_method != 'all') {
    $where_conditions[] = "p.method = '$filter_method'";
}

if (!empty($filter_date)) {
    $where_conditions[] = "DATE(p.created_at) = '$filter_date'";
}

$where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

// ====== GET PAYMENTS DATA ======
$payments = $conn->query("
    SELECT 
        p.id,
        p.booking_id,
        p.amount,
        p.method,
        p.status,
        p.created_at,
        b.start_time,
        b.status as booking_status,
        u.name AS user_name,
        u.email AS user_email,
        m.name AS mua_name,
        pk.name AS package_name
    FROM payments p
    LEFT JOIN bookings b ON p.booking_id = b.id
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN mua m ON b.mua_id = m.id
    LEFT JOIN packages pk ON b.package_id = pk.id
    $where_clause
    ORDER BY p.created_at DESC
");

// ====== GET STATISTICS ======
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_revenue
    FROM payments
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments Management - LoveMUA Admin</title>
    <link rel="stylesheet" href="assets/css/payments.css">
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
            <a href="payments.php" class="nav-item active">
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
                <h1>Payments Management</h1>
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
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Total Payments</p>
                </div>
            </div>

            <div class="stat-card revenue">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3>Rp <?= number_format($stats['total_revenue'], 0, ',', '.') ?></h3>
                    <p>Total Revenue</p>
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

            <div class="stat-card paid">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['paid'] ?></h3>
                    <p>Paid</p>
                </div>
            </div>

            <div class="stat-card failed">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['failed'] ?></h3>
                    <p>Failed</p>
                </div>
            </div>
        </section>

        <!-- Page Content -->
        <section class="content-section">
            <div class="content-header">
                <div>
                    <h2>Manage Payments</h2>
                    <p>View and manage all payment transactions</p>
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
                        <option value="paid" <?= $filter_status == 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="failed" <?= $filter_status == 'failed' ? 'selected' : '' ?>>Failed</option>
                    </select>

                    <select name="method" onchange="applyFilter('method', this.value)">
                        <option value="all" <?= $filter_method == 'all' ? 'selected' : '' ?>>All Methods</option>
                        <option value="bank_transfer" <?= $filter_method == 'bank_transfer' ? 'selected' : '' ?>>Bank
                            Transfer</option>
                        <option value="credit_card" <?= $filter_method == 'credit_card' ? 'selected' : '' ?>>Credit
                            Card</option>
                        <option value="e_wallet" <?= $filter_method == 'e_wallet' ? 'selected' : '' ?>>E-Wallet
                        </option>
                    </select>

                    <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>"
                        onchange="applyFilter('date', this.value)">
                </div>

                <?php if (!empty($search) || !empty($filter_status) || !empty($filter_method) || !empty($filter_date)): ?>
                <a href="payments.php" class="btn-clear">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
                <?php endif; ?>
            </div>

            <!-- Payments Table -->
            <div class="table-container">
                <?php if (mysqli_num_rows($payments) > 0): ?>
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Booking ID</th>
                            <th>Customer</th>
                            <th>MUA</th>
                            <th>Package</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($payment = $payments->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?= str_pad($payment['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                            <td><strong>#<?= str_pad($payment['booking_id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                            <td>
                                <div class="customer-info">
                                    <strong><?= htmlspecialchars($payment['user_name']) ?></strong>
                                    <small><?= htmlspecialchars($payment['user_email']) ?></small>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($payment['mua_name']) ?></td>
                            <td><?= htmlspecialchars($payment['package_name']) ?></td>
                            <td><strong class="amount">Rp <?= number_format($payment['amount'], 0, ',', '.') ?></strong>
                            </td>
                            <td>
                                <span class="method-badge">
                                    <?php if ($payment['method'] == 'bank_transfer'): ?>
                                    <i class="fas fa-university"></i>
                                    <?php elseif ($payment['method'] == 'credit_card'): ?>
                                    <i class="fas fa-credit-card"></i>
                                    <?php else: ?>
                                    <i class="fas fa-wallet"></i>
                                    <?php endif; ?>
                                    <?= ucfirst(str_replace('_', ' ', $payment['method'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?= $payment['status'] ?>">
                                    <?= ucfirst($payment['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="datetime-info">
                                    <strong><?= date('d M Y', strtotime($payment['created_at'])) ?></strong>
                                    <small><?= date('H:i', strtotime($payment['created_at'])) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($payment['status'] == 'pending'): ?>
                                    <button class="btn-action paid"
                                        onclick="updateStatus(<?= $payment['id'] ?>, 'paid', '<?= htmlspecialchars($payment['user_name']) ?>')">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn-action failed"
                                        onclick="updateStatus(<?= $payment['id'] ?>, 'failed', '<?= htmlspecialchars($payment['user_name']) ?>')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <?php else: ?>
                                    <span class="no-action">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-receipt"></i>
                    <h3>No Payments Found</h3>
                    <p>
                        <?= !empty($search) || !empty($filter_status) || !empty($filter_method) || !empty($filter_date) ? 'No payments match your filters.' : 'No payments have been made yet.' ?>
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

    // Update Payment Status
    function updateStatus(id, status, userName) {
        let message = '';
        if (status == 'paid') {
            message = `Mark payment from ${userName} as PAID?\n\nThis will also mark the booking as completed.`;
        } else if (status == 'failed') {
            message = `Mark payment from ${userName} as FAILED?\n\nThis action cannot be undone!`;
        }

        if (confirm(message)) {
            window.location.href = `?update_status=${status}&id=${id}`;
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