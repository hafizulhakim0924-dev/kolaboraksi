<?php
session_start();

// Koneksi database
$host = 'localhost';
$dbname = 'rank3598_apk';
$username = 'rank3598_apk';
$password = 'Hakim123!';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

// Proses Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $admin_user = trim($_POST['username']);
    $admin_pass = trim($_POST['password']);
    
    if ($admin_user === 'admin' && $admin_pass === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $admin_user;
        header('Location: addkampanye.php');
        exit;
    } else {
        $login_error = "Username atau password salah!";
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: addkampanye.php');
    exit;
}

// Cek apakah sudah login
if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login Admin - Kelola Kampanye</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            
            .login-container {
                background: white;
                padding: 40px;
                border-radius: 20px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                max-width: 400px;
                width: 100%;
            }
            
            .login-header {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .login-header i {
                font-size: 60px;
                color: #667eea;
                margin-bottom: 15px;
            }
            
            .login-header h2 {
                color: #333;
                margin-bottom: 10px;
            }
            
            .login-header p {
                color: #666;
                font-size: 14px;
            }
            
            .form-group {
                margin-bottom: 20px;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 8px;
                color: #555;
                font-weight: 600;
            }
            
            .form-group input {
                width: 100%;
                padding: 12px;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                font-size: 14px;
                transition: border-color 0.3s;
            }
            
            .form-group input:focus {
                outline: none;
                border-color: #667eea;
            }
            
            .btn-login {
                width: 100%;
                padding: 12px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.3s;
            }
            
            .btn-login:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            }
            
            .error {
                background: #f8d7da;
                color: #721c24;
                padding: 10px;
                border-radius: 8px;
                margin-bottom: 20px;
                text-align: center;
                font-size: 14px;
            }
            
            .back-home {
                text-align: center;
                margin-top: 20px;
            }
            
            .back-home a {
                color: #667eea;
                text-decoration: none;
                font-size: 14px;
            }
            
            .back-home a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <i class="fas fa-user-shield"></i>
                <h2>Login Admin</h2>
                <p>Kelola Kampanye Donasi</p>
            </div>
            
            <?php if (isset($login_error)): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $login_error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Username</label>
                    <input type="text" name="username" placeholder="Masukkan username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password</label>
                    <input type="password" name="password" placeholder="Masukkan password" required>
                </div>
                
                <button type="submit" name="login" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>
            
            <div class="back-home">
                <a href="dashboarduser.php"><i class="fas fa-arrow-left"></i> Kembali ke Beranda</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$message = '';

// Proses Tambah Kampanye
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah_kampanye'])) {
    $csr_id = (int)$_POST['csr_id'];
    $judul = trim($_POST['judul']);
    $target_nominal = $_POST['target_nominal'];
    $cerita = trim($_POST['cerita']);
    $waktu_berakhir = $_POST['waktu_berakhir'];
    $status = $_POST['status'];
    
    // Upload foto
    $foto = '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['foto']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = uniqid() . '.' . $ext;
            $upload_path = 'uploads/' . $new_filename;
            
            if (!file_exists('uploads')) {
                mkdir('uploads', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
                $foto = $upload_path;
            }
        }
    }
    
    if (empty($judul) || empty($target_nominal) || empty($cerita) || empty($waktu_berakhir)) {
        $message = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> Semua field harus diisi!</div>';
    } else {
        $stmt = $pdo->prepare("INSERT INTO kampanye_donasi (csr_id, judul, foto, target_nominal, cerita, status, waktu_berakhir, terkumpul) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        if ($stmt->execute([$csr_id, $judul, $foto, $target_nominal, $cerita, $status, $waktu_berakhir])) {
            $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Kampanye berhasil ditambahkan!</div>';
        } else {
            $message = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> Gagal menambahkan kampanye!</div>';
        }
    }
}

// Proses Edit Status Kampanye
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_status'])) {
    $kampanye_id = (int)$_POST['kampanye_id'];
    $new_status = $_POST['new_status'];
    
    $stmt = $pdo->prepare("UPDATE kampanye_donasi SET status = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $kampanye_id])) {
        $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Status kampanye berhasil diubah!</div>';
    } else {
        $message = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> Gagal mengubah status kampanye!</div>';
    }
}

