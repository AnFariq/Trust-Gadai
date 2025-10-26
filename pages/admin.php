<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: index.php");
    exit;
}
include "db.php";

// Handle status update
if (isset($_GET['update_status']) && isset($_GET['id']) && isset($_GET['status'])) {
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    $status = mysqli_real_escape_string($conn, $_GET['status']);
    
    // Validasi status yang diperbolehkan
    $allowed_status = ['Proses', 'Aktif', 'Selesai'];
    if (in_array($status, $allowed_status)) {
        $update_query = "UPDATE gadai SET status = '$status' WHERE id = '$id'";
        if (mysqli_query($conn, $update_query)) {
            $success_message = "Status barang #" . str_pad($id, 4, '0', STR_PAD_LEFT) . " berhasil diubah menjadi '$status'!";
            // Redirect untuk menghindari duplicate submission
            header("Location: " . $_SERVER['PHP_SELF'] . "?success=1&status=" . $status);
            exit;
        } else {
            $error_message = "Gagal mengubah status: " . mysqli_error($conn);
        }
    } else {
        $error_message = "Status tidak valid!";
    }
}

// Show success message dari redirect
if (isset($_GET['success']) && isset($_GET['status'])) {
    $success_message = "Status barang berhasil diubah menjadi '" . htmlspecialchars($_GET['status']) . "'!";
}

// Ambil semua data gadai dengan info user
$query = "SELECT g.*, u.nama as user_nama, u.email as user_email 
          FROM gadai g 
          JOIN users u ON g.user_id = u.id 
          ORDER BY g.created_at DESC";
$result = mysqli_query($conn, $query);

// Hitung statistik
$stat_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Proses' THEN 1 ELSE 0 END) as proses,
                SUM(CASE WHEN status = 'Aktif' THEN 1 ELSE 0 END) as aktif,
                SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END) as selesai,
                SUM(CASE WHEN status = 'Aktif' THEN nilai_barang ELSE 0 END) as total_nilai
               FROM gadai";
