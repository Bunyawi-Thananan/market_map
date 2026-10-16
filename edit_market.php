<?php
session_start();
include 'db.php';

// 1. เช็คว่าล็อกอินหรือยัง
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}
$market_id = intval($_GET['id']);

// ดึงข้อมูลตลาด
$sql = "SELECT * FROM markets WHERE id = $market_id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    echo "<script>alert('ไม่พบข้อมูลตลาดที่ต้องการแก้ไข'); window.location='index.php';</script>";
    exit();
}
$market = $result->fetch_assoc();

// 2. เช็คสิทธิ์ความปลอดภัยขั้นสูง (ใครเข้าหน้านี้ได้บ้าง?)
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
$is_creator = ($_SESSION['user_id'] === $market['created_by']);

// ถ้าไม่ใช่แอดมิน และไม่ใช่คนสร้างตลาดนี้ เตะออกทันที!
if (!$is_admin && !$is_creator) {
    echo "<script>alert('ไม่มีสิทธิ์เข้าถึง! คุณไม่ใช่เจ้าของตลาดนี้ครับ'); window.location='index.php';</script>";
    exit();
}

$message = "";

// ==========================================
// ส่วนที่ 1: ระบบลบตลาด และไฟล์รูปทั้งหมด (Delete)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'delete') {
    
    // 1. ลบรูปร้านค้าทั้งหมดที่อยู่ในตลาดนี้ (DB ลบไฟล์รูปให้ไม่ได้ เราต้องสั่งลบเอง)
    $shops_res = $conn->query("SELECT image FROM shops WHERE market_id = $market_id");
    if ($shops_res && $shops_res->num_rows > 0) {
        while($shop = $shops_res->fetch_assoc()) {
            if (!empty($shop['image']) && file_exists('uploads/' . $shop['image'])) {
                unlink('uploads/' . $shop['image']);
            }
        }
    }
    
    // ลบข้อมูลร้านและคำร้อง (กันเหนียวเผื่อ DB ไม่ได้ตั้ง CASCADE ไว้)
    $conn->query("DELETE FROM shops WHERE market_id = $market_id");
    $conn->query("DELETE FROM shop_requests WHERE market_id = $market_id");

    // 2. ปิดงานแจ้งปัญหา (Reports) ที่เกี่ยวกับตลาดนี้
    $conn->query("UPDATE reports SET status = 'resolved' WHERE market_id = $market_id");

    // 3. ลบรูปหน้าปกตลาด
    if (!empty($market['image']) && file_exists('uploads/' . $market['image'])) {
        unlink('uploads/' . $market['image']);
    }

    // 4. ลบข้อมูลตลาด
    if ($conn->query("DELETE FROM markets WHERE id = $market_id")) {
        echo "<script>alert('🗑️ ลบตลาด และรูปร้านค้าทั้งหมดเรียบร้อยแล้ว!'); window.location='index.php';</script>";
        exit();
    } else {
        $message = "เกิดข้อผิดพลาดในการลบ: " . $conn->error;
    }
}

