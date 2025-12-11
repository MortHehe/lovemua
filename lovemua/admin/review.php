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

// ====== DELETE REVIEW ======
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $delete_query = "DELETE FROM review WHERE id = ?";
    $stmt_delete = mysqli_prepare($conn, $delete_query);
    mysqli_stmt_bind_param($stmt_delete, "i", $delete_id);
    if (mysqli_stmt_execute($stmt_delete)) {
        header("Location: review.php?success=deleted");
        exit;
    }
}

// ====== FILTERS ======
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$filter_rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$filter_package = isset($_GET['package']) ? (int)$_GET['package'] : 0;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE '%$search%' OR p.name LIKE '%$search%' OR r.comment LIKE '%$search%')";
}

if ($filter_rating > 0) {
    $where_conditions[] = "r.rating = $filter_rating";
}

if ($filter_package > 0) {
    $where_conditions[] = "r.package_id = $filter_package";
}

if (!empty($filter_date_from)) {
    $where_conditions[] = "DATE(r.created_at) >= '$filter_date_from'";
}

if (!empty($filter_date_to)) {
    $where_conditions[] = "DATE(r.created_at) <= '$filter_date_to'";
}

$where_clause = count($where_conditions) > 0 ? "WHERE " . implode(" AND ", $where_conditions) : "";

