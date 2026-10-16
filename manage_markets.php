<?php
session_start();
include 'db.php';

// 1. เช็คสิทธิ์: ต้องเป็น Admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('ไม่มีสิทธิ์เข้าถึง!'); window.location='index.php';</script>";
    exit();
}

$message = "";

// ==========================================
// ระบบลบตลาดแบบถอนรากถอนโคน (Master Delete)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'delete_market') {
    $m_id = intval($_POST['target_market']);
    
    // 1. ลบรูปร้านค้าทั้งหมด
    $shops_res = $conn->query("SELECT image FROM shops WHERE market_id = $m_id");
    if ($shops_res && $shops_res->num_rows > 0) {
        while($shop = $shops_res->fetch_assoc()) {
            if (!empty($shop['image']) && file_exists('uploads/' . $shop['image'])) unlink('uploads/' . $shop['image']);
        }
    }
    // 2. ลบข้อมูลร้านและคำร้อง
    $conn->query("DELETE FROM shops WHERE market_id = $m_id");
    $conn->query("DELETE FROM shop_requests WHERE market_id = $m_id");
    
    // 3. ลบรูปตลาด
    $img_res = $conn->query("SELECT image FROM markets WHERE id = $m_id");
    if ($img_res && $img_res->num_rows > 0) {
        $img_name = $img_res->fetch_assoc()['image'];
        if (!empty($img_name) && file_exists('uploads/' . $img_name)) unlink('uploads/' . $img_name);
    }
    
    // 4. ลบข้อมูลตลาด และอัปเดตสถานะการแจ้งปัญหา
    $conn->query("DELETE FROM markets WHERE id = $m_id");
    $conn->query("UPDATE reports SET status = 'resolved' WHERE market_id = $m_id");
    
    echo "<script>alert('🗑️ ลบตลาด และข้อมูลที่เกี่ยวข้องทั้งหมดเรียบร้อยแล้ว'); window.location='manage_markets.php';</script>";
    exit();
}

// ดึงข้อมูลตลาดทั้งหมด พร้อมนับจำนวนร้านค้าในตลาดนั้นๆ
$sql = "SELECT m.*, 
        (SELECT COUNT(*) FROM shops WHERE market_id = m.id) as shop_count 
        FROM markets m 
        ORDER BY m.created_at DESC";