// Proses Hapus Kampanye
if (isset($_GET['delete'])) {
    $kampanye_id = (int)$_GET['delete'];
    
    // Ambil foto untuk dihapus
    $stmt = $pdo->prepare("SELECT foto FROM kampanye_donasi WHERE id = ?");
    $stmt->execute([$kampanye_id]);
    $kampanye = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Hapus kampanye
    $stmt = $pdo->prepare("DELETE FROM kampanye_donasi WHERE id = ?");
    if ($stmt->execute([$kampanye_id])) {
        // Hapus foto jika ada
        if ($kampanye && $kampanye['foto'] && file_exists($kampanye['foto'])) {
            unlink($kampanye['foto']);
        }
        $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Kampanye berhasil dihapus!</div>';
    } else {
        $message = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> Gagal menghapus kampanye!</div>';
    }
}

// Ambil semua CSR
$stmt_csr = $pdo->query("SELECT * FROM csr_donasi ORDER BY nama ASC");
$csr_list = $stmt_csr->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua kampanye
$stmt_kampanye = $pdo->query("SELECT k.*, c.nama as csr_nama FROM kampanye_donasi k JOIN csr_donasi c ON k.csr_id = c.id ORDER BY k.created_at DESC");
$kampanye_list = $stmt_kampanye->fetchAll(PDO::FETCH_ASSOC);

