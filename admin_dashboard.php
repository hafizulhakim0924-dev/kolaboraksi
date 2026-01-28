<?php
session_start();
include "db.php";

// Check if admin is logged in
if (!isset($_SESSION['admin_utama_id'])) {
    header("Location: admin_login.php");
    exit;
}

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $campaign_id = intval($_POST['campaign_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($campaign_id > 0 && in_array($action, ['approve', 'reject'])) {
        $status = $action == 'approve' ? 'approved' : 'rejected';
        $sql = "UPDATE campaigns SET status = '$status' WHERE id = $campaign_id";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Kampanye berhasil " . ($action == 'approve' ? 'disetujui' : 'ditolak');
        } else {
            $_SESSION['error'] = "Error: " . mysqli_error($conn);
        }
        
        header("Location: admin_dashboard.php");
        exit;
    }
}

// Get campaigns waiting for approval
$pending_campaigns = [];
$result = mysqli_query($conn, "SELECT * FROM campaigns WHERE status = 'pending' ORDER BY created_at DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $pending_campaigns[] = $row;
}

// Get all campaigns for overview
$all_campaigns = [];
$result = mysqli_query($conn, "SELECT id, title, organizer, status, created_at, donasi_terkumpul FROM campaigns ORDER BY created_at DESC LIMIT 50");
while ($row = mysqli_fetch_assoc($result)) {
    $all_campaigns[] = $row;
}

// Statistics
$stats_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM campaigns WHERE status = 'pending'"))['total'];
$stats_approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM campaigns WHERE status = 'approved' OR status IS NULL OR status = ''"))['total'];
$stats_rejected = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM campaigns WHERE status = 'rejected'"))['total'];

function rupiah($number) {
    return "Rp " . number_format($number, 0, ',', '.');
}

function getStatusBadge($status) {
    if (empty($status) || $status === 'approved' || $status === NULL) {
        return '<span style="background: #10b981; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">Approved</span>';
    } elseif ($status === 'pending') {
        return '<span style="background: #f59e0b; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">Pending</span>';
    } elseif ($status === 'rejected') {
        return '<span style="background: #ef4444; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600;">Rejected</span>';
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - KolaborAksi</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 700;
        }

        .header-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            padding: 8px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
        }

        .section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #333;
        }

        .campaign-card {
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        .campaign-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .campaign-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .campaign-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .campaign-info {
            font-size: 14px;
            color: #666;
        }

        .campaign-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-approve {
            background: #10b981;
            color: white;
        }

        .btn-approve:hover {
            background: #059669;
        }

        .btn-reject {
            background: #ef4444;
            color: white;
        }

        .btn-reject:hover {
            background: #dc2626;
        }

        .campaign-image {
            width: 100%;
            max-width: 200px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            margin-top: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .campaign-header {
                flex-direction: column;
            }

            .campaign-actions {
                width: 100%;
            }

            .btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-shield-alt"></i> Admin Utama Dashboard</h1>
            <div class="header-info">
                <span>Selamat datang, <b><?= htmlspecialchars($_SESSION['admin_utama_nama']) ?></b></span>
                <a href="admin_logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert error"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Menunggu Persetujuan</h3>
                <div class="value" style="color: #f59e0b;"><?= $stats_pending ?></div>
            </div>
            <div class="stat-card">
                <h3>Disetujui</h3>
                <div class="value" style="color: #10b981;"><?= $stats_approved ?></div>
            </div>
            <div class="stat-card">
                <h3>Ditolak</h3>
                <div class="value" style="color: #ef4444;"><?= $stats_rejected ?></div>
            </div>
        </div>

        <!-- Pending Campaigns -->
        <div class="section">
            <h2 class="section-title"><i class="fas fa-clock"></i> Kampanye Menunggu Persetujuan</h2>
            
            <?php if (count($pending_campaigns) > 0): ?>
                <?php foreach ($pending_campaigns as $campaign): ?>
                    <div class="campaign-card">
                        <div class="campaign-header">
                            <div style="flex: 1;">
                                <div class="campaign-title"><?= htmlspecialchars($campaign['title']) ?></div>
                                <div class="campaign-info">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($campaign['organizer']) ?><br>
                                    <i class="fas fa-calendar"></i> <?= date('d M Y, H:i', strtotime($campaign['created_at'])) ?>
                                </div>
                                <?php if (!empty($campaign['image'])): ?>
                                    <img src="<?= htmlspecialchars($campaign['image']) ?>" alt="Campaign" class="campaign-image" onerror="this.style.display='none'">
                                <?php endif; ?>
                            </div>
                            <div class="campaign-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-approve" onclick="return confirm('Setujui kampanye ini?')">
                                        <i class="fas fa-check"></i> Setujui
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="campaign_id" value="<?= $campaign['id'] ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-reject" onclick="return confirm('Tolak kampanye ini?')">
                                        <i class="fas fa-times"></i> Tolak
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>Tidak ada kampanye yang menunggu persetujuan</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- All Campaigns Overview -->
        <div class="section">
            <h2 class="section-title"><i class="fas fa-list"></i> Semua Kampanye</h2>
            
            <?php if (count($all_campaigns) > 0): ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f5f5f5; border-bottom: 2px solid #e0e0e0;">
                            <th style="padding: 12px; text-align: left;">Judul</th>
                            <th style="padding: 12px; text-align: left;">Organizer</th>
                            <th style="padding: 12px; text-align: center;">Status</th>
                            <th style="padding: 12px; text-align: right;">Donasi</th>
                            <th style="padding: 12px; text-align: left;">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_campaigns as $camp): ?>
                            <tr style="border-bottom: 1px solid #f0f0f0;">
                                <td style="padding: 12px;">
                                    <a href="kampanye-detail.php?id=<?= $camp['id'] ?>" style="color: #667eea; text-decoration: none;">
                                        <?= htmlspecialchars($camp['title']) ?>
                                    </a>
                                </td>
                                <td style="padding: 12px;"><?= htmlspecialchars($camp['organizer']) ?></td>
                                <td style="padding: 12px; text-align: center;"><?= getStatusBadge($camp['status']) ?></td>
                                <td style="padding: 12px; text-align: right;"><?= rupiah(intval($camp['donasi_terkumpul'])) ?></td>
                                <td style="padding: 12px;"><?= date('d M Y', strtotime($camp['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>Tidak ada kampanye</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