$stat_result = mysqli_query($conn, $stat_query);
$stats = mysqli_fetch_assoc($stat_result);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Trust Gadai</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <style>
        .sidebar {
            width: 250px;
            transition: all 0.3s ease;
        }
        
        .main-content {
            transition: all 0.3s ease;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                z-index: 50;
                height: 100vh;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 40;
            }
            
            .overlay.active {
                display: block;
            }
        }
        
        .fade-in {
            animation: fadeIn 0.8s ease-in-out;
        }
        
        .slide-up {
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.8);
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 2% auto;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 1200px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.4s ease;
        }

        .modal-header {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            color: white;
            padding: 20px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 20px;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .close:hover {
            transform: scale(1.2);
        }

        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .photo-item {
            position: relative;
            cursor: pointer;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .photo-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 12px rgba(0,0,0,0.2);
        }

        .photo-item img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .photo-label {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            color: white;
            text-align: center;
            padding: 10px;
            font-weight: 600;
        }

        .image-viewer {
            display: none;
            position: fixed;
            z-index: 200;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.95);
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .image-viewer img {
            max-width: 90%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 0 40px rgba(255,255,255,0.2);
        }

        .image-viewer-close {
            position: absolute;
            top: 20px;
            right: 40px;
            color: white;
            font-size: 40px;
            cursor: pointer;
            z-index: 201;
        }

        .info-card {
            background: #f9fafb;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #6b7280;
        }

        .info-value {
            color: #1f2937;
            font-weight: 500;
        }
    </style>
</head>
<body class="font-sans bg-gray-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-gradient-to-r from-red-500 to-red-600 rounded-lg flex items-center justify-center shadow-lg">
                        <i data-lucide="shield" class="w-7 h-7 text-white"></i>
                    </div>
                    <div>
                        <span class="text-2xl font-bold text-gray-800">Trust Gadai</span>
                        <p class="text-xs text-red-600 font-semibold">Admin Panel</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button class="flex items-center space-x-2 text-gray-700">
                            <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                                <i data-lucide="user-cog" class="w-5 h-5 text-red-600"></i>
                            </div>
                            <div class="hidden md:block text-left">
                                <p class="text-xs text-gray-500">Administrator</p>
                            </div>
                        </button>
                    </div>
                    <button class="md:hidden text-gray-600" id="mobile-menu-btn">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Dashboard Content -->
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div class="sidebar bg-red-800 text-white">
            <div class="p-4">
                <div class="flex items-center space-x-3 mb-8 pb-4 border-b border-red-700">
                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                        <i data-lucide="shield-check" class="w-6 h-6 text-red-600"></i>
                    </div>
                    <span class="text-xl font-bold">Admin Panel</span>
                </div>
                
                <nav class="space-y-2">
                    <a href="#" class="flex items-center space-x-3 p-3 rounded-lg bg-red-700 transition-colors" 
                    onclick="showSection('dashboard-content', this); return false;">
                        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="#" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-red-700 transition-colors" 
                    onclick="showSection('manage-barang', this); return false;">
                        <i data-lucide="package" class="w-5 h-5"></i>
                        <span>Kelola Barang</span>
                        <?php if ($stats['proses'] > 0): ?>
                        <span class="bg-yellow-500 text-white text-xs rounded-full px-2 py-1 ml-auto animate-pulse"><?php echo $stats['proses']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="#" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-red-700 transition-colors" 
                    onclick="logout(); return false;">
                        <i data-lucide="log-out" class="w-5 h-5"></i>
                        <span>Keluar</span>
                    </a>
                </nav>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content flex-1 p-6">
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Dashboard Admin</h1>
                        <p class="text-gray-500 mt-1">Kelola semua transaksi gadai</p>
                    </div>
                    <button class="md:hidden text-gray-600" id="sidebar-toggle">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
                
                <!-- Alert Messages -->
                <?php if (isset($success_message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                    <i data-lucide="check-circle" class="w-5 h-5 mr-2"></i>
                    <span><?php echo $success_message; ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                    <i data-lucide="alert-circle" class="w-5 h-5 mr-2"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Dashboard Content -->
                <div id="dashboard-content">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                        <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white p-6 rounded-xl shadow-lg fade-in transform hover:scale-105 transition-transform">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-blue-100 text-sm font-medium">Total Barang</p>
                                    <h3 class="text-3xl font-bold mt-2"><?php echo $stats['total']; ?></h3>
                                    <p class="text-blue-100 text-xs mt-1">Semua transaksi</p>
                                </div>
                                <div class="w-14 h-14 bg-blue-400 bg-opacity-30 rounded-lg flex items-center justify-center">
                                    <i data-lucide="package" class="w-8 h-8"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 text-white p-6 rounded-xl shadow-lg fade-in transform hover:scale-105 transition-transform">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-yellow-100 text-sm font-medium">Menunggu Proses</p>
                                    <h3 class="text-3xl font-bold mt-2"><?php echo $stats['proses']; ?></h3>
                                    <p class="text-yellow-100 text-xs mt-1">Perlu ditinjau</p>
                                </div>
                                <div class="w-14 h-14 bg-yellow-400 bg-opacity-30 rounded-lg flex items-center justify-center">
                                    <i data-lucide="clock" class="w-8 h-8"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-green-500 to-green-600 text-white p-6 rounded-xl shadow-lg fade-in transform hover:scale-105 transition-transform">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-green-100 text-sm font-medium">Gadai Aktif</p>
                                    <h3 class="text-3xl font-bold mt-2"><?php echo $stats['aktif']; ?></h3>
                                    <p class="text-green-100 text-xs mt-1">Rp <?php echo number_format($stats['total_nilai'], 0, ',', '.'); ?></p>
                                </div>
                                <div class="w-14 h-14 bg-green-400 bg-opacity-30 rounded-lg flex items-center justify-center">
                                    <i data-lucide="check-circle" class="w-8 h-8"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-purple-500 to-purple-600 text-white p-6 rounded-xl shadow-lg fade-in transform hover:scale-105 transition-transform">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-purple-100 text-sm font-medium">Selesai</p>
                                    <h3 class="text-3xl font-bold mt-2"><?php echo $stats['selesai']; ?></h3>
                                    <p class="text-purple-100 text-xs mt-1">Transaksi selesai</p>
                                </div>
                                <div class="w-14 h-14 bg-purple-400 bg-opacity-30 rounded-lg flex items-center justify-center">
                                    <i data-lucide="check-check" class="w-8 h-8"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-xl shadow-md slide-up">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-bold">Transaksi Terbaru</h2>
                            <button onclick="showSection('manage-barang', document.querySelector('[onclick*=manage-barang]'))" 
                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                                Lihat Semua
                                <i data-lucide="arrow-right" class="w-4 h-4 ml-1"></i>
                            </button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="p-3 text-left text-sm font-semibold text-gray-600">ID</th>
                                        <th class="p-3 text-left text-sm font-semibold text-gray-600">User</th>
                                        <th class="p-3 text-left text-sm font-semibold text-gray-600">Nama Barang</th>
                                        <th class="p-3 text-left text-sm font-semibold text-gray-600">Tanggal</th>
                                        <th class="p-3 text-left text-sm font-semibold text-gray-600">Nilai</th>
                                        <th class="p-3 text-left text-sm font-semibold text-gray-600">Status</th>
                                        <th class="p-3 text-left text-sm font-semibold text-gray-600">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $count = 0;
                                    mysqli_data_seek($result, 0);
                                    while ($row = mysqli_fetch_assoc($result)): 
                                        if ($count >= 5) break;
                                        $count++;
                                    ?>
                                    <tr class="border-b hover:bg-gray-50 transition-colors">
                                        <td class="p-3 font-mono text-sm text-gray-600">#<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                        <td class="p-3">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-2">
                                                    <i data-lucide="user" class="w-4 h-4 text-blue-600"></i>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-sm"><?php echo htmlspecialchars($row['user_nama']); ?></div>
                                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['user_email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="p-3 font-medium"><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                                        <td class="p-3">
                                            <div class="text-sm"><?php echo date('d M Y', strtotime($row['tanggal'])); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo date('H:i', strtotime($row['created_at'])); ?></div>
                                        </td>
                                        <td class="p-3 font-semibold text-green-600">Rp <?php echo number_format($row['nilai_barang'], 0, ',', '.'); ?></td>
                                        <td class="p-3">
                                            <?php 
                                            $status_color = 'gray';
                                            if ($row['status'] == 'Aktif') $status_color = 'green';
                                            elseif ($row['status'] == 'Proses') $status_color = 'yellow';
                                            elseif ($row['status'] == 'Selesai') $status_color = 'blue';
                                            ?>
                                            <span class="bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-800 px-3 py-1 rounded-full text-xs font-semibold">
                                                <?php echo $row['status']; ?>
                                            </span>
                                        </td>
                                        <td class="p-3">
                                            <button onclick="viewDetail(<?php echo $row['id']; ?>)" 
                                                    class="text-blue-600 hover:text-blue-800 p-2 hover:bg-blue-50 rounded-lg transition-colors" 
                                                    title="Detail">
                                                <i data-lucide="eye" class="w-5 h-5"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Manage Barang Section -->
                <div id="manage-barang" class="hidden">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-2xl font-bold">Kelola Barang Gadai</h2>
                            <p class="text-gray-500 mt-1">Kelola dan update status semua transaksi</p>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-md overflow-hidden slide-up">
                        <div class="border-b bg-gray-50">
                            <div class="flex overflow-x-auto">
                                <button class="filter-btn px-6 py-4 border-b-2 border-blue-600 text-blue-600 font-semibold text-sm" data-status="all">
                                    <div class="flex items-center space-x-2">
                                        <i data-lucide="list" class="w-4 h-4"></i>
                                        <span>Semua (<?php echo $stats['total']; ?>)</span>
                                    </div>
                                </button>
                                <button class="filter-btn px-6 py-4 border-b-2 border-transparent text-gray-600 hover:text-yellow-600 font-semibold text-sm transition-colors" data-status="Proses">
                                    <div class="flex items-center space-x-2">
                                        <i data-lucide="clock" class="w-4 h-4"></i>
                                        <span>Proses (<?php echo $stats['proses']; ?>)</span>
                                    </div>
                                </button>
                                <button class="filter-btn px-6 py-4 border-b-2 border-transparent text-gray-600 hover:text-green-600 font-semibold text-sm transition-colors" data-status="Aktif">
                                    <div class="flex items-center space-x-2">
                                        <i data-lucide="check-circle" class="w-4 h-4"></i>
                                        <span>Aktif (<?php echo $stats['aktif']; ?>)</span>
                                    </div>
                                </button>
                                <button class="filter-btn px-6 py-4 border-b-2 border-transparent text-gray-600 hover:text-purple-600 font-semibold text-sm transition-colors" data-status="Selesai">
                                    <div class="flex items-center space-x-2">
                                        <i data-lucide="check-check" class="w-4 h-4"></i>
                                        <span>Selesai (<?php echo $stats['selesai']; ?>)</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="p-3 text-left text-sm font-semibold text-gray-600">ID</th>
                                            <th class="p-3 text-left text-sm font-semibold text-gray-600">User</th>
                                            <th class="p-3 text-left text-sm font-semibold text-gray-600">Nama Barang</th>
                                            <th class="p-3 text-left text-sm font-semibold text-gray-600">Kategori</th>
                                            <th class="p-3 text-left text-sm font-semibold text-gray-600">Tanggal</th>
                                            <th class="p-3 text-left text-sm font-semibold text-gray-600">Nilai</th>
                                            <th class="p-3 text-left text-sm font-semibold text-gray-600">Lokasi</th>
                                            <th class="p-3 text-left text-sm font-semibold text-gray-600">Status</th>
                                            <th class="p-3 text-left text-sm font-semibold text-gray-600">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        mysqli_data_seek($result, 0);
                                        while ($row = mysqli_fetch_assoc($result)): 
                                        ?>
                                        <tr class="border-b item-row hover:bg-gray-50 transition-colors" data-status="<?php echo $row['status']; ?>">
                                            <td class="p-3 font-mono text-sm text-gray-600">#<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                            <td class="p-3">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center mr-2">
                                                        <i data-lucide="user" class="w-4 h-4 text-blue-600"></i>
                                                    </div>
                                                    <div>
                                                        <div class="font-medium text-sm"><?php echo htmlspecialchars($row['user_nama']); ?></div>
                                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['user_email']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-3 font-medium"><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                                            <td class="p-3">
                                                <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs font-medium">
                                                    <?php echo htmlspecialchars($row['kategori']); ?>
                                                </span>
                                            </td>
                                            <td class="p-3">
                                                <div class="text-sm"><?php echo date('d M Y', strtotime($row['tanggal'])); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo date('H:i', strtotime($row['created_at'])); ?></div>
                                            </td>
                                            <td class="p-3 font-semibold text-green-600">Rp <?php echo number_format($row['nilai_barang'], 0, ',', '.'); ?></td>
                                            <td class="p-3 text-sm"><?php echo htmlspecialchars($row['lokasi'] ?? '-'); ?></td>
                                            <td class="p-3">
                                                <?php 
                                                $status_color = 'gray';
                                                if ($row['status'] == 'Aktif') $status_color = 'green';
                                                elseif ($row['status'] == 'Proses') $status_color = 'yellow';
                                                elseif ($row['status'] == 'Selesai') $status_color = 'purple';
                                                ?>
                                                <span class="bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-800 px-3 py-1 rounded-full text-xs font-semibold">
                                                    <?php echo $row['status']; ?>
                                                </span>
                                            </td>
                                            <td class="p-3">
                                                <div class="flex space-x-2">
                                                    <button onclick="viewDetail(<?php echo $row['id']; ?>)" 
                                                            class="text-blue-600 hover:text-blue-800 p-2 hover:bg-blue-50 rounded-lg transition-colors" 
                                                            title="Lihat Detail">
                                                        <i data-lucide="eye" class="w-5 h-5"></i>
                                                    </button>
                                                    <?php if ($row['status'] == 'Proses'): ?>
                                                    <button onclick="confirmStatusChange(<?php echo $row['id']; ?>, 'Aktif', '<?php echo htmlspecialchars($row['nama_barang']); ?>')" 
                                                            class="text-green-600 hover:text-green-800 p-2 hover:bg-green-50 rounded-lg transition-colors" 
                                                            title="Aktifkan Gadai">
                                                        <i data-lucide="check-circle" class="w-5 h-5"></i>
                                                    </button>
                                                    <button onclick="confirmStatusChange(<?php echo $row['id']; ?>, 'Selesai', '<?php echo htmlspecialchars($row['nama_barang']); ?>')" 
                                                            class="text-red-600 hover:text-red-800 p-2 hover:bg-red-50 rounded-lg transition-colors" 
                                                            title="Tolak/Selesaikan">
                                                        <i data-lucide="x-circle" class="w-5 h-5"></i>
                                                    </button>
                                                    <?php elseif ($row['status'] == 'Aktif'): ?>
                                                    <button onclick="confirmStatusChange(<?php echo $row['id']; ?>, 'Selesai', '<?php echo htmlspecialchars($row['nama_barang']); ?>')" 
                                                            class="text-purple-600 hover:text-purple-800 p-2 hover:bg-purple-50 rounded-lg transition-colors" 
                                                            title="Tandai Selesai">
                                                        <i data-lucide="check-check" class="w-5 h-5"></i>
                                                    </button>
                                                    <button onclick="confirmStatusChange(<?php echo $row['id']; ?>, 'Proses', '<?php echo htmlspecialchars($row['nama_barang']); ?>')" 
                                                            class="text-yellow-600 hover:text-yellow-800 p-2 hover:bg-yellow-50 rounded-lg transition-colors" 
                                                            title="Kembalikan ke Proses">
                                                        <i data-lucide="arrow-left-circle" class="w-5 h-5"></i>
                                                    </button>
                                                    <?php elseif ($row['status'] == 'Selesai'): ?>
                                                    <button onclick="confirmStatusChange(<?php echo $row['id']; ?>, 'Aktif', '<?php echo htmlspecialchars($row['nama_barang']); ?>')" 
                                                            class="text-green-600 hover:text-green-800 p-2 hover:bg-green-50 rounded-lg transition-colors" 
                                                            title="Aktifkan Kembali">
                                                        <i data-lucide="rotate-ccw" class="w-5 h-5"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail Barang -->
    <div id="detailModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="text-2xl font-bold">Detail Barang Gadai</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Image Viewer -->
    <div id="imageViewer" class="image-viewer" onclick="closeImageViewer()">
        <span class="image-viewer-close">&times;</span>
        <img id="viewerImage" src="" alt="Full Image">
    </div>

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Toggle sidebar on mobile
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('overlay');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            });
        }
        
        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
            });
        }
        
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
        
        // Show different sections
        function showSection(sectionId, el) {
            document.getElementById('dashboard-content').classList.add('hidden');
            document.getElementById('manage-barang').classList.add('hidden');

            document.getElementById(sectionId).classList.remove('hidden');

            document.querySelectorAll('.sidebar nav a').forEach(link => {
                link.classList.remove('bg-red-700');
            });

            if (el) {
                el.classList.add('bg-red-700');
            }

            if (window.innerWidth < 768) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
            
            lucide.createIcons();
        }

        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const status = this.getAttribute('data-status');
                
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('border-blue-600', 'text-blue-600', 'border-yellow-600', 'text-yellow-600', 'border-green-600', 'text-green-600', 'border-purple-600', 'text-purple-600');
                    b.classList.add('border-transparent', 'text-gray-600');
                });
                
                if (status === 'Proses') {
                    this.classList.add('border-yellow-600', 'text-yellow-600');
                } else if (status === 'Aktif') {
                    this.classList.add('border-green-600', 'text-green-600');
                } else if (status === 'Selesai') {
                    this.classList.add('border-purple-600', 'text-purple-600');
                } else {
                    this.classList.add('border-blue-600', 'text-blue-600');
                }
                
                this.classList.remove('border-transparent', 'text-gray-600');
                
                document.querySelectorAll('.item-row').forEach(row => {
                    if (status === 'all' || row.getAttribute('data-status') === status) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
                
                lucide.createIcons();
            });
        });

        // View detail function
        function viewDetail(id) {
            fetch('get_detail.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    
                    // Build photos HTML
                    let photosHtml = '<div class="photo-grid">';
                    let hasPhotos = false;
                    
                    // Check foto_barang first
                    if (data.foto_barang) {
                        photosHtml += `
                            <div class="photo-item" onclick="viewImage('../uploads/${data.foto_barang}')">
                                <img src="../uploads/${data.foto_barang}" alt="Foto Barang">
                                <div class="photo-label">Foto Utama</div>
                            </div>
                        `;
                        hasPhotos = true;
                    }
                    
                    // Check foto1-foto5
                    for (let i = 1; i <= 5; i++) {
                        const foto = data['foto' + i];
                        if (foto && foto != data.foto_barang) {
                            photosHtml += `
                                <div class="photo-item" onclick="viewImage('../uploads/${foto}')">
                                    <img src="../uploads/${foto}" alt="Foto ${i}">
                                    <div class="photo-label">Foto ${i}</div>
                                </div>
                            `;
                            hasPhotos = true;
                        }
                    }
                    
                    if (!hasPhotos) {
                        photosHtml += '<p class="text-gray-500 text-center col-span-full">Tidak ada foto</p>';
                    }
                    
                    photosHtml += '</div>';
                    
                    // Determine status color
                    let statusColor = 'gray';
                    if (data.status == 'Aktif') statusColor = 'green';
                    else if (data.status == 'Proses') statusColor = 'yellow';
                    else if (data.status == 'Selesai') statusColor = 'purple';
                    
                    const modalContent = `
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <h3 class="font-bold text-lg mb-3 flex items-center">
                                    <i data-lucide="package" class="w-5 h-5 mr-2 text-blue-600"></i>
                                    Informasi Barang
                                </h3>
                                <div class="info-card">
                                    <div class="info-row">
                                        <span class="info-label">ID Transaksi:</span>
                                        <span class="info-value font-mono">#${String(data.id).padStart(4, '0')}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Nama Barang:</span>
                                        <span class="info-value">${data.nama_barang}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Kategori:</span>
                                        <span class="info-value">
                                            <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs font-medium">${data.kategori}</span>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Nilai Barang:</span>
                                        <span class="info-value font-semibold text-green-600 text-lg">Rp ${parseInt(data.nilai_barang).toLocaleString('id-ID')}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Lokasi:</span>
                                        <span class="info-value">${data.lokasi || '-'}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Status:</span>
                                        <span class="info-value">
                                            <span class="bg-${statusColor}-100 text-${statusColor}-800 px-3 py-1 rounded-full text-xs font-semibold">${data.status}</span>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Tanggal Upload:</span>
                                        <span class="info-value">${new Date(data.tanggal).toLocaleDateString('id-ID', {day: 'numeric', month: 'long', year: 'numeric'})}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Waktu:</span>
                                        <span class="info-value">${new Date(data.created_at).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'})}</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h3 class="font-bold text-lg mb-3 flex items-center">
                                    <i data-lucide="user" class="w-5 h-5 mr-2 text-blue-600"></i>
                                    Informasi Pemilik
                                </h3>
                                <div class="info-card">
                                    <div class="info-row">
                                        <span class="info-label">User ID:</span>
                                        <span class="info-value font-mono">#${data.user_id}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Nama:</span>
                                        <span class="info-value font-semibold">${data.user_nama}</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">Email:</span>
                                        <span class="info-value">${data.user_email}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <h3 class="font-bold text-lg mb-3 flex items-center">
                                <i data-lucide="file-text" class="w-5 h-5 mr-2 text-blue-600"></i>
                                Deskripsi Barang
                            </h3>
                            <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <p class="text-gray-700 leading-relaxed">${data.deskripsi}</p>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <h3 class="font-bold text-lg mb-3 flex items-center">
                                <i data-lucide="image" class="w-5 h-5 mr-2 text-blue-600"></i>
                                Foto Barang
                            </h3>
                            ${photosHtml}
                        </div>
                        
                        <div class="mt-6 flex flex-wrap gap-3">
                            ${data.status == 'Proses' ? `
                                <button onclick="updateStatus(${data.id}, 'Aktif', '${data.nama_barang}')" class="flex-1 min-w-[200px] bg-gradient-to-r from-green-600 to-green-700 text-white py-3 px-6 rounded-lg font-semibold hover:from-green-700 hover:to-green-800 transition-all transform hover:scale-105 flex items-center justify-center">
                                    <i data-lucide="check-circle" class="w-5 h-5 mr-2"></i>
                                    Setujui & Aktifkan
                                </button>
                                <button onclick="updateStatus(${data.id}, 'Selesai', '${data.nama_barang}')" class="bg-red-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-red-700 transition-all flex items-center justify-center">
                                    <i data-lucide="x-circle" class="w-5 h-5 mr-2"></i>
                                    Tolak
                                </button>
                            ` : ''}
                            ${data.status == 'Aktif' ? `
                                <button onclick="updateStatus(${data.id}, 'Selesai', '${data.nama_barang}')" class="flex-1 min-w-[200px] bg-gradient-to-r from-purple-600 to-purple-700 text-white py-3 px-6 rounded-lg font-semibold hover:from-purple-700 hover:to-purple-800 transition-all transform hover:scale-105 flex items-center justify-center">
                                    <i data-lucide="check-check" class="w-5 h-5 mr-2"></i>
                                    Tandai Selesai
                                </button>
                                <button onclick="updateStatus(${data.id}, 'Proses', '${data.nama_barang}')" class="bg-yellow-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-yellow-700 transition-all flex items-center justify-center">
                                    <i data-lucide="arrow-left-circle" class="w-5 h-5 mr-2"></i>
                                    Kembalikan
                                </button>
                            ` : ''}
                            ${data.status == 'Selesai' ? `
                                <button onclick="updateStatus(${data.id}, 'Aktif', '${data.nama_barang}')" class="flex-1 min-w-[200px] bg-gradient-to-r from-green-600 to-green-700 text-white py-3 px-6 rounded-lg font-semibold hover:from-green-700 hover:to-green-800 transition-all transform hover:scale-105 flex items-center justify-center">
                                    <i data-lucide="rotate-ccw" class="w-5 h-5 mr-2"></i>
                                    Aktifkan Kembali
                                </button>
                            ` : ''}
                            <button onclick="closeModal()" class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-100 transition-all">
                                Tutup
                            </button>
                        </div>
                    `;
                    
                    document.getElementById('modalContent').innerHTML = modalContent;
                    document.getElementById('detailModal').style.display = 'block';
                    lucide.createIcons();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Gagal memuat detail barang');
                });
        }

        // Update status function with confirmation
        function confirmStatusChange(id, status, namaBarang) {
            let message = '';
            let icon = '';
            
            if (status === 'Aktif') {
                message = `Setujui dan aktifkan gadai untuk:\n"${namaBarang}"?\n\nBarang akan disetujui dan status berubah menjadi AKTIF.`;
                icon = '✅';
            } else if (status === 'Selesai') {
                message = `Selesaikan transaksi gadai untuk:\n"${namaBarang}"?\n\nTransaksi akan ditandai sebagai SELESAI.`;
                icon = '🏁';
            } else if (status === 'Proses') {
                message = `Kembalikan ke status Proses untuk:\n"${namaBarang}"?\n\nStatus akan dikembalikan ke PROSES untuk review ulang.`;
                icon = '🔄';
            }
            
            if (confirm(icon + ' ' + message)) {
                window.location.href = `?update_status=1&id=${id}&status=${status}`;
            }
        }

        // Update status function (dipanggil dari modal)
        function updateStatus(id, status, namaBarang) {
            confirmStatusChange(id, status, namaBarang);
            closeModal();
        }

        // View image in full screen
        function viewImage(src) {
            event.stopPropagation();
            document.getElementById('viewerImage').src = src;
            document.getElementById('imageViewer').style.display = 'flex';
        }

        // Close modal
        function closeModal() {
            document.getElementById('detailModal').style.display = 'none';
        }

        // Close image viewer
        function closeImageViewer() {
            document.getElementById('imageViewer').style.display = 'none';
        }
        
        // Logout function
        function logout() {
            if (confirm('Apakah Anda yakin ingin keluar dari Admin Panel?')) {
                window.location.href = 'logout.php';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('detailModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Auto-close alert messages after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.bg-green-100, .bg-red-100');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>