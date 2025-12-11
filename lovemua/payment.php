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

// Get Booking ID from URL
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;

if ($booking_id <= 0) {
    header("Location: all-packages.php");
    exit;
}

// Process Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'complete') {
            // Update booking status to completed
            $query_update = "UPDATE bookings SET status = 'completed' WHERE id = ?";
            $stmt_update = mysqli_prepare($conn, $query_update);
            mysqli_stmt_bind_param($stmt_update, "i", $booking_id);
            mysqli_stmt_execute($stmt_update);
            
            // Update payment status to paid
            $query_payment_update = "UPDATE payments SET status = 'paid' WHERE booking_id = ?";
            $stmt_payment_update = mysqli_prepare($conn, $query_payment_update);
            mysqli_stmt_bind_param($stmt_payment_update, "i", $booking_id);
            mysqli_stmt_execute($stmt_payment_update);
            
            // Generate Invoice
            $query_payment = "SELECT id, amount FROM payments WHERE booking_id = ?";
            $stmt_payment = mysqli_prepare($conn, $query_payment);
            mysqli_stmt_bind_param($stmt_payment, "i", $booking_id);
            mysqli_stmt_execute($stmt_payment);
            $result_payment = mysqli_stmt_get_result($stmt_payment);
            $payment = mysqli_fetch_assoc($result_payment);
            
            if ($payment) {
                // Generate invoice number
                $invoice_number = "INV/" . date('Y/m') . "/" . str_pad($booking_id, 5, '0', STR_PAD_LEFT);
                
                // Insert invoice
                $query_invoice = "INSERT INTO invoice (booking_id, payment_id, invoice_number, amount, issued_date) 
                                 VALUES (?, ?, ?, ?, NOW())";
                $stmt_invoice = mysqli_prepare($conn, $query_invoice);
                mysqli_stmt_bind_param($stmt_invoice, "iisd", $booking_id, $payment['id'], $invoice_number, $payment['amount']);
                mysqli_stmt_execute($stmt_invoice);
            }
            
            // Reload page
            header("Location: payment.php?booking_id=" . $booking_id);
            exit;
            
        } elseif ($_POST['action'] == 'cancel') {
            // Update booking status to cancelled
            $query_cancel = "UPDATE bookings SET status = 'cancelled' WHERE id = ?";
            $stmt_cancel = mysqli_prepare($conn, $query_cancel);
            mysqli_stmt_bind_param($stmt_cancel, "i", $booking_id);
            mysqli_stmt_execute($stmt_cancel);
            
            // Update payment status to failed
            $query_payment_cancel = "UPDATE payments SET status = 'failed' WHERE booking_id = ?";
            $stmt_payment_cancel = mysqli_prepare($conn, $query_payment_cancel);
            mysqli_stmt_bind_param($stmt_payment_cancel, "i", $booking_id);
            mysqli_stmt_execute($stmt_payment_cancel);
            
            // Redirect
            header("Location: all-packages.php");
            exit;
        }
    }
}

// Fetch Booking Data
$query_booking = "SELECT b.*, 
                  p.id as package_id, p.name as package_name, p.price, p.duration_hours, p.description,
                  pc.name as category_name,
                  m.id as mua_id, m.name as mua_name, m.phone as mua_phone, m.photo as mua_photo,
                  u.name as user_name, u.email as user_email, u.phone as user_phone,
                  (SELECT pi.filename FROM package_images pi WHERE pi.package_id = p.id ORDER BY pi.id ASC LIMIT 1) as package_image
                  FROM bookings b
                  JOIN packages p ON b.package_id = p.id
                  JOIN packages_categories pc ON p.category_id = pc.id
                  JOIN mua m ON b.mua_id = m.id
                  JOIN users u ON b.user_id = u.id
                  WHERE b.id = ? AND b.user_id = ?";
