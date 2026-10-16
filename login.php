<?php
session_start();
include 'db.php';

$error_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // ดึงข้อมูล User ออกมาก่อน
    $sql = "SELECT * FROM users WHERE username='$username'";
    $result = $conn->query($sql);

    // เราจะใช้ข้อความ Error เดียวกันทั้งหมดเพื่อความปลอดภัย
    $generic_error = "ชื่อผู้ใช้ หรือ รหัสผ่าน ไม่ถูกต้อง";

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // ตรวจสอบรหัสผ่าน
        if (password_verify($password, $row['password'])) {
            // รหัสถูก!
            $_SESSION['user_id'] = $username;
            
            // ให้ระบบจำสิทธิ์การใช้งาน (Role)
            $_SESSION['role'] = $row['role']; 
            
            $_SESSION['LAST_ACTIVITY'] = time();
            
            header("Location: index.php");
            exit;
        } else {
            // รหัสผิด (ใช้ข้อความรวม)
            $error_msg = $generic_error;
        }
    } else {
        // ไม่พบชื่อผู้ใช้ (ใช้ข้อความรวม)
        $error_msg = $generic_error;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เข้าสู่ระบบ - MarketMap</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">

    <a href="index.php" class="btn btn-white bg-white shadow-sm position-absolute top-0 start-0 m-3 m-md-4 rounded-pill text-secondary fw-bold text-decoration-none" style="z-index: 1000;">
        <i class="bi bi-arrow-left"></i> กลับหน้าหลัก
    </a>

    <div class="card card-auth shadow-lg border-0" style="border-radius: 15px;">
        <div class="card-body p-4 p-md-5">
            
            <h3 class="text-center mb-4 text-primary fw-bold">
                <i class="bi bi-person-circle"></i> เข้าสู่ระบบ
            </h3>

            <?php if($error_msg): ?>
                <div class="alert alert-danger text-center small rounded-3" role="alert">
                    <i class="bi bi-shield-exclamation"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>
            
            <?php 
            if(isset($_GET['msg'])) {
                if($_GET['msg'] == 'timeout') {
                    echo '<div class="alert alert-warning text-center small rounded-3" role="alert"><i class="bi bi-clock-history"></i> หมดเวลาการใช้งาน กรุณาเข้าสู่ระบบใหม่</div>';
                } elseif($_GET['msg'] == 'logout') {
                    echo '<div class="alert alert-success text-center small rounded-3" role="alert"><i class="bi bi-check-circle-fill"></i> ออกจากระบบเรียบร้อยแล้ว</div>';
                }
            }
            ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label text-muted fw-bold small">ชื่อผู้ใช้ (Username)</label>
                    <input type="text" name="username" class="form-control shadow-sm" placeholder="กรอกชื่อผู้ใช้" required>
                </div>
                <div class="mb-4">
                    <label class="form-label text-muted fw-bold small">รหัสผ่าน (Password)</label>
                    <input type="password" name="password" class="form-control shadow-sm" placeholder="กรอกรหัสผ่าน" required>
                </div>
                
                <div class="text-end mb-3">
                    <a href="forgot_password.php" class="text-decoration-none small text-danger">ลืมรหัสผ่าน?</a>
                </div>

                <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm">
                    เข้าสู่ระบบ
                </button>
            </form>

            <hr class="my-4">

            <div class="text-center">
                <span class="text-muted small">ยังไม่มีบัญชีใช่ไหม?</span>
                <a href="register.php" class="text-decoration-none fw-bold small">สมัครสมาชิกที่นี่</a>
            </div>

        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>