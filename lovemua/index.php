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

// Fetch MUAs
$query_mua = "SELECT * FROM mua LIMIT 6";
$result_mua = mysqli_query($conn, $query_mua);

// Fetch Package Categories
$query_categories = "SELECT * FROM packages_categories";
$result_categories = mysqli_query($conn, $query_categories);

// Fetch Packages with MUA info
// Fetch Packages with MUA info and first image using LEFT JOIN
$query_packages = "SELECT p.*, m.name as mua_name, pc.name as category_name, pi.filename as image
                   FROM packages p 
                   JOIN mua m ON p.mua_id = m.id 
                   JOIN packages_categories pc ON p.category_id = pc.id 
                   LEFT JOIN package_images pi ON pi.package_id = p.id
                   GROUP BY p.id
                   ORDER BY p.id DESC
                   LIMIT 8";
$result_packages = mysqli_query($conn, $query_packages);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LoveMUA - Professional Makeup Artist Services</title>
    <link rel="stylesheet" href="assets/css/index.css">
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
                <h1>Love<span>MUA</span></h1>
            </div>
            <ul class="nav-menu">
                <li><a href="#home" class="active">Home</a></li>
                <li><a href="#mua">Our MUAs</a></li>
                <li><a href="#categories">Categories</a></li>
                <li><a href="#packages">Packages</a></li>
                <li><a href="#contact">Contact</a></li>
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

    <!-- Hero Section with Slider -->
    <section id="home" class="hero-section">
        <div class="hero-slider">
            <div class="slide active"
                style="background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('assets/images/profesional.png') center/cover;">
                <div class="slide-content">
                    <h1 class="hero-title">Transform Your Beauty</h1>
                    <p class="hero-subtitle">Professional Makeup Artists for Your Special Moments</p>
                    <a href="#packages" class="hero-btn">Book Now</a>
                </div>
            </div>
            <div class="slide"
                style="background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('assets/images/weeding.png') center/cover;">
                <div class="slide-content">
                    <h1 class="hero-title">Wedding Perfection</h1>
                    <p class="hero-subtitle">Make Your Big Day Unforgettable</p>
                    <a href="#packages" class="hero-btn">Explore Packages</a>
                </div>
            </div>
            <div class="slide"
                style="background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)), url('assets/images/graduate.png') center/cover;">
                <div class="slide-content">
                    <h1 class="hero-title">Graduation Glam</h1>
                    <p class="hero-subtitle">Shine on Your Achievement Day</p>
                    <a href="#packages" class="hero-btn">View Services</a>
                </div>
            </div>
        </div>
        <button class="slider-btn prev" onclick="changeSlide(-1)">
            <i class="fas fa-chevron-left"></i>
        </button>
        <button class="slider-btn next" onclick="changeSlide(1)">
            <i class="fas fa-chevron-right"></i>
        </button>
        <div class="slider-dots">
            <span class="dot active" onclick="currentSlide(1)"></span>
            <span class="dot" onclick="currentSlide(2)"></span>
            <span class="dot" onclick="currentSlide(3)"></span>
        </div>
    </section>

    <!-- Logo/Brand Section -->
    <section class="brand-section">
        <div class="container">
            <div class="brand-logo">
                <img src="assets/images/Love.png" alt="LoveMUA Logo">
            </div>
            <p class="brand-tagline">Your Trusted Partner for Professional Makeup Services</p>
        </div>
    </section>
    
    <!-- MUA Section -->
    <section id="mua" class="mua-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Our Professional MUAs</h2>
                <p class="section-subtitle">Meet our talented makeup artists ready to make you beautiful</p>
            </div>
            <div class="mua-grid">
                <?php while ($mua = mysqli_fetch_assoc($result_mua)): ?>
                <div class="mua-card">
                    <div class="mua-image">
                        <img src="<?= !empty($mua['photo']) ? 'admin/assets/images/mua/' . htmlspecialchars($mua['photo']) : 'assets/images/default-mua.jpg' ?>"
                            alt="<?= htmlspecialchars($mua['name']) ?>">
                        <div class="mua-overlay">
                            <a href="mua-detail.php?id=<?= $mua['id'] ?>" class="view-btn">View Profile</a>
                        </div>
                    </div>
                    <div class="mua-info">
                        <h3><?= htmlspecialchars($mua['name']) ?></h3>
                        <p><?= htmlspecialchars(substr($mua['bio'], 0, 100)) ?>...</p>
                        <div class="mua-contact">
                            <i class="fas fa-phone"></i>
                            <span><?= htmlspecialchars($mua['phone']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <div class="section-footer">
                <a href="all-muas.php" class="btn-secondary">View All MUAs</a>
            </div>
        </div>
    </section>

    <!-- Package Categories Section -->
    <section id="categories" class="categories-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Package Categories</h2>
                <p class="section-subtitle">Choose the perfect category for your special occasion</p>
            </div>
            <div class="categories-grid">
                <?php while ($category = mysqli_fetch_assoc($result_categories)): ?>
                <div class="category-card">
                    <div class="category-icon">
                        <i class="fas fa-crown"></i>
                    </div>
                    <h3><?= htmlspecialchars($category['name']) ?></h3>
                    <a href="all-packages.php?category=<?= $category['id'] ?>" class="category-link">
                        Explore Packages <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Packages Section -->
    <section id="packages" class="packages-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Popular Packages</h2>
                <p class="section-subtitle">Discover our most loved makeup packages</p>
            </div>
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
                        <p class="package-mua">
                            <i class="fas fa-user"></i> by <?= htmlspecialchars($package['mua_name']) ?>
                        </p>
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
            <div class="section-footer">
                <a href="all-packages.php" class="btn-secondary">View All Packages</a>
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
                        <li><a href="#home">Home</a></li>
                        <li><a href="#mua">Our MUAs</a></li>
                        <li><a href="#categories">Categories</a></li>
                        <li><a href="#packages">Packages</a></li>
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

    // Hero Slider
    let slideIndex = 1;
    let slideTimer;

    showSlides(slideIndex);
    autoSlide();

    function changeSlide(n) {
        clearTimeout(slideTimer);
        showSlides(slideIndex += n);
        autoSlide();
    }

    function currentSlide(n) {
        clearTimeout(slideTimer);
        showSlides(slideIndex = n);
        autoSlide();
    }

    function showSlides(n) {
        let slides = document.getElementsByClassName("slide");
        let dots = document.getElementsByClassName("dot");

        if (n > slides.length) {
            slideIndex = 1
        }
        if (n < 1) {
            slideIndex = slides.length
        }

        for (let i = 0; i < slides.length; i++) {
            slides[i].classList.remove("active");
        }
        for (let i = 0; i < dots.length; i++) {
            dots[i].classList.remove("active");
        }

        slides[slideIndex - 1].classList.add("active");
        dots[slideIndex - 1].classList.add("active");
    }

    function autoSlide() {
        slideTimer = setTimeout(() => {
            slideIndex++;
            showSlides(slideIndex);
            autoSlide();
        }, 5000);
    }

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