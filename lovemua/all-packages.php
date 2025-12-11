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
$packages_per_page = 12;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $packages_per_page;

// Filters
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$mua_filter = isset($_GET['mua']) ? (int)$_GET['mua'] : 0;
$min_price = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'latest';

// Build WHERE clause
$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE '%$search%' OR p.description LIKE '%$search%')";
}

if ($category_filter > 0) {
    $where_conditions[] = "p.category_id = $category_filter";
}

if ($mua_filter > 0) {
    $where_conditions[] = "p.mua_id = $mua_filter";
}

if ($min_price > 0) {
    $where_conditions[] = "p.price >= $min_price";
}

if ($max_price > 0) {
    $where_conditions[] = "p.price <= $max_price";
}

$where_clause = "";
if (count($where_conditions) > 0) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Sort clause
$order_clause = "ORDER BY p.id DESC";
switch ($sort) {
    case 'latest':
        $order_clause = "ORDER BY p.id DESC";
        break;
    case 'oldest':
        $order_clause = "ORDER BY p.id ASC";
        break;
    case 'name_asc':
        $order_clause = "ORDER BY p.name ASC";
        break;
    case 'name_desc':
        $order_clause = "ORDER BY p.name DESC";
        break;
    case 'price_low':
        $order_clause = "ORDER BY p.price ASC";
        break;
    case 'price_high':
        $order_clause = "ORDER BY p.price DESC";
        break;
    case 'duration_short':
        $order_clause = "ORDER BY p.duration_hours ASC";
        break;
    case 'duration_long':
        $order_clause = "ORDER BY p.duration_hours DESC";
        break;
}

// Count total packages for pagination
$count_query = "SELECT COUNT(DISTINCT p.id) as total 
                FROM packages p
                JOIN mua m ON p.mua_id = m.id
                JOIN packages_categories pc ON p.category_id = pc.id
                $where_clause";
$count_result = mysqli_query($conn, $count_query);
$total_packages = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_packages / $packages_per_page);

// Fetch packages with images
$query_packages = "SELECT p.*, 
                   m.name as mua_name, 
                   pc.name as category_name,
                   (SELECT pi.filename FROM package_images pi WHERE pi.package_id = p.id ORDER BY pi.id ASC LIMIT 1) as image
                   FROM packages p
                   JOIN mua m ON p.mua_id = m.id
                   JOIN packages_categories pc ON p.category_id = pc.id
                   $where_clause
                   GROUP BY p.id
                   $order_clause
                   LIMIT $packages_per_page OFFSET $offset";

$result_packages = mysqli_query($conn, $query_packages);

// Fetch all categories for filter
$categories_query = "SELECT * FROM packages_categories ORDER BY name ASC";
$categories_result = mysqli_query($conn, $categories_query);

// Fetch all MUAs for filter dropdown
$muas_query = "SELECT id, name FROM mua ORDER BY name ASC";
$muas_result = mysqli_query($conn, $muas_query);

// Get selected category/MUA name for display
$selected_category_name = '';
$selected_mua_name = '';

if ($category_filter > 0) {
    $cat_query = mysqli_query($conn, "SELECT name FROM packages_categories WHERE id = $category_filter");
    if ($cat_row = mysqli_fetch_assoc($cat_query)) {
        $selected_category_name = $cat_row['name'];
    }
}

if ($mua_filter > 0) {
    $mua_query = mysqli_query($conn, "SELECT name FROM mua WHERE id = $mua_filter");
    if ($mua_row = mysqli_fetch_assoc($mua_query)) {
        $selected_mua_name = $mua_row['name'];
    }
}

