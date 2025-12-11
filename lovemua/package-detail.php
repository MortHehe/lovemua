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
$package_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($package_id <= 0) {
    header("Location: all-packages.php");
    exit;
}

// Fetch Package Data with MUA and Category Info
$query_package = "SELECT p.*, 
                  m.id as mua_id, m.name as mua_name, m.phone as mua_phone, 
                  m.email as mua_email, m.photo as mua_photo, m.bio as mua_bio,
                  pc.id as category_id, pc.name as category_name
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

// Fetch Package Images
$query_images = "SELECT * FROM package_images WHERE package_id = ? ORDER BY id ASC";
$stmt_images = mysqli_prepare($conn, $query_images);
mysqli_stmt_bind_param($stmt_images, "i", $package_id);
mysqli_stmt_execute($stmt_images);
$result_images = mysqli_stmt_get_result($stmt_images);
$images = [];
while ($img = mysqli_fetch_assoc($result_images)) {
    $images[] = $img;
}

// If no images, use placeholder
if (empty($images)) {
    $images[] = ['filename' => '', 'id' => 0];
}

// Fetch Related Packages (Same Category)
$query_related = "SELECT p.*, m.name as mua_name, pc.name as category_name,
                  (SELECT pi.filename FROM package_images pi WHERE pi.package_id = p.id ORDER BY pi.id ASC LIMIT 1) as image
                  FROM packages p
                  JOIN mua m ON p.mua_id = m.id
                  JOIN packages_categories pc ON p.category_id = pc.id
                  WHERE p.category_id = ? AND p.id != ?
                  ORDER BY RAND()
                  LIMIT 6";
$stmt_related = mysqli_prepare($conn, $query_related);
mysqli_stmt_bind_param($stmt_related, "ii", $package['category_id'], $package_id);
mysqli_stmt_execute($stmt_related);
$result_related = mysqli_stmt_get_result($stmt_related);

// Fetch Reviews for this Package
$query_reviews = "SELECT r.*, u.name as user_name 
                  FROM review r
                  JOIN users u ON r.user_id = u.id
                  WHERE r.package_id = ?
                  ORDER BY r.created_at DESC";
$stmt_reviews = mysqli_prepare($conn, $query_reviews);
mysqli_stmt_bind_param($stmt_reviews, "i", $package_id);
mysqli_stmt_execute($stmt_reviews);
$result_reviews = mysqli_stmt_get_result($stmt_reviews);

// Calculate average rating
$query_avg_rating = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
                     FROM review 
                     WHERE package_id = ?";
$stmt_avg = mysqli_prepare($conn, $query_avg_rating);
mysqli_stmt_bind_param($stmt_avg, "i", $package_id);
mysqli_stmt_execute($stmt_avg);
$result_avg = mysqli_stmt_get_result($stmt_avg);
$rating_data = mysqli_fetch_assoc($result_avg);
$avg_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
$total_reviews = $rating_data['total_reviews'];

