<?php
session_start();
include "db.php";

// Set header untuk JSON response
header('Content-Type: application/json');

// Cek apakah admin sudah login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Cek apakah ID ada
if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID tidak ditemukan']);
    exit;
}

$id = mysqli_real_escape_string($conn, $_GET['id']);

// Ambil detail barang dengan info user
$query = "SELECT g.*, u.nama as user_nama, u.email as user_email 
          FROM gadai g 
          JOIN users u ON g.user_id = u.id 
          WHERE g.id = '$id'";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    echo json_encode(['error' => 'Barang tidak ditemukan']);
    exit;
}

$data = mysqli_fetch_assoc($result);

// Return data dalam format JSON
echo json_encode($data);
?>