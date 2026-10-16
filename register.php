<?php
session_start();
include 'db.php'; 

$message = ""; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $email = $_POST['email']; // รับค่าอีเมลที่ส่งมา
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. เช็คว่ารหัสผ่านตรงกันไหม
    if ($password !== $confirm_password) {
        $message = "รหัสผ่านไม่ตรงกันครับ!";
    } else {
        // 2. เช็คว่า Username หรือ Email นี้มีคนใช้หรือยัง
        $check_user = "SELECT * FROM users WHERE username = '$username' OR email = '$email'";
        $result = $conn->query($check_user);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['username'] === $username) {
                $message = "ชื่อผู้ใช้นี้ถูกใช้งานแล้วครับ";
            } else {
                $message = "อีเมลนี้ถูกใช้สมัครไปแล้วครับ";
            }
        } else {
            // 3. เข้ารหัส Password 
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // 4. บันทึกลงฐานข้อมูล (เพิ่ม email ลงไปด้วย)
            $sql = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$hashed_password')";

            if ($conn->query($sql) === TRUE) {
                echo "<script>alert('สมัครสมาชิกเรียบร้อย! กรุณาล็อกอิน'); window.location='login.php';</script>";
            } else {
                $message = "Error: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก - MarketMap</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-page">

    <div class="card card-auth">
        <div class="card-body p-4">
            
            <h3 class="text-center mb-4 text-success fw-bold">
                <i class="bi bi-person-plus-fill"></i> สร้างบัญชีใหม่
            </h3>
            
            <?php if($message != ""): ?>
                <div class="alert alert-danger text-center" role="alert">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">ตั้งชื่อผู้ใช้</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">อีเมล</label>
                    <input type="email" name="email" class="form-control" placeholder="example@email.com" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">ตั้งรหัสผ่าน</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">ยืนยันรหัสผ่านอีกครั้ง</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn btn-success w-100 py-2 rounded-pill fw-bold shadow-sm">
                    <i class="bi bi-check-circle"></i> ลงทะเบียน
                </button>
            </form>
            
            <hr class="my-4">

            <div class="text-center">
                <span class="text-muted">มีบัญชีอยู่แล้ว?</span>
                <a href="login.php" class="text-decoration-none fw-bold">เข้าสู่ระบบที่นี่</a>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>