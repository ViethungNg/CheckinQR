<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Nếu đã đăng nhập thì chuyển hướng sang trang chủ admin
if (isLoggedIn()) {
    redirect(url('/admin/index.php'));
}

$error = '';

if (isPost()) {
    requireCsrfToken();
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu.';
    } else {
        if (loginAdmin($username, $password)) {
            redirect(url('/admin/index.php'));
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không chính xác.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMT - Checkin - Đăng nhập Quản trị</title>
    <link rel="icon" href="<?php echo url('img/logo pmt.png'); ?>" type="image/png">
    <style>
        :root {
            --primary-color: #d32f2f;
            --primary-hover: #b71c1c;
            --bg-color: #f4f6f8;
            --text-color: #333;
            --border-radius: 8px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: var(--text-color);
        }
        .login-container {
            background: #fff;
            padding: 2.5rem 2rem;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h1 {
            color: var(--primary-color);
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        .login-logo-img {
            max-height: 105px;
            max-width: 280px;
            width: auto;
            height: auto;
            object-fit: contain;
            margin-bottom: 12px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 1px solid #ccc;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        .btn-primary {
            width: 100%;
            padding: 0.8rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .btn-primary:hover {
            background-color: var(--primary-hover);
        }
        .alert {
            padding: 0.8rem;
            border-radius: var(--border-radius);
            background-color: #ffebee;
            color: #c62828;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border: 1px solid #ef9a9a;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-header">
        <img src="<?php echo url('img/logo pmt.png'); ?>" alt="Logo PMT" class="login-logo-img">
        <h2 style="font-size: 1.5rem; color: #111; font-weight: 800;">PMT - Checkin</h2>
        <p style="color: #666; font-size: 0.9rem;">Hệ thống Quản lý & Điểm danh Sự kiện</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert"><?php echo esc($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?php echo csrfField(); ?>
        <div class="form-group">
            <label for="username">Tên đăng nhập</label>
            <input type="text" id="username" name="username" class="form-control" required autofocus autocomplete="username">
        </div>
        <div class="form-group">
            <label for="password">Mật khẩu</label>
            <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn-primary">Đăng nhập</button>
    </form>
</div>

</body>
</html>
