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

// CEK ID PACKAGE
if (!isset($_GET['id'])) {
    header("Location: packages.php");
    exit;
}

$package_id = intval($_GET['id']);

// Ambil data package
$pkg = $conn->query("SELECT * FROM packages WHERE id='$package_id'")->fetch_assoc();
if (!$pkg) {
    echo "Package tidak ditemukan!";
    exit;
}

// PROSES UPDATE PACKAGE
if (isset($_POST['update_package'])) {

    $name        = $_POST['name'];
    $price       = $_POST['price'];
    $duration    = $_POST['duration_hours'];
    $category_id = $_POST['category_id'];
    $mua_id      = $_POST['mua_id'];
    $description = $_POST['description'];

    $conn->query("
        UPDATE packages SET 
            name='$name',
            price='$price',
            duration_hours='$duration',
            category_id='$category_id',
            mua_id='$mua_id',
            description='$description'
        WHERE id='$package_id'
    ");

    // UPLOAD GAMBAR BARU (MULTIPLE)
    if (!empty($_FILES['images']['name'][0])) {

        // pastikan folder ada
        if (!is_dir("../uploads/mua_packages")) {
            mkdir("../uploads/mua_packages", 0777, true);
        }

        foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
            $filename = time() . "_" . basename($_FILES['images']['name'][$i]);
            $dest = "../uploads/mua_packages/" . $filename;

            if (move_uploaded_file($tmp, $dest)) {
                $conn->query("
                    INSERT INTO package_images (package_id, filename)
                    VALUES ('$package_id', '$filename')
                ");
            }
        }
    }

    header("Location: packages.php");
    exit;
}

// PROSES HAPUS GAMBAR
if (isset($_GET['delete_img'])) {
    $img_id = intval($_GET['delete_img']);

    // ambil filename dulu
    $f = $conn->query("SELECT filename FROM package_images WHERE id='$img_id'")->fetch_assoc();
    if ($f) {
        $filepath = "../uploads/mua_packages/" . $f['filename'];
        if (file_exists($filepath)) unlink($filepath);

        $conn->query("DELETE FROM package_images WHERE id='$img_id'");
    }

    header("Location: package_edit.php?id=$package_id");
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Package</title>

    <style>
    body {
        font-family: Arial;
        background: #f5f5f5;
        padding: 20px;
    }

    .navbar {
        background: #333;
        padding: 15px;
    }

    .navbar a {
        color: white;
        margin-right: 15px;
        text-decoration: none;
        font-weight: bold;
    }

    .navbar a:hover {
        text-decoration: underline;
    }


    .container {
        background: white;
        padding: 25px;
        border-radius: 8px;
        max-width: 700px;
        margin: auto;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }

    input,
    select,
    textarea {
        width: 100%;
        padding: 10px;
        margin: 8px 0;
    }

    .btn {
        padding: 10px 16px;
        background: black;
        color: white;
        border: none;
        cursor: pointer;
        border-radius: 6px;
        text-decoration: none;
    }

    .btn:hover {
        opacity: .75;
    }

    .gallery img {
        width: 90px;
        height: 90px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #ccc;
        margin: 5px;
    }

    .gallery a {
        display: inline-block;
        color: red;
        font-size: 13px;
        text-decoration: none;
    }

    .back-btn {
        display: inline-block;
        margin-bottom: 15px;
    }
    </style>
</head>

<body>
    <!-- NAVBAR -->
    <div class="navbar">
        <a href="dashboard.php">Dashboard</a>
        <a href="mua.php">MUA</a>
        <a href="packages_Categories.php">Categories</a>
        <a href="packages.php">Packages</a>
        <a href="bookings.php">Bookings</a>
        <a href="payments.php">Payments</a>
        <a href="invoice.php">Invoice</a>
        <a href="review.php">Review</a>
        <a href="users.php">Users</a>
        <a href="logout.php" style="float:right;">Logout</a>
    </div>

    <div class="container">

        <a href="packages.php" class="btn back-btn">← Back</a>

        <h2>Edit Package</h2>

        <form method="POST" enctype="multipart/form-data">

            <input type="text" name="name" value="<?= $pkg['name']; ?>" placeholder="Nama Package" required>

            <label>Kategori</label>
            <select name="category_id">
                <?php
            $cats = $conn->query("SELECT * FROM packages_categories");
            while ($c = $cats->fetch_assoc()) {
                $sel = ($c['id'] == $pkg['category_id']) ? "selected" : "";
                echo "<option value='{$c['id']}' $sel>{$c['name']}</option>";
            }
            ?>
            </select>

            <label>Pilih MUA</label>
            <select name="mua_id" required>
                <?php
            $muas = $conn->query("SELECT * FROM mua ORDER BY name ASC");
            while ($m = $muas->fetch_assoc()) {
                $sel = ($m['id'] == $pkg['mua_id']) ? "selected" : "";
                echo "<option value='{$m['id']}' $sel>{$m['name']}</option>";
            }
            ?>
            </select>

            <input type="number" name="price" value="<?= $pkg['price']; ?>" placeholder="Harga" required>
            <input type="number" name="duration" value="<?= $pkg['duration_hours']; ?>" placeholder="Durasi (jam)"
                required>

            <textarea name="description" placeholder="Deskripsi"><?= $pkg['description']; ?></textarea>

            <label>Upload Gambar Baru</label>
            <input type="file" name="images[]" multiple>

            <h3>Gallery Saat Ini</h3>
            <div class="gallery">
                <?php
            $gallery = $conn->query("SELECT * FROM package_images WHERE package_id='$package_id'");
            while ($g = $gallery->fetch_assoc()) { ?>
                <div style="display:inline-block; text-align:center; margin-right:10px;">
                    <img src="../uploads/mua_packages/<?= $g['filename']; ?>">
                    <br>
                    <a href="package_edit.php?id=<?= $package_id; ?>&delete_img=<?= $g['id']; ?>"
                        onclick="return confirm('Hapus gambar ini?')">Hapus</a>
                </div>
                <?php } ?>
            </div>

            <button class="btn" name="update_package">Simpan Perubahan</button>

        </form>
    </div>

</body>

</html>