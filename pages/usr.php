<?php
include "db.php";
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'user') {
    header("Location: ../index.html");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle upload barang
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_barang'])) {
    $nama_barang = mysqli_real_escape_string($conn, $_POST['nama_barang']);
    $kategori = mysqli_real_escape_string($conn, $_POST['kategori']);
    $deskripsi = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    $nilai_barang = mysqli_real_escape_string($conn, $_POST['nilai_barang']);
    
    // Handle file upload
    $foto_barang = '';
    if (isset($_FILES['foto_barang']) && $_FILES['foto_barang']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['foto_barang']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        $filesize = $_FILES['foto_barang']['size'];
        
        if (in_array(strtolower($filetype), $allowed) && $filesize <= 5242880) { // 5MB
            $new_filename = uniqid() . '.' . $filetype;
            $upload_path = '../uploads/' . $new_filename;
            
            // Create uploads directory if not exists
            if (!file_exists('../uploads/')) {
                mkdir('../uploads/', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['foto_barang']['tmp_name'], $upload_path)) {
                $foto_barang = $new_filename;
            }
        }
    }
    
    // Insert ke database dengan status default "Proses"
    $tanggal = date('Y-m-d');
    $status = 'Proses'; // Status default saat pertama kali input
    
    $query = "INSERT INTO gadai (user_id, nama_barang, kategori, deskripsi, nilai_barang, foto_barang, tanggal, status) 
              VALUES ('$user_id', '$nama_barang', '$kategori', '$deskripsi', '$nilai_barang', '$foto_barang', '$tanggal', '$status')";
    
    if (mysqli_query($conn, $query)) {
        $success_message = "Barang berhasil diajukan untuk digadaikan dengan status 'Proses'!";
    } else {
        $error_message = "Gagal mengajukan gadai: " . mysqli_error($conn);
    }
}

// Ambil data barang user
$query_barang = "SELECT * FROM gadai WHERE user_id = '$user_id' ORDER BY tanggal DESC";
$result_barang = mysqli_query($conn, $query_barang);

// Hitung statistik per status
$total_barang = mysqli_num_rows($result_barang);
$total_proses = 0;
$total_aktif = 0;
$total_selesai = 0;
$total_nilai = 0;
$pesan_baru = 0;

while ($row = mysqli_fetch_assoc($result_barang)) {
    if ($row['status'] == 'Proses') {
        $total_proses++;
    } elseif ($row['status'] == 'Aktif') {
        $total_aktif++;
        $total_nilai += $row['nilai_barang'];
    } elseif ($row['status'] == 'Selesai') {
        $total_selesai++;
    }
}

// Reset pointer result
mysqli_data_seek($result_barang, 0);

// Ambil 3 barang terbaru untuk dashboard
$query_recent = "SELECT * FROM gadai WHERE user_id = '$user_id' ORDER BY tanggal DESC LIMIT 3";
$result_recent = mysqli_query($conn, $query_recent);

