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

// ====== FILTERS ======
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(i.invoice_number LIKE '%$search%' OR u.name LIKE '%$search%' OR m.name LIKE '%$search%' OR pk.name LIKE '%$search%')";
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "DATE(i.issued_date) >= '$filter_date_from'";
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "DATE(i.issued_date) <= '$filter_date_to'";
}

$where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

// ====== GET INVOICES DATA ======
$invoices = $conn->query("
    SELECT 
        i.id,
        i.booking_id,
        i.payment_id,
        i.invoice_number,
        i.amount,
        i.issued_date,
        u.name AS user_name,
        u.email AS user_email,
        m.name AS mua_name,
        pk.name AS package_name,
        b.start_time,
        b.location,
        b.notes,
        p.status AS payment_status
    FROM invoice i
    LEFT JOIN bookings b ON i.booking_id = b.id
    LEFT JOIN payments p ON i.payment_id = p.id
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN mua m ON b.mua_id = m.id
    LEFT JOIN packages pk ON b.package_id = pk.id
    $where_clause
    ORDER BY i.issued_date DESC
");

// ====== GET STATISTICS ======
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(amount) as total_amount
    FROM invoice
")->fetch_assoc();

// Get monthly stats
$monthly_stats = $conn->query("
    SELECT 
        DATE_FORMAT(issued_date, '%Y-%m') as month,
        COUNT(*) as count,
        SUM(amount) as amount
    FROM invoice
    WHERE issued_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(issued_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Management - LoveMUA Admin</title>
    <link rel="stylesheet" href="assets/css/invoice.css">
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
            <a href="invoice.php" class="nav-item active">
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
                <h1>Invoice Management</h1>
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

        <!-- Statistics Cards -->
        <section class="stats-section">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Total Invoices</p>
                </div>
            </div>

            <div class="stat-card revenue">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-info">
                    <h3>Rp <?= number_format($stats['total_amount'], 0, ',', '.') ?></h3>
                    <p>Total Amount</p>
                </div>
            </div>

            <?php if ($monthly_stats && mysqli_num_rows($monthly_stats) > 0): 
                $current_month = $monthly_stats->fetch_assoc();
            ?>
            <div class="stat-card monthly">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $current_month['count'] ?></h3>
                    <p>This Month</p>
                </div>
            </div>

            <div class="stat-card monthly-revenue">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3>Rp <?= number_format($current_month['amount'], 0, ',', '.') ?></h3>
                    <p>Monthly Revenue</p>
                </div>
            </div>
            <?php endif; ?>
        </section>

        <!-- Page Content -->
        <section class="content-section">
            <div class="content-header">
                <div>
                    <h2>Manage Invoices</h2>
                    <p>View and manage all generated invoices</p>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" class="search-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search"
                            placeholder="Search by invoice number, customer, MUA, or package..."
                            value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-search">Search</button>
                    </div>
                </form>

                <div class="filter-dropdowns">
                    <div class="date-range">
                        <label><i class="fas fa-calendar"></i> From:</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>"
                            onchange="applyDateFilter()">
                    </div>
                    <div class="date-range">
                        <label><i class="fas fa-calendar"></i> To:</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>"
                            onchange="applyDateFilter()">
                    </div>
                </div>

                <?php if (!empty($search) || !empty($filter_date_from) || !empty($filter_date_to)): ?>
                <a href="invoice.php" class="btn-clear">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
                <?php endif; ?>
            </div>

            <!-- Invoices Table -->
            <div class="table-container">
                <?php if (mysqli_num_rows($invoices) > 0): ?>
                <table class="invoices-table">
                    <thead>
                        <tr>
                            <th>Invoice ID</th>
                            <th>Invoice Number</th>
                            <th>Booking ID</th>
                            <th>Customer</th>
                            <th>MUA</th>
                            <th>Package</th>
                            <th>Amount</th>
                            <th>Payment Status</th>
                            <th>Issued Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($invoices, 0);
                        while ($invoice = $invoices->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><strong>#<?= str_pad($invoice['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                            <td>
                                <span class="invoice-number">
                                    <i class="fas fa-file-invoice"></i>
                                    <?= htmlspecialchars($invoice['invoice_number']) ?>
                                </span>
                            </td>
                            <td><strong>#<?= str_pad($invoice['booking_id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                            <td>
                                <div class="customer-info">
                                    <strong><?= htmlspecialchars($invoice['user_name']) ?></strong>
                                    <small><?= htmlspecialchars($invoice['user_email']) ?></small>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($invoice['mua_name']) ?></td>
                            <td><?= htmlspecialchars($invoice['package_name']) ?></td>
                            <td><strong class="amount">Rp <?= number_format($invoice['amount'], 0, ',', '.') ?></strong>
                            </td>
                            <td>
                                <span class="payment-badge <?= $invoice['payment_status'] ?>">
                                    <?= ucfirst($invoice['payment_status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="datetime-info">
                                    <strong><?= date('d M Y', strtotime($invoice['issued_date'])) ?></strong>
                                    <small><?= date('H:i', strtotime($invoice['issued_date'])) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="invoice_view.php?id=<?= $invoice['id'] ?>" class="btn-action view"
                                        title="View Invoice">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button class="btn-action print" title="Print Invoice"
                                        onclick="printInvoice(<?= $invoice['id'] ?>)">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice"></i>
                    <h3>No Invoices Found</h3>
                    <p>
                        <?= !empty($search) || !empty($filter_date_from) || !empty($filter_date_to) ? 'No invoices match your filters.' : 'No invoices have been generated yet.' ?>
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

    // Apply Date Filter
    function applyDateFilter() {
        const dateFrom = document.querySelector('input[name="date_from"]').value;
        const dateTo = document.querySelector('input[name="date_to"]').value;
        const search = document.querySelector('input[name="search"]').value;

        const url = new URL(window.location.href);
        url.searchParams.set('date_from', dateFrom);
        url.searchParams.set('date_to', dateTo);
        if (search) url.searchParams.set('search', search);

        window.location.href = url.toString();
    }

    // Print Invoice
    function printInvoice(id) {
        window.open(`invoice_view.php?id=${id}&print=1`, '_blank');
    }
    </script>
</body>

</html>