$markets_res = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการตลาดทั้งหมด - Admin Panel</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        body { background-color: #f4f6f9; font-family: 'Prompt', sans-serif; overflow-x: hidden; }
        .sidebar { min-height: 100vh; background: #212529; color: white; }
        .sidebar-link { color: #adb5bd; text-decoration: none; padding: 12px 20px; display: block; border-radius: 8px; margin-bottom: 5px; transition: 0.2s; }
        .sidebar-link:hover, .sidebar-link.active { background-color: #0d6efd; color: white; }
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
        .market-img-thumb { width: 45px; height: 45px; object-fit: cover; border-radius: 8px; border: 1px solid #dee2e6; }
    </style>
</head>
<body>

    <div class="d-flex">
        <div class="sidebar p-3 d-none d-md-block" style="width: 260px;">
            <h4 class="text-white fw-bold mb-4 text-center mt-2">
                <i class="bi bi-shield-lock-fill text-warning"></i> Admin Panel
            </h4>
            
            <p class="text-muted small text-uppercase fw-bold mb-2 px-3">เมนูหลัก</p>
            <a href="admin_dashboard.php" class="sidebar-link"><i class="bi bi-grid-fill me-2"></i> ภาพรวม (Dashboard)</a>
            <a href="admin_verify.php" class="sidebar-link"><i class="bi bi-check-square-fill me-2"></i> ศูนย์ตรวจสอบ</a>
            
            <p class="text-muted small text-uppercase fw-bold mt-4 mb-2 px-3">จัดการข้อมูล</p>
            <a href="manage_users.php" class="sidebar-link"><i class="bi bi-people-fill me-2"></i> จัดการผู้ใช้งาน</a>
            <a href="manage_markets.php" class="sidebar-link active"><i class="bi bi-shop me-2"></i> จัดการตลาดทั้งหมด</a>
            
            <hr class="text-secondary mt-5">
            <a href="index.php" class="btn btn-outline-light w-100 rounded-pill"><i class="bi bi-house-door"></i> กลับหน้าแผนที่</a>
        </div>

        <div class="flex-grow-1 p-4 w-100">
            
            <div class="d-md-none bg-dark text-white p-3 rounded-3 mb-4 d-flex justify-content-between align-items-center">
                <h5 class="m-0 fw-bold"><i class="bi bi-shop"></i> จัดการตลาดทั้งหมด</h5>
                <a href="admin_dashboard.php" class="btn btn-sm btn-outline-light rounded-pill">กลับหน้าหลัก</a>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold text-dark m-0">คลังข้อมูลตลาด</h3>
                <span class="badge bg-success rounded-pill px-3 py-2">รวม <?php echo $markets_res->num_rows; ?> ตลาด</span>
            </div>

            <?php if($message): ?>
                <div class="alert alert-warning rounded-pill"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo $message; ?></div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle m-0">
                            <thead class="table-light text-muted small">
                                <tr>
                                    <th class="ps-4 py-3">รูปภาพ</th>
                                    <th>ชื่อตลาด / รายละเอียด</th>
                                    <th>คนสร้าง</th>
                                    <th class="text-center">จำนวนร้าน</th>
                                    <th class="text-center">สถานะ</th>
                                    <th class="text-center pe-4">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($markets_res && $markets_res->num_rows > 0): ?>
                                    <?php while($m = $markets_res->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <?php if(!empty($m['image'])): ?>
                                                    <img src="uploads/<?php echo $m['image']; ?>" class="market-img-thumb" alt="ปกตลาด">
                                                <?php else: ?>
                                                    <div class="bg-light text-muted d-flex align-items-center justify-content-center market-img-thumb">
                                                        <i class="bi bi-image"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <h6 class="fw-bold text-success mb-0"><?php echo htmlspecialchars($m['name']); ?></h6>
                                                <small class="text-muted d-inline-block text-truncate" style="max-width: 200px;">
                                                    <?php echo htmlspecialchars($m['details']); ?>
                                                </small>
                                            </td>
                                            <td><span class="badge bg-light text-dark border">@<?php echo htmlspecialchars($m['created_by']); ?></span></td>
                                            <td class="text-center">
                                                <span class="badge bg-primary rounded-pill"><?php echo $m['shop_count']; ?> ร้าน</span>
                                            </td>
                                            <td class="text-center">
                                                <?php if($m['status'] == 'approved'): ?>
                                                    <span class="badge bg-success rounded-pill px-2">ออนไลน์</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark rounded-pill px-2">รอตรวจ</span>
                                                <?php endif; ?>
                                                
                                                <?php if($m['is_verified'] == 1): ?>
                                                    <br><small class="text-primary fw-bold" style="font-size: 0.7rem;"><i class="bi bi-patch-check-fill"></i> ทางการ</small>
                                                <?php else: ?>
                                                    <br><small class="text-secondary fw-bold" style="font-size: 0.7rem;"><i class="bi bi-people-fill"></i> ชุมชน</small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center pe-4">
                                                <div class="btn-group shadow-sm">
                                                    <a href="https://maps.google.com/?q=<?php echo $m['lat']; ?>,<?php echo $m['lng']; ?>" target="_blank" class="btn btn-info btn-sm text-white" title="ดูพิกัด"><i class="bi bi-geo-alt-fill"></i></a>
                                                    <a href="edit_market.php?id=<?php echo $m['id']; ?>" class="btn btn-warning btn-sm text-dark" title="แก้ไขตลาด"><i class="bi bi-pencil-square"></i></a>
                                                    
                                                    <form method="post" class="d-inline" onsubmit="return confirm('⚠️ คำเตือน: ต้องการลบตลาดนี้ใช่ไหม?\n\n* ร้านค้าทั้งหมด <?php echo $m['shop_count']; ?> ร้าน จะถูกลบทิ้งถาวรด้วย!');">
                                                        <input type="hidden" name="action" value="delete_market">
                                                        <input type="hidden" name="target_market" value="<?php echo $m['id']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" title="ลบตลาด" style="border-top-left-radius: 0; border-bottom-left-radius: 0;">
                                                            <i class="bi bi-trash-fill"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-shop fs-1 d-block mb-2 text-light"></i> ไม่มีข้อมูลตลาดในระบบ</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
</body>
</html>