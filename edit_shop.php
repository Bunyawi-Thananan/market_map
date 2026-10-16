<?php
session_start();
include 'db.php';

// 1. เช็คการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. รับค่า ID ร้านค้าที่ต้องการแก้ไข
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}
$shop_id = intval($_GET['id']);

// 3. ดึงข้อมูลร้านค้าปัจจุบัน
$sql = "SELECT * FROM shops WHERE id = $shop_id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo "<script>alert('ไม่พบข้อมูลร้านค้านี้'); window.location='index.php';</script>";
    exit();
}
$shop = $result->fetch_assoc();
$market_id = $shop['market_id']; // เก็บ ID ตลาดไว้ใช้ตอนเด้งกลับ

// 4. เช็คสิทธิ์: ต้องเป็น "เจ้าของร้าน" หรือ "Admin" เท่านั้น
$is_owner = ($_SESSION['user_id'] === $shop['owner_username']);
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

if (!$is_owner && (!$is_admin)) {
    echo "<script>alert('คุณไม่มีสิทธิ์แก้ไขร้านค้านี้ครับ!'); window.location='market_detail.php?id=$market_id';</script>";
    exit();
}

$message = "";
// หมวดหมู่ร้านค้า (ต้องมีเพื่อดึงมาแสดงใน Dropdown)
$categories = ['ผักสด', 'ผลไม้', 'ของสด/เนื้อสัตว์', 'อาหารปรุงสำเร็จ', 'ขนม/ของหวาน', 'เครื่องดื่ม', 'เสื้อผ้า/ของใช้', 'ทั่วไป'];

// ==========================================
// ส่วนที่ 1: ระบบลบร้านค้า (Delete)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'delete') {
    // ลบไฟล์รูปออกจากโฟลเดอร์ก่อน
    if (!empty($shop['image']) && file_exists('uploads/' . $shop['image'])) {
        unlink('uploads/' . $shop['image']); 
    }

    if ($conn->query("DELETE FROM shops WHERE id = $shop_id")) {
        echo "<script>alert('ลบร้านค้าเรียบร้อยแล้ว!'); window.location='market_detail.php?id=$market_id';</script>";
        exit();
    } else {
        $message = "เกิดข้อผิดพลาดในการลบ: " . $conn->error;
    }
}

// ==========================================
// ส่วนที่ 2: ระบบอัปเดตข้อมูล (Update)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'update') {
    $shop_name = $conn->real_escape_string($_POST['shop_name']);
    $description = $conn->real_escape_string($_POST['description']);
    $category = $conn->real_escape_string($_POST['category']); // 🌟 รับค่าหมวดหมู่เพิ่มมา
    
    $update_image_sql = ""; 

    // เช็คว่ามีการอัปโหลดรูปใหม่หรือไม่
    if (isset($_FILES['shop_image']) && $_FILES['shop_image']['error'] == 0) {
        $upload_dir = 'uploads/';
        $file_extension = strtolower(pathinfo($_FILES["shop_image"]["name"], PATHINFO_EXTENSION));
        $allowed_extensions = array("jpg", "jpeg", "png", "webp", "gif");

        if (in_array($file_extension, $allowed_extensions)) {
            $new_file_name = uniqid('shop_') . '.' . $file_extension;
            $target_file = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES["shop_image"]["tmp_name"], $target_file)) {
                // อัปรูปใหม่สำเร็จ -> ลบรูปเก่าทิ้ง
                if (!empty($_POST['old_image']) && file_exists($upload_dir . $_POST['old_image'])) {
                    unlink($upload_dir . $_POST['old_image']);
                }
                $update_image_sql = ", image = '$new_file_name'";
            } else {
                $message = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพใหม่";
            }
        } else {
            $message = "รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, WEBP, GIF) เท่านั้น";
        }
    }

    // อัปเดตลงฐานข้อมูล (รวม category ด้วย)
    if (empty($message)) {
        $sql_update = "UPDATE shops SET 
                       shop_name = '$shop_name', 
                       description = '$description',
                       category = '$category'
                       $update_image_sql 
                       WHERE id = $shop_id";

        if ($conn->query($sql_update) === TRUE) {
            echo "<script>alert('อัปเดตข้อมูลร้านค้าเรียบร้อย!'); window.location='market_detail.php?id=$market_id';</script>";
            exit();
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขร้านค้า - MarketMap</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        body.auth-page { height: auto !important; min-height: 100vh; padding: 40px 20px; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; }
    </style>
</head>
<body class="auth-page">

    <div class="card shadow-lg border-0" style="max-width: 500px; width: 100%; border-radius: 15px;"> 
        <div class="card-body p-4 p-md-5">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="text-warning fw-bold m-0"><i class="bi bi-pencil-square"></i> แก้ไขข้อมูลร้าน</h4>
                <a href="market_detail.php?id=<?php echo $market_id; ?>" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-arrow-left"></i> กลับ</a>
            </div>

            <?php if($message): ?>
                <div class="alert alert-danger rounded-pill text-center"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="old_image" value="<?php echo htmlspecialchars($shop['image']); ?>">

                <div class="mb-3">
                    <label class="form-label text-muted fw-bold small">ชื่อร้านค้า</label>
                    <input type="text" name="shop_name" class="form-control shadow-sm" value="<?php echo htmlspecialchars($shop['shop_name']); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted fw-bold small">หมวดหมู่สินค้า</label>
                    <select name="category" class="form-select shadow-sm" required>
                        <?php 
                        $current_cat = isset($shop['category']) ? $shop['category'] : 'ทั่วไป';
                        foreach($categories as $cat): 
                        ?>
                            <option value="<?php echo $cat; ?>" <?php echo ($current_cat == $cat) ? 'selected' : ''; ?>>
                                <?php echo $cat; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="form-label text-muted fw-bold small">รายละเอียด / เมนูเด็ด</label>
                    <textarea name="description" class="form-control shadow-sm" rows="3" required><?php echo htmlspecialchars($shop['description']); ?></textarea>
                </div>

                <div class="mb-4 p-3 border rounded bg-white shadow-sm">
                    <label class="form-label fw-bold text-primary"><i class="bi bi-image"></i> รูปร้านค้า</label>
                    
                    <?php if(!empty($shop['image'])): ?>
                        <div class="mb-3 text-center">
                            <img src="uploads/<?php echo $shop['image']; ?>" class="img-thumbnail rounded shadow-sm" style="height: 150px; width: 150px; object-fit: cover;" alt="รูปปัจจุบัน">
                            <small class="d-block text-muted mt-1">รูปปัจจุบัน</small>
                        </div>
                    <?php endif; ?>

                    <input type="file" name="shop_image" class="form-control" accept="image/png, image/jpeg, image/webp, image/gif">
                    <small class="text-danger mt-1 d-block">* หากไม่ต้องการเปลี่ยนรูป ไม่ต้องเลือกไฟล์ใหม่</small>
                </div>

                <button type="submit" class="btn btn-warning w-100 rounded-pill fw-bold shadow-sm py-2 mb-3 text-dark">
                    <i class="bi bi-save"></i> บันทึกการเปลี่ยนแปลง
                </button>
            </form>

            <hr class="my-4">

            <form method="post" onsubmit="return confirm('⚠️ คุณแน่ใจหรือไม่ว่าต้องการ ลบร้านค้านี้? \n\nการกระทำนี้ไม่สามารถย้อนกลับได้!');">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-outline-danger w-100 rounded-pill fw-bold py-2">
                    <i class="bi bi-trash3-fill"></i> ลบร้านค้านี้ทิ้ง
                </button>
            </form>

        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>