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

// Get Invoice ID from URL
$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($invoice_id <= 0) {
    header("Location: invoice.php");
    exit;
}

// Check if print mode
$print_mode = isset($_GET['print']) && $_GET['print'] == 1;

// Fetch Invoice Data with all related information
$query_invoice = "
    SELECT 
        i.*,
        b.start_time, b.end_time, b.status as booking_status, b.location, b.notes,
        p.status as payment_status, p.method as payment_method, p.created_at as payment_date,
        u.name as user_name, u.email as user_email, u.phone as user_phone,
        m.name as mua_name, m.phone as mua_phone,
        pk.name as package_name, pk.price, pk.duration_hours,
        pc.name as category_name,
        (SELECT pi.filename FROM package_images pi WHERE pi.package_id = pk.id ORDER BY pi.id ASC LIMIT 1) as package_image
    FROM invoice i
    LEFT JOIN bookings b ON i.booking_id = b.id
    LEFT JOIN payments p ON i.payment_id = p.id
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN mua m ON b.mua_id = m.id
    LEFT JOIN packages pk ON b.package_id = pk.id
    LEFT JOIN packages_categories pc ON pk.category_id = pc.id
    WHERE i.id = ?
";

$stmt = mysqli_prepare($conn, $query_invoice);
mysqli_stmt_bind_param($stmt, "i", $invoice_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) == 0) {
    header("Location: invoice.php");
    exit;
}

$invoice = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?= htmlspecialchars($invoice['invoice_number']) ?> - LoveMUA Admin</title>
    <link rel="stylesheet" href="assets/css/invoice-view.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if ($print_mode): ?>
    <style>
    @media print {
        .no-print {
            display: none !important;
        }

        body {
            background: white;
        }

        .invoice-card {
            box-shadow: none;
            page-break-inside: avoid;
        }
    }
    </style>
    <?php endif; ?>
</head>

