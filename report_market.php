<?php
session_start();
include 'db.php';

// 1. บังคับล็อกอินก่อนแจ้ง (ป้องกันคนนอกมากดสแปมก่อกวน)
if (!isset($_SESSION['user_id'])) {
    echo "<script>alert('กรุณาเข้าสู่ระบบก่อนแจ้งข้อมูลผิดพลาดครับ'); window.location='login.php';</script>";
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}
$market_id = intval($_GET['id']);
$username = $_SESSION['user_id'];

// ดึงชื่อตลาดมาแสดงให้รู้ว่ากำลังแจ้งตลาดไหน
$sql_market = "SELECT name FROM markets WHERE id = $market_id";
$res_market = $conn->query($sql_market);
if ($res_market->num_rows == 0) {
    echo "<script>alert('ไม่พบตลาดนี้ในระบบ'); window.location='index.php';</script>";
    exit();
}
$market_name = $res_market->fetch_assoc()['name'];

$message = "";

// 2. บันทึกข้อมูลเมื่อกดส่งฟอร์ม
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $report_type = $conn->real_escape_string($_POST['report_type']);
    $details = $conn->real_escape_string($_POST['details']);

    $sql_report = "INSERT INTO reports (market_id, reporter_username, report_type, details) 
                   VALUES ($market_id, '$username', '$report_type', '$details')";

    if ($conn->query($sql_report) === TRUE) {
        echo "<script>alert('ขอบคุณที่ช่วยแจ้งข้อมูลครับ! แอดมินจะรีบตรวจสอบโดยเร็วที่สุด'); window.location='index.php';</script>";
        exit();
    } else {
        $message = "เกิดข้อผิดพลาด: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แจ้งข้อมูลผิดพลาด - MarketMap</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body.auth-page { background-color: #f8f9fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
    </style>
</head>
<body class="auth-page">

    <div class="card shadow-lg border-0" style="max-width: 500px; width: 100%; border-radius: 15px;"> 
        <div class="card-body p-4 p-md-5">
            
            <div class="text-center mb-4">
                <div class="bg-danger text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 70px; height: 70px; font-size: 2rem;">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <h3 class="fw-bold text-dark">แจ้งข้อมูลผิดพลาด</h3>
                <p class="text-muted mb-1">ตลาดที่ต้องการแจ้ง:</p>
                <h5 class="text-danger fw-bold">"<?php echo htmlspecialchars($market_name); ?>"</h5>
            </div>

            <?php if($message): ?>
                <div class="alert alert-danger rounded-pill text-center"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label text-muted fw-bold small">ปัญหาที่พบ <span class="text-danger">*</span></label>
                    <select name="report_type" class="form-select shadow-sm border-danger" required>
                        <option value="">-- เลือกปัญหา --</option>
                        <option value="หมุดผิดตำแหน่ง">📍 หมุดปักผิดตำแหน่ง / ไม่ตรงพิกัดจริง</option>
                        <option value="ตลาดปิดกิจการแล้ว">🚪 ตลาดนี้ปิดกิจการ / ไม่มีอยู่จริงแล้ว</option>
                        <option value="ชื่อหรือเวลาผิด">📝 ชื่อตลาด หรือ เวลาเปิด-ปิด ไม่ถูกต้อง</option>
                        <option value="เนื้อหาไม่เหมาะสม">🚫 มีรูปภาพหรือเนื้อหาที่ไม่เหมาะสม (Spam)</option>
                        <option value="อื่นๆ">❓ อื่นๆ</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted fw-bold small">รายละเอียดเพิ่มเติม (เพื่อช่วยแอดมินตรวจสอบ)</label>
                    <textarea name="details" class="form-control shadow-sm" rows="3" placeholder="เช่น ความจริงตลาดเปิดแค่เสาร์-อาทิตย์ครับ..."></textarea>
                </div>

                <button type="submit" class="btn btn-danger w-100 rounded-pill fw-bold shadow-sm py-2 mb-3 text-white">
                    <i class="bi bi-send-fill"></i> ส่งรายงานให้แอดมิน
                </button>
            </form>

            <div class="text-center">
                <a href="index.php" class="text-decoration-none text-secondary"><i class="bi bi-arrow-left"></i> ยกเลิกและกลับไปหน้าแผนที่</a>
            </div>

        </div>
    </div>
    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>