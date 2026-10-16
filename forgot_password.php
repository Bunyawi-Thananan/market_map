<?php
session_start();
include 'db.php';

$message = "";
$msg_type = ""; // ไว้กำหนดสีแจ้งเตือน (success/danger)

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. เช็คว่ารหัสผ่านใหม่ตรงกันไหม
    if ($new_password !== $confirm_password) {
        $message = "รหัสผ่านใหม่ไม่ตรงกันครับ!";
        $msg_type = "danger";
    } else {
        // 2. เช็คว่ามี Username และ Email นี้คู่กันในระบบไหม
        $check_user = "SELECT * FROM users WHERE username = '$username' AND email = '$email'";
        $result = $conn->query($check_user);

        if ($result->num_rows > 0) {
            // เจอข้อมูล! แปลว่าเป็นเจ้าของตัวจริง ทำการเข้ารหัสผ่านใหม่
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // 3. อัปเดตรหัสผ่านลงฐานข้อมูล
            $sql = "UPDATE users SET password = '$hashed_password' WHERE username = '$username'";

            if ($conn->query($sql) === TRUE) {
                echo "<script>alert('เปลี่ยนรหัสผ่านเรียบร้อยแล้ว! กรุณาเข้าสู่ระบบใหม่'); window.location='login.php';</script>";
                exit();
            } else {
                $message = "เกิดข้อผิดพลาด: " . $conn->error;
                $msg_type = "danger";
            }
        } else {
            // ไม่เจอข้อมูล
            $message = "ชื่อผู้ใช้ หรือ อีเมล ไม่ถูกต้องครับ!";
            $msg_type = "danger";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - MarketMap</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">

    <div class="card card-auth">
        <div class="card-body p-4">
            
            <h3 class="text-center mb-4 text-primary fw-bold">
                <i class="bi bi-shield-lock-fill"></i> รีเซ็ตรหัสผ่าน
            </h3>
            
            <p class="text-center text-muted small mb-4">
                กรุณากรอกชื่อผู้ใช้และอีเมลที่ใช้สมัคร เพื่อตั้งรหัสผ่านใหม่
            </p>

            <?php if($message != ""): ?>
                <div class="alert alert-<?php echo $msg_type; ?> text-center" role="alert">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">ชื่อผู้ใช้ (Username)</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">อีเมล (Email)</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <hr>

                <div class="mb-3">
                    <label class="form-label text-success fw-bold">ตั้งรหัสผ่านใหม่</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="mb-4">
                    <label class="form-label text-success fw-bold">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 py-2 rounded-pill fw-bold shadow-sm">
                    <i class="bi bi-arrow-clockwise"></i> เปลี่ยนรหัสผ่าน
                </button>
            </form>
            
            <hr class="my-4">

            <div class="text-center">
                <a href="login.php" class="text-decoration-none fw-bold text-secondary">
                    <i class="bi bi-arrow-left"></i> กลับไปหน้าเข้าสู่ระบบ
                </a>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>