<body <?= $print_mode ? 'onload="window.print(); window.onafterprint = function(){ window.close(); }"' : '' ?>>

    <?php if (!$print_mode): ?>
    <!-- Back Button -->
    <div class="back-button no-print">
        <a href="invoice.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Invoices
        </a>
    </div>
    <?php endif; ?>

    <!-- Invoice Section -->
    <section class="invoice-section">
        <div class="container">
            <?php if (!$print_mode): ?>
            <div class="success-header no-print">
                <div class="success-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <h1>Invoice Details</h1>
                <p>View and manage invoice information</p>
            </div>
            <?php endif; ?>

            <div class="invoice-card">
                <div class="invoice-header">
                    <div class="invoice-logo">
                        <h2>Love<span>MUA</span></h2>
                        <p>Professional Makeup Services</p>
                        <div class="company-info">
                            <p><i class="fas fa-map-marker-alt"></i> Jakarta, Indonesia</p>
                            <p><i class="fas fa-phone"></i> +62 812-3456-7890</p>
                            <p><i class="fas fa-envelope"></i> info@lovemua.com</p>
                        </div>
                    </div>
                    <div class="invoice-info">
                        <h3>INVOICE</h3>
                        <p class="invoice-number"><?= htmlspecialchars($invoice['invoice_number']) ?></p>
                        <p class="date">Issued Date: <?= date('d F Y', strtotime($invoice['issued_date'])) ?></p>
                        <p class="date">Time: <?= date('H:i:s', strtotime($invoice['issued_date'])) ?></p>
                    </div>
                </div>

                <div class="invoice-body">
                    <!-- Customer Information -->
                    <div class="info-grid">
                        <div class="info-section">
                            <div class="invoice-section-title">
                                <i class="fas fa-user"></i> Customer Information
                            </div>
                            <div class="customer-info">
                                <p><strong>Name:</strong> <?= htmlspecialchars($invoice['user_name']) ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($invoice['user_email']) ?></p>
                                <p><strong>Phone:</strong> <?= htmlspecialchars($invoice['user_phone']) ?></p>
                            </div>
                        </div>

                        <div class="info-section">
                            <div class="invoice-section-title">
                                <i class="fas fa-user-tie"></i> Service Provider
                            </div>
                            <div class="customer-info">
                                <p><strong>MUA:</strong> <?= htmlspecialchars($invoice['mua_name']) ?></p>
                                <p><strong>Phone:</strong> <?= htmlspecialchars($invoice['mua_phone']) ?></p>
                                <p><strong>Category:</strong> <?= htmlspecialchars($invoice['category_name']) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Booking Information -->
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
                                <td><strong>Booking ID</strong></td>
                                <td>#<?= str_pad($invoice['booking_id'], 5, '0', STR_PAD_LEFT) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Package</strong></td>
                                <td><?= htmlspecialchars($invoice['package_name']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Booking Date</strong></td>
                                <td><?= date('l, d F Y', strtotime($invoice['start_time'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Time</strong></td>
                                <td><?= date('H:i', strtotime($invoice['start_time'])) ?> -
                                    <?= date('H:i', strtotime($invoice['end_time'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Duration</strong></td>
                                <td><?= $invoice['duration_hours'] ?> Hours</td>
                            </tr>
                            <tr>
                                <td><strong>Location</strong></td>
                                <td><?= htmlspecialchars($invoice['location']) ?></td>
                            </tr>
                             <?php if (!empty($invoice['notes'])): ?>
                            <tr>
                                <td><strong>Special Notes</strong></td>
                                <td><?= nl2br(htmlspecialchars($invoice['notes'])) ?></td>
                            </tr>
                              <?php endif; ?>
                            <tr>
                                <td><strong>Booking Status</strong></td>
                                <td>
                                    <span class="status-badge <?= $invoice['booking_status'] ?>">
                                        <?= ucfirst($invoice['booking_status']) ?>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Payment Information -->
                    <div class="invoice-section-title">
                        <i class="fas fa-credit-card"></i> Payment Information
                    </div>
                    <table class="invoice-table">
                        <thead>
                            <tr>
                                <th>Payment Details</th>
                                <th>Information</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Payment ID</strong></td>
                                <td>#<?= str_pad($invoice['payment_id'], 5, '0', STR_PAD_LEFT) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Payment Method</strong></td>
                                <td><?= ucfirst($invoice['payment_method']) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Payment Date</strong></td>
                                <td><?= date('d F Y, H:i', strtotime($invoice['payment_date'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Payment Status</strong></td>
                                <td>
                                    <span class="payment-badge <?= $invoice['payment_status'] ?>">
                                        <?= ucfirst($invoice['payment_status']) ?>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Payment Summary -->
                    <div class="invoice-section-title">
                        <i class="fas fa-money-bill-wave"></i> Payment Summary
                    </div>
                    <table class="payment-table">
                        <tr>
                            <td>Package Price</td>
                            <td class="text-right">Rp <?= number_format($invoice['price'], 0, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td>Service Fee</td>
                            <td class="text-right">Rp 0</td>
                        </tr>
                        <tr>
                            <td>Tax (0%)</td>
                            <td class="text-right">Rp 0</td>
                        </tr>
                        <tr class="total-row">
                            <td><strong>Total Paid</strong></td>
                            <td class="text-right"><strong>Rp
                                    <?= number_format($invoice['amount'], 0, ',', '.') ?></strong></td>
                        </tr>
                    </table>

                    <div class="payment-status-badge <?= $invoice['payment_status'] ?>">
                        <i class="fas fa-check-circle"></i>
                        <?= strtoupper($invoice['payment_status']) ?>
                    </div>
                </div>

                <div class="invoice-footer">
                    <p><i class="fas fa-info-circle"></i> This is a computer-generated invoice and does not require a
                        signature.</p>
                    <p>Thank you for using LoveMUA services!</p>
                    <p>For any inquiries, please contact us at info@lovemua.com or +62 812-3456-7890</p>
                </div>
            </div>

            <?php if (!$print_mode): ?>
            <!-- Action Buttons -->
            <div class="action-buttons-invoice no-print">
                <button onclick="window.print()" class="btn-print">
                    <i class="fas fa-print"></i> Print Invoice
                </button>
                <button onclick="downloadPDF()" class="btn-download">
                    <i class="fas fa-download"></i> Download PDF
                </button>
                <a href="bookings.php?id=<?= $invoice['booking_id'] ?>" class="btn-view-booking">
                    <i class="fas fa-calendar-alt"></i> View Booking
                </a>
                <a href="invoice.php" class="btn-back-list">
                    <i class="fas fa-list"></i> Back to Invoice List
                </a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
    function downloadPDF() {
        // Open print dialog which user can save as PDF
        window.print();
    }

    // Auto-close after print in print mode
    <?php if ($print_mode): ?>
    window.onafterprint = function() {
        window.close();
    };
    <?php endif; ?>
    </script>
</body>

</html>