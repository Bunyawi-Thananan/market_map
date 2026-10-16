<?php
session_start();
include 'db.php';

// ดึงเฉพาะตลาดที่สถานะเป็น 'approved' (อนุมัติแล้ว) มาแสดงบนแผนที่
// หมุดที่เพิ่งเพิ่มใหม่แล้วเป็น 'pending' จะถูกซ่อนไว้ก่อน
$sql = "SELECT * FROM markets WHERE status = 'approved'";
$result = $conn->query($sql);

$markets = array();
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $markets[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode([
    'data' => $markets,
    'is_logged_in' => isset($_SESSION['user_id']),
    'role' => isset($_SESSION['role']) ? $_SESSION['role'] : 'user'
]);
?>