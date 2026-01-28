<?php
session_start();
include "db.php"; // koneksi $conn

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $id = mysqli_real_escape_string($conn, $_POST['reg_id']);
    $nama = mysqli_real_escape_string($conn, $_POST['reg_nama']);
    $password = mysqli_real_escape_string($conn, $_POST['reg_password']);
    
    // Check if ID already exists
    $check = mysqli_query($conn, "SELECT * FROM akunpartner WHERE id='$id'");
    if (mysqli_num_rows($check) > 0) {
        $reg_error = "ID sudah terdaftar";
    } else {
        // Insert new partner
        $sql = "INSERT INTO akunpartner (id, nama, password) VALUES ('$id', '$nama', '$password')";
        if (mysqli_query($conn, $sql)) {
            $reg_success = "Pendaftaran berhasil! Silakan login.";
        } else {
            $reg_error = "Error: " . mysqli_error($conn);
        }
    }
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $id = mysqli_real_escape_string($conn, $_POST['id']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    $sql = "SELECT * FROM akunpartner WHERE id='$id' AND password='$password'";
    $q = mysqli_query($conn, $sql);

    if (mysqli_num_rows($q) == 1) {
        $data = mysqli_fetch_assoc($q);
        $_SESSION['partner_id']  = $data['id'];
        $_SESSION['partner_nama'] = $data['nama'];
        header("Location: partner_dashboard.php");
        exit;
    } else {
        $error = "ID atau Password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partner Login - KolaborAksi</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #17a697 0%, #0f6f5f 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 400px;
        }

        .auth-box {
            background: white;
            padding: 30px 20px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .auth-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .auth-header p {
            font-size: 14px;
            color: #666;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
        }

        .tab {
            flex: 1;
            padding: 12px;
            background: none;
            border: none;
            font-size: 14px;
            font-weight: 600;
            color: #999;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s;
        }

        .tab.active {
            color: #17a697;
            border-bottom-color: #17a697;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s;
        }

        .form-input:focus {
            outline: none;
            border-color: #17a697;
            box-shadow: 0 0 0 3px rgba(23, 166, 151, 0.1);
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #17a697 0%, #0f6f5f 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(23, 166, 151, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #7f1d1d;
            border: 1px solid #fecaca;
        }

        @media (min-width: 768px) {
            .auth-box {
                padding: 40px 35px;
            }

            .auth-header h1 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-box">
            <div class="auth-header">
                <h1>Partner Admin</h1>
                <p>KolaborAksi</p>
            </div>

            <div class="tabs">
                <button class="tab active" onclick="switchTab('login')">Login</button>
                <button class="tab" onclick="switchTab('register')">Daftar</button>
            </div>

            <!-- Login Tab -->
            <div id="login-tab" class="tab-content active">
                <?php if(isset($error)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if(isset($reg_success)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($reg_success) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">ID Partner</label>
                        <input type="text" name="id" class="form-input" placeholder="Masukkan ID" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-input" placeholder="Masukkan Password" required>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary">Login</button>
                </form>
            </div>

            <!-- Register Tab -->
            <div id="register-tab" class="tab-content">
                <?php if(isset($reg_error)): ?>
                    <div class="alert alert-error"><?= htmlspecialchars($reg_error) ?></div>
                <?php endif; ?>
                <?php if(isset($reg_success)): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($reg_success) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label">ID Partner</label>
                        <input type="text" name="reg_id" class="form-input" placeholder="Masukkan ID" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nama</label>
                        <input type="text" name="reg_nama" class="form-input" placeholder="Masukkan Nama" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="reg_password" class="form-input" placeholder="Masukkan Password" required>
                    </div>
                    <button type="submit" name="register" class="btn btn-primary">Daftar</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Remove active class from all tabs and contents
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            
            // Add active class to selected tab and content
            if (tab === 'login') {
                document.querySelector('.tab:first-child').classList.add('active');
                document.getElementById('login-tab').classList.add('active');
            } else {
                document.querySelector('.tab:last-child').classList.add('active');
                document.getElementById('register-tab').classList.add('active');
            }
        }
    </script>
</body>
</html>