// Statistik
$stmt_stats = $pdo->query("SELECT 
    COUNT(*) as total_kampanye,
    SUM(terkumpul) as total_terkumpul,
    SUM(CASE WHEN status = 'terbaru' THEN 1 ELSE 0 END) as total_terbaru,
    SUM(CASE WHEN status = 'event' THEN 1 ELSE 0 END) as total_event,
    SUM(CASE WHEN status = 'favorit' THEN 1 ELSE 0 END) as total_favorit
    FROM kampanye_donasi");
$stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Kelola Kampanye</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            line-height: 1.6;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar h2 {
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .navbar-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .navbar a {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .navbar a:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
        }
        
        .stat-card i {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .stat-card.blue { color: #667eea; }
        .stat-card.green { color: #4caf50; }
        .stat-card.orange { color: #ff9800; }
        .stat-card.purple { color: #9c27b0; }
        .stat-card.red { color: #f44336; }
        
        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            font-size: 14px;
            color: #888;
        }
        
        .section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        
        .section-header h3 {
            color: #333;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        table thead {
            background: #f8f9fa;
        }
        
        table th,
        table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        table th {
            font-weight: 600;
            color: #333;
        }
        
        table td {
            color: #666;
        }
        
        table tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.terbaru {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .status-badge.event {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        
        .status-badge.favorit {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .campaign-image {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-edit {
            background: #4caf50;
            color: white;
        }
        
        .btn-edit:hover {
            background: #45a049;
        }
        
        .btn-delete {
            background: #f44336;
            color: white;
        }
        
        .btn-delete:hover {
            background: #da190b;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }
        
        .modal-header h3 {
            color: #333;
        }
        
        .close {
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .close:hover {
            color: #000;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>
            <i class="fas fa-tachometer-alt"></i>
            Admin Panel - Kelola Kampanye
        </h2>
        <div class="navbar-right">
            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
            <a href="dashboarduser.php"><i class="fas fa-home"></i> Beranda</a>
            <a href="?logout=1"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php echo $message; ?>
        
        <!-- Statistik -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <i class="fas fa-bullhorn"></i>
                <div class="value"><?php echo $stats['total_kampanye']; ?></div>
                <div class="label">Total Kampanye</div>
            </div>
            <div class="stat-card green">
                <i class="fas fa-money-bill-wave"></i>
                <div class="value">Rp <?php echo number_format($stats['total_terkumpul'], 0, ',', '.'); ?></div>
                <div class="label">Total Terkumpul</div>
            </div>
            <div class="stat-card orange">
                <i class="fas fa-fire"></i>
                <div class="value"><?php echo $stats['total_terbaru']; ?></div>
                <div class="label">Kampanye Terbaru</div>
            </div>
            <div class="stat-card purple">
                <i class="fas fa-calendar-alt"></i>
                <div class="value"><?php echo $stats['total_event']; ?></div>
                <div class="label">Event</div>
            </div>
            <div class="stat-card red">
                <i class="fas fa-star"></i>
                <div class="value"><?php echo $stats['total_favorit']; ?></div>
                <div class="label">Favorit</div>
            </div>
        </div>
        
        <!-- Form Tambah Kampanye -->
        <div class="section">
            <div class="section-header">
                <h3><i class="fas fa-plus-circle"></i> Tambah Kampanye Baru</h3>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Organisasi CSR *</label>
                        <select name="csr_id" required>
                            <option value="">Pilih Organisasi CSR</option>
                            <?php foreach ($csr_list as $csr): ?>
                                <option value="<?php echo $csr['id']; ?>"><?php echo htmlspecialchars($csr['nama']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Status Kampanye *</label>
                        <select name="status" required>
                            <option value="terbaru">🔥 Kampanye Terbaru</option>
                            <option value="event">🎉 Rekomendasi Event</option>
                            <option value="favorit">⭐ Kategori Favorit</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Judul Kampanye *</label>
                        <input type="text" name="judul" placeholder="Masukkan judul kampanye" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Upload Foto Kampanye</label>
                        <input type="file" name="foto" accept="image/*">
                    </div>
                    
                    <div class="form-group">
                        <label>Target Nominal (Rp) *</label>
                        <input type="number" name="target_nominal" min="1" placeholder="Contoh: 50000000" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Waktu Berakhir *</label>
                        <input type="date" name="waktu_berakhir" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Cerita Penggalangan Dana *</label>
                        <textarea name="cerita" placeholder="Tulis cerita dan tujuan penggalangan dana..." required></textarea>
                    </div>
                </div>
                
                <button type="submit" name="tambah_kampanye" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Kampanye
                </button>
            </form>
        </div>
        
        <!-- Daftar Kampanye -->
        <div class="section">
            <div class="section-header">
                <h3><i class="fas fa-list"></i> Daftar Semua Kampanye</h3>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Judul</th>
                            <th>Organisasi</th>
                            <th>Status</th>
                            <th>Target</th>
                            <th>Terkumpul</th>
                            <th>Progress</th>
                            <th>Berakhir</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kampanye_list as $kampanye): 
                            $progress = ($kampanye['target_nominal'] > 0) ? ($kampanye['terkumpul'] / $kampanye['target_nominal'] * 100) : 0;
                        ?>
                            <tr>
                                <td>
                                    <?php if ($kampanye['foto']): ?>
                                        <img src="<?php echo htmlspecialchars($kampanye['foto']); ?>" alt="Foto" class="campaign-image">
                                    <?php else: ?>
                                        <div style="width: 80px; height: 60px; background: #e0e0e0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-image" style="color: #999;"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($kampanye['judul']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($kampanye['csr_nama']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $kampanye['status']; ?>">
                                        <?php 
                                        $status_text = [
                                            'terbaru' => '🔥 Terbaru',
                                            'event' => '🎉 Event',
                                            'favorit' => '⭐ Favorit'
                                        ];
                                        echo $status_text[$kampanye['status']];
                                        ?>
                                    </span>
                                </td>
                                <td>Rp <?php echo number_format($kampanye['target_nominal'], 0, ',', '.'); ?></td>
                                <td>Rp <?php echo number_format($kampanye['terkumpul'], 0, ',', '.'); ?></td>
                                <td>
                                    <div style="width: 100px; background: #e0e0e0; height: 8px; border-radius: 4px; overflow: hidden;">
                                        <div style="width: <?php echo min($progress, 100); ?>%; background: #4caf50; height: 100%;"></div>
                                    </div>
                                    <small><?php echo number_format($progress, 1); ?>%</small>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($kampanye['waktu_berakhir'])); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-sm btn-edit" onclick="openEditModal(<?php echo $kampanye['id']; ?>, '<?php echo $kampanye['status']; ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-sm btn-delete" onclick="deleteKampanye(<?php echo $kampanye['id']; ?>, '<?php echo addslashes($kampanye['judul']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modal Edit Status -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Ubah Status Kampanye</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="kampanye_id" id="edit_kampanye_id">
                
                <div class="form-group">
                    <label>Pilih Status Baru *</label>
                    <select name="new_status" id="edit_status" required>
                        <option value="terbaru">🔥 Kampanye Terbaru</option>
                        <option value="event">🎉 Rekomendasi Event</option>
                        <option value="favorit">⭐ Kategori Favorit</option>
                    </select>
                </div>
                
                <button type="submit" name="edit_status" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Perubahan
                </button>
            </form>
        </div>
    </div>
    
    <script>
        function openEditModal(kampanye_id, current_status) {
            document.getElementById('edit_kampanye_id').value = kampanye_id;
            document.getElementById('edit_status').value = current_status;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function deleteKampanye(id, judul) {
            if (confirm('Apakah Anda yakin ingin menghapus kampanye "' + judul + '"?\n\nData yang sudah dihapus tidak dapat dikembalikan.')) {
                window.location.href = '?delete=' + id;
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>