// Simpan status barang untuk tracking perubahan
$current_statuses = [];
mysqli_data_seek($result_barang, 0);
while ($row = mysqli_fetch_assoc($result_barang)) {
    $current_statuses[$row['id']] = $row['status'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard User - Trust Gadai</title>
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
        
        .chat-container {
            height: 400px;
            overflow-y: auto;
        }
        
        .chat-message {
            max-width: 80%;
        }
        
        .upload-area {
            border: 2px dashed #cbd5e1;
            transition: all 0.3s ease;
        }
    
        .upload-area:hover {
            border-color: #3b82f6;
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
        
        .hover-scale {
            transition: transform 0.3s ease;
        }
        
        .hover-scale:hover {
            transform: scale(1.05);
        }

        .status-badge {
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        .reload-notification {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 60;
            animation: slideInRight 0.5s ease-out;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body class="font-sans bg-gray-100">
    <!-- Reload Notification (Hidden by default) -->
    <div id="reload-notification" class="reload-notification hidden">
        <div class="bg-blue-600 text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3">
            <i data-lucide="refresh-cw" class="w-5 h-5 animate-spin"></i>
            <span>Status barang berubah! Memuat ulang...</span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 bg-gradient-to-r from-white-500 to-cyan-500 rounded-lg flex items-center justify-center shadow-lg">
                        <img src="../assets/img/logo_fix.png" alt="Trust Gadai Logo" class="w-20 h-15">
                    </div>
                    <span class="text-2xl font-bold text-gray-800">Trust Gadai</span>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="relative">
                        <button class="flex items-center space-x-2 text-gray-700">
                            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                <i data-lucide="user" class="w-5 h-5 text-blue-600"></i>
                            </div>
                            <span><?php echo $_SESSION['nama']; ?></span>
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
        <div class="sidebar bg-blue-800 text-white">
            <div class="p-4">
                <div class="flex items-center space-x-3 mb-8">
                    <div class="w-10 h-10 bg-white rounded-lg flex items-center justify-center">
                        <img src="../assets/img/logo_fix.png" alt="Trust Gadai Logo" class="w-20 h-15">
                    </div>
                    <span class="text-xl font-bold">Trust Gadai</span>
                </div>
                
                <nav class="space-y-2">
                    <a href="#" class="flex items-center space-x-3 p-3 rounded-lg bg-blue-700 transition-colors" 
                    onclick="showSection('dashboard-content', this); return false;">
                        <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="#" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-700 transition-colors" 
                    onclick="showSection('upload-barang', this); return false;">
                        <i data-lucide="upload" class="w-5 h-5"></i>
                        <span>Upload Barang</span>
                    </a>
                    <a href="#" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-700 transition-colors" 
                    onclick="showSection('cek-barang', this); return false;">
                        <i data-lucide="package" class="w-5 h-5"></i>
                        <span>Cek Barang</span>
                        <?php if ($total_proses > 0): ?>
                        <span class="bg-yellow-500 text-white text-xs rounded-full px-2 py-1 ml-auto"><?php echo $total_proses; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="#" class="flex items-center space-x-3 p-3 rounded-lg hover:bg-blue-700 transition-colors" 
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
                    <h1 class="text-2xl font-bold text-gray-800">Dashboard User</h1>
                    <button class="md:hidden text-gray-600" id="sidebar-toggle">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
                
                <!-- Alert Messages -->
                <?php if (isset($success_message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex items-center">
                    <i data-lucide="check-circle" class="w-5 h-5 mr-2"></i>
                    <span><?php echo $success_message; ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex items-center">
                    <i data-lucide="alert-circle" class="w-5 h-5 mr-2"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
                <?php endif; ?>
                
                <!-- Dashboard Content -->
                <div id="dashboard-content">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <div class="bg-blue-50 p-5 rounded-xl fade-in">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-blue-600">Total Barang</p>
                                    <h3 class="text-2xl font-bold mt-1"><?php echo $total_barang; ?></h3>
                                </div>
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                    <i data-lucide="package" class="w-6 h-6 text-blue-600"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-yellow-50 p-5 rounded-xl fade-in">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-yellow-600">Proses</p>
                                    <h3 class="text-2xl font-bold mt-1"><?php echo $total_proses; ?></h3>
                                </div>
                                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                                    <i data-lucide="clock" class="w-6 h-6 text-yellow-600"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-green-50 p-5 rounded-xl fade-in">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-green-600">Aktif</p>
                                    <h3 class="text-2xl font-bold mt-1"><?php echo $total_aktif; ?></h3>
                                    <p class="text-sm text-gray-600">Rp <?php echo number_format($total_nilai, 0, ',', '.'); ?></p>
                                </div>
                                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                    <i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-purple-50 p-5 rounded-xl fade-in">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-purple-600">Selesai</p>
                                    <h3 class="text-2xl font-bold mt-1"><?php echo $total_selesai; ?></h3>
                                </div>
                                <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                    <i data-lucide="check-check" class="w-6 h-6 text-purple-600"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-xl shadow-md slide-up">
                        <h2 class="text-xl font-bold mb-4">Barang Terbaru</h2>
                        <div class="overflow-x-auto">
                            <?php if (mysqli_num_rows($result_recent) > 0): ?>
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="p-3 text-left">Nama Barang</th>
                                        <th class="p-3 text-left">Tanggal</th>
                                        <th class="p-3 text-left">Nilai</th>
                                        <th class="p-3 text-left">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = mysqli_fetch_assoc($result_recent)): ?>
                                    <tr class="border-b hover:bg-gray-50 transition-colors">
                                        <td class="p-3"><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                                        <td class="p-3"><?php echo date('d M Y', strtotime($row['tanggal'])); ?></td>
                                        <td class="p-3">Rp <?php echo number_format($row['nilai_barang'], 0, ',', '.'); ?></td>
                                        <td class="p-3">
                                            <?php 
                                            $status_color = 'gray';
                                            if ($row['status'] == 'Aktif') $status_color = 'green';
                                            elseif ($row['status'] == 'Proses') $status_color = 'yellow';
                                            elseif ($row['status'] == 'Selesai') $status_color = 'blue';
                                            ?>
                                            <span class="status-badge bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-800 px-3 py-1 rounded-full text-sm font-medium">
                                                <?php echo $row['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="text-center py-12">
                                <i data-lucide="package-x" class="w-16 h-16 text-gray-300 mx-auto mb-3"></i>
                                <p class="text-gray-500 text-lg">Anda belum melakukan gadai apapun</p>
                                <button onclick="showSection('upload-barang', document.querySelector('[onclick*=upload-barang]'))" 
                                        class="mt-4 bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                    Upload Barang Sekarang
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Upload Barang Section (Initially Hidden) -->
                <div id="upload-barang" class="hidden">
                    <h2 class="text-2xl font-bold mb-6">Upload Barang Gadai</h2>
                    
                    <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
                        <div class="flex items-center">
                            <i data-lucide="info" class="w-5 h-5 text-blue-600 mr-3"></i>
                            <p class="text-blue-800">
                                <strong>Informasi:</strong> Setelah Anda mengajukan barang, status akan menjadi "Proses" dan akan ditinjau oleh admin kami.
                            </p>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 p-6 rounded-xl mb-6 slide-up">
                        <form method="POST" enctype="multipart/form-data" class="space-y-6" onsubmit="return validateForm()">
                            <input type="hidden" name="upload_barang" value="1">
                            
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Nama Barang *</label>
                                <input type="text" name="nama_barang" id="nama_barang" required 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" 
                                       placeholder="Contoh: iPhone 13 Pro 128GB">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Kategori Barang *</label>
                                <select name="kategori" id="kategori" required 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                                    <option value="">Pilih kategori</option>
                                    <option value="Elektronik">Elektronik</option>
                                    <option value="Perhiasan">Perhiasan</option>
                                    <option value="Kendaraan">Kendaraan</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Deskripsi Barang *</label>
                                <textarea name="deskripsi" id="deskripsi" required 
                                          class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" 
                                          rows="4" 
                                          placeholder="Deskripsikan kondisi, spesifikasi, dan kelengkapan barang secara detail"></textarea>
                                <p class="text-sm text-gray-500 mt-1">Semakin detail deskripsi, semakin cepat proses verifikasi</p>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Perkiraan Nilai Barang (Rp) *</label>
                                <input type="number" name="nilai_barang" id="nilai_barang" required min="100000"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" 
                                       placeholder="Masukkan perkiraan nilai (minimal Rp 100.000)">
                                <p class="text-sm text-gray-500 mt-1">Nilai ini akan diverifikasi oleh tim kami</p>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 font-medium mb-2">Upload Foto Barang</label>
                                <input type="file" name="foto_barang" id="foto_barang" accept="image/jpeg,image/jpg,image/png" 
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                                       onchange="previewImage(event)">
                                <p class="text-sm text-gray-500 mt-2">Format: JPG, PNG (Maks. 5MB)</p>
                                <div id="image-preview" class="mt-4 hidden">
                                    <img id="preview" class="max-w-xs rounded-lg shadow-md" alt="Preview">
                                </div>
                            </div>
                            
                            <div class="flex space-x-4">
                                <button type="submit" 
                                        class="flex-1 bg-gradient-to-r from-blue-600 to-cyan-600 text-white py-3 rounded-lg font-semibold hover:from-blue-700 hover:to-cyan-700 transition-all transform hover:scale-105 flex items-center justify-center">
                                    <i data-lucide="upload" class="w-5 h-5 mr-2"></i>
                                    Ajukan Gadai
                                </button>
                                <button type="reset" 
                                        class="px-6 py-3 border-2 border-gray-300 text-gray-700 rounded-lg font-semibold hover:bg-gray-100 transition-all">
                                    Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Cek Barang Section (Initially Hidden) -->
                <div id="cek-barang" class="hidden">
                    <h2 class="text-2xl font-bold mb-6">Cek Status Barang</h2>
                    
                    <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6 slide-up">
                        <div class="border-b">
                            <div class="flex overflow-x-auto">
                                <button class="filter-btn px-6 py-3 border-b-2 border-blue-600 text-blue-600 font-medium" data-status="all">
                                    Semua (<?php echo $total_barang; ?>)
                                </button>
                                <button class="filter-btn px-6 py-3 border-b-2 border-transparent text-gray-600 hover:text-yellow-600 font-medium" data-status="Proses">
                                    Proses (<?php echo $total_proses; ?>)
                                </button>
                                <button class="filter-btn px-6 py-3 border-b-2 border-transparent text-gray-600 hover:text-green-600 font-medium" data-status="Aktif">
                                    Aktif (<?php echo $total_aktif; ?>)
                                </button>
                                <button class="filter-btn px-6 py-3 border-b-2 border-transparent text-gray-600 hover:text-blue-600 font-medium" data-status="Selesai">
                                    Selesai (<?php echo $total_selesai; ?>)
                                </button>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <?php 
                            mysqli_data_seek($result_barang, 0);
                            if (mysqli_num_rows($result_barang) > 0): 
                            ?>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="p-3 text-left">Foto</th>
                                            <th class="p-3 text-left">Nama Barang</th>
                                            <th class="p-3 text-left">Kategori</th>
                                            <th class="p-3 text-left">Tanggal</th>
                                            <th class="p-3 text-left">Nilai</th>
                                            <th class="p-3 text-left">Status</th>
                                            <th class="p-3 text-left">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = mysqli_fetch_assoc($result_barang)): ?>
                                        <tr class="border-b item-row hover:bg-gray-50 transition-colors" data-status="<?php echo $row['status']; ?>" data-id="<?php echo $row['id']; ?>">
                                            <td class="p-3">
                                                <?php if (!empty($row['foto_barang'])): ?>
                                                <img src="../uploads/<?php echo $row['foto_barang']; ?>" alt="Foto Barang" class="w-16 h-16 object-cover rounded-lg shadow-sm">
                                                <?php else: ?>
                                                <div class="w-16 h-16 bg-gray-200 rounded-lg flex items-center justify-center">
                                                    <i data-lucide="image" class="w-8 h-8 text-gray-400"></i>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-3 font-medium"><?php echo htmlspecialchars($row['nama_barang']); ?></td>
                                            <td class="p-3"><?php echo htmlspecialchars($row['kategori']); ?></td>
                                            <td class="p-3"><?php echo date('d M Y', strtotime($row['tanggal'])); ?></td>
                                            <td class="p-3 font-semibold">Rp <?php echo number_format($row['nilai_barang'], 0, ',', '.'); ?></td>
                                            <td class="p-3">
                                                <?php 
                                                $status_color = 'gray';
                                                if ($row['status'] == 'Aktif') $status_color = 'green';
                                                elseif ($row['status'] == 'Proses') $status_color = 'yellow';
                                                elseif ($row['status'] == 'Selesai') $status_color = 'blue';
                                                ?>
                                                <span class="status-badge bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-800 px-3 py-1 rounded-full text-sm font-medium">
                                                    <?php echo $row['status']; ?>
                                                </span>
                                            </td>
                                            <td class="p-3">
                                                <button onclick="viewDetail(<?php echo $row['id']; ?>)" class="text-blue-600 hover:text-blue-800 transition-colors">
                                                    <i data-lucide="eye" class="w-5 h-5"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="text-center py-12">
                                <i data-lucide="package-x" class="w-16 h-16 text-gray-300 mx-auto mb-3"></i>
                                <p class="text-gray-500 text-lg">Anda belum melakukan gadai apapun</p>
                                <button onclick="showSection('upload-barang', document.querySelector('[onclick*=upload-barang]'))" 
                                        class="mt-4 bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                    Upload Barang Sekarang
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay"></div>

    <script>
        // Store current statuses for change detection
        const currentStatuses = <?php echo json_encode($current_statuses); ?>;
        
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Toggle sidebar on mobile
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('overlay');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        });
        
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        });
        
        // Check for status changes every 5 seconds
        setInterval(checkStatusChanges, 5000);
        
        function checkStatusChanges() {
            fetch('check_status.php')
                .then(response => response.json())
                .then(data => {
                    let hasChanges = false;
                    
                    // Compare current statuses with new statuses
                    for (let id in data) {
                        if (currentStatuses[id] && currentStatuses[id] !== data[id]) {
                            hasChanges = true;
                            break;
                        }
                    }
                    
                    if (hasChanges) {
                        showReloadNotification();
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    }
                })
                .catch(error => console.log('Status check error:', error));
        }
        
        function showReloadNotification() {
            const notification = document.getElementById('reload-notification');
            notification.classList.remove('hidden');
            lucide.createIcons();
        }
        
        // Show different sections
        function showSection(sectionId, el) {
            // Hide all sections
            document.getElementById('dashboard-content').classList.add('hidden');
            document.getElementById('upload-barang').classList.add('hidden');
            document.getElementById('cek-barang').classList.add('hidden');

            // Show selected section
            document.getElementById(sectionId).classList.remove('hidden');

            // Remove active state from all nav links
            document.querySelectorAll('.sidebar nav a').forEach(link => {
                link.classList.remove('bg-blue-700');
            });

            // Add active state to current link
            if (el) {
                el.classList.add('bg-blue-700');
            }

            // Close sidebar on mobile after selection
            if (window.innerWidth < 768) {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
            
            // Reinitialize icons
            lucide.createIcons();
        }

        // Filter functionality
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const status = this.getAttribute('data-status');
                
                // Update active button
                document.querySelectorAll('.filter-btn').forEach(b => {
                    b.classList.remove('border-blue-600', 'text-blue-600', 'border-yellow-600', 'text-yellow-600', 'border-green-600', 'text-green-600');
                    b.classList.add('border-transparent', 'text-gray-600');
                });
                
                // Set color based on status
                if (status === 'Proses') {
                    this.classList.add('border-yellow-600', 'text-yellow-600');
                } else if (status === 'Aktif') {
                    this.classList.add('border-green-600', 'text-green-600');
                } else if (status === 'Selesai') {
                    this.classList.add('border-blue-600', 'text-blue-600');
                } else {
                    this.classList.add('border-blue-600', 'text-blue-600');
                }
                
                this.classList.remove('border-transparent', 'text-gray-600');
                
                // Filter items
                document.querySelectorAll('.item-row').forEach(row => {
                    if (status === 'all' || row.getAttribute('data-status') === status) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });

        // View detail function
        function viewDetail(id) {
            alert('Menampilkan detail barang ID: ' + id + '\n\nFitur detail akan segera tersedia.');
            // Implement detail view logic here
        }
        
        // Form validation
        function validateForm() {
            const nama = document.getElementById('nama_barang').value;
            const kategori = document.getElementById('kategori').value;
            const deskripsi = document.getElementById('deskripsi').value;
            const nilai = document.getElementById('nilai_barang').value;
            
            if (!nama || !kategori || !deskripsi || !nilai) {
                alert('Mohon lengkapi semua field yang wajib diisi (*)');
                return false;
            }
            
            if (parseInt(nilai) < 100000) {
                alert('Nilai barang minimal Rp 100.000');
                return false;
            }
            
            if (confirm('Apakah Anda yakin ingin mengajukan barang ini untuk digadaikan?\n\nStatus awal akan menjadi "Proses" dan akan ditinjau oleh admin.')) {
                return true;
            }
            
            return false;
        }
        
        // Preview image before upload
        function previewImage(event) {
            const file = event.target.files[0];
            if (file) {
                // Check file size (5MB = 5242880 bytes)
                if (file.size > 5242880) {
                    alert('Ukuran file terlalu besar! Maksimal 5MB');
                    event.target.value = '';
                    return;
                }
                
                // Check file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!validTypes.includes(file.type)) {
                    alert('Format file tidak valid! Gunakan JPG atau PNG');
                    event.target.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview').src = e.target.result;
                    document.getElementById('image-preview').classList.remove('hidden');
                }
                reader.readAsDataURL(file);
            }
        }
        
        // Logout function
        function logout() {
            if (confirm('Apakah Anda yakin ingin keluar?')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>