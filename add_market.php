<?php
session_start();
include 'db.php';

// --- ระบบแปลงลิงก์ Google Maps ---
if (isset($_POST['ajax_resolve_url'])) {
    $url = $_POST['ajax_resolve_url'];
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $headers = @get_headers($url, 1);
        if ($headers && isset($headers['Location'])) {
            $dest = is_array($headers['Location']) ? end($headers['Location']) : $headers['Location'];
            echo $dest;
        } else {
            echo $url;
        }
    }
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$message = "";

// --- ระบบบันทึกข้อมูลแบบแบ่งระดับสิทธิ์ (Role-based Auto Status) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['ajax_resolve_url'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $details = $conn->real_escape_string($_POST['details']);
    $open_time = $conn->real_escape_string($_POST['open_time']);
    $lat = $_POST['lat'];
    $lng = $_POST['lng'];
    $created_by = $_SESSION['user_id'];
    $role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
    
    // 🔥 แก้ไขแล้ว: ให้ Admin และ Market Owner อนุมัติผ่านทันที
    if ($role === 'admin' || $role === 'market_owner') {
        $status = 'approved'; // โชว์บนแผนที่ทันที
        $is_verified = 1;     // ได้ป้าย "ทางการ"
    } else {
        $status = 'pending';  // User ธรรมดา/Shop เพิ่ม ต้องรอแอดมินตรวจ
        $is_verified = 0;     // ได้ป้าย "ชุมชน"
    }

    $image_name = NULL;

    // ระบบอัปโหลดรูปภาพ
    if (isset($_FILES['market_image']) && $_FILES['market_image']['error'] == 0) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        $file_extension = strtolower(pathinfo($_FILES["market_image"]["name"], PATHINFO_EXTENSION));
        $allowed_extensions = array("jpg", "jpeg", "png", "webp", "gif");

        if (in_array($file_extension, $allowed_extensions)) {
            $new_file_name = uniqid('market_') . '.' . $file_extension;
            $target_file = $upload_dir . $new_file_name;

            if (move_uploaded_file($_FILES["market_image"]["tmp_name"], $target_file)) {
                $image_name = $new_file_name;
            } else {
                $message = "เกิดข้อผิดพลาดในการบันทึกไฟล์รูปภาพ";
            }
        } else {
            $message = "กรุณาอัปโหลดเฉพาะไฟล์รูปภาพเท่านั้น";
        }
    }

    // บันทึกลง Database
    if (empty($message)) {
        $sql = "INSERT INTO markets (name, details, open_time, lat, lng, created_by, image, status, is_verified) 
                VALUES ('$name', '$details', '$open_time', '$lat', '$lng', '$created_by', " . ($image_name ? "'$image_name'" : "NULL") . ", '$status', $is_verified)";

        if ($conn->query($sql) === TRUE) {
            if ($status === 'approved') {
                echo "<script>alert('✅ เพิ่มตลาดเรียบร้อยแล้ว หมุดแสดงบนแผนที่ทันที!'); window.location='index.php';</script>";
            } else {
                echo "<script>alert('⏳ ขอบคุณที่ร่วมแบ่งปัน! ข้อมูลถูกส่งไปให้ผู้ดูแลระบบตรวจสอบแล้ว หมุดจะแสดงบนแผนที่เมื่อได้รับการอนุมัติครับ'); window.location='index.php';</script>";
            }
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
    <title>เพิ่มตำแหน่งตลาด</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/leaflet.css">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        #map-picker { height: 350px; width: 100%; border-radius: 10px; border: 2px solid #198754; cursor: crosshair; }
        body.auth-page { height: auto !important; min-height: 100vh; padding: 40px 20px; display: flex; align-items: center; justify-content: center; background-color: #f4f6f9; }
    </style>
</head>
<body class="auth-page">

    <div class="card shadow-lg border-0" style="max-width: 600px; width: 100%; border-radius: 15px;"> 
        <div class="card-body p-4 p-md-5">
            
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="text-success fw-bold m-0"><i class="bi bi-geo-alt-fill text-danger"></i> เพิ่มตลาดใหม่</h4>
                <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
            </div>

            <?php if(isset($_SESSION['role']) && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'market_owner'): ?>
                <div class="alert alert-warning small rounded-3 shadow-sm mb-4">
                    <i class="bi bi-info-circle-fill"></i> <strong>หมายเหตุ:</strong> ข้อมูลที่คุณเพิ่มจะถูกส่งให้ผู้ดูแลระบบตรวจสอบความถูกต้องก่อนแสดงผลบนแผนที่สาธารณะครับ
                </div>
            <?php endif; ?>

            <?php if($message): ?>
                <div class="alert alert-danger rounded-pill text-center"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                
                <div class="mb-4 p-3 bg-light rounded-3 border border-success border-opacity-25 shadow-sm">
                    <label class="form-label fw-bold text-success mb-2">
                        <i class="bi bi-link-45deg"></i> ดึงพิกัดจาก Google Maps (แนะนำ)
                    </label>
                    <div class="input-group">
                        <input type="text" id="gmap_link" class="form-control border-success" placeholder="วางลิงก์ Google Maps ที่นี่...">
                        <button class="btn btn-success fw-bold" type="button" onclick="extractFromLink()">
                            ดึงพิกัด
                        </button>
                    </div>
                    <small class="text-muted mt-1 d-block" id="link_status">* คัดลอกลิงก์จาก Google Maps มาวางเพื่อความแม่นยำ</small>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted fw-bold small">ชื่อตลาด <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control shadow-sm" placeholder="เช่น ตลาดนัดรถไฟ, ตลาดสดเทศบาล" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted fw-bold small">เวลาเปิด-ปิด <span class="text-danger">*</span></label>
                    <input type="text" name="open_time" class="form-control shadow-sm" placeholder="เช่น 08:00 - 18:00 (เปิดทุกวัน)" required>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted fw-bold small">รายละเอียดตลาด</label>
                    <textarea name="details" class="form-control shadow-sm" rows="3" placeholder="ของกินอร่อย, มีที่จอดรถ..."></textarea>
                </div>

                <div class="mb-4 p-3 border rounded-3 bg-white shadow-sm">
                    <label class="form-label fw-bold text-primary small"><i class="bi bi-image"></i> รูปภาพหน้าปกตลาด (ถ้ามี)</label>
                    <input type="file" name="market_image" class="form-control" accept="image/png, image/jpeg, image/webp, image/gif">
                </div>

                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label text-success fw-bold mb-0 small">
                            <i class="bi bi-pin-map-fill"></i> พิกัดบนแผนที่ <span class="text-danger">*</span>
                        </label>
                        <button type="button" onclick="locateMe()" class="btn btn-sm btn-outline-primary rounded-pill">
                            <i class="bi bi-crosshair"></i> พิกัดปัจจุบัน
                        </button>
                    </div>
                    
                    <div id="map-picker" class="mb-2 shadow-sm"></div>
                    
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white text-muted">Lat</span>
                        <input type="text" id="lat" name="lat" class="form-control bg-light" readonly required>
                        <span class="input-group-text bg-white text-muted">Lng</span>
                        <input type="text" id="lng" name="lng" class="form-control bg-light" readonly required>
                    </div>
                </div>

                <button type="submit" class="btn btn-success w-100 rounded-pill fw-bold shadow-sm py-2 mt-2" style="font-size: 1.1rem;">
                    <i class="bi bi-send-fill"></i> บันทึกข้อมูลตลาด
                </button>
            </form>

        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/leaflet.js"></script>
    
    <script>
        var map = L.map('map-picker').setView([13.7563, 100.5018], 10); 
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', { attribution: 'OpenStreetMap' }).addTo(map);

        var marker;
        var userCircle; 

        map.on('click', function(e) { updateMarker(e.latlng.lat, e.latlng.lng); });

        function updateMarker(lat, lng) {
            if (marker) map.removeLayer(marker);
            marker = L.marker([lat, lng]).addTo(map);
            document.getElementById('lat').value = lat.toFixed(6);
            document.getElementById('lng').value = lng.toFixed(6);
            map.setView([lat, lng], 16);
        }

        function locateMe() { map.locate({setView: true, maxZoom: 16}); }
        map.on('locationfound', function(e) {
            if (userCircle) map.removeLayer(userCircle);
            userCircle = L.circle(e.latlng, e.accuracy).addTo(map);
            updateMarker(e.latlng.lat, e.latlng.lng);
        });

        // ฟังก์ชันดึงพิกัดจากลิงก์ Google Maps
        async function extractFromLink() {
            let url = document.getElementById('gmap_link').value.trim();
            let status = document.getElementById('link_status');
            
            if (!url) { alert("กรุณาวางลิงก์ก่อนครับ"); return; }
            status.innerHTML = '<span class="text-warning"><i class="bi bi-hourglass-split"></i> กำลังประมวลผล...</span>';

            if (url.includes("goo.gl") || url.includes("googleusercontent.com")) {
                try {
                    let formData = new FormData();
                    formData.append('ajax_resolve_url', url);
                    let response = await fetch('add_market.php', { method: 'POST', body: formData });
                    url = await response.text(); 
                } catch (err) {
                    status.innerHTML = '<span class="text-danger">ผิดพลาดในการแปลงลิงก์</span>';
                    return;
                }
            }

            let regexPlace = /!3d([-0-9]+\.[0-9]+)!4d([-0-9]+\.[0-9]+)/;
            let matchPlace = url.match(regexPlace);
            let lat, lng, precisionMode = false;

            if (matchPlace && matchPlace.length >= 3) {
                lat = parseFloat(matchPlace[1]); lng = parseFloat(matchPlace[2]); precisionMode = true;
            } else {
                let regexGeneral = /[@?&/]([-0-9]+\.[0-9]+),([-0-9]+\.[0-9]+)/;
                let matchGeneral = url.match(regexGeneral);
                if (matchGeneral && matchGeneral.length >= 3) {
                    lat = parseFloat(matchGeneral[1]); lng = parseFloat(matchGeneral[2]);
                }
            }

            if (lat && lng) {
                updateMarker(lat, lng);
                status.innerHTML = precisionMode 
                    ? '<span class="text-success fw-bold"><i class="bi bi-check-circle-fill"></i> ได้พิกัดแม่นยำ!</span>'
                    : '<span class="text-warning fw-bold"><i class="bi bi-exclamation-circle"></i> ได้พิกัดกลางจอ (อาจต้องขยับหมุดช่วยนิดหน่อย)</span>';
            } else {
                status.innerHTML = '<span class="text-danger">หาพิกัดไม่เจอ</span>';
            }
        }
    </script>
</body>
</html>