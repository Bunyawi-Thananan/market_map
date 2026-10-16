<?php
session_start();
include 'db.php';

// เช็คว่าล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// เช็คว่ามี ID ส่งมาไหม
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // คำสั่ง SQL ลบข้อมูล
    $sql = "DELETE FROM markets WHERE id = $id";

    if ($conn->query($sql) === TRUE) {
        echo "<script>
            alert('ลบข้อมูลตลาดเรียบร้อยแล้ว');
            window.location = 'index.php';
        </script>";
    } else {
        echo "Error deleting record: " . $conn->error;
    }
} else {
    header("Location: index.php");
}
?>