?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Packages - LoveMUA Makeup Packages</title>
    <link rel="stylesheet" href="assets/css/all-packages.css">
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
                <li><a href="all-categories.php">Categories</a></li>
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

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i> Home</a>
                <span>/</span>
                <?php if ($category_filter > 0): ?>
                <a href="all-packages.php">Packages</a>
                <span>/</span>
                <span><?= htmlspecialchars($selected_category_name) ?></span>
                <?php elseif ($mua_filter > 0): ?>
                <a href="all-packages.php">Packages</a>
                <span>/</span>
                <span><?= htmlspecialchars($selected_mua_name) ?></span>
                <?php else: ?>
                <span>All Packages</span>
                <?php endif; ?>
            </div>
            <h1>
                <?php 
                if ($category_filter > 0) {
                    echo htmlspecialchars($selected_category_name) . " Packages";
                } elseif ($mua_filter > 0) {
                    echo "Packages by " . htmlspecialchars($selected_mua_name);
                } else {
                    echo "All Makeup Packages";
                }
                ?>
            </h1>
            <p>Find the perfect makeup package for your special occasion</p>
        </div>
    </section>

    <!-- Category Pills Filter -->
    <section class="category-filter-section">
        <div class="container">
            <div class="category-pills">
                <a href="all-packages.php" class="pill <?= $category_filter == 0 ? 'active' : '' ?>">
                    <i class="fas fa-th"></i> All Categories
                </a>
                <?php 
                mysqli_data_seek($categories_result, 0);
                while ($cat = mysqli_fetch_assoc($categories_result)): 
                ?>
                <a href="?category=<?= $cat['id'] ?>"
                    class="pill <?= $category_filter == $cat['id'] ? 'active' : '' ?>">
                    <?= htmlspecialchars($cat['name']) ?>
                </a>
                <?php endwhile; ?>
            </div>
        </div>
    </section>

    <!-- Search & Filter Section -->
    <section class="filter-section">
        <div class="container">
            <div class="filter-row-1">
                <form method="GET" action="all-packages.php" class="search-form">
                    <?php if ($category_filter > 0): ?>
                    <input type="hidden" name="category" value="<?= $category_filter ?>">
                    <?php endif; ?>
                    <?php if ($mua_filter > 0): ?>
                    <input type="hidden" name="mua" value="<?= $mua_filter ?>">
                    <?php endif; ?>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search package by name..."
                            value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="search-btn">Search</button>
                    </div>
                </form>
            </div>

            <div class="filter-row-2">
                <div class="filter-group">
                    <label><i class="fas fa-user"></i> MUA:</label>
                    <select id="mua-filter" onchange="applyFilter('mua', this.value)">
                        <option value="0">All MUAs</option>
                        <?php 
                        mysqli_data_seek($muas_result, 0);
                        while ($mua = mysqli_fetch_assoc($muas_result)): 
                        ?>
                        <option value="<?= $mua['id'] ?>" <?= $mua_filter == $mua['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mua['name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group price-filter">
                    <label><i class="fas fa-tag"></i> Price Range:</label>
                    <input type="number" id="min-price" placeholder="Min"
                        value="<?= $min_price > 0 ? $min_price : '' ?>">
                    <span>-</span>
                    <input type="number" id="max-price" placeholder="Max"
                        value="<?= $max_price > 0 ? $max_price : '' ?>">
                    <button onclick="applyPriceFilter()" class="apply-btn">Apply</button>
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-sort"></i> Sort By:</label>
                    <select id="sort-select" onchange="applyFilter('sort', this.value)">
                        <option value="latest" <?= $sort == 'latest' ? 'selected' : '' ?>>Latest</option>
                        <option value="oldest" <?= $sort == 'oldest' ? 'selected' : '' ?>>Oldest</option>
                        <option value="name_asc" <?= $sort == 'name_asc' ? 'selected' : '' ?>>Name (A-Z)</option>
                        <option value="name_desc" <?= $sort == 'name_desc' ? 'selected' : '' ?>>Name (Z-A)</option>
                        <option value="price_low" <?= $sort == 'price_low' ? 'selected' : '' ?>>Price (Low to High)
                        </option>
                        <option value="price_high" <?= $sort == 'price_high' ? 'selected' : '' ?>>Price (High to Low)
                        </option>
                        <option value="duration_short" <?= $sort == 'duration_short' ? 'selected' : '' ?>>Duration
                            (Short to Long)</option>
                        <option value="duration_long" <?= $sort == 'duration_long' ? 'selected' : '' ?>>Duration (Long
                            to Short)</option>
                    </select>
                </div>
            </div>

            <div class="results-info">
                <div class="results-text">
                    <p>Showing <strong><?= mysqli_num_rows($result_packages) ?></strong> of
                        <strong><?= $total_packages ?></strong> packages
                    </p>
                    <?php if ($category_filter > 0 || $mua_filter > 0 || !empty($search) || $min_price > 0 || $max_price > 0): ?>
                    <div class="active-filters">
                        <?php if ($category_filter > 0): ?>
                        <span class="filter-tag">
                            Category: <?= htmlspecialchars($selected_category_name) ?>
                            <a href="<?= http_build_query(array_diff_key($_GET, ['category' => ''])) ?>">×</a>
                        </span>
                        <?php endif; ?>
                        <?php if ($mua_filter > 0): ?>
                        <span class="filter-tag">
                            MUA: <?= htmlspecialchars($selected_mua_name) ?>
                            <a href="<?= http_build_query(array_diff_key($_GET, ['mua' => ''])) ?>">×</a>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($search)): ?>
                        <span class="filter-tag">
                            Search: "<?= htmlspecialchars($search) ?>"
                            <a href="<?= http_build_query(array_diff_key($_GET, ['search' => ''])) ?>">×</a>
                        </span>
                        <?php endif; ?>
                        <?php if ($min_price > 0 || $max_price > 0): ?>
                        <span class="filter-tag">
                            Price: Rp <?= number_format($min_price) ?> - Rp <?= number_format($max_price) ?>
                            <a
                                href="<?= http_build_query(array_diff_key($_GET, ['min_price' => '', 'max_price' => ''])) ?>">×</a>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($category_filter > 0 || $mua_filter > 0 || !empty($search) || $min_price > 0 || $max_price > 0): ?>
                <a href="all-packages.php" class="clear-all-btn">
                    <i class="fas fa-times-circle"></i> Clear All Filters
                </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Placeholder untuk mencegah content jump -->
    <div class="filter-placeholder"></div>

    <!-- Packages Grid Section -->
    <section class="packages-list-section">
        <div class="container">
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

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-btn">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php endif; ?>

                <div class="page-numbers">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i == $page): ?>
                    <span class="page-number active"><?= $i ?></span>
                    <?php else: ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                        class="page-number"><?= $i ?></a>
                    <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if ($page < $total_pages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-btn">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <div class="no-results">
                <i class="fas fa-box-open"></i>
                <h3>No Packages Found</h3>
                <p>Sorry, we couldn't find any packages matching your criteria.</p>
                <a href="all-packages.php" class="btn-primary">View All Packages</a>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Need Help Choosing the Right Package?</h2>
                <p>Contact us and we'll help you find the perfect makeup package for your special occasion</p>
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

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.user-dropdown')) {
            dropdownContent.classList.remove('show');
        }
    });

    // Filter functions
    function applyFilter(filterType, value) {
        const url = new URL(window.location.href);

        if (value == 0 || value == '') {
            url.searchParams.delete(filterType);
        } else {
            url.searchParams.set(filterType, value);
        }

        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

    function applyPriceFilter() {
        const minPrice = document.getElementById('min-price').value;
        const maxPrice = document.getElementById('max-price').value;
        const url = new URL(window.location.href);

        if (minPrice) {
            url.searchParams.set('min_price', minPrice);
        } else {
            url.searchParams.delete('min_price');
        }

        if (maxPrice) {
            url.searchParams.set('max_price', maxPrice);
        } else {
            url.searchParams.delete('max_price');
        }

        url.searchParams.set('page', '1');
        window.location.href = url.toString();
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