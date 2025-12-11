<?php
session_start();

// HARUS LOGIN
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// CEK ROLE JANGAN ADMIN MASUK USER PAGE
if ($_SESSION['role'] === "admin") {
    header("Location: admin/dashboard.php");
    exit;
}

// Include database connection
require_once 'includes/db.php';

// Handle Cancel Booking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'cancel') {
    $booking_id = (int)$_POST['booking_id'];
    
    // Verify ownership
    $verify_query = "SELECT id, status FROM bookings WHERE id = ? AND user_id = ?";
    $stmt_verify = mysqli_prepare($conn, $verify_query);
    mysqli_stmt_bind_param($stmt_verify, "ii", $booking_id, $_SESSION['user_id']);
    mysqli_stmt_execute($stmt_verify);
    $verify_result = mysqli_stmt_get_result($stmt_verify);
    
    if (mysqli_num_rows($verify_result) > 0) {
        $booking = mysqli_fetch_assoc($verify_result);
        
        // Only allow cancel if pending or confirmed
        if ($booking['status'] == 'pending' || $booking['status'] == 'confirmed') {
            // Update booking status
            $cancel_query = "UPDATE bookings SET status = 'cancelled' WHERE id = ?";
            $stmt_cancel = mysqli_prepare($conn, $cancel_query);
            mysqli_stmt_bind_param($stmt_cancel, "i", $booking_id);
            mysqli_stmt_execute($stmt_cancel);
            
            // Update payment status
            $payment_cancel = "UPDATE payments SET status = 'failed' WHERE booking_id = ?";
            $stmt_payment = mysqli_prepare($conn, $payment_cancel);
            mysqli_stmt_bind_param($stmt_payment, "i", $booking_id);
            mysqli_stmt_execute($stmt_payment);
            
            $_SESSION['message'] = "Booking cancelled successfully.";
        }
    }
    
    header("Location: my-bookings.php");
    exit;
}

// Filters
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';

// Build WHERE clause
$where_conditions = ["b.user_id = " . $_SESSION['user_id']];

if ($status_filter != 'all') {
    $where_conditions[] = "b.status = '$status_filter'";
}

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE '%$search%' OR m.name LIKE '%$search%')";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Sort clause
$order_clause = "ORDER BY b.id DESC";
switch ($sort) {
    case 'latest':
        $order_clause = "ORDER BY b.id DESC";
        break;
    case 'oldest':
        $order_clause = "ORDER BY b.id ASC";
        break;
    case 'date_nearest':
        $order_clause = "ORDER BY b.start_time ASC";
        break;
    case 'price_high':
        $order_clause = "ORDER BY p.price DESC";
        break;
    case 'price_low':
        $order_clause = "ORDER BY p.price ASC";
        break;
}

// Fetch Bookings
$query_bookings = "SELECT b.*, 
                   p.id as package_id, p.name as package_name, p.price, p.duration_hours,
                   pc.name as category_name,
                   m.id as mua_id, m.name as mua_name, m.phone as mua_phone, m.photo as mua_photo,
                   pay.amount, pay.status as payment_status,
                   (SELECT pi.filename FROM package_images pi WHERE pi.package_id = p.id ORDER BY pi.id ASC LIMIT 1) as package_image
                   FROM bookings b
                   JOIN packages p ON b.package_id = p.id
                   JOIN packages_categories pc ON p.category_id = pc.id
                   JOIN mua m ON b.mua_id = m.id
                   LEFT JOIN payments pay ON pay.booking_id = b.id
                   $where_clause
                   $order_clause";

$result_bookings = mysqli_query($conn, $query_bookings);

// Count by status for tabs
$count_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
                FROM bookings WHERE user_id = " . $_SESSION['user_id'];
