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

// Get MUA ID from URL
$mua_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($mua_id <= 0) {
    header("Location: all-muas.php");
    exit;
}

// Fetch MUA Data
$query_mua = "SELECT * FROM mua WHERE id = ?";
$stmt = mysqli_prepare($conn, $query_mua);
mysqli_stmt_bind_param($stmt, "i", $mua_id);
mysqli_stmt_execute($stmt);
$result_mua = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result_mua) == 0) {
    header("Location: all-muas.php");
    exit;
}

$mua = mysqli_fetch_assoc($result_mua);

// Fetch MUA Statistics
$query_stats = "SELECT 
                COUNT(DISTINCT p.id) as total_packages,
                COUNT(DISTINCT pc.id) as total_categories,
                MIN(p.price) as min_price,
                MAX(p.price) as max_price
                FROM packages p
                LEFT JOIN packages_categories pc ON p.category_id = pc.id
                WHERE p.mua_id = ?";
$stmt_stats = mysqli_prepare($conn, $query_stats);
mysqli_stmt_bind_param($stmt_stats, "i", $mua_id);
mysqli_stmt_execute($stmt_stats);
$result_stats = mysqli_stmt_get_result($stmt_stats);
$stats = mysqli_fetch_assoc($result_stats);

// Fetch Categories for this MUA
$query_categories = "SELECT DISTINCT pc.name 
                     FROM packages p
                     JOIN packages_categories pc ON p.category_id = pc.id
                     WHERE p.mua_id = ?";
$stmt_cat = mysqli_prepare($conn, $query_categories);
mysqli_stmt_bind_param($stmt_cat, "i", $mua_id);
mysqli_stmt_execute($stmt_cat);
$result_categories = mysqli_stmt_get_result($stmt_cat);
$categories = [];
while ($cat = mysqli_fetch_assoc($result_categories)) {
    $categories[] = $cat['name'];
}

// Pagination for Packages
$packages_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $packages_per_page;

// Count total packages
$count_query = "SELECT COUNT(*) as total FROM packages WHERE mua_id = ?";
$stmt_count = mysqli_prepare($conn, $count_query);
mysqli_stmt_bind_param($stmt_count, "i", $mua_id);
mysqli_stmt_execute($stmt_count);
$count_result = mysqli_stmt_get_result($stmt_count);
$total_packages = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_packages / $packages_per_page);

// Fetch Packages with pagination
$query_packages = "SELECT p.*, pc.name as category_name, pi.filename as image
                   FROM packages p 
                   JOIN packages_categories pc ON p.category_id = pc.id 
                   LEFT JOIN package_images pi ON pi.package_id = p.id
                   WHERE p.mua_id = ?
                   GROUP BY p.id
                   ORDER BY p.id DESC
                   LIMIT ? OFFSET ?";
