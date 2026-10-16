<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('ไม่มีสิทธิ์เข้าถึง! เฉพาะผู้ดูแลระบบเท่านั้น'); window.location='index.php';</script>";
    exit();
}

$message = "";

// ==========================================
// ระบบจัดการปุ่ม อนุมัติ/ปฏิเสธ/แจ้งปัญหา (โค้ดลบไฟล์ขยะ 100% ตัวเดิม)
// ==========================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    // 1. จัดการคำร้อง KYC
    if ($action == 'approve_user') {
        $req_sql = "SELECT username, market_id FROM verify_requests WHERE id = $id";
        $req_res = $conn->query($req_sql);
        if ($req_res && $req_res->num_rows > 0) {
            $req = $req_res->fetch_assoc();
            $req_username = $req['username'];
            $req_market_id = $req['market_id'];

            $conn->query("UPDATE users SET role = 'market_owner' WHERE username = '$req_username'");
            $conn->query("UPDATE verify_requests SET status = 'approved' WHERE id = $id");
            
            if (!empty($req_market_id)) {
                $conn->query("UPDATE markets SET status = 'approved', is_verified = 1 WHERE id = $req_market_id");
            }
            echo "<script>alert('✅ อนุมัติสิทธิ์และเปิดตลาดให้คุณ $req_username เรียบร้อยแล้ว!'); window.location='admin_verify.php';</script>";
            exit();
        }
    } elseif ($action == 'reject_user') {
        $req_sql = "SELECT document_image, market_id FROM verify_requests WHERE id = $id";
        $req_res = $conn->query($req_sql);
        
        if ($req_res && $req_res->num_rows > 0) {
            $req = $req_res->fetch_assoc();
            $doc_image = $req['document_image'];
            $req_market_id = $req['market_id'];

            if (!empty($doc_image) && file_exists('uploads/documents/' . $doc_image)) unlink('uploads/documents/' . $doc_image);

            if (!empty($req_market_id)) {
                $shops_res = $conn->query("SELECT image FROM shops WHERE market_id = $req_market_id");
                if ($shops_res && $shops_res->num_rows > 0) {
                    while($shop = $shops_res->fetch_assoc()) {
                        if (!empty($shop['image']) && file_exists('uploads/' . $shop['image'])) unlink('uploads/' . $shop['image']);
                    }
                }
                $conn->query("DELETE FROM shops WHERE market_id = $req_market_id");
                $conn->query("DELETE FROM shop_requests WHERE market_id = $req_market_id");

                $img_res = $conn->query("SELECT image FROM markets WHERE id = $req_market_id");
                if ($img_res && $img_res->num_rows > 0) {
                    $m_image = $img_res->fetch_assoc()['image'];
                    if (!empty($m_image) && file_exists('uploads/' . $m_image)) unlink('uploads/' . $m_image);
                }
                $conn->query("DELETE FROM markets WHERE id = $req_market_id");
            }
        }
        $conn->query("DELETE FROM verify_requests WHERE id = $id");
        echo "<script>alert('❌ ปฏิเสธคำร้อง และลบไฟล์แนบทิ้งทั้งหมดแล้ว'); window.location='admin_verify.php';</script>";
        exit();
    }
    
    // 2. จัดการคำร้อง ตลาดชุมชน
    elseif ($action == 'approve_market') {
        if ($conn->query("UPDATE markets SET status = 'approved', is_verified = 0 WHERE id = $id")) {
            echo "<script>alert('✅ อนุมัติตลาดชุมชนให้แสดงบนแผนที่แล้ว!'); window.location='admin_verify.php';</script>";
            exit();
        }
    } elseif ($action == 'reject_market') {
        $shops_res = $conn->query("SELECT image FROM shops WHERE market_id = $id");
        if ($shops_res && $shops_res->num_rows > 0) {
            while($shop = $shops_res->fetch_assoc()) {
                if (!empty($shop['image']) && file_exists('uploads/' . $shop['image'])) unlink('uploads/' . $shop['image']);
            }
        }
        $conn->query("DELETE FROM shops WHERE market_id = $id");
        $conn->query("DELETE FROM shop_requests WHERE market_id = $id");

        $img_res = $conn->query("SELECT image FROM markets WHERE id = $id");
        if ($img_res && $img_res->num_rows > 0) {
            $img_name = $img_res->fetch_assoc()['image'];
            if (!empty($img_name) && file_exists('uploads/' . $img_name)) unlink('uploads/' . $img_name);
        }
        
        if ($conn->query("DELETE FROM markets WHERE id = $id")) {
            echo "<script>alert('❌ ลบข้อมูลตลาดขยะ และร้านค้าด้านในทิ้งทั้งหมดแล้ว'); window.location='admin_verify.php';</script>";
            exit();
        }
    }

    // 3. จัดการการแจ้งปัญหา
    elseif ($action == 'dismiss_report') {
        $conn->query("UPDATE reports SET status = 'resolved' WHERE id = $id");
        echo "<script>alert('✅ ปิดงานแจ้งปัญหานี้เรียบร้อยแล้ว'); window.location='admin_verify.php';</script>";
        exit();
    } elseif ($action == 'delete_reported_market') {
        if (isset($_GET['market_id'])) {
            $m_id = intval($_GET['market_id']);
            
            $shops_res = $conn->query("SELECT image FROM shops WHERE market_id = $m_id");
            if ($shops_res && $shops_res->num_rows > 0) {
                while($shop = $shops_res->fetch_assoc()) {
                    if (!empty($shop['image']) && file_exists('uploads/' . $shop['image'])) unlink('uploads/' . $shop['image']);
                }
            }
            $conn->query("DELETE FROM shops WHERE market_id = $m_id");
            $conn->query("DELETE FROM shop_requests WHERE market_id = $m_id");
            
            $img_res = $conn->query("SELECT image FROM markets WHERE id = $m_id");
            if ($img_res && $img_res->num_rows > 0) {
                $img_name = $img_res->fetch_assoc()['image'];
                if (!empty($img_name) && file_exists('uploads/' . $img_name)) unlink('uploads/' . $img_name);
            }
            $conn->query("DELETE FROM markets WHERE id = $m_id");
            $conn->query("UPDATE reports SET status = 'resolved' WHERE market_id = $m_id");
            
            echo "<script>alert('🗑️ ลบตลาดที่มีปัญหา ร้านค้าด้านใน และปิดงานแจ้งเตือนทั้งหมดเรียบร้อยแล้ว'); window.location='admin_verify.php';</script>";
            exit();
        }
    }
}

