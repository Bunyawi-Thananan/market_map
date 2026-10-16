<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['market_id'])) {
    header("Location: index.php"); exit();
}

$market_id = intval($_GET['market_id']);
$market = $conn->query("SELECT created_by, name FROM markets WHERE id=$market_id")->fetch_assoc();

// เช็คสิทธิ์ (ต้องเป็นเจ้าของตลาดนี้ หรือ แอดมิน)
if ($_SESSION['user_id'] !== $market['created_by'] && $_SESSION['role'] !== 'admin') {
    die("ไม่มีสิทธิ์เข้าถึง");
}

// จัดการการ อนุมัติ / ปฏิเสธ
if (isset($_GET['action']) && isset($_GET['req_id'])) {
    $req_id = intval($_GET['req_id']);
    $action = $_GET['action'];
    $req_user = $conn->query("SELECT username FROM shop_requests WHERE id=$req_id")->fetch_assoc()['username'];

    if ($action == 'approve') {
        $conn->query("UPDATE shop_requests SET status='approved' WHERE id=$req_id");
        // อัปเกรดสิทธิ์เป็นพ่อค้าแม่ค้า (ถ้าเขายังเป็นแค่ user)
        $conn->query("UPDATE users SET role='shop' WHERE username='$req_user' AND role='user'");
        echo "<script>alert('อนุมัติแล้ว!'); window.location='manage_shop_requests.php?market_id=$market_id';</script>";
    } elseif ($action == 'reject') {
        $conn->query("UPDATE shop_requests SET status='rejected' WHERE id=$req_id");
        echo "<script>alert('ปฏิเสธคำร้องแล้ว'); window.location='manage_shop_requests.php?market_id=$market_id';</script>";
    }
}

$requests = $conn->query("SELECT * FROM shop_requests WHERE market_id=$market_id AND status='pending'");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการคำร้อง - <?php echo $market['name']; ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
    <div class="container max-w-700">
        <a href="market_detail.php?id=<?php echo $market_id; ?>" class="btn btn-secondary mb-3">กลับไปหน้าตลาด</a>
        <h3 class="fw-bold text-dark">คำร้องขอเปิดร้าน (<?php echo $market['name']; ?>)</h3>
        
        <table class="table table-bordered bg-white shadow-sm mt-3">
            <thead class="table-dark"><tr><th>ผู้ขอเปิดร้าน</th><th>รายละเอียดที่แจ้งมา</th><th>จัดการ</th></tr></thead>
            <tbody>
                <?php if($requests->num_rows > 0): while($r = $requests->fetch_assoc()): ?>
                    <tr>
                        <td class="fw-bold text-primary">@<?php echo htmlspecialchars($r['username']); ?></td>
                        <td><?php echo htmlspecialchars($r['request_reason']); ?></td>
                        <td>
                            <a href="?market_id=<?php echo $market_id; ?>&action=approve&req_id=<?php echo $r['id']; ?>" class="btn btn-success btn-sm">อนุมัติ</a>
                            <a href="?market_id=<?php echo $market_id; ?>&action=reject&req_id=<?php echo $r['id']; ?>" class="btn btn-danger btn-sm">ปฏิเสธ</a>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="3" class="text-center text-muted">ไม่มีคำร้องใหม่</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>