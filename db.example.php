<?php
// คัดลอกไฟล์นี้เป็น db.php แล้วแก้ค่าให้ตรงกับเครื่องของคุณ
$host = "localhost";
$user = "root";
$pass = "";
$db = "market_map";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// ตั้งค่าภาษาไทยให้ถูกต้อง
$conn->set_charset("utf8");
?>