// Check if current user has reviewed this package
$user_has_reviewed = false;
if (isset($_SESSION['user_id'])) {
    $query_user_review = "SELECT id FROM review WHERE user_id = ? AND package_id = ?";
    $stmt_user_review = mysqli_prepare($conn, $query_user_review);
    mysqli_stmt_bind_param($stmt_user_review, "ii", $_SESSION['user_id'], $package_id);
    mysqli_stmt_execute($stmt_user_review);
    $result_user_review = mysqli_stmt_get_result($stmt_user_review);
    $user_has_reviewed = mysqli_num_rows($result_user_review) > 0;
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($package['name']) ?> - LoveMUA Package Details</title>
    <link rel="stylesheet" href="assets/css/package-detail.css">
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
                <li><a href="all-packages.php" class="active">Packages</a></li>
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
                <a href="all-packages.php?category=<?= $package['category_id'] ?>">
                    <?= htmlspecialchars($package['category_name']) ?>
                </a>
                <span>/</span>
                <span><?= htmlspecialchars($package['name']) ?></span>
            </div>
        </div>
    </section>

    <!-- Package Gallery Section -->
    <section class="package-gallery-section">
        <div class="container">
            <div class="gallery-wrapper">
                <!-- Main Image Display -->
                <div class="main-image-container">
                    <div class="category-badge"><?= htmlspecialchars($package['category_name']) ?></div>
                    <img id="mainImage"
                        src="<?= !empty($images[0]['filename']) ? 'uploads/mua_packages/' . htmlspecialchars($images[0]['filename']) : 'assets/images/package-placeholder.jpg' ?>"
                        alt="<?= htmlspecialchars($package['name']) ?>"
                        onerror="this.src='assets/images/package-placeholder.jpg'">

                    <?php if (count($images) > 1): ?>
                    <!-- Navigation Arrows -->
                    <button class="gallery-nav prev" onclick="changeImage(-1)">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="gallery-nav next" onclick="changeImage(1)">
                        <i class="fas fa-chevron-right"></i>
                    </button>

                    <!-- Image Counter -->
                    <div class="image-counter">
                        <span id="currentImageNum">1</span> / <?= count($images) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (count($images) > 1): ?>
                <!-- Thumbnail Gallery -->
                <div class="thumbnail-gallery">
                    <?php foreach ($images as $index => $image): ?>
                    <div class="thumbnail <?= $index === 0 ? 'active' : '' ?>" onclick="selectImage(<?= $index ?>)">
                        <img src="<?= !empty($image['filename']) ? 'uploads/mua_packages/' . htmlspecialchars($image['filename']) : 'assets/images/package-placeholder.jpg' ?>"
                            alt="Thumbnail <?= $index + 1 ?>"
                            onerror="this.src='assets/images/package-placeholder.jpg'">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Package Detail Section -->
    <section class="package-detail-section">
        <div class="container">
            <div class="detail-grid">
                <!-- Left Column: Package Info -->
                <div class="info-column">
                    <h1 class="package-title"><?= htmlspecialchars($package['name']) ?></h1>

                    <!-- MUA Info Card -->
                    <div class="mua-info-card">
                        <div class="mua-avatar">
                            <img src="<?= !empty($package['mua_photo']) ? 'admin/assets/images/mua/' . htmlspecialchars($package['mua_photo']) : 'assets/images/default-mua.jpg' ?>"
                                alt="<?= htmlspecialchars($package['mua_name']) ?>">
                        </div>
                        <div class="mua-details">
                            <span class="mua-label">Makeup Artist</span>
                            <a href="mua-detail.php?id=<?= $package['mua_id'] ?>" class="mua-name">
                                <?= htmlspecialchars($package['mua_name']) ?>
                            </a>
                            <p class="mua-bio-short"><?= htmlspecialchars(substr($package['mua_bio'], 0, 80)) ?>...
                            </p>
                        </div>
                    </div>

                    <!-- Package Description -->
                    <div class="section-card">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i> Package Description
                        </h2>
                        <p class="package-description"><?= nl2br(htmlspecialchars($package['description'])) ?></p>
                    </div>

                    <!-- Package Details -->
                    <div class="section-card">
                        <h2 class="section-title">
                            <i class="fas fa-list-check"></i> Package Details
                        </h2>
                        <div class="details-list">
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <strong>Duration</strong>
                                    <span><?= $package['duration_hours'] ?> Hours</span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-tag"></i>
                                <div>
                                    <strong>Category</strong>
                                    <span><?= htmlspecialchars($package['category_name']) ?></span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-user"></i>
                                <div>
                                    <strong>Artist</strong>
                                    <span><?= htmlspecialchars($package['mua_name']) ?></span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-check-circle"></i>
                                <div>
                                    <strong>Availability</strong>
                                    <span class="available-status">Available for Booking</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Booking Card (Sticky) -->
                <div class="booking-column">
                    <div class="booking-card sticky-card">
                        <div class="price-section">
                            <span class="price-label">Package Price</span>
                            <div class="price-amount">Rp <?= number_format($package['price'], 0, ',', '.') ?></div>
                            <span class="price-note">Per session (<?= $package['duration_hours'] ?> hours)</span>
                        </div>

                        <div class="booking-actions">
                            <a href="booking.php?package_id=<?= $package_id ?>" class="btn-book-now">
                                <i class="fas fa-calendar-check"></i> Book Now
                            </a>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $package['mua_phone']) ?>?text=Hi, I'm interested in the package: <?= urlencode($package['name']) ?>"
                                target="_blank" class="btn-whatsapp">
                                <i class="fab fa-whatsapp"></i> Chat via WhatsApp
                            </a>
                        </div>

                        <div class="contact-options">
                            <div class="contact-item">
                                <i class="fas fa-phone"></i>
                                <a href="tel:<?= htmlspecialchars($package['mua_phone']) ?>">
                                    <?= htmlspecialchars($package['mua_phone']) ?>
                                </a>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <a href="mailto:<?= htmlspecialchars($package['mua_email']) ?>">
                                    <?= htmlspecialchars($package['mua_email']) ?>
                                </a>
                            </div>
                        </div>

                        <div class="booking-info">
                            <div class="info-item">
                                <i class="fas fa-shield-alt"></i>
                                <span>Secure Booking</span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-redo"></i>
                                <span>Flexible Rescheduling</span>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-star"></i>
                                <span>Professional Service</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Reviews Section -->
    <section class="reviews-section">
        <div class="container">
            <div class="reviews-header">
                <div class="reviews-stats">
                    <?php if ($total_reviews > 0): ?>
                    <div class="rating-summary">
                        <div class="rating-number"><?= $avg_rating ?></div>
                        <div class="rating-stars">
                            <?php
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $avg_rating) {
                                echo '<i class="fas fa-star"></i>';
                            } elseif ($i - 0.5 <= $avg_rating) {
                                echo '<i class="fas fa-star-half-alt"></i>';
                            } else {
                                echo '<i class="fas fa-star empty"></i>';
                            }
                        }
                        ?>
                        </div>
                        <div class="rating-count"><?= $total_reviews ?> Review<?= $total_reviews > 1 ? 's' : '' ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!$user_has_reviewed): ?>
                <button class="write-review-btn" onclick="openReviewModal()">
                    <i class="fas fa-pen"></i> Write a Review
                </button>
                <?php else: ?>
                <div style="color: #4caf50; font-weight: 600;">
                    <i class="fas fa-check-circle"></i> You have reviewed this package
                </div>
                <?php endif; ?>
            </div>

            <div class="reviews-container" id="reviewsContainer">
                <?php if (mysqli_num_rows($result_reviews) > 0): ?>
                <?php while ($review = mysqli_fetch_assoc($result_reviews)): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <div class="reviewer-avatar">
                                <?= strtoupper(substr($review['user_name'], 0, 1)) ?>
                            </div>
                            <div class="reviewer-details">
                                <h4><?= htmlspecialchars($review['user_name']) ?></h4>
                                <div class="review-date">
                                    <?= date('d M Y', strtotime($review['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <div class="review-rating">
                            <?php
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $review['rating']) {
                                    echo '<i class="fas fa-star"></i>';
                                } else {
                                    echo '<i class="fas fa-star empty"></i>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <p class="review-comment"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                </div>
                <?php endwhile; ?>
                <?php else: ?>
                <div class="no-reviews">
                    <i class="fas fa-comments"></i>
                    <h3>No Reviews Yet</h3>
                    <p>Be the first to review this package!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Review Modal -->
    <div class="review-modal" id="reviewModal">
        <div class="review-modal-content">
            <div class="review-modal-header">
                <h3>Write Your Review</h3>
                <button class="close-modal" onclick="closeReviewModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="review-alert" id="reviewAlert"></div>

            <form id="reviewForm">
                <input type="hidden" name="package_id" value="<?= $package_id ?>">

                <div class="review-form-group">
                    <label>Your Rating *</label>
                    <div class="star-rating" id="starRating">
                        <i class="far fa-star" data-rating="1"></i>
                        <i class="far fa-star" data-rating="2"></i>
                        <i class="far fa-star" data-rating="3"></i>
                        <i class="far fa-star" data-rating="4"></i>
                        <i class="far fa-star" data-rating="5"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" value="0">
                </div>

                <div class="review-form-group">
                    <label for="reviewComment">Your Review *</label>
                    <textarea name="comment" id="reviewComment" class="review-textarea"
                        placeholder="Share your experience with this package..." maxlength="500" required></textarea>
                    <div class="char-count">
                        <span id="charCount">0</span> / 500 characters
                    </div>
                </div>

                <div class="review-form-actions">
                    <button type="button" class="btn-cancel-review" onclick="closeReviewModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn-submit-review" id="submitReviewBtn">
                        <i class="fas fa-paper-plane"></i> Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Related Packages Section -->
    <?php if (mysqli_num_rows($result_related) > 0): ?>
    <section class="related-packages-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title-main">More <?= htmlspecialchars($package['category_name']) ?> Packages</h2>
                <p class="section-subtitle">Discover other packages in this category</p>
            </div>

            <div class="packages-grid">
                <?php while ($related = mysqli_fetch_assoc($result_related)): ?>
                <div class="package-card">
                    <div class="package-badge"><?= htmlspecialchars($related['category_name']) ?></div>
                    <div class="package-image">
                        <?php 
                        $imagePath = !empty($related['image']) ? 'uploads/mua_packages/' . htmlspecialchars($related['image']) : 'assets/images/package-placeholder.jpg';
                        ?>
                        <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($related['name']) ?>"
                            onerror="this.src='assets/images/package-placeholder.jpg'">
                    </div>
                    <div class="package-content">
                        <h3><?= htmlspecialchars($related['name']) ?></h3>
                        <p class="package-mua">
                            <i class="fas fa-user"></i> by <?= htmlspecialchars($related['mua_name']) ?>
                        </p>
                        <p class="package-description">
                            <?= htmlspecialchars(substr($related['description'], 0, 80)) ?>...
                        </p>
                        <div class="package-meta">
                            <span class="package-duration">
                                <i class="fas fa-clock"></i> <?= $related['duration_hours'] ?> hours
                            </span>
                            <span class="package-price">Rp <?= number_format($related['price'], 0, ',', '.') ?></span>
                        </div>
                        <a href="package-detail.php?id=<?= $related['id'] ?>" class="package-btn">View Details</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <div class="section-footer">
                <a href="all-packages.php?category=<?= $package['category_id'] ?>" class="btn-view-all">
                    <i class="fas fa-th"></i> View All <?= htmlspecialchars($package['category_name']) ?> Packages
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Book This Package?</h2>
                <p>Transform your special moment with professional makeup artistry</p>
                <div class="cta-buttons">
                    <a href="booking.php?package_id=<?= $package_id ?>" class="cta-btn primary">
                        <i class="fas fa-calendar-check"></i> Book Now
                    </a>
                    <a href="all-packages.php" class="cta-btn secondary">
                        <i class="fas fa-box-open"></i> Browse More Packages
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Fixed Mobile Booking Button -->
    <div class="mobile-booking-bar">
        <div class="mobile-price">Rp <?= number_format($package['price'], 0, ',', '.') ?></div>
        <a href="booking.php?package_id=<?= $package_id ?>" class="mobile-book-btn">
            <i class="fas fa-calendar-check"></i> Book Now
        </a>
    </div>

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
    // Image Gallery Data
    const images = <?= json_encode(array_map(function($img) {
        return !empty($img['filename']) ? 'uploads/mua_packages/' . $img['filename'] : 'assets/images/package-placeholder.jpg';
    }, $images)) ?>;
    let currentImageIndex = 0;

    function changeImage(direction) {
        currentImageIndex += direction;
        if (currentImageIndex >= images.length) currentImageIndex = 0;
        if (currentImageIndex < 0) currentImageIndex = images.length - 1;
        updateImage();
    }

    function selectImage(index) {
        currentImageIndex = index;
        updateImage();
    }

    function updateImage() {
        document.getElementById('mainImage').src = images[currentImageIndex];
        document.getElementById('currentImageNum').textContent = currentImageIndex + 1;

        // Update active thumbnail
        document.querySelectorAll('.thumbnail').forEach((thumb, index) => {
            if (index === currentImageIndex) {
                thumb.classList.add('active');
            } else {
                thumb.classList.remove('active');
            }
        });
    }

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

    // Sticky Booking Card
    window.addEventListener('scroll', function() {
        const bookingCard = document.querySelector('.sticky-card');
        const bookingColumn = document.querySelector('.booking-column');
        const footer = document.querySelector('.footer');

        if (bookingCard && bookingColumn) {
            const columnRect = bookingColumn.getBoundingClientRect();
            const footerRect = footer.getBoundingClientRect();

            if (window.scrollY > 300 && footerRect.top > window.innerHeight) {
                bookingCard.classList.add('is-sticky');
            } else {
                bookingCard.classList.remove('is-sticky');
            }
        }
    });

    // Mobile Booking Bar Show/Hide
    window.addEventListener('scroll', function() {
        const mobileBar = document.querySelector('.mobile-booking-bar');
        const bookingSection = document.querySelector('.package-detail-section');

        if (mobileBar && bookingSection) {
            const sectionRect = bookingSection.getBoundingClientRect();

            if (sectionRect.top < 0) {
                mobileBar.classList.add('show');
            } else {
                mobileBar.classList.remove('show');
            }
        }
    });

    // Review Modal Functions
    function openReviewModal() {
        document.getElementById('reviewModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeReviewModal() {
        document.getElementById('reviewModal').classList.remove('show');
        document.body.style.overflow = 'auto';
        document.getElementById('reviewForm').reset();
        document.getElementById('ratingInput').value = '0';
        document.querySelectorAll('#starRating i').forEach(star => {
            star.classList.remove('fas', 'active');
            star.classList.add('far');
        });
        document.getElementById('charCount').textContent = '0';
        hideAlert();
    }

    // Star Rating System
    const starRating = document.getElementById('starRating');
    const ratingInput = document.getElementById('ratingInput');
    const stars = starRating.querySelectorAll('i');

    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-rating');
            ratingInput.value = rating;

            stars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas', 'active');
                } else {
                    s.classList.remove('fas', 'active');
                    s.classList.add('far');
                }
            });
        });

        star.addEventListener('mouseenter', function() {
            const rating = this.getAttribute('data-rating');
            stars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.remove('far');
                    s.classList.add('fas');
                } else {
                    s.classList.remove('fas');
                    s.classList.add('far');
                }
            });
        });
    });

    starRating.addEventListener('mouseleave', function() {
        const currentRating = ratingInput.value;
        stars.forEach((s, index) => {
            if (index < currentRating) {
                s.classList.remove('far');
                s.classList.add('fas', 'active');
            } else {
                s.classList.remove('fas', 'active');
                s.classList.add('far');
            }
        });
    });

    // Character Counter
    const reviewComment = document.getElementById('reviewComment');
    const charCount = document.getElementById('charCount');

    reviewComment.addEventListener('input', function() {
        charCount.textContent = this.value.length;
    });

    // Alert Functions
    function showAlert(message, type) {
        const alert = document.getElementById('reviewAlert');
        alert.textContent = message;
        alert.className = 'review-alert show ' + type;
    }

    function hideAlert() {
        const alert = document.getElementById('reviewAlert');
        alert.className = 'review-alert';
    }

    // Submit Review Form
    document.getElementById('reviewForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const rating = document.getElementById('ratingInput').value;
        const comment = document.getElementById('reviewComment').value.trim();
        const submitBtn = document.getElementById('submitReviewBtn');

        // Validation
        if (rating === '0') {
            showAlert('Please select a rating', 'error');
            return;
        }

        if (comment.length < 10) {
            showAlert('Review must be at least 10 characters', 'error');
            return;
        }

        // Disable submit button
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

        // Prepare form data
        const formData = new FormData(this);

        // Submit via AJAX
        fetch('submit-review.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');

                    // Add new review to the page
                    setTimeout(() => {
                        location.reload(); // Reload to show new review
                    }, 1500);
                } else {
                    showAlert(data.message, 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Review';
                }
            })
            .catch(error => {
                showAlert('An error occurred. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Review';
            });
    });

    // Close modal when clicking outside
    document.getElementById('reviewModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeReviewModal();
        }
    });
    </script>
</body>

</html>