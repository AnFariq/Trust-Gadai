<?php
include "db.php";
session_start();

// Set header untuk JSON response
header('Content-Type: application/json');

// Cek apakah user sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    echo json_encode([]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil status terbaru dari database
$query = "SELECT id, status FROM gadai WHERE user_id = '$user_id'";
$result = mysqli_query($conn, $query);

$statuses = [];
while ($row = mysqli_fetch_assoc($result)) {
    $statuses[$row['id']] = $row['status'];
}

// Return status dalam format JSON
echo json_encode($statuses);
?>