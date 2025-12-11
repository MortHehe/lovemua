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

// ===== FETCH STATISTICS =====

// Total Bookings
$total_bookings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings"))['total'];
$bookings_last_month = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings WHERE MONTH(start_time) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))"))['total'];
$bookings_trend = $bookings_last_month > 0 ? round((($total_bookings - $bookings_last_month) / $bookings_last_month) * 100, 1) : 0;

// Total Revenue This Month
$revenue_query = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) AND status = 'paid'");
$total_revenue = mysqli_fetch_assoc($revenue_query)['total'];
$revenue_last_month = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND status = 'paid'"))['total'];
$revenue_trend = $revenue_last_month > 0 ? round((($total_revenue - $revenue_last_month) / $revenue_last_month) * 100, 1) : 0;

// Total MUAs
$total_muas = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM mua"))['total'];

// Total Packages
$total_packages = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM packages"))['total'];

// Pending Payments
$pending_payments = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM payments WHERE status = 'pending'"))['total'];

// Today's Bookings
$today_bookings = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM bookings WHERE DATE(start_time) = CURDATE()"))['total'];

// Total Users
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'user'"))['total'];

// Pending Reviews
$pending_reviews = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM review"))['total'];

// ===== BOOKING STATUS DISTRIBUTION =====
$status_query = mysqli_query($conn, "SELECT status, COUNT(*) as count FROM bookings GROUP BY status");
$status_data = [];
while ($row = mysqli_fetch_assoc($status_query)) {
    $status_data[$row['status']] = $row['count'];
}