$stmt_booking = mysqli_prepare($conn, $query_booking);
mysqli_stmt_bind_param($stmt_booking, "ii", $booking_id, $_SESSION['user_id']);
mysqli_stmt_execute($stmt_booking);
$result_booking = mysqli_stmt_get_result($stmt_booking);

if (mysqli_num_rows($result_booking) == 0) {
    header("Location: all-packages.php");
    exit;
}

$booking = mysqli_fetch_assoc($result_booking);

// Fetch Payment Data
$query_payment = "SELECT * FROM payments WHERE booking_id = ?";
$stmt_payment = mysqli_prepare($conn, $query_payment);
mysqli_stmt_bind_param($stmt_payment, "i", $booking_id);
mysqli_stmt_execute($stmt_payment);
$result_payment = mysqli_stmt_get_result($stmt_payment);
$payment = mysqli_fetch_assoc($result_payment);

// Fetch Invoice Data if completed
$invoice = null;
if ($booking['status'] == 'completed') {
    $query_invoice = "SELECT * FROM invoice WHERE booking_id = ?";
    $stmt_invoice = mysqli_prepare($conn, $query_invoice);
    mysqli_stmt_bind_param($stmt_invoice, "i", $booking_id);
    mysqli_stmt_execute($stmt_invoice);
    $result_invoice = mysqli_stmt_get_result($stmt_invoice);
    if (mysqli_num_rows($result_invoice) > 0) {
        $invoice = mysqli_fetch_assoc($result_invoice);
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Booking #<?= $booking_id ?> - LoveMUA</title>
    <link rel="stylesheet" href="assets/css/payment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h3>Checking Payment Status...</h3>
            <p>Please wait while we verify your payment</p>
        </div>
    </div>

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
                        <a href="my-bookings.php"><i class="fas fa-calendar"></i> My Bookings</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb -->
    <section class="breadcrumb-section">
        <div class="container">
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <span>/</span>
                <a href="all-packages.php">Packages</a>
                <span>/</span>
                <a href="my-bookings.php">My Bookings</a>
                <span>/</span>
                <span>Payment</span>
            </div>
        </div>
    </section>

    <?php if ($booking['status'] == 'pending'): ?>
    <!-- Payment Section (Pending Status) -->
    <section class="payment-section">
        <div class="container">
            <div class="payment-header">
                <i class="fas fa-credit-card"></i>
                <h1>Complete Your Payment</h1>
                <p>Booking ID: #<?= str_pad($booking_id, 5, '0', STR_PAD_LEFT) ?></p>
            </div>

            <div class="payment-grid">
                <!-- Left Column: Booking Details -->
                <div class="booking-details-card">
                    <h2 class="card-title">
                        <i class="fas fa-clipboard-list"></i> Booking Details
                    </h2>

                    <div class="package-preview">
                        <div class="package-image">
                            <img src="<?= !empty($booking['package_image']) ? 'uploads/mua_packages/' . htmlspecialchars($booking['package_image']) : 'assets/images/package-placeholder.jpg' ?>"
                                alt="<?= htmlspecialchars($booking['package_name']) ?>">
                            <div class="category-badge"><?= htmlspecialchars($booking['category_name']) ?></div>
                        </div>
                    </div>

                    <div class="detail-list">
                        <div class="detail-item">
                            <i class="fas fa-box-open"></i>
                            <div>
                                <strong>Package</strong>
                                <span><?= htmlspecialchars($booking['package_name']) ?></span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-user-tie"></i>
                            <div>
                                <strong>Makeup Artist</strong>
                                <span><?= htmlspecialchars($booking['mua_name']) ?></span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-calendar-day"></i>
                            <div>
                                <strong>Booking Date</strong>
                                <span><?= date('l, d F Y', strtotime($booking['start_time'])) ?></span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <strong>Time</strong>
                                <span><?= date('H:i', strtotime($booking['start_time'])) ?> -
                                    <?= date('H:i', strtotime($booking['end_time'])) ?></span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-hourglass-half"></i>
                            <div>
                                <strong>Duration</strong>
                                <span><?= $booking['duration_hours'] ?> Hours</span>
                            </div>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <strong>Location</strong>
                                <span><?= htmlspecialchars($booking['location']) ?></span>
                            </div>
                        </div>
                        <?php if (!empty($booking['notes'])): ?>
                        <div class="detail-item notes-item">
                            <i class="fas fa-sticky-note"></i>
                            <div>
                                <strong>Special Notes</strong>
                                <span><?= nl2br(htmlspecialchars($booking['notes'])) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="detail-item highlight">
                            <i class="fas fa-tag"></i>
                            <div>
                                <strong>Total Amount</strong>
                                <span class="price">Rp <?= number_format($payment['amount'], 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="status-badge pending">
                        <i class="fas fa-clock"></i> Waiting for Payment
                    </div>
                </div>

                <!-- Right Column: Payment Instructions -->
                <div class="payment-instructions-card">
                    <h2 class="card-title">
                        <i class="fas fa-qrcode"></i> Payment Instructions
                    </h2>

                    <div class="qr-code-section">
                        <div class="qr-code">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=LOVEMUA-BOOKING-<?= $booking_id ?>"
                                alt="QR Code Payment">
                        </div>
                        <p class="qr-hint">Scan this QR code using your banking app</p>
                    </div>

                    <div class="bank-info">
                        <h3><i class="fas fa-university"></i> Bank Transfer Details</h3>
                        <div class="bank-item">
                            <span>Bank Name:</span>
                            <strong>Bank Central Asia (BCA)</strong>
                        </div>
                        <div class="bank-item">
                            <span>Account Number:</span>
                            <strong>1234567890</strong>
                        </div>
                        <div class="bank-item">
                            <span>Account Name:</span>
                            <strong>LoveMUA Indonesia</strong>
                        </div>
                        <div class="bank-item highlight">
                            <span>Amount:</span>
                            <strong>Rp <?= number_format($payment['amount'], 0, ',', '.') ?></strong>
                        </div>
                    </div>

                    <div class="payment-steps">
                        <h3><i class="fas fa-list-ol"></i> How to Pay</h3>
                        <ol>
                            <li>Open your banking app or mobile banking</li>
                            <li>Select transfer or scan QR code</li>
                            <li>Enter the exact amount shown above</li>
                            <li>Complete the transaction</li>
                            <li>Click "Check Status" button below after payment</li>
                        </ol>
                    </div>

                    <form method="POST" id="checkStatusForm">
                        <input type="hidden" name="action" value="complete">
                        <div class="action-buttons">
                            <button type="submit" class="btn-check-status">
                                <i class="fas fa-check-circle"></i> Check Payment Status
                            </button>
                            <button type="button" class="btn-cancel" onclick="confirmCancel()">
                                <i class="fas fa-times-circle"></i> Cancel Booking
                            </button>
                        </div>
                    </form>

                    <div class="payment-note">
                        <i class="fas fa-info-circle"></i>
                        <p>Payment must be completed within 24 hours. Your booking will be automatically cancelled if
                            payment is not received.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php elseif ($booking['status'] == 'completed' && $invoice): ?>
    <!-- Invoice Section (Completed Status) -->
    <section class="invoice-section">
        <div class="container">
            <div class="success-header">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1>Payment Successful!</h1>
                <p>Your booking has been confirmed</p>
            </div>

            <div class="invoice-card">
                <div class="invoice-header">
                    <div class="invoice-logo">
                        <h2>Love<span>MUA</span></h2>
                        <p>Professional Makeup Services</p>
                    </div>
                    <div class="invoice-info">
                        <h3>INVOICE</h3>
                        <p><?= htmlspecialchars($invoice['invoice_number']) ?></p>
                        <p class="date">Date: <?= date('d F Y', strtotime($invoice['issued_date'])) ?></p>
                    </div>
                </div>

                <div class="invoice-body">
                    <div class="invoice-section-title">
                        <i class="fas fa-user"></i> Customer Information
                    </div>
                    <div class="customer-info">
                        <p><strong>Name:</strong> <?= htmlspecialchars($booking['user_name']) ?></p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($booking['user_email']) ?></p>
                        <p><strong>Phone:</strong> <?= htmlspecialchars($booking['user_phone']) ?></p>
                    </div>

                    <div class="invoice-section-title">
                        <i class="fas fa-calendar-check"></i> Booking Information
                    </div>
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Package</strong></td>
                                <td><?= htmlspecialchars($booking['package_name']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Category</strong></td>
                                <td><?= htmlspecialchars($booking['category_name']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Makeup Artist</strong></td>
                                <td><?= htmlspecialchars($booking['mua_name']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Booking Date</strong></td>
                                <td><?= date('l, d F Y', strtotime($booking['start_time'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Time</strong></td>
                                <td><?= date('H:i', strtotime($booking['start_time'])) ?> -
                                    <?= date('H:i', strtotime($booking['end_time'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Duration</strong></td>
                                <td><?= $booking['duration_hours'] ?> Hours</td>
                            </tr>
                            <tr>
                                <td><strong>Location</strong></td>
                                <td><?= htmlspecialchars($booking['location']) ?></td>
                            </tr>
                            <tr>
                                 <td><strong>Special Notes</strong></td>
                                <td><?= nl2br(htmlspecialchars($booking['notes'])) ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="invoice-section-title">
                        <i class="fas fa-money-bill-wave"></i> Payment Summary
                    </div>
                    <table class="payment-table">
                        <tr>
                            <td>Package Price</td>
                            <td class="text-right">Rp <?= number_format($invoice['amount'], 0, ',', '.') ?></td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>Total Paid</strong></td>
                            <td class="text-right"><strong>Rp
                                    <?= number_format($invoice['amount'], 0, ',', '.') ?></strong></td>
                        </tr>
                    </table>

                    <div class="payment-status-badge">
                        <i class="fas fa-check-circle"></i> PAID
                    </div>
                </div>

                <div class="invoice-footer">
                    <p><i class="fas fa-info-circle"></i> Thank you for booking with LoveMUA!</p>
                    <p>For any inquiries, please contact us at info@lovemua.com or +62 812-3456-7890</p>
                </div>
            </div>

            <div class="action-buttons-invoice">
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print"></i> Print Invoice
                </button>
                <a href="my-bookings.php" class="btn-bookings">
                    <i class="fas fa-calendar-alt"></i> View My Bookings
                </a>
                <a href="index.php" class="btn-home">
                    <i class="fas fa-home"></i> Back to Home
                </a>
            </div>
        </div>
    </section>

    <?php else: ?>
    <!-- Cancelled or Unknown Status -->
    <section class="error-section">
        <div class="container">
            <div class="error-content">
                <i class="fas fa-times-circle"></i>
                <h1>Booking Not Available</h1>
                <p>This booking has been cancelled or does not exist.</p>
                <a href="all-packages.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Browse Packages
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

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
    </form>

    <script>
    // User Dropdown Toggle
    const userBtn = document.querySelector('.user-btn');
    const dropdownContent = document.querySelector('.dropdown-content');

    if (userBtn && dropdownContent) {
        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownContent.classList.toggle('show');
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.user-dropdown')) {
                dropdownContent.classList.remove('show');
            }
        });
    }

    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });

    // Check Status with Loading Animation
    const checkStatusForm = document.getElementById('checkStatusForm');
    const loadingOverlay = document.getElementById('loadingOverlay');

    if (checkStatusForm && loadingOverlay) {
        checkStatusForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Show loading overlay
            loadingOverlay.classList.add('show');

            // Wait 1.5 seconds before submitting
            setTimeout(function() {
                checkStatusForm.submit();
            }, 1500);
        });
    }

    // Cancel Booking Confirmation
    function confirmCancel() {
        if (confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
            document.getElementById('cancelForm').submit();
        }
    }
    </script>
</body>

</html>