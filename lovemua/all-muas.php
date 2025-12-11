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

// Pagination settings
$muas_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $muas_per_page;

// Search and Sort
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';

// Build query
$where_clause = "";
if (!empty($search)) {
    $where_clause = "WHERE m.name LIKE '%$search%' OR m.bio LIKE '%$search%'";
}

// Sort clause
$order_clause = "ORDER BY m.name ASC";
switch ($sort) {
    case 'name_asc':
        $order_clause = "ORDER BY m.name ASC";
        break;
    case 'name_desc':
        $order_clause = "ORDER BY m.name DESC";
        break;
    case 'packages_most':
        $order_clause = "ORDER BY package_count DESC";
        break;
    case 'price_low':
        $order_clause = "ORDER BY min_price ASC";
        break;
    case 'price_high':
        $order_clause = "ORDER BY min_price DESC";
        break;
}

// Count total MUAs for pagination
$count_query = "SELECT COUNT(DISTINCT m.id) as total FROM mua m $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_muas = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_muas / $muas_per_page);

// Fetch MUAs with package info
$query_muas = "SELECT 
                m.*,
                COUNT(DISTINCT p.id) as package_count,
                MIN(p.price) as min_price,
                GROUP_CONCAT(DISTINCT pc.name SEPARATOR ', ') as categories
               FROM mua m
               LEFT JOIN packages p ON p.mua_id = m.id
               LEFT JOIN packages_categories pc ON pc.id = p.category_id
               $where_clause
               GROUP BY m.id
               $order_clause
               LIMIT $muas_per_page OFFSET $offset";