// ==========================================
// ส่วนที่ 2: ระบบแก้ไขข้อมูล (Update)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'update') {
    $name = $conn->real_escape_string($_POST['name']);
    $details = $conn->real_escape_string($_POST['details']);
    $open_time = $conn->real_escape_string($_POST['open_time']);
    $lat = $_POST['lat'];
    $lng = $_POST['lng'];

    $update_image_sql = ""; 

    if (isset($_FILES['market_image']) && $_FILES['market_image']['error'] == 0) {
        $upload_dir = 'uploads/';
        $file_extension = strtolower(pathinfo($_FILES["market_image"]["name"], PATHINFO_EXTENSION));
        $allowed_extensions = array("jpg", "jpeg", "png", "webp", "gif");

        if (in_array($file_extension, $allowed_extensions)) {
            $new_file_name = uniqid('market_') . '.' . $file_extension;
            $target_file = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES["market_image"]["tmp_name"], $target_file)) {
                // ลบรูปเก่าทิ้งก่อน
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

    if (empty($message)) {
        $sql = "UPDATE markets SET 
                name = '$name', 
                details = '$details', 
                open_time = '$open_time', 
                lat = '$lat', 
                lng = '$lng' 
                $update_image_sql 
                WHERE id = $market_id";

        if ($conn->query($sql) === TRUE) {
            echo "<script>alert('✅ อัปเดตข้อมูลเรียบร้อย!'); window.location='index.php';</script>";
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
    <title>แก้ไขตลาด - MarketMap</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/leaflet.css">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        #map-picker { height: 350px; width: 100%; border-radius: 10px; border: 2px solid #ffc107; cursor: crosshair; }
        body.auth-page { height: auto !important; min-height: 100vh; padding: 40px 20px; display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="auth-page bg-light">

    <div class="card card-auth shadow-lg border-0" style="max-width: 600px; width: 100%;"> 
        <div class="card-body p-4">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="text-warning fw-bold m-0"><i class="bi bi-pencil-square"></i> แก้ไขข้อมูลตลาด</h4>
                <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-arrow-left"></i> กลับ</a>
            </div>

            <?php if($message): ?>
                <div class="alert alert-danger"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="old_image" value="<?php echo htmlspecialchars($market['image']); ?>">

                <div class="mb-3">
                    <label class="form-label text-muted fw-bold">ชื่อตลาด</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($market['name']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted fw-bold">เวลาเปิด-ปิด</label>
                    <input type="text" name="open_time" class="form-control" value="<?php echo htmlspecialchars($market['open_time']); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted fw-bold">รายละเอียดตลาด</label>
                    <textarea name="details" class="form-control" rows="3"><?php echo htmlspecialchars($market['details']); ?></textarea>
                </div>

                <div class="mb-4 p-3 border rounded bg-white">
                    <label class="form-label fw-bold text-primary"><i class="bi bi-image"></i> รูปภาพตลาด</label>
                    
                    <?php if(!empty($market['image'])): ?>
                        <div class="mb-3 text-center">
                            <img src="uploads/<?php echo $market['image']; ?>" class="img-thumbnail rounded shadow-sm" style="max-height: 150px;" alt="รูปปัจจุบัน">
                            <small class="d-block text-muted mt-1">รูปปัจจุบัน</small>
                        </div>
                    <?php endif; ?>

                    <input type="file" name="market_image" class="form-control mt-2" accept="image/png, image/jpeg, image/webp, image/gif">
                    <small class="text-danger mt-1 d-block">* หากไม่ต้องการเปลี่ยนรูป ไม่ต้องเลือกไฟล์ใหม่</small>
                </div>

                <div class="mb-4">
                    <label class="form-label text-warning fw-bold mb-2">
                        <i class="bi bi-pin-map-fill"></i> ขยับหมุดเพื่อแก้ไขตำแหน่ง
                    </label>
                    
                    <div id="map-picker" class="mb-2 shadow-sm"></div>
                    
                    <div class="input-group">
                        <span class="input-group-text bg-light">Lat</span>
                        <input type="text" id="lat" name="lat" class="form-control" value="<?php echo $market['lat']; ?>" readonly style="background-color: #e9ecef;">
                        <span class="input-group-text bg-light">Lng</span>
                        <input type="text" id="lng" name="lng" class="form-control" value="<?php echo $market['lng']; ?>" readonly style="background-color: #e9ecef;">
                    </div>
                </div>

                <button type="submit" class="btn btn-warning w-100 rounded-pill fw-bold shadow-sm py-2 mb-3 text-dark">
                    <i class="bi bi-save"></i> บันทึกการเปลี่ยนแปลง
                </button>
            </form>

            <hr class="my-4">

            <form method="post" onsubmit="return confirm('⚠️ คุณแน่ใจหรือไม่ว่าต้องการ ลบตลาดนี้? \n\n*ร้านค้าทั้งหมดและรูปภาพที่อยู่ในตลาดนี้จะถูกลบทิ้งไปด้วยอย่างถาวร!');">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-outline-danger w-100 rounded-pill fw-bold py-2">
                    <i class="bi bi-trash3-fill"></i> ลบตลาดนี้ทิ้ง
                </button>
            </form>

        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/leaflet.js"></script>
    
    <script>
        var currentLat = <?php echo $market['lat']; ?>;
        var currentLng = <?php echo $market['lng']; ?>;

        var map = L.map('map-picker').setView([currentLat, currentLng], 15); 
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', { attribution: 'OpenStreetMap' }).addTo(map);

        var marker = L.marker([currentLat, currentLng], {draggable: true}).addTo(map);

        marker.on('dragend', function(event) {
            var position = marker.getLatLng();
            document.getElementById('lat').value = position.lat.toFixed(6);
            document.getElementById('lng').value = position.lng.toFixed(6);
            map.setView([position.lat, position.lng]);
        });

        map.on('click', function(e) {
            marker.setLatLng(e.latlng);
            document.getElementById('lat').value = e.latlng.lat.toFixed(6);
            document.getElementById('lng').value = e.latlng.lng.toFixed(6);
            map.setView([e.latlng.lat, e.latlng.lng]);
        });
    </script>
</body>
</html>