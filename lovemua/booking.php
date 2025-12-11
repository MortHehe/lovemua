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

// Get Package ID from URL
$package_id = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;

if ($package_id <= 0) {
    header("Location: all-packages.php");
    exit;
}

// Fetch Package Data with MUA Info
$query_package = "SELECT p.*, 
                  m.id as mua_id, m.name as mua_name, m.phone as mua_phone, 
                  m.email as mua_email, m.photo as mua_photo,
                  pc.name as category_name,
                  (SELECT pi.filename FROM package_images pi WHERE pi.package_id = p.id ORDER BY pi.id ASC LIMIT 1) as image
                  FROM packages p
                  JOIN mua m ON p.mua_id = m.id
                  JOIN packages_categories pc ON p.category_id = pc.id
                  WHERE p.id = ?";
$stmt = mysqli_prepare($conn, $query_package);
mysqli_stmt_bind_param($stmt, "i", $package_id);
mysqli_stmt_execute($stmt);
$result_package = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result_package) == 0) {
    header("Location: all-packages.php");
    exit;
}

$package = mysqli_fetch_assoc($result_package);

// Process Booking Form
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $booking_date = $_POST['booking_date'];
    $booking_time = $_POST['booking_time'];
    $location = mysqli_real_escape_string($conn, $_POST['location']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    $user_id = $_SESSION['user_id'];
    $mua_id = $package['mua_id'];
    
    // Combine date and time to create start_time
    $start_time = $booking_date . ' ' . $booking_time . ':00';
    
    // Calculate end_time based on duration_hours
    $duration_hours = $package['duration_hours'];
    $end_time = date('Y-m-d H:i:s', strtotime($start_time . ' +' . $duration_hours . ' hours'));
    
    // Validate that booking is in the future
    if (strtotime($start_time) <= time()) {
        $error = "Booking time must be in the future!";
    } elseif (empty($location)) {
        $error = "Location is required!";
    } else {
        // Check for conflicts with existing bookings
        $conflict_query = "SELECT id FROM bookings 
                          WHERE mua_id = ? 
                          AND status NOT IN ('cancelled')
                          AND (
                              (start_time < ? AND end_time > ?) OR
                              (start_time < ? AND end_time > ?) OR
                              (start_time >= ? AND end_time <= ?)
                          )";
        $stmt_conflict = mysqli_prepare($conn, $conflict_query);
        mysqli_stmt_bind_param($stmt_conflict, "issssss", $mua_id, $end_time, $start_time, $start_time, $end_time, $start_time, $end_time);
        mysqli_stmt_execute($stmt_conflict);
        $result_conflict = mysqli_stmt_get_result($stmt_conflict);
        
        if (mysqli_num_rows($result_conflict) > 0) {
            $error = "Sorry, this MUA is already booked for the selected time. Please choose a different time.";
        } else {
            // Insert into bookings table
            $query_insert = "INSERT INTO bookings (user_id, mua_id, package_id, start_time, end_time, location, notes, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            $stmt_insert = mysqli_prepare($conn, $query_insert);
            mysqli_stmt_bind_param($stmt_insert, "iiissss", $user_id, $mua_id, $package_id, $start_time, $end_time, $location, $notes);
            
            if (mysqli_stmt_execute($stmt_insert)) {
                $booking_id = mysqli_insert_id($conn);
                
                // Insert into payments table
                $amount = $package['price'];
                $query_payment = "INSERT INTO payments (booking_id, amount, method, status, created_at) 
                                 VALUES (?, ?, 'bank_transfer', 'pending', NOW())";
                $stmt_payment = mysqli_prepare($conn, $query_payment);
                mysqli_stmt_bind_param($stmt_payment, "id", $booking_id, $amount);
                mysqli_stmt_execute($stmt_payment);
                
                // Redirect to payment page
                header("Location: payment.php?booking_id=" . $booking_id);
                exit;
            } else {
                $error = "Failed to create booking. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book <?= htmlspecialchars($package['name']) ?> - LoveMUA</title>
    <link rel="stylesheet" href="assets/css/booking.css">
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
                <a href="package-detail.php?id=<?= $package_id ?>">
                    <?= htmlspecialchars($package['name']) ?>
                </a>
                <span>/</span>
                <span>Booking</span>
            </div>
        </div>
    </section>

    <!-- Booking Section -->
    <section class="booking-section">
        <div class="container">
            <div class="booking-header">
                <i class="fas fa-calendar-check"></i>
                <h1>Book Your Appointment</h1>
                <p>Complete the form below to reserve your makeup session</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <div class="booking-grid">
                <!-- Left Column: Package Info -->
                <div class="package-info-card">
                    <h2 class="card-title">
                        <i class="fas fa-box-open"></i> Package Details
                    </h2>

                    <div class="package-preview">
                        <div class="package-image">
                            <img src="<?= !empty($package['image']) ? 'uploads/mua_packages/' . htmlspecialchars($package['image']) : 'assets/images/package-placeholder.jpg' ?>"
                                alt="<?= htmlspecialchars($package['name']) ?>"
                                onerror="this.src='assets/images/package-placeholder.jpg'">
                            <div class="category-badge"><?= htmlspecialchars($package['category_name']) ?></div>
                        </div>

                        <div class="package-details">
                            <h3><?= htmlspecialchars($package['name']) ?></h3>
                            <p class="package-mua">
                                <i class="fas fa-user"></i> by <?= htmlspecialchars($package['mua_name']) ?>
                            </p>
                            <p class="package-description">
                                <?= htmlspecialchars(substr($package['description'], 0, 150)) ?>...
                            </p>
                        </div>
                    </div>

                    <div class="package-meta-list">
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <strong>Duration</strong>
                                <span><?= $package['duration_hours'] ?> Hours</span>
                            </div>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-tag"></i>
                            <div>
                                <strong>Price</strong>
                                <span class="price">Rp <?= number_format($package['price'], 0, ',', '.') ?></span>
                            </div>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-user-tie"></i>
                            <div>
                                <strong>Artist</strong>
                                <span><?= htmlspecialchars($package['mua_name']) ?></span>
                            </div>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-phone"></i>
                            <div>
                                <strong>Contact</strong>
                                <span><?= htmlspecialchars($package['mua_phone']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Booking Form -->
                <div class="booking-form-card">
                    <h2 class="card-title">
                        <i class="fas fa-calendar-alt"></i> Booking Information
                    </h2>

                    <form method="POST" action="" id="bookingForm">
                        <div class="form-group">
                            <label for="booking_date">
                                <i class="fas fa-calendar"></i> Booking Date <span class="required">*</span>
                            </label>
                            <input type="date" id="booking_date" name="booking_date" class="form-control"
                                min="<?= date('Y-m-d') ?>" required>
                            <small class="form-hint">Select your preferred date for the makeup session</small>
                        </div>

                        <div class="form-group">
                            <label for="booking_time">
                                <i class="fas fa-clock"></i> Start Time <span class="required">*</span>
                            </label>
                            <select id="booking_time" name="booking_time" class="form-control" required>
                                <option value="">-- Select Time --</option>
                                <option value="06:00">06:00 AM</option>
                                <option value="07:00">07:00 AM</option>
                                <option value="08:00">08:00 AM</option>
                                <option value="09:00">09:00 AM</option>
                                <option value="10:00">10:00 AM</option>
                                <option value="11:00">11:00 AM</option>
                                <option value="12:00">12:00 PM</option>
                                <option value="13:00">01:00 PM</option>
                                <option value="14:00">02:00 PM</option>
                                <option value="15:00">03:00 PM</option>
                                <option value="16:00">04:00 PM</option>
                                <option value="17:00">05:00 PM</option>
                                <option value="18:00">06:00 PM</option>
                                <option value="19:00">07:00 PM</option>
                                <option value="20:00">08:00 PM</option>
                            </select>
                            <small class="form-hint">Choose when you want the session to begin</small>
                        </div>

                        <div class="form-group">
                            <label for="location">
                                <i class="fas fa-map-marker-alt"></i> Location <span class="required">*</span>
                            </label>
                            <textarea id="location" name="location" class="form-control" rows="3"
                                placeholder="Enter the complete address where the makeup session will take place..."
                                required></textarea>
                            <small class="form-hint">Provide the full address including street name, building, and
                                area</small>
                        </div>

                        <div class="form-group">
                            <label for="notes">
                                <i class="fas fa-sticky-note"></i> Special Notes / Requests <span
                                    class="optional">(Optional)</span>
                            </label>
                            <textarea id="notes" name="notes" class="form-control" rows="4"
                                placeholder="Any special requests, allergies, or important information for the MUA..."></textarea>
                            <small class="form-hint">e.g., allergies, makeup preferences, theme, or special
                                requirements</small>
                        </div>

                        <!-- Booking Summary (Dynamic) -->
                        <div class="booking-summary" id="bookingSummary" style="display: none;">
                            <h3>
                                <i class="fas fa-info-circle"></i> Booking Summary
                            </h3>
                            <div class="summary-item">
                                <span>Start Time:</span>
                                <strong id="summaryStart">-</strong>
                            </div>
                            <div class="summary-item">
                                <span>End Time:</span>
                                <strong id="summaryEnd">-</strong>
                            </div>
                            <div class="summary-item">
                                <span>Duration:</span>
                                <strong><?= $package['duration_hours'] ?> Hours</strong>
                            </div>
                            <div class="summary-item total">
                                <span>Total Price:</span>
                                <strong>Rp <?= number_format($package['price'], 0, ',', '.') ?></strong>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="package-detail.php?id=<?= $package_id ?>" class="btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Package
                            </a>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-check-circle"></i> Confirm Booking
                            </button>
                        </div>
                    </form>

                    <div class="booking-notes">
                        <h4><i class="fas fa-exclamation-triangle"></i> Important Notes</h4>
                        <ul>
                            <li>Booking confirmation will be sent via email</li>
                            <li>Payment must be completed to confirm your booking</li>
                            <li>Provide accurate location for the MUA to reach you</li>
                            <li>Contact the MUA directly for any special requests</li>
                        </ul>
                    </div>
                </div>
            </div>
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

    // Dynamic Booking Summary
    const bookingDate = document.getElementById('booking_date');
    const bookingTime = document.getElementById('booking_time');
    const bookingSummary = document.getElementById('bookingSummary');
    const summaryStart = document.getElementById('summaryStart');
    const summaryEnd = document.getElementById('summaryEnd');
    const durationHours = <?= $package['duration_hours'] ?>;

    function updateSummary() {
        const date = bookingDate.value;
        const time = bookingTime.value;

        if (date && time) {
            // Create start datetime
            const startDateTime = new Date(date + 'T' + time);

            // Calculate end datetime
            const endDateTime = new Date(startDateTime.getTime() + (durationHours * 60 * 60 * 1000));

            // Format dates for display
            const options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };

            summaryStart.textContent = startDateTime.toLocaleDateString('id-ID', options);
            summaryEnd.textContent = endDateTime.toLocaleDateString('id-ID', options);

            bookingSummary.style.display = 'block';
        } else {
            bookingSummary.style.display = 'none';
        }
    }

    bookingDate.addEventListener('change', updateSummary);
    bookingTime.addEventListener('change', updateSummary);

    // Form validation
    document.getElementById('bookingForm').addEventListener('submit', function(e) {
        const date = bookingDate.value;
        const time = bookingTime.value;
        const location = document.getElementById('location').value.trim();

        if (!date || !time) {
            e.preventDefault();
            alert('Please select both date and time for your booking!');
            return false;
        }

        if (!location) {
            e.preventDefault();
            alert('Please provide the location for the makeup session!');
            return false;
        }

        // Check if booking is in the future
        const selectedDateTime = new Date(date + 'T' + time);
        const now = new Date();

        if (selectedDateTime <= now) {
            e.preventDefault();
            alert('Please select a future date and time for your booking!');
            return false;
        }

        return true;
    });
    </script>
</body>

</html>