$stmt_packages = mysqli_prepare($conn, $query_packages);
mysqli_stmt_bind_param($stmt_packages, "iii", $mua_id, $packages_per_page, $offset);
mysqli_stmt_execute($stmt_packages);
$result_packages = mysqli_stmt_get_result($stmt_packages);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($mua['name']) ?> - LoveMUA Professional Makeup Artist</title>
    <link rel="stylesheet" href="assets/css/mua-detail.css">
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
                <li><a href="all-muas.php" class="active">Our MUAs</a></li>
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
                <a href="all-muas.php">Our MUAs</a>
                <span>/</span>
                <span><?= htmlspecialchars($mua['name']) ?></span>
            </div>
        </div>
    </section>

    <!-- MUA Hero Section -->
    <section class="mua-hero">
        <div class="mua-hero-image"
            style="background: linear-gradient(rgba(0,0,0,0.4), rgba(233,30,99,0.6)), url('<?= !empty($mua['photo']) ? 'admin/assets/images/mua/' . htmlspecialchars($mua['photo']) : 'assets/images/default-mua.jpg' ?>') center/cover;">
            <div class="container">
                <div class="mua-hero-content">
                    <div class="mua-badge-hero">
                        <i class="fas fa-star"></i> Professional MUA
                    </div>
                    <h1 class="mua-name"><?= htmlspecialchars($mua['name']) ?></h1>
                    <div class="mua-quick-contact">
                        <a href="tel:<?= htmlspecialchars($mua['phone']) ?>" class="contact-btn">
                            <i class="fas fa-phone"></i> <?= htmlspecialchars($mua['phone']) ?>
                        </a>
                        <a href="mailto:<?= htmlspecialchars($mua['email']) ?>" class="contact-btn">
                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($mua['email']) ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- MUA Info Section -->
    <section class="mua-info-section">
        <div class="container">
            <div class="info-grid">
                <!-- Bio Column -->
                <div class="bio-column">
                    <div class="section-card">
                        <h2 class="section-title">
                            <i class="fas fa-user-circle"></i> About Me
                        </h2>
                        <p class="bio-text"><?= nl2br(htmlspecialchars($mua['bio'])) ?></p>
                    </div>

                    <?php if (!empty($categories)): ?>
                    <div class="section-card categories-card">
                        <h3 class="card-title">
                            <i class="fas fa-layer-group"></i> Specializations
                        </h3>
                        <div class="categories-tags">
                            <?php foreach ($categories as $category): ?>
                            <span class="category-tag"><?= htmlspecialchars($category) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Stats Column -->
                <div class="stats-column">
                    <div class="section-card stats-card">
                        <h3 class="card-title">
                            <i class="fas fa-chart-line"></i> Statistics
                        </h3>
                        <div class="stats-grid">
                            <div class="stat-box">
                                <div class="stat-icon">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-number"><?= $stats['total_packages'] ?></div>
                                    <div class="stat-label">Packages</div>
                                </div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-icon">
                                    <i class="fas fa-tags"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-number"><?= $stats['total_categories'] ?></div>
                                    <div class="stat-label">Categories</div>
                                </div>
                            </div>
                            <div class="stat-box full-width">
                                <div class="stat-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="stat-info">
                                    <div class="stat-number">Rp <?= number_format($stats['min_price'], 0, ',', '.') ?> -
                                        Rp <?= number_format($stats['max_price'], 0, ',', '.') ?></div>
                                    <div class="stat-label">Price Range</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="section-card contact-card">
                        <h3 class="card-title">
                            <i class="fas fa-address-card"></i> Contact Information
                        </h3>
                        <div class="contact-list">
                            <div class="contact-item">
                                <i class="fas fa-phone"></i>
                                <a
                                    href="tel:<?= htmlspecialchars($mua['phone']) ?>"><?= htmlspecialchars($mua['phone']) ?></a>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <a
                                    href="mailto:<?= htmlspecialchars($mua['email']) ?>"><?= htmlspecialchars($mua['email']) ?></a>
                            </div>
                        </div>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $mua['phone']) ?>" target="_blank"
                            class="whatsapp-btn">
                            <i class="fab fa-whatsapp"></i> Chat on WhatsApp
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Packages Section -->
    <section class="packages-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title-main">Available Packages by <?= htmlspecialchars($mua['name']) ?></h2>
                <p class="section-subtitle">Discover all makeup packages offered by this talented artist</p>
            </div>

            <?php if (mysqli_num_rows($result_packages) > 0): ?>
            <div class="packages-grid">
                <?php while ($package = mysqli_fetch_assoc($result_packages)): ?>
                <div class="package-card">
                    <div class="package-badge"><?= htmlspecialchars($package['category_name']) ?></div>
                    <div class="package-image">
                        <?php 
                        $imagePath = !empty($package['image']) ? 'uploads/mua_packages/' . htmlspecialchars($package['image']) : 'assets/images/package-placeholder.jpg';
                        ?>
                        <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($package['name']) ?>"
                            onerror="this.src='assets/images/package-placeholder.jpg'">
                    </div>
                    <div class="package-content">
                        <h3><?= htmlspecialchars($package['name']) ?></h3>
                        <p class="package-description">
                            <?= htmlspecialchars(substr($package['description'], 0, 80)) ?>...
                        </p>
                        <div class="package-meta">
                            <span class="package-duration">
                                <i class="fas fa-clock"></i> <?= $package['duration_hours'] ?> hours
                            </span>
                            <span class="package-price">Rp <?= number_format($package['price'], 0, ',', '.') ?></span>
                        </div>
                        <a href="package-detail.php?id=<?= $package['id'] ?>" class="package-btn">Book Now</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?id=<?= $mua_id ?>&page=<?= $page - 1 ?>" class="page-btn">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php endif; ?>

                <div class="page-numbers">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                    <span class="page-number active"><?= $i ?></span>
                    <?php else: ?>
                    <a href="?id=<?= $mua_id ?>&page=<?= $i ?>" class="page-number"><?= $i ?></a>
                    <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $total_pages): ?>
                <a href="?id=<?= $mua_id ?>&page=<?= $page + 1 ?>" class="page-btn">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="no-packages">
                <i class="fas fa-box-open"></i>
                <h3>No Packages Available</h3>
                <p>This makeup artist hasn't added any packages yet.</p>
                <a href="all-muas.php" class="btn-primary">Browse Other MUAs</a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Book <?= htmlspecialchars($mua['name']) ?>?</h2>
                <p>Contact directly or browse more packages to find your perfect makeup service</p>
                <div class="cta-buttons">
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $mua['phone']) ?>" target="_blank"
                        class="cta-btn primary">
                        <i class="fab fa-whatsapp"></i> WhatsApp Now
                    </a>
                    <a href="all-muas.php" class="cta-btn secondary">
                        <i class="fas fa-users"></i> View Other MUAs
                    </a>
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

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.user-dropdown')) {
            dropdownContent.classList.remove('show');
        }
    });

    // Smooth Scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
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
    </script>
</body>

</html>