// ==========================================
// ดึงข้อมูลรออนุมัติมาแสดงในตาราง
// ==========================================
$req_users = $conn->query("SELECT * FROM verify_requests WHERE status = 'pending' ORDER BY created_at ASC");
$req_markets = $conn->query("SELECT * FROM markets WHERE status = 'pending' AND is_verified = 0 ORDER BY id DESC");
$req_reports = $conn->query("
    SELECT r.*, m.name as market_name 
    FROM reports r 
    LEFT JOIN markets m ON r.market_id = m.id 
    WHERE r.status = 'pending' 
    ORDER BY r.created_at ASC
");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบหลังบ้าน - Admin Dashboard</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        body { background-color: #f4f6f9; }
        .admin-header { background-color: #212529; color: white; padding: 40px 20px; border-radius: 0 0 20px 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
        .nav-tabs .nav-link { color: #6c757d; font-weight: bold; padding: 12px 20px; border: none; }
        .nav-tabs .nav-link.active { color: #0d6efd; border-bottom: 3px solid #0d6efd; background-color: transparent; }
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
        .doc-thumbnail { width: 50px; height: 50px; object-fit: cover; border-radius: 8px; cursor: pointer; border: 1px solid #dee2e6; }
        
        /* สไตล์สำหรับปุ่มให้สวยงาม */
        .btn-group .btn { border-radius: 0; }
        .btn-group .btn:first-child { border-top-left-radius: 5px; border-bottom-left-radius: 5px; }
        .btn-group .btn:last-child { border-top-right-radius: 5px; border-bottom-right-radius: 5px; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-dark px-3 shadow-sm sticky-top">
        <span class="navbar-brand mb-0 h1 fw-bold"><i class="bi bi-shield-lock-fill text-warning"></i> Admin Dashboard</span>
        <a href="index.php" class="btn btn-outline-light btn-sm rounded-pill"><i class="bi bi-house-door"></i> กลับหน้าแผนที่</a>
    </nav>

    <div class="admin-header mb-4 text-center">
        <div class="container">
            <h2 class="fw-bold mb-2">ระบบจัดการและตรวจสอบข้อมูล</h2>
            <p class="mb-0 text-secondary">ดูแลและกรองข้อมูลให้ MarketMap ปลอดภัยจากสแปม</p>
        </div>
    </div>

    <div class="container pb-5">
        <?php if($message): ?>
            <div class="alert alert-danger rounded-pill"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo $message; ?></div>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-4 justify-content-center border-0" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="user-tab" data-bs-toggle="tab" data-bs-target="#user-pane" type="button" role="tab">
                    <i class="bi bi-person-badge-fill text-primary"></i> เจ้าของตลาด
                    <?php if($req_users && $req_users->num_rows > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-1"><?php echo $req_users->num_rows; ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="market-tab" data-bs-toggle="tab" data-bs-target="#market-pane" type="button" role="tab">
                    <i class="bi bi-geo-alt-fill text-success"></i> ตลาดชุมชน 
                    <?php if($req_markets && $req_markets->num_rows > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-1"><?php echo $req_markets->num_rows; ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="report-tab" data-bs-toggle="tab" data-bs-target="#report-pane" type="button" role="tab">
                    <i class="bi bi-exclamation-triangle-fill text-danger"></i> แจ้งปัญหา 
                    <?php if($req_reports && $req_reports->num_rows > 0): ?>
                        <span class="badge bg-danger rounded-pill ms-1"><?php echo $req_reports->num_rows; ?></span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>

        <div class="tab-content" id="adminTabsContent">
            
            <div class="tab-pane fade show active" id="user-pane" role="tabpanel">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-muted small">
                                    <tr>
                                        <th class="ps-4">ชื่อผู้ใช้</th>
                                        <th>เบอร์ติดต่อ</th>
                                        <th>ตลาดที่ขอสร้าง</th>
                                        <th class="text-center">เอกสารยืนยัน</th>
                                        <th class="text-center pe-4">การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($req_users && $req_users->num_rows > 0): ?>
                                        <?php while($row = $req_users->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-primary"><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($row['username']); ?></td>
                                                <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                                <td>
                                                    <?php 
                                                        $m_id = $row['market_id'];
                                                        if($m_id) {
                                                            // 🌟 อัปเดต: ดึงพิกัด lat, lng มาด้วยเพื่อสร้างลิงก์ดูพิกัด
                                                            $m_res = $conn->query("SELECT name, lat, lng FROM markets WHERE id=$m_id");
                                                            if($m_res && $m_res->num_rows > 0) {
                                                                $m_data = $m_res->fetch_assoc();
                                                                echo "<span class='badge bg-success shadow-sm mb-1'>".htmlspecialchars($m_data['name'])."</span><br>";
                                                                echo "<a href='https://maps.google.com/?q=".$m_data['lat'].",".$m_data['lng']."' target='_blank' class='badge bg-info text-dark text-decoration-none'><i class='bi bi-geo-alt-fill'></i> เช็คพิกัดตลาด</a>";
                                                            } else { echo "-"; }
                                                        } else { echo "-"; }
                                                    ?>
                                                </td>
                                                <td class="text-center">
                                                    <a href="uploads/documents/<?php echo $row['document_image']; ?>" target="_blank">
                                                        <img src="uploads/documents/<?php echo $row['document_image']; ?>" class="doc-thumbnail" alt="เอกสาร">
                                                    </a>
                                                </td>
                                                <td class="text-center pe-4">
                                                    <div class="btn-group shadow-sm">
                                                        <a href="admin_verify.php?action=approve_user&id=<?php echo $row['id']; ?>" class="btn btn-success btn-sm"><i class="bi bi-check-lg"></i> อนุมัติ</a>
                                                        <a href="admin_verify.php?action=reject_user&id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('ปฏิเสธและลบข้อมูลนี้ทิ้ง?');"><i class="bi bi-x-lg"></i> ปฏิเสธ</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3 text-light"></i><h6 class="fw-bold">ไม่มีคำร้องใหม่</h6></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="market-pane" role="tabpanel">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-muted small">
                                    <tr>
                                        <th class="ps-4">ชื่อตลาด</th>
                                        <th>รายละเอียด</th>
                                        <th>คนเพิ่มข้อมูล</th>
                                        <th class="text-center">การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($req_markets && $req_markets->num_rows > 0): ?>
                                        <?php while($row = $req_markets->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-success"><?php echo htmlspecialchars($row['name']); ?></td>
                                                <td><p class="mb-0 small text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($row['details']); ?></p></td>
                                                <td><span class="badge bg-light text-dark border">@<?php echo htmlspecialchars($row['created_by']); ?></span></td>
                                                <td class="text-center pe-4">
                                                    
                                                    <div class="btn-group shadow-sm">
                                                        
                                                        <a href="https://maps.google.com/?q=<?php echo $row['lat']; ?>,<?php echo $row['lng']; ?>" target="_blank" class="btn btn-info btn-sm text-white" title="ดูพิกัดบน Google Maps">
                                                            <i class="bi bi-geo-alt-fill"></i>
                                                        </a>
                                                        
                                                        <a href="edit_market.php?id=<?php echo $row['id']; ?>" class="btn btn-warning btn-sm text-dark" title="แก้ไข / ขยับหมุดให้ตรง">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </a>
                                                        
                                                        <a href="admin_verify.php?action=approve_market&id=<?php echo $row['id']; ?>" class="btn btn-success btn-sm" title="อนุมัติให้โชว์บนแผนที่">
                                                            <i class="bi bi-check-lg"></i> อนุมัติ
                                                        </a>
                                                        
                                                        <a href="admin_verify.php?action=reject_market&id=<?php echo $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('ลบตลาดสแปมนี้ทิ้ง?');" title="ปฏิเสธ / ลบทิ้ง">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>

                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center py-5 text-muted"><i class="bi bi-check2-circle fs-1 d-block mb-3 text-light"></i><h6 class="fw-bold">ไม่มีตลาดรออนุมัติ</h6></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="report-pane" role="tabpanel">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light text-muted small">
                                    <tr>
                                        <th class="ps-4">ชื่อตลาดที่ถูกแจ้ง</th>
                                        <th>หัวข้อปัญหา</th>
                                        <th>รายละเอียด</th>
                                        <th>ผู้แจ้ง</th>
                                        <th class="text-center pe-4">การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($req_reports && $req_reports->num_rows > 0): ?>
                                        <?php while($row = $req_reports->fetch_assoc()): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-danger">
                                                    <?php echo $row['market_name'] ? htmlspecialchars($row['market_name']) : '<span class="text-muted">(ตลาดถูกลบไปแล้ว)</span>'; ?>
                                                </td>
                                                <td><span class="badge bg-warning text-dark border"><?php echo htmlspecialchars($row['report_type']); ?></span></td>
                                                <td><p class="mb-0 small text-muted text-truncate" style="max-width: 200px;"><?php echo htmlspecialchars($row['details']); ?></p></td>
                                                <td><small class="text-muted">@<?php echo htmlspecialchars($row['reporter_username']); ?></small></td>
                                                <td class="text-center pe-4">
                                                    <div class="btn-group shadow-sm">
                                                        <?php if($row['market_id']): ?>
                                                            <a href="edit_market.php?id=<?php echo $row['market_id']; ?>" class="btn btn-primary btn-sm" title="เข้าไปแก้ไขข้อมูลตลาดนี้">
                                                                <i class="bi bi-pencil-square"></i> แก้ไขตลาด
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="admin_verify.php?action=dismiss_report&id=<?php echo $row['id']; ?>" class="btn btn-secondary btn-sm" onclick="return confirm('เพิกเฉยต่อการแจ้งเตือนนี้?');" title="ปิดแจ้งเตือน">
                                                            <i class="bi bi-check-all"></i> เพิกเฉย
                                                        </a>
                                                        <?php if($row['market_id']): ?>
                                                            <a href="admin_verify.php?action=delete_reported_market&id=<?php echo $row['id']; ?>&market_id=<?php echo $row['market_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('⚠️ ยืนยันการลบตลาดนี้ทิ้งออกจากแผนที่? ร้านค้าด้านในจะถูกลบทั้งหมด!');" title="ลบตลาดนี้ทิ้ง">
                                                                <i class="bi bi-trash"></i> ลบตลาด
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-shield-check fs-1 d-block mb-3 text-light"></i><h6 class="fw-bold">ยังไม่มีการแจ้งปัญหาเข้ามา</h6></td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>