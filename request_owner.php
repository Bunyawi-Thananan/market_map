<?php
session_start();
include 'db.php';

// --- ระบบแปลงลิงก์ Google Maps ---
if (isset($_POST['ajax_resolve_url'])) {
    $url = $_POST['ajax_resolve_url'];
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $headers = @get_headers($url, 1);
        echo ($headers && isset($headers['Location'])) ? (is_array($headers['Location']) ? end($headers['Location']) : $headers['Location']) : $url;
    }
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['user_id'];
$current_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';

if ($current_role == 'admin' || $current_role == 'market_owner') {
    echo "<script>alert('คุณมีสิทธิ์ระดับสูงอยู่แล้ว สามารถจัดการตลาดได้เลยครับ'); window.location='index.php';</script>";
    exit();
}

// 1. เช็คว่ากำลังรอตรวจสอบอยู่หรือไม่
$check_sql = "SELECT * FROM verify_requests WHERE username = '$username' AND status = 'pending'";
$already_pending = ($conn->query($check_sql)->num_rows > 0);
$message = "";

// 2. ดึงรายชื่อ "ตลาดที่ User คนนี้เคยเป็นคนสร้างไว้ (แต่ยังไม่ได้เป็นตลาดทางการ)"
$sql_my_markets = "SELECT id, name FROM markets WHERE created_by = '$username' AND is_verified = 0";
$my_markets_result = $conn->query($sql_my_markets);

