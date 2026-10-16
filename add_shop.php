<?php
session_start();
include 'db.php';

// 1. เช็คการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. เช็ค ID ตลาด
if (!isset($_GET['market_id']) || empty($_GET['market_id'])) {
    header("Location: index.php");
    exit();
}
$market_id = intval($_GET['market_id']);

$market_query = $conn->query("SELECT name, created_by FROM markets WHERE id = $market_id");
if ($market_query->num_rows == 0) {
    echo "<script>alert('ไม่พบตลาดนี้ในระบบ'); window.location='index.php';</script>";
    exit();
}
$market_data = $market_query->fetch_assoc();
$market_name = $market_data['name'];

// 3. 🚨 ระบบตรวจสอบสิทธิ์ (เฉพาะ Admin, เจ้าของตลาดนี้, หรือคนที่ได้รับอนุมัติแล้วเท่านั้น)
$is_admin = ($_SESSION['role'] === 'admin');
$is_this_market_owner = ($_SESSION['user_id'] === $market_data['created_by']);

if (!$is_admin && !$is_this_market_owner) {
    // ถ้าไม่ใช่แอดมินหรือเจ้าของตลาด ต้องเช็คว่าเคยได้รับอนุมัติให้ขายในตลาดนี้ไหม
    $chk_approved = $conn->query("SELECT id FROM shop_requests WHERE market_id = $market_id AND username = '{$_SESSION['user_id']}' AND status = 'approved'");
    if ($chk_approved->num_rows == 0) {
        echo "<script>alert('ไม่อนุญาต! คุณต้องส่งคำร้องและได้รับการอนุมัติจากเจ้าของตลาดก่อนครับ'); window.location='market_detail.php?id=$market_id';</script>";
        exit();
    }
}

$message = "";


// 4. บันทึกข้อมูล
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $shop_name = $conn->real_escape_string($_POST['shop_name']);
    $description = $conn->real_escape_string($_POST['description']);
    $category = $conn->real_escape_string($_POST['category']); // รับค่าหมวดหมู่เพิ่มเข้ามา
    $owner_username = $_SESSION['user_id'];
    
    $image_name = NULL;

    // ระบบอัปโหลดรูปร้านค้า
    if (isset($_FILES['shop_image']) && $_FILES['shop_image']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_extension = strtolower(pathinfo($_FILES["shop_image"]["name"], PATHINFO_EXTENSION));
        $allowed_extensions = array("jpg", "jpeg", "png", "webp", "gif");

        if (in_array($file_extension, $allowed_extensions)) {
            $new_file_name = uniqid('shop_') . '.' . $file_extension;
            $target_file = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES["shop_image"]["tmp_name"], $target_file)) {
                $image_name = $new_file_name;
            } else {
                $message = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ";
            }
        } else {
            $message = "รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, WEBP, GIF) เท่านั้น";
        }
    }

    // บันทึกลงฐานข้อมูล (เพิ่มคอลัมน์ category เข้าไปด้วย)
    if (empty($message)) {
        $sql = "INSERT INTO shops (market_id, owner_username, shop_name, description, category, image) 
                VALUES ($market_id, '$owner_username', '$shop_name', '$description', '$category', " . ($image_name ? "'$image_name'" : "NULL") . ")";

        if ($conn->query($sql) === TRUE) {
            echo "<script>alert('🎉 เพิ่มร้านค้าเรียบร้อยแล้ว!'); window.location='market_detail.php?id=$market_id';</script>";
            exit();
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}

// รายการหมวดหมู่ให้เลือก
$categories = ['ผักสด', 'ผลไม้', 'ของสด/เนื้อสัตว์', 'อาหารปรุงสำเร็จ', 'ขนม/ของหวาน', 'เครื่องดื่ม', 'เสื้อผ้า/ของใช้', 'ทั่วไป'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มร้านค้า - MarketMap</title>
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
            <div class="text-center mb-4">
                <div class="bg-info text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 70px; height: 70px; font-size: 2rem;">
                    <i class="bi bi-shop"></i>
                </div>
                <h3 class="fw-bold text-dark">เปิดร้านใหม่</h3>
                <h5 class="text-success fw-bold mt-1">ใน "<?php echo htmlspecialchars($market_name); ?>"</h5>
            </div>

            <?php if($message): ?>
                <div class="alert alert-danger rounded-pill text-center"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo $message; ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label text-muted fw-bold small">ชื่อร้านค้า <span class="text-danger">*</span></label>
                    <input type="text" name="shop_name" class="form-control shadow-sm" placeholder="เช่น ร้านส้มตำป้าศรี" required>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted fw-bold small">หมวดหมู่สินค้า <span class="text-danger">*</span></label>
                    <select name="category" class="form-select shadow-sm" required>
                        <option value="">-- เลือกหมวดหมู่ --</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted fw-bold small">รายละเอียด / เมนูเด็ด <span class="text-danger">*</span></label>
                    <textarea name="description" class="form-control shadow-sm" rows="3" placeholder="เช่น ส้มตำรสเด็ด ไก่ย่างวิเชียรบุรี เปิดทุกวัน..." required></textarea>
                </div>

                <div class="mb-4 p-3 border rounded bg-light shadow-sm">
                    <label class="form-label fw-bold text-info"><i class="bi bi-camera"></i> รูปหน้าร้าน หรือ เมนูเด็ด (ถ้ามี)</label>
                    <input type="file" name="shop_image" class="form-control" accept="image/png, image/jpeg, image/webp, image/gif">
                    <small class="text-muted d-block mt-1">อัปโหลดรูปภาพเพื่อดึงดูดลูกค้า!</small>
                </div>

                <button type="submit" class="btn btn-info w-100 rounded-pill fw-bold shadow-sm py-2 mb-3 text-white">
                    <i class="bi bi-plus-circle"></i> บันทึกข้อมูลร้านค้า
                </button>
            </form>

            <div class="text-center">
                <a href="market_detail.php?id=<?php echo $market_id; ?>" class="text-decoration-none text-secondary">
                    <i class="bi bi-arrow-left"></i> กลับไปหน้าตลาด
                </a>
            </div>
        </div>
    </div>
    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>