$count_result = mysqli_query($conn, $count_query);
$counts = mysqli_fetch_assoc($count_result);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - LoveMUA</title>
    <link rel="stylesheet" href="assets/css/my-bookings.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <!-- Floating Social Media Sidebar -->
    <div class="floating-social">
        <a href="https://instagram.com/lovemua" target="_blank" class="social-link instagram">
            <i class="fab fa-instagram"></i>
        </a>
        <a href="https://facebook.com/lovemua" target="_blank" class="social-link facebook">
            <i class="fab fa-facebook-f"></i>
        </a>
        <a href="https://wa.me/628123456789" target="_blank" class="social-link whatsapp">
            <i class="fab fa-whatsapp"></i>
        </a>
        <a href="https://twitter.com/lovemua" target="_blank" class="social-link twitter">
            <i class="fab fa-twitter"></i>
        </a>
    </div>

    <!-- Navbar -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <a href="index.php">
                    <h1>Love<span>MUA</span></h1>
                </a>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php">Home</a></li>
                <li><a href="all-muas.php">Our MUAs</a></li>
                <li><a href="index.php#categories">Categories</a></li>
                <li><a href="all-packages.php">Packages</a></li>
                <li><a href="index.php#contact">Contact</a></li>
            </ul>
            <div class="nav-user">
                <span class="user-name">Hi, <?= htmlspecialchars($_SESSION['name']); ?>!</span>
                <div class="user-dropdown">
                    <button class="user-btn">
                        <i class="fas fa-user-circle"></i>
                    </button>
                    <div class="dropdown-content">
                        <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                        <a href="my-bookings.php" class="active"><i class="fas fa-calendar"></i> My Bookings</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <span>/</span>
                <span>My Bookings</span>
            </div>
            <h1>My Bookings</h1>
            <p>Manage all your makeup service bookings</p>
        </div>
    </section>

    <!-- Success Message -->
    <?php if (isset($_SESSION['message'])): ?>
    <div class="alert-message success">
        <div class="container">
            <i class="fas fa-check-circle"></i>
            <?= htmlspecialchars($_SESSION['message']) ?>
        </div>
    </div>
    <?php unset($_SESSION['message']); endif; ?>

    <!-- Filter & Search Section -->
    <section class="filter-section">
        <div class="container">
            <!-- Status Tabs -->
            <div class="status-tabs">
                <a href="?status=all" class="tab <?= $status_filter == 'all' ? 'active' : '' ?>">
                    All <span class="count"><?= $counts['total'] ?></span>
                </a>
                <a href="?status=pending" class="tab <?= $status_filter == 'pending' ? 'active' : '' ?>">
                    Pending <span class="count"><?= $counts['pending'] ?></span>
                </a>
                <a href="?status=confirmed" class="tab <?= $status_filter == 'confirmed' ? 'active' : '' ?>">
                    Confirmed <span class="count"><?= $counts['confirmed'] ?></span>
                </a>
                <a href="?status=completed" class="tab <?= $status_filter == 'completed' ? 'active' : '' ?>">
                    Completed <span class="count"><?= $counts['completed'] ?></span>
                </a>
                <a href="?status=cancelled" class="tab <?= $status_filter == 'cancelled' ? 'active' : '' ?>">
                    Cancelled <span class="count"><?= $counts['cancelled'] ?></span>
                </a>
            </div>

            <!-- Search & Sort -->
            <div class="filter-controls">
                <form method="GET" class="search-form">
                    <input type="hidden" name="status" value="<?= $status_filter ?>">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by package or MUA name..."
                            value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="search-btn">Search</button>
                    </div>
                </form>

                <div class="sort-wrapper">
                    <label><i class="fas fa-sort"></i> Sort By:</label>
                    <select id="sort-select" onchange="applySort(this.value)">
                        <option value="latest" <?= $sort == 'latest' ? 'selected' : '' ?>>Latest First</option>
                        <option value="oldest" <?= $sort == 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="date_nearest" <?= $sort == 'date_nearest' ? 'selected' : '' ?>>Date (Nearest)
                        </option>
                        <option value="price_high" <?= $sort == 'price_high' ? 'selected' : '' ?>>Price (High to Low)
                        </option>
                        <option value="price_low" <?= $sort == 'price_low' ? 'selected' : '' ?>>Price (Low to High)
                        </option>
                    </select>
                </div>
            </div>

            <?php if (!empty($search)): ?>
            <div class="active-search">
                <span>Search: "<?= htmlspecialchars($search) ?>"</span>
                <a href="?status=<?= $status_filter ?>" class="clear-search">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Bookings List Section -->
    <section class="bookings-section">
        <div class="container">
            <?php if (mysqli_num_rows($result_bookings) > 0): ?>
            <div class="bookings-grid">
                <?php while ($booking = mysqli_fetch_assoc($result_bookings)): ?>
                <div class="booking-card">
                    <div class="booking-image">
                        <img src="<?= !empty($booking['package_image']) ? 'uploads/mua_packages/' . htmlspecialchars($booking['package_image']) : 'assets/images/package-placeholder.jpg' ?>"
                            alt="<?= htmlspecialchars($booking['package_name']) ?>"
                            onerror="this.src='assets/images/package-placeholder.jpg'">
                        <div class="status-badge <?= strtolower($booking['status']) ?>">
                            <?= ucfirst($booking['status']) ?>
                        </div>
                    </div>

                    <div class="booking-content">
                        <div class="booking-header">
                            <h3><?= htmlspecialchars($booking['package_name']) ?></h3>
                            <span class="booking-id">#<?= str_pad($booking['id'], 5, '0', STR_PAD_LEFT) ?></span>
                        </div>

                        <div class="mua-info-small">
                            <img src="<?= !empty($booking['mua_photo']) ? 'admin/assets/images/mua/' . htmlspecialchars($booking['mua_photo']) : 'assets/images/default-mua.jpg' ?>"
                                alt="<?= htmlspecialchars($booking['mua_name']) ?>">
                            <div>
                                <span class="label">Makeup Artist</span>
                                <a href="mua-detail.php?id=<?= $booking['mua_id'] ?>" class="mua-name">
                                    <?= htmlspecialchars($booking['mua_name']) ?>
                                </a>
                            </div>
                        </div>

                        <div class="booking-details">
                            <div class="detail-row">
                                <i class="fas fa-calendar-day"></i>
                                <span><?= date('l, d F Y', strtotime($booking['start_time'])) ?></span>
                            </div>
                            <div class="detail-row">
                                <i class="fas fa-clock"></i>
                                <span><?= date('H:i', strtotime($booking['start_time'])) ?> -
                                    <?= date('H:i', strtotime($booking['end_time'])) ?></span>
                            </div>
                            <div class="detail-row">
                                 <i class="fas fa-map-marker-alt"></i>
                                 <span class="location"><?= htmlspecialchars($booking['location']) ?></span>
                            </div>
                            <div class="detail-row">
                                <i class="fas fa-tag"></i>
                                <span class="price">Rp <?= number_format($booking['amount'], 0, ',', '.') ?></span>
                            </div>

                            <?php if (!empty($booking['notes'])): ?>
                            <div class="detail-row notes-row">
                                <i class="fas fa-sticky-note"></i>
                                <span class="notes"><?= htmlspecialchars(substr($booking['notes'], 0, 80)) ?><?= strlen($booking['notes']) > 80 ? '...' : '' ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="booking-actions">
                            <?php if ($booking['status'] == 'pending'): ?>
                            <a href="payment.php?booking_id=<?= $booking['id'] ?>" class="btn-action primary">
                                <i class="fas fa-credit-card"></i> Pay Now
                            </a>
                            <button onclick="cancelBooking(<?= $booking['id'] ?>)" class="btn-action danger">
                                <i class="fas fa-times"></i> Cancel
                            </button>

                            <?php elseif ($booking['status'] == 'confirmed'): ?>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $booking['mua_phone']) ?>?text=Hi, I have a booking (ID: #<?= str_pad($booking['id'], 5, '0', STR_PAD_LEFT) ?>)"
                                target="_blank" class="btn-action success">
                                <i class="fab fa-whatsapp"></i> Contact MUA
                            </a>
                            <button onclick="cancelBooking(<?= $booking['id'] ?>, true)" class="btn-action danger">
                                <i class="fas fa-times"></i> Cancel
                            </button>

                            <?php elseif ($booking['status'] == 'completed'): ?>
                            <a href="payment.php?booking_id=<?= $booking['id'] ?>" class="btn-action primary">
                                <i class="fas fa-file-invoice"></i> View Invoice
                            </a>
                            <a href="package-detail.php?id=<?= $booking['package_id'] ?>" class="btn-action secondary">
                                <i class="fas fa-redo"></i> Book Again
                            </a>

                            <?php elseif ($booking['status'] == 'cancelled'): ?>
                            <a href="package-detail.php?id=<?= $booking['package_id'] ?>" class="btn-action primary">
                                <i class="fas fa-redo"></i> Book Again
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <h3>No Bookings Found</h3>
                <p>
                    <?php if ($status_filter != 'all'): ?>
                    You don't have any <?= $status_filter ?> bookings yet.
                    <?php elseif (!empty($search)): ?>
                    No bookings match your search criteria.
                    <?php else: ?>
                    You haven't booked any packages yet. Start exploring our amazing makeup services!
                    <?php endif; ?>
                </p>
                <div class="empty-actions">
                    <?php if (!empty($search) || $status_filter != 'all'): ?>
                    <a href="my-bookings.php" class="btn-empty secondary">
                        <i class="fas fa-list"></i> View All Bookings
                    </a>
                    <?php endif; ?>
                    <a href="all-packages.php" class="btn-empty primary">
                        <i class="fas fa-box-open"></i> Browse Packages
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-col">
                    <h3>Love<span>MUA</span></h3>
                    <p>Professional makeup artist services for all your special occasions. Beauty, elegance, and
                        perfection in every touch.</p>
                    <div class="footer-social">
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-whatsapp"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="all-muas.php">Our MUAs</a></li>
                        <li><a href="all-categories.php">Categories</a></li>
                        <li><a href="all-packages.php">Packages</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Services</h4>
                    <ul>
                        <li><a href="#">Wedding Makeup</a></li>
                        <li><a href="#">Graduation Makeup</a></li>
                        <li><a href="#">Party Makeup</a></li>
                        <li><a href="#">Photoshoot Makeup</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Contact Us</h4>
                    <ul class="contact-info">
                        <li><i class="fas fa-map-marker-alt"></i> Jakarta, Indonesia</li>
                        <li><i class="fas fa-phone"></i> +62 812-3456-7890</li>
                        <li><i class="fas fa-envelope"></i> info@lovemua.com</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 LoveMUA. All rights reserved. | Designed with <i class="fas fa-heart"></i> for beauty</p>
            </div>
        </div>
    </footer>

    <!-- Cancel Form (Hidden) -->
    <form method="POST" id="cancelForm" style="display: none;">
        <input type="hidden" name="action" value="cancel">
        <input type="hidden" name="booking_id" id="cancelBookingId">
    </form>

    <script>
    // User Dropdown Toggle
    const userBtn = document.querySelector('.user-btn');
    const dropdownContent = document.querySelector('.dropdown-content');

    userBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdownContent.classList.toggle('show');
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.user-dropdown')) {
            dropdownContent.classList.remove('show');
        }
    });

    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // Sort function
    function applySort(sortValue) {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', sortValue);
        window.location.href = url.toString();
    }

    // Cancel Booking with Confirmation
    function cancelBooking(bookingId, isConfirmed = false) {
        let message = isConfirmed ?
            'Are you sure you want to cancel this confirmed booking? A cancellation fee may apply.' :
            'Are you sure you want to cancel this booking?';

        if (confirm(message)) {
            document.getElementById('cancelBookingId').value = bookingId;
            document.getElementById('cancelForm').submit();
        }
    }

    // Auto-hide success message after 5 seconds
    const alertMessage = document.querySelector('.alert-message');
    if (alertMessage) {
        setTimeout(() => {
            alertMessage.style.opacity = '0';
            setTimeout(() => alertMessage.remove(), 300);
        }, 5000);
    }
    </script>
</body>

</html>