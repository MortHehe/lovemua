<?php
$conn = new mysqli("localhost", "root", "", "lovemua");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>