// ===== TOP PERFORMING MUAs =====
$top_muas = mysqli_query($conn, "SELECT m.name, COUNT(b.id) as total_bookings, COALESCE(AVG(r.rating), 0) as avg_rating
                                 FROM mua m
                                 LEFT JOIN bookings b ON m.id = b.mua_id
                                 LEFT JOIN packages p ON b.package_id = p.id
                                 LEFT JOIN review r ON p.id = r.package_id
                                 GROUP BY m.id
                                 ORDER BY total_bookings DESC
                                 LIMIT 5");

// ===== TOP PACKAGES =====
$top_packages = mysqli_query($conn, "SELECT p.name, pc.name as category, COUNT(b.id) as total_bookings
                                     FROM packages p
                                     LEFT JOIN bookings b ON p.id = b.package_id
                                     LEFT JOIN packages_categories pc ON p.category_id = pc.id
                                     GROUP BY p.id
                                     ORDER BY total_bookings DESC
                                     LIMIT 5");

// ===== RECENT ACTIVITIES =====
$recent_activities = mysqli_query($conn, "
    (SELECT 'booking' as type, b.id as ref_id, u.name as user_name, b.start_time as activity_time, p.name as detail
     FROM bookings b
     JOIN users u ON b.user_id = u.id
     JOIN packages p ON b.package_id = p.id
     ORDER BY b.id DESC LIMIT 5)
    UNION ALL
    (SELECT 'payment' as type, pay.id as ref_id, u.name as user_name, pay.created_at as activity_time, CONCAT('Rp ', FORMAT(pay.amount, 0)) as detail
     FROM payments pay
     JOIN bookings b ON pay.booking_id = b.id
     JOIN users u ON b.user_id = u.id
     WHERE pay.status = 'paid'
     ORDER BY pay.created_at DESC LIMIT 5)
    ORDER BY activity_time DESC
    LIMIT 10
");

// ===== REVENUE CHART DATA (Last 6 Months) =====
$revenue_chart_query = mysqli_query($conn, "
    SELECT DATE_FORMAT(created_at, '%b') as month, COALESCE(SUM(amount), 0) as total
    FROM payments
    WHERE status = 'paid'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY DATE_FORMAT(created_at, '%Y-%m')
");
$revenue_chart_months = [];
$revenue_chart_values = [];
while ($row = mysqli_fetch_assoc($revenue_chart_query)) {
    $revenue_chart_months[] = $row['month'];
    $revenue_chart_values[] = $row['total'];
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LoveMUA</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <a href="dashboard.php" class="nav-item active">
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
                <h1>Dashboard</h1>
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

        <!-- Stats Cards -->
        <section class="stats-section">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon bookings">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= $total_bookings ?></h3>
                        <p>Total Bookings</p>
                        <span class="stat-trend <?= $bookings_trend >= 0 ? 'positive' : 'negative' ?>">
                            <i class="fas fa-arrow-<?= $bookings_trend >= 0 ? 'up' : 'down' ?>"></i>
                            <?= abs($bookings_trend) ?>% from last month
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon revenue">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Rp <?= number_format($total_revenue / 1000000, 1) ?>M</h3>
                        <p>Revenue This Month</p>
                        <span class="stat-trend <?= $revenue_trend >= 0 ? 'positive' : 'negative' ?>">
                            <i class="fas fa-arrow-<?= $revenue_trend >= 0 ? 'up' : 'down' ?>"></i>
                            <?= abs($revenue_trend) ?>% from last month
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon muas">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= $total_muas ?></h3>
                        <p>Active MUAs</p>
                        <span class="stat-info">
                            <i class="fas fa-box-open"></i> <?= $total_packages ?> packages available
                        </span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-details">
                        <h3><?= $total_users ?></h3>
                        <p>Total Users</p>
                        <span class="stat-info">
                            <i class="fas fa-calendar-day"></i> <?= $today_bookings ?> bookings today
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <!-- Charts & Activities -->
        <section class="dashboard-grid">
            <!-- Revenue Chart -->
            <div class="dashboard-card chart-card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-line"></i> Revenue Overview</h2>
                    <span class="card-subtitle">Last 6 months</span>
                </div>
                <div class="card-body">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="dashboard-card activities-card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Recent Activities</h2>
                </div>
                <div class="card-body">
                    <div class="activities-list">
                        <?php while ($activity = mysqli_fetch_assoc($recent_activities)): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?= $activity['type'] ?>">
                                <i
                                    class="fas fa-<?= $activity['type'] == 'booking' ? 'calendar-plus' : 'money-bill-wave' ?>"></i>
                            </div>
                            <div class="activity-details">
                                <p>
                                    <?php if ($activity['type'] == 'booking'): ?>
                                    <strong><?= htmlspecialchars($activity['user_name']) ?></strong> booked
                                    <em><?= htmlspecialchars($activity['detail']) ?></em>
                                    <?php else: ?>
                                    Payment received from
                                    <strong><?= htmlspecialchars($activity['user_name']) ?></strong>
                                    <em><?= $activity['detail'] ?></em>
                                    <?php endif; ?>
                                </p>
                                <span
                                    class="activity-time"><?= date('M d, H:i', strtotime($activity['activity_time'])) ?></span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Booking Status Chart -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2><i class="fas fa-chart-pie"></i> Booking Status</h2>
                </div>
                <div class="card-body">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="dashboard-card quick-actions-card">
                <div class="card-header">
                    <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="mua.php?add=true" class="quick-action-btn">
                            <i class="fas fa-user-plus"></i>
                            <span>Add MUA</span>
                        </a>
                        <a href="packages.php?add=true" class="quick-action-btn">
                            <i class="fas fa-box"></i>
                            <span>Add Package</span>
                        </a>
                        <a href="bookings.php" class="quick-action-btn">
                            <i class="fas fa-calendar-alt"></i>
                            <span>View Bookings</span>
                        </a>
                        <a href="payments.php?status=pending" class="quick-action-btn">
                            <i class="fas fa-clock"></i>
                            <span>Pending Payments</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Alerts & Top Performers -->
        <section class="bottom-section">
            <!-- Alerts -->
            <div class="dashboard-card alerts-card">
                <div class="card-header">
                    <h2><i class="fas fa-exclamation-circle"></i> Requires Attention</h2>
                </div>
                <div class="card-body">
                    <div class="alerts-list">
                        <?php if ($pending_payments > 0): ?>
                        <a href="payments.php?status=pending" class="alert-item warning">
                            <i class="fas fa-clock"></i>
                            <div>
                                <strong><?= $pending_payments ?> Pending Payments</strong>
                                <p>Waiting for confirmation</p>
                            </div>
                        </a>
                        <?php endif; ?>
                        <?php if ($today_bookings > 0): ?>
                        <a href="bookings.php?date=today" class="alert-item info">
                            <i class="fas fa-calendar-day"></i>
                            <div>
                                <strong><?= $today_bookings ?> Bookings Today</strong>
                                <p>Scheduled for today</p>
                            </div>
                        </a>
                        <?php endif; ?>
                        <?php if ($pending_reviews > 0): ?>
                        <a href="review.php" class="alert-item success">
                            <i class="fas fa-star"></i>
                            <div>
                                <strong><?= $pending_reviews ?> Reviews</strong>
                                <p>Customer feedback available</p>
                            </div>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top MUAs -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2><i class="fas fa-trophy"></i> Top Performing MUAs</h2>
                </div>
                <div class="card-body">
                    <div class="top-list">
                        <?php $rank = 1; while ($mua = mysqli_fetch_assoc($top_muas)): ?>
                        <div class="top-item">
                            <span class="rank">#<?= $rank++ ?></span>
                            <div class="top-details">
                                <strong><?= htmlspecialchars($mua['name']) ?></strong>
                                <p><?= $mua['total_bookings'] ?> bookings | ⭐
                                    <?= number_format($mua['avg_rating'], 1) ?></p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>

            <!-- Top Packages -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h2><i class="fas fa-box-open"></i> Most Booked Packages</h2>
                </div>
                <div class="card-body">
                    <div class="top-list">
                        <?php $rank = 1; while ($pkg = mysqli_fetch_assoc($top_packages)): ?>
                        <div class="top-item">
                            <span class="rank">#<?= $rank++ ?></span>
                            <div class="top-details">
                                <strong><?= htmlspecialchars($pkg['name']) ?></strong>
                                <p><?= $pkg['total_bookings'] ?> bookings | <?= htmlspecialchars($pkg['category']) ?>
                                </p>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
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

    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($revenue_chart_months) ?>,
            datasets: [{
                label: 'Revenue',
                data: <?= json_encode($revenue_chart_values) ?>,
                borderColor: '#e91e63',
                backgroundColor: 'rgba(233, 30, 99, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'Rp ' + (value / 1000000).toFixed(1) + 'M';
                        }
                    }
                }
            }
        }
    });

    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Confirmed', 'Completed', 'Cancelled'],
            datasets: [{
                data: [
                    <?= $status_data['pending'] ?? 0 ?>,
                    <?= $status_data['confirmed'] ?? 0 ?>,
                    <?= $status_data['completed'] ?? 0 ?>,
                    <?= $status_data['cancelled'] ?? 0 ?>
                ],
                backgroundColor: ['#ff9800', '#4caf50', '#2196f3', '#f44336']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    </script>
</body>

</html>