// ====== GET REVIEWS DATA ======
$reviews = $conn->query("
    SELECT 
        r.id,
        r.user_id,
        r.package_id,
        r.rating,
        r.comment,
        r.created_at,
        u.name AS user_name,
        u.email AS user_email,
        p.name AS package_name,
        m.name AS mua_name
    FROM review r
    LEFT JOIN users u ON r.user_id = u.id
    LEFT JOIN packages p ON r.package_id = p.id
    LEFT JOIN mua m ON p.mua_id = m.id
    $where_clause
    ORDER BY r.created_at DESC
");

// ====== GET STATISTICS ======
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        AVG(rating) as avg_rating,
        SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
        SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
        SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
        SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
        SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
    FROM review
")->fetch_assoc();

// Get all packages for filter dropdown
$packages_list = $conn->query("SELECT id, name FROM packages ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Management - LoveMUA Admin</title>
    <link rel="stylesheet" href="assets/css/review.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            <a href="dashboard.php" class="nav-item">
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
            <a href="review.php" class="nav-item active">
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
                <h1>Review Management</h1>
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

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span>
                <?php
                if ($_GET['success'] == 'deleted') echo 'Review deleted successfully!';
                ?>
            </span>
            <button class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <section class="stats-section">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['total'] ?></h3>
                    <p>Total Reviews</p>
                </div>
            </div>

            <div class="stat-card average">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($stats['avg_rating'], 1) ?> <i class="fas fa-star star-icon"></i></h3>
                    <p>Average Rating</p>
                </div>
            </div>

            <div class="stat-card excellent">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['five_star'] ?></h3>
                    <p>5 Star Reviews</p>
                </div>
            </div>

            <div class="stat-card good">
                <div class="stat-icon">
                    <i class="fas fa-thumbs-up"></i>
                </div>
                <div class="stat-info">
                    <h3><?= $stats['four_star'] + $stats['three_star'] ?></h3>
                    <p>4-3 Star Reviews</p>
                </div>
            </div>
        </section>

        <!-- Rating Distribution -->
        <section class="rating-distribution">
            <h2><i class="fas fa-chart-bar"></i> Rating Distribution</h2>
            <div class="rating-bars">
                <?php for ($i = 5; $i >= 1; $i--): 
                    $star_count = $stats[$i == 5 ? 'five_star' : ($i == 4 ? 'four_star' : ($i == 3 ? 'three_star' : ($i == 2 ? 'two_star' : 'one_star')))];
                    $percentage = $stats['total'] > 0 ? ($star_count / $stats['total']) * 100 : 0;
                ?>
                <div class="rating-bar-item">
                    <div class="rating-label">
                        <?= $i ?> <i class="fas fa-star"></i>
                    </div>
                    <div class="rating-bar-container">
                        <div class="rating-bar-fill" style="width: <?= $percentage ?>%"></div>
                    </div>
                    <div class="rating-count"><?= $star_count ?></div>
                </div>
                <?php endfor; ?>
            </div>
        </section>

        <!-- Page Content -->
        <section class="content-section">
            <div class="content-header">
                <div>
                    <h2>Manage Reviews</h2>
                    <p>View and manage customer reviews and ratings</p>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" class="search-form">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by customer, package, or comment..."
                            value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-search">Search</button>
                    </div>
                </form>

                <div class="filter-dropdowns">
                    <select name="rating" onchange="applyFilter()">
                        <option value="0">All Ratings</option>
                        <option value="5" <?= $filter_rating == 5 ? 'selected' : '' ?>>5 Stars</option>
                        <option value="4" <?= $filter_rating == 4 ? 'selected' : '' ?>>4 Stars</option>
                        <option value="3" <?= $filter_rating == 3 ? 'selected' : '' ?>>3 Stars</option>
                        <option value="2" <?= $filter_rating == 2 ? 'selected' : '' ?>>2 Stars</option>
                        <option value="1" <?= $filter_rating == 1 ? 'selected' : '' ?>>1 Star</option>
                    </select>

                    <select name="package" onchange="applyFilter()">
                        <option value="0">All Packages</option>
                        <?php while ($pkg = $packages_list->fetch_assoc()): ?>
                        <option value="<?= $pkg['id'] ?>" <?= $filter_package == $pkg['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pkg['name']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>

                    <div class="date-range">
                        <label><i class="fas fa-calendar"></i> From:</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($filter_date_from) ?>"
                            onchange="applyFilter()">
                    </div>

                    <div class="date-range">
                        <label><i class="fas fa-calendar"></i> To:</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($filter_date_to) ?>"
                            onchange="applyFilter()">
                    </div>
                </div>

                <?php if (!empty($search) || $filter_rating > 0 || $filter_package > 0 || !empty($filter_date_from) || !empty($filter_date_to)): ?>
                <a href="review.php" class="btn-clear">
                    <i class="fas fa-times"></i> Clear Filters
                </a>
                <?php endif; ?>
            </div>

            <!-- Reviews Table -->
            <div class="table-container">
                <?php if (mysqli_num_rows($reviews) > 0): ?>
                <table class="reviews-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Package</th>
                            <th>MUA</th>
                            <th>Rating</th>
                            <th>Comment</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($reviews, 0);
                        while ($review = $reviews->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><strong>#<?= str_pad($review['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                            <td>
                                <div class="customer-info">
                                    <strong><?= htmlspecialchars($review['user_name']) ?></strong>
                                    <small><?= htmlspecialchars($review['user_email']) ?></small>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($review['package_name']) ?></td>
                            <td><?= htmlspecialchars($review['mua_name']) ?></td>
                            <td>
                                <div class="rating-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?= $i <= $review['rating'] ? 'filled' : 'empty' ?>"></i>
                                    <?php endfor; ?>
                                    <span class="rating-number"><?= $review['rating'] ?>.0</span>
                                </div>
                            </td>
                            <td>
                                <div class="comment-preview">
                                    <?= htmlspecialchars(substr($review['comment'], 0, 100)) ?>
                                    <?= strlen($review['comment']) > 100 ? '...' : '' ?>
                                </div>
                            </td>
                            <td>
                                <div class="datetime-info">
                                    <strong><?= date('d M Y', strtotime($review['created_at'])) ?></strong>
                                    <small><?= date('H:i', strtotime($review['created_at'])) ?></small>
                                </div>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-action view" title="View Full Review"
                                        onclick="viewReview(<?= $review['id'] ?>, '<?= htmlspecialchars(addslashes($review['user_name'])) ?>', '<?= htmlspecialchars(addslashes($review['package_name'])) ?>', <?= $review['rating'] ?>, '<?= htmlspecialchars(addslashes($review['comment'])) ?>', '<?= date('d M Y, H:i', strtotime($review['created_at'])) ?>')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-action delete" title="Delete Review"
                                        onclick="deleteReview(<?= $review['id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-star"></i>
                    <h3>No Reviews Found</h3>
                    <p>
                        <?= !empty($search) || $filter_rating > 0 || $filter_package > 0 || !empty($filter_date_from) || !empty($filter_date_to) ? 'No reviews match your filters.' : 'No reviews have been submitted yet.' ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Review Modal -->
    <div class="modal" id="reviewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-star"></i> Review Details</h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-section">
                    <label><i class="fas fa-user"></i> Customer:</label>
                    <p id="modalCustomer"></p>
                </div>
                <div class="modal-section">
                    <label><i class="fas fa-box-open"></i> Package:</label>
                    <p id="modalPackage"></p>
                </div>
                <div class="modal-section">
                    <label><i class="fas fa-star"></i> Rating:</label>
                    <div id="modalRating" class="rating-stars"></div>
                </div>
                <div class="modal-section">
                    <label><i class="fas fa-calendar"></i> Date:</label>
                    <p id="modalDate"></p>
                </div>
                <div class="modal-section full">
                    <label><i class="fas fa-comment"></i> Full Comment:</label>
                    <p id="modalComment" class="full-comment"></p>
                </div>
            </div>
        </div>
    </div>

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

    // Apply Filter
    function applyFilter() {
        const rating = document.querySelector('select[name="rating"]').value;
        const packageId = document.querySelector('select[name="package"]').value;
        const dateFrom = document.querySelector('input[name="date_from"]').value;
        const dateTo = document.querySelector('input[name="date_to"]').value;
        const search = document.querySelector('input[name="search"]').value;

        const url = new URL(window.location.href);
        url.searchParams.set('rating', rating);
        url.searchParams.set('package', packageId);
        url.searchParams.set('date_from', dateFrom);
        url.searchParams.set('date_to', dateTo);
        if (search) url.searchParams.set('search', search);

        window.location.href = url.toString();
    }

    // View Review Modal
    function viewReview(id, customer, packageName, rating, comment, date) {
        document.getElementById('modalCustomer').textContent = customer;
        document.getElementById('modalPackage').textContent = packageName;
        document.getElementById('modalDate').textContent = date;
        document.getElementById('modalComment').textContent = comment;

        // Display stars
        let starsHTML = '';
        for (let i = 1; i <= 5; i++) {
            starsHTML += `<i class="fas fa-star ${i <= rating ? 'filled' : 'empty'}"></i>`;
        }
        starsHTML += ` <span class="rating-number">${rating}.0</span>`;
        document.getElementById('modalRating').innerHTML = starsHTML;

        document.getElementById('reviewModal').classList.add('show');
    }

    function closeModal() {
        document.getElementById('reviewModal').classList.remove('show');
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('reviewModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    // Delete Review
    function deleteReview(id) {
        if (confirm('Are you sure you want to delete this review? This action cannot be undone.')) {
            window.location.href = `review.php?delete=${id}`;
        }
    }
    </script>
</body>

</html>