if ($_SERVER["REQUEST_METHOD"] == "POST" && !$already_pending && !isset($_POST['ajax_resolve_url'])) {
    
    $request_type = $_POST['request_type']; // 'existing' หรือ 'new'
    $phone = $conn->real_escape_string($_POST['phone']);
    $document_image_name = NULL;

    // อัปโหลดเอกสารยืนยันตัวตน (บังคับทั้ง 2 กรณี)
    if (isset($_FILES['document_image']) && $_FILES['document_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES["document_image"]["name"], PATHINFO_EXTENSION));
        if (in_array($ext, ["jpg", "jpeg", "png", "webp", "pdf"])) {
            if (!is_dir('uploads/documents/')) mkdir('uploads/documents/', 0777, true);
            $document_image_name = uniqid('doc_') . '.' . $ext;
            if (!move_uploaded_file($_FILES["document_image"]["tmp_name"], 'uploads/documents/' . $document_image_name)) {
                $message = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์เอกสาร";
            }
        } else {
            $message = "เอกสารรองรับเฉพาะไฟล์รูปภาพ หรือ PDF เท่านั้นครับ";
        }
    } else {
        $message = "กรุณาอัปโหลดเอกสารยืนยันตัวตนด้วยครับ";
    }

    if (empty($message) && $document_image_name) {
        
        // กรณีที่ 1: เลือกตลาดเดิมที่เคยสร้างไว้แล้ว
        if ($request_type == 'existing') {
            $existing_market_id = intval($_POST['existing_market_id']);
            
            $sql_req = "INSERT INTO verify_requests (username, phone, document_image, market_id) 
                        VALUES ('$username', '$phone', '$document_image_name', $existing_market_id)";
            
            if ($conn->query($sql_req) === TRUE) {
                echo "<script>alert('✅ ส่งเอกสารเพื่อขอเป็นเจ้าของตลาดเดิมเรียบร้อยแล้ว! กรุณารอแอดมินอนุมัติครับ'); window.location='index.php';</script>";
                exit();
            } else {
                $message = "Error Request: " . $conn->error;
            }

        // กรณีที่ 2: สร้างตลาดใหม่บนแผนที่เลย
        } elseif ($request_type == 'new') {
            $name = $conn->real_escape_string($_POST['name']);
            $details = $conn->real_escape_string($_POST['details']);
            $open_time = $conn->real_escape_string($_POST['open_time']);
            $lat = $_POST['lat'];
            $lng = $_POST['lng'];
            $market_image_name = NULL;

            // อัปโหลดรูปตลาด
            if (isset($_FILES['market_image']) && $_FILES['market_image']['error'] == 0) {
                $ext = strtolower(pathinfo($_FILES["market_image"]["name"], PATHINFO_EXTENSION));
                if (in_array($ext, ["jpg", "jpeg", "png", "webp"])) {
                    $market_image_name = uniqid('market_') . '.' . $ext;
                    move_uploaded_file($_FILES["market_image"]["tmp_name"], 'uploads/' . $market_image_name);
                }
            }

            // 1. สร้างตลาดใหม่
            $sql_market = "INSERT INTO markets (name, details, open_time, lat, lng, created_by, image, status, is_verified) 
                           VALUES ('$name', '$details', '$open_time', '$lat', '$lng', '$username', " . ($market_image_name ? "'$market_image_name'" : "NULL") . ", 'pending', 1)";
            
            if ($conn->query($sql_market) === TRUE) {
                $new_market_id = $conn->insert_id; 

                // 2. สร้างคำร้องพร้อมผูก ID ตลาด
                $sql_req = "INSERT INTO verify_requests (username, phone, document_image, market_id) 
                            VALUES ('$username', '$phone', '$document_image_name', $new_market_id)";
                
                if ($conn->query($sql_req) === TRUE) {
                    echo "<script>alert('✅ ส่งข้อมูลตลาดใหม่และเอกสารเรียบร้อยแล้ว! กรุณารอแอดมินตรวจสอบครับ'); window.location='index.php';</script>";
                    exit();
                } else {
                    $message = "Error Request: " . $conn->error;
                }
            } else {
                $message = "Error Market: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงทะเบียนเจ้าของตลาด - MarketMap</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/leaflet.css">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        /* แก้ไขส่วนนี้เพื่อให้เลื่อนจอได้ปกติ */
        body.auth-page { 
            background-color: #f4f6f9; 
            min-height: 100vh; 
            padding: 40px 20px; 
            display: block !important; /* บังคับยกเลิกการจัดกึ่งกลางแนวตั้ง */
        }
        .kyc-card { 
            max-width: 700px; 
            margin: 40px auto; /* เว้นระยะขอบบน-ล่าง และจัดกึ่งกลางซ้าย-ขวา */
            border-radius: 15px; 
            border: none; 
        }
        #map-picker { height: 350px; width: 100%; border-radius: 10px; border: 2px solid #198754; cursor: crosshair; }
    </style>
</head>
<body class="auth-page">

    <div class="card shadow-lg kyc-card"> 
        <div class="card-body p-4 p-md-5">
            
            <div class="text-center mb-4">
                <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3 shadow-sm" style="width: 70px; height: 70px; font-size: 2rem;">
                    <i class="bi bi-shield-check"></i>
                </div>
                <h3 class="fw-bold text-dark">ลงทะเบียนเจ้าของตลาด</h3>
                <p class="text-muted">ส่งเอกสารยืนยันความเป็นเจ้าของ เพื่อรับสิทธิ์จัดการตลาดของคุณ</p>
            </div>

            <?php if($message): ?>
                <div class="alert alert-danger rounded-pill text-center"><?php echo $message; ?></div>
            <?php endif; ?>

            <?php if($already_pending): ?>
                <div class="alert alert-warning text-center rounded-3 p-4 shadow-sm">
                    <i class="bi bi-hourglass-split" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold mt-3">คำร้องของคุณกำลังอยู่ระหว่างตรวจสอบ</h5>
                    <p class="mb-0 text-muted small">ผู้ดูแลระบบกำลังตรวจสอบเอกสารของคุณครับ</p>
                    <a href="index.php" class="btn btn-warning mt-3 rounded-pill fw-bold w-100">กลับหน้าหลัก</a>
                </div>
            <?php else: ?>
                <form method="post" enctype="multipart/form-data" id="kycForm">
                    
                    <h5 class="fw-bold text-success border-bottom pb-2 mb-3"><i class="bi bi-geo-alt-fill"></i> 1. เลือกข้อมูลตลาด</h5>
                    
                    <div class="mb-4">
                        <label class="form-label text-muted fw-bold small">รูปแบบการอ้างอิงตลาด <span class="text-danger">*</span></label>
                        <select name="request_type" id="request_type" class="form-select shadow-sm fw-bold text-primary border-primary" onchange="toggleFormSection()">
                            <?php if ($my_markets_result->num_rows > 0): ?>
                                <option value="existing">✅ เลือกจากตลาดที่ฉันเคยปักหมุดไว้แล้ว</option>
                            <?php endif; ?>
                            <option value="new">📍 สร้างตลาดใหม่ลงบนแผนที่เลย</option>
                        </select>
                    </div>

                    <div id="section_existing" class="mb-4 p-3 bg-light rounded-3 border" style="<?php echo ($my_markets_result->num_rows > 0) ? 'display:block;' : 'display:none;'; ?>">
                        <label class="form-label text-muted fw-bold small">เลือกตลาดของคุณ <span class="text-danger">*</span></label>
                        <select name="existing_market_id" id="existing_market_id" class="form-select shadow-sm">
                            <?php 
                                if ($my_markets_result->num_rows > 0) {
                                    while($row = $my_markets_result->fetch_assoc()) {
                                        echo "<option value='".$row['id']."'>".$row['name']."</option>";
                                    }
                                }
                            ?>
                        </select>
                        <small class="text-muted mt-2 d-block"><i class="bi bi-info-circle"></i> รายชื่อนี้คือตลาดที่คุณเคยปักหมุดไว้บนระบบ</small>
                    </div>

                    <div id="section_new" style="<?php echo ($my_markets_result->num_rows > 0) ? 'display:none;' : 'display:block;'; ?>">
                        
                        <div class="mb-3 p-3 bg-light rounded-3 border border-success border-opacity-25 shadow-sm">
                            <label class="form-label fw-bold text-success mb-2">
                                <i class="bi bi-link-45deg"></i> ดึงพิกัดจาก Google Maps (แนะนำ)
                            </label>
                            <div class="input-group">
                                <input type="text" id="gmap_link" class="form-control border-success" placeholder="วางลิงก์ Google Maps ที่นี่...">
                                <button class="btn btn-success fw-bold" type="button" onclick="extractFromLink()">ดึงพิกัด</button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold small">ชื่อตลาด <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="new_name" class="form-control shadow-sm">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold small">เวลาเปิด-ปิด <span class="text-danger">*</span></label>
                            <input type="text" name="open_time" id="new_time" class="form-control shadow-sm">
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold small">รายละเอียดตลาด</label>
                            <textarea name="details" class="form-control shadow-sm" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted fw-bold small">รูปภาพหน้าปกตลาด (ถ้ามี)</label>
                            <input type="file" name="market_image" class="form-control" accept="image/png, image/jpeg, image/webp">
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label text-success fw-bold mb-0 small"><i class="bi bi-pin-map-fill"></i> พิกัดบนแผนที่</label>
                                <button type="button" onclick="locateMe()" class="btn btn-sm btn-outline-primary rounded-pill">
                                    <i class="bi bi-crosshair"></i> พิกัดปัจจุบัน
                                </button>
                            </div>
                            <div id="map-picker" class="mb-2 shadow-sm"></div>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white">Lat</span>
                                <input type="text" id="lat" name="lat" class="form-control bg-light" readonly>
                                <span class="input-group-text bg-white">Lng</span>
                                <input type="text" id="lng" name="lng" class="form-control bg-light" readonly>
                            </div>
                        </div>
                    </div>

                    <h5 class="fw-bold text-primary border-bottom pb-2 mb-3 mt-4"><i class="bi bi-shield-lock-fill"></i> 2. ยืนยันความเป็นเจ้าของ</h5>

                    <div class="mb-3">
                        <label class="form-label text-muted fw-bold small">เบอร์โทรศัพท์ติดต่อ <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control shadow-sm" placeholder="08X-XXX-XXXX" required>
                    </div>

                    <div class="mb-4 p-3 border rounded bg-white shadow-sm">
                        <label class="form-label fw-bold text-primary"><i class="bi bi-file-earmark-arrow-up-fill"></i> อัปโหลดเอกสารยืนยัน <span class="text-danger">*</span></label>
                        <input type="file" name="document_image" class="form-control" accept=".jpg, .jpeg, .png, .webp, .pdf" required>
                        <small class="text-muted mt-2 d-block">* สำเนาบัตรประชาชน หรือ ใบอนุญาตจัดตั้งตลาด (เซ็นรับรองสำเนา)</small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold shadow-sm py-2 mb-3" style="font-size: 1.1rem;">
                        <i class="bi bi-send-fill"></i> ส่งคำร้องขอเป็นเจ้าของตลาด
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="text-center mt-2">
                <a href="index.php" class="text-decoration-none text-secondary"><i class="bi bi-arrow-left"></i> กลับไปหน้าแผนที่</a>
            </div>
        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/leaflet.js"></script>
    <script>
        var map, marker, userCircle;

        // ฟังก์ชันซ่อน/แสดง ฟอร์มตาม Dropdown
        function toggleFormSection() {
            var type = document.getElementById('request_type').value;
            var secExisting = document.getElementById('section_existing');
            var secNew = document.getElementById('section_new');
            
            if(type === 'existing') {
                secExisting.style.display = 'block';
                secNew.style.display = 'none';
                
                // ยกเลิกการบังคับกรอก (Required) ในส่วนสร้างตลาดใหม่
                document.getElementById('new_name').required = false;
                document.getElementById('new_time').required = false;
                document.getElementById('lat').required = false;
            } else {
                secExisting.style.display = 'none';
                secNew.style.display = 'block';
                
                // บังคับกรอกช่องสร้างตลาดใหม่
                document.getElementById('new_name').required = true;
                document.getElementById('new_time').required = true;
                document.getElementById('lat').required = true;
                
                // รีเฟรชแผนที่ (แก้บั๊กแผนที่เทาเวลาซ่อนและแสดง)
                setTimeout(function(){ 
                    if(map) { map.invalidateSize(); } 
                    else { initMap(); }
                }, 100);
            }
        }

        function initMap() {
            map = L.map('map-picker').setView([13.7563, 100.5018], 10); 
            L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png').addTo(map);

            map.on('click', function(e) { updateMarker(e.latlng.lat, e.latlng.lng); });

            map.on('locationfound', function(e) {
                if (userCircle) map.removeLayer(userCircle);
                userCircle = L.circle(e.latlng, e.accuracy).addTo(map);
                updateMarker(e.latlng.lat, e.latlng.lng);
            });
        }

        function updateMarker(lat, lng) {
            if (marker) map.removeLayer(marker);
            marker = L.marker([lat, lng]).addTo(map);
            document.getElementById('lat').value = lat.toFixed(6);
            document.getElementById('lng').value = lng.toFixed(6);
            map.setView([lat, lng], 16);
        }

        function locateMe() { 
            if(map) map.locate({setView: true, maxZoom: 16}); 
        }

        async function extractFromLink() {
            let url = document.getElementById('gmap_link').value.trim();
            if (!url) return alert("กรุณาวางลิงก์ Google Maps ก่อนครับ");
            
            if (url.includes("goo.gl") || url.includes("googleusercontent.com")) {
                let fd = new FormData(); fd.append('ajax_resolve_url', url);
                let res = await fetch('request_owner.php', { method: 'POST', body: fd });
                url = await res.text();
            }

            let match = url.match(/!3d([-0-9.]+)!4d([-0-9.]+)/) || url.match(/[@?&/]([-0-9.]+),([-0-9.]+)/);
            if (match && match.length >= 3) {
                updateMarker(parseFloat(match[1]), parseFloat(match[2]));
            } else {
                alert("หาพิกัดไม่เจอครับ ลองจิ้มบนแผนที่แทนนะครับ");
            }
        }

        // เริ่มต้นตรวจสอบฟอร์ม
        window.onload = function() {
            toggleFormSection();
            if(document.getElementById('request_type').value === 'new') {
                initMap();
            }
        };
    </script>
</body>
</html>