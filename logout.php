<?php
session_start();
// เคลียร์ค่าเซสชันทั้งหมด
session_unset();
session_destroy();

// ส่งกลับไปหน้า login พร้อมข้อความว่า logout แล้ว
header("Location: login.php?msg=logout");
exit();
?>