<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['market_id'])) {
    header("Location: index.php"); exit();
}

$market_id = intval($_GET['market_id']);
$username = $_SESSION['user_id'];

// เช็คว่าเคยส่งไปแล้วหรือยัง
$check = $conn->query("SELECT status FROM shop_requests WHERE market_id=$market_id AND username='$username'");
if ($check->num_rows > 0) {
    echo "<script>alert('คุณได้ส่งคำร้องไปแล้ว กรุณารอการตรวจสอบครับ'); window.location='market_detail.php?id=$market_id';</script>";
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reason = $conn->real_escape_string($_POST['request_reason']);
    $sql = "INSERT INTO shop_requests (market_id, username, request_reason) VALUES ($market_id, '$username', '$reason')";
    if ($conn->query($sql) === TRUE) {
        echo "<script>alert('ส่งคำร้องเรียบร้อยแล้ว! โปรดรอเจ้าของตลาดอนุมัติ'); window.location='market_detail.php?id=$market_id';</script>";
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ขอเปิดร้านค้า</title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">
    <div class="card shadow p-4" style="max-width: 400px; width:100%;">
        <h4 class="text-success text-center fw-bold mb-3">ยื่นคำร้องขอเปิดร้าน</h4>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">คุณต้องการขายอะไร / ชื่อร้านอะไร?</label>
                <textarea name="request_reason" class="form-control" rows="3" required placeholder="เช่น ขอเปิดร้านขายน้ำปั่นครับ..."></textarea>
            </div>
            <button type="submit" class="btn btn-success w-100 rounded-pill">ส่งคำร้อง</button>
            <a href="market_detail.php?id=<?php echo $market_id; ?>" class="btn btn-outline-secondary w-100 rounded-pill mt-2">ยกเลิก</a>
        </form>
    </div>
</body>
</html>