$result_muas = mysqli_query($conn, $query_muas);

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All MUAs - LoveMUA Professional Makeup Artists</title>
    <link rel="stylesheet" href="assets/css/all-muas.css">
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

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <span>/</span>
                <span>Our MUAs</span>
            </div>
            <h1>Our Professional Makeup Artists</h1>
            <p>Discover talented makeup artists ready to make your special moments unforgettable</p>
        </div>
    </section>

    <!-- Search & Filter Section -->
    <section class="filter-section">
        <div class="container">
            <div class="filter-wrapper">
                <form method="GET" action="all-muas.php" class="search-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search MUA by name..."
                            value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="search-btn">Search</button>
                    </div>
                </form>

                <div class="sort-wrapper">
                    <label><i class="fas fa-sort"></i> Sort By:</label>
                    <select id="sort-select" onchange="sortMUAs(this.value)">
                        <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                        <option value="name_desc" <?= $sort == 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                        <option value="packages_most" <?= $sort == 'packages_most' ? 'selected' : '' ?>>Most Packages
                        </option>
                        <option value="price_low" <?= $sort == 'price_low' ? 'selected' : '' ?>>Price (Low to High)
                        </option>
                        <option value="price_high" <?= $sort == 'price_high' ? 'selected' : '' ?>>Price (High to Low)
                        </option>
                    </select>
                </div>
            </div>

            <div class="results-info">
                <p>Showing <strong><?= mysqli_num_rows($result_muas) ?></strong> of <strong><?= $total_muas ?></strong>
                    makeup artists</p>
                <?php if (!empty($search)): ?>
                <a href="all-muas.php" class="clear-search"><i class="fas fa-times"></i> Clear Search</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Placeholder untuk mencegah content jump -->
    <div class="filter-placeholder"></div>

    <!-- MUAs Grid Section -->
    <section class="muas-list-section">
        <div class="container">
            <?php if (mysqli_num_rows($result_muas) > 0): ?>
            <div class="muas-grid">
                <?php while ($mua = mysqli_fetch_assoc($result_muas)): ?>
                <div class="mua-card">
                    <div class="mua-image">
                        <img src="<?= !empty($mua['photo']) ? 'admin/assets/images/mua/' . htmlspecialchars($mua['photo']) : 'assets/images/default-mua.jpg' ?>"
                            alt="<?= htmlspecialchars($mua['name']) ?>">
                        <div class="mua-badge">
                            <i class="fas fa-star"></i> Professional
                        </div>
                    </div>

                    <div class="mua-content">
                        <h3><?= htmlspecialchars($mua['name']) ?></h3>
                        <p class="mua-bio"><?= htmlspecialchars(substr($mua['bio'], 0, 120)) ?>...</p>

                        <div class="mua-stats">
                            <div class="stat-item">
                                <i class="fas fa-box"></i>
                                <span><?= $mua['package_count'] ?> Packages</span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-tag"></i>
                                <span>From Rp <?= number_format($mua['min_price'], 0, ',', '.') ?></span>
                            </div>
                        </div>

                        <?php if (!empty($mua['categories'])): ?>
                        <div class="mua-categories">
                            <i class="fas fa-layer-group"></i>
                            <span><?= htmlspecialchars($mua['categories']) ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="mua-contact">
                            <i class="fas fa-phone"></i>
                            <span><?= htmlspecialchars($mua['phone']) ?></span>
                        </div>

                        <div class="mua-actions">
                            <a href="mua-detail.php?id=<?= $mua['id'] ?>" class="btn-primary">
                                <i class="fas fa-user"></i> View Profile
                            </a>
                            <a href="all-packages.php?mua=<?= $mua['id'] ?>" class="btn-secondary">
                                <i class="fas fa-box-open"></i> View Packages
                            </a>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>" class="page-btn">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php endif; ?>

                <div class="page-numbers">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                    <span class="page-number active"><?= $i ?></span>
                    <?php else: ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>"
                        class="page-number"><?= $i ?></a>
                    <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>" class="page-btn">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No MUAs Found</h3>
                <p>Sorry, we couldn't find any makeup artists matching your search.</p>
                <a href="all-muas.php" class="btn-primary">View All MUAs</a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Can't Find What You're Looking For?</h2>
                <p>Contact us and we'll help you find the perfect makeup artist for your special occasion</p>
                <a href="index.php#contact" class="cta-btn">
                    <i class="fas fa-phone"></i> Contact Us
                </a>
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

    // Sort function
    function sortMUAs(sortValue) {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', sortValue);
        url.searchParams.set('page', '1'); // Reset to page 1 when sorting
        window.location.href = url.toString();
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

    // Filter Section Sticky Behavior
    let lastScrollTop = 0;
    const filterSection = document.querySelector('.filter-section');
    const filterPlaceholder = document.querySelector('.filter-placeholder');
    const pageHeader = document.querySelector('.page-header');

    // Get initial position and height of filter section
    let filterInitialTop;
    let filterHeight;
    let isFixed = false;

    // Calculate positions after page load
    window.addEventListener('load', function() {
        filterInitialTop = filterSection.offsetTop;
        filterHeight = filterSection.offsetHeight;
    });

    window.addEventListener('scroll', function() {
        let scrollTop = window.pageYOffset || document.documentElement.scrollTop;

        // Update initial position if not set yet
        if (!filterInitialTop) {
            filterInitialTop = filterSection.offsetTop;
            filterHeight = filterSection.offsetHeight;
        }

        // Jika scroll melewati posisi awal filter
        if (scrollTop > filterInitialTop) {
            if (!isFixed) {
                // Aktifkan fixed mode
                filterSection.classList.add('fixed');
                filterPlaceholder.classList.add('active');
                filterPlaceholder.style.height = filterHeight + 'px';
                isFixed = true;
            }

            // Scroll ke atas - tampilkan filter
            if (scrollTop < lastScrollTop) {
                filterSection.classList.add('show');
            }
            // Scroll ke bawah - sembunyikan filter
            else {
                filterSection.classList.remove('show');
            }
        } else {
            if (isFixed) {
                // Kembali ke posisi awal
                filterSection.classList.remove('fixed');
                filterSection.classList.remove('show');
                filterPlaceholder.classList.remove('active');
                filterPlaceholder.style.height = '0';
                isFixed = false;
            }
        }

        lastScrollTop = scrollTop <= 0 ? 0 : scrollTop;
    });
    </script>
</body>

</html>