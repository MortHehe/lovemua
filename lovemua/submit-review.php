<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to submit a review']);
    exit;
}

// Check if user is not admin
if ($_SESSION['role'] === 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admins cannot submit reviews']);
    exit;
}

require_once 'includes/db.php';

// Validate POST data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$user_id = $_SESSION['user_id'];
$package_id = isset($_POST['package_id']) ? (int)$_POST['package_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

// Validation
if ($package_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid package']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
    exit;
}

if (empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'Please write a comment']);
    exit;
}

if (strlen($comment) < 10) {
    echo json_encode(['success' => false, 'message' => 'Comment must be at least 10 characters']);
    exit;
}

// Check if package exists
$query_check = "SELECT id FROM packages WHERE id = ?";
$stmt_check = mysqli_prepare($conn, $query_check);
mysqli_stmt_bind_param($stmt_check, "i", $package_id);
mysqli_stmt_execute($stmt_check);
$result_check = mysqli_stmt_get_result($stmt_check);

if (mysqli_num_rows($result_check) == 0) {
    echo json_encode(['success' => false, 'message' => 'Package not found']);
    exit;
}

// Check if user already reviewed this package
$query_existing = "SELECT id FROM review WHERE user_id = ? AND package_id = ?";
$stmt_existing = mysqli_prepare($conn, $query_existing);
mysqli_stmt_bind_param($stmt_existing, "ii", $user_id, $package_id);
mysqli_stmt_execute($stmt_existing);
$result_existing = mysqli_stmt_get_result($stmt_existing);

if (mysqli_num_rows($result_existing) > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already reviewed this package']);
    exit;
}

// Insert review
$query_insert = "INSERT INTO review (user_id, package_id, rating, comment, created_at) VALUES (?, ?, ?, ?, NOW())";
$stmt_insert = mysqli_prepare($conn, $query_insert);
mysqli_stmt_bind_param($stmt_insert, "iiis", $user_id, $package_id, $rating, $comment);

if (mysqli_stmt_execute($stmt_insert)) {
    // Get the inserted review with user info
    $review_id = mysqli_insert_id($conn);
    
    $query_review = "SELECT r.*, u.name as user_name 
                     FROM review r 
                     JOIN users u ON r.user_id = u.id 
                     WHERE r.id = ?";
    $stmt_review = mysqli_prepare($conn, $query_review);
    mysqli_stmt_bind_param($stmt_review, "i", $review_id);
    mysqli_stmt_execute($stmt_review);
    $result_review = mysqli_stmt_get_result($stmt_review);
    $review = mysqli_fetch_assoc($result_review);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Review submitted successfully!',
        'review' => $review
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit review. Please try again.']);
}

mysqli_close($conn);
?>