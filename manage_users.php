<?php
session_start();
include 'db.php';

// 1. เช็คสิทธิ์: ต้องเป็น Admin เท่านั้น
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('ไม่มีสิทธิ์เข้าถึง!'); window.location='index.php';</script>";
    exit();
}

$message = "";
$current_admin = $_SESSION['user_id'];

// ==========================================
// ระบบเปลี่ยนสิทธิ์ผู้ใช้งาน (Change Role)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'change_role') {
    $target_user = $conn->real_escape_string($_POST['target_user']);
    $new_role = $conn->real_escape_string($_POST['new_role']);

    // ป้องกันแอดมินเปลี่ยนสิทธิ์ตัวเอง (เดี๋ยวจะล็อกอินเข้าหลังบ้านไม่ได้)
    if ($target_user === $current_admin) {
        $message = "⚠️ ไม่สามารถเปลี่ยนสิทธิ์ของตัวเองได้ครับ";
    } else {
        $sql = "UPDATE users SET role = '$new_role' WHERE username = '$target_user'";
        if ($conn->query($sql) === TRUE) {
            echo "<script>alert('✅ อัปเดตสิทธิ์ของ @$target_user เป็น $new_role เรียบร้อยแล้ว!'); window.location='manage_users.php';</script>";
            exit();
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}

// ==========================================
// ระบบลบบัญชีผู้ใช้ (Delete User)
// ==========================================
if (isset($_POST['action']) && $_POST['action'] == 'delete_user') {
    $target_user = $conn->real_escape_string($_POST['target_user']);
    
    if ($target_user === $current_admin) {
        $message = "⚠️ ไม่สามารถลบบัญชีของตัวเองได้ครับ";
    } else {
        // ลบบัญชีออกจากระบบ
        $sql = "DELETE FROM users WHERE username = '$target_user'";
        if ($conn->query($sql) === TRUE) {
            echo "<script>alert('🗑️ ลบบัญชีผู้ใช้ @$target_user ถาวรเรียบร้อยแล้ว'); window.location='manage_users.php';</script>";
            exit();
        } else {
            $message = "Error: " . $conn->error;
        }
    }
}

// ดึงข้อมูลผู้ใช้ทั้งหมด (เรียงตามสิทธิ์ แอดมินขึ้นก่อน)
$users_res = $conn->query("SELECT * FROM users ORDER BY FIELD(role, 'admin', 'market_owner', 'shop', 'user'), username ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้งาน - Admin Panel</title>
    
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
            <a href="manage_users.php" class="sidebar-link active"><i class="bi bi-people-fill me-2"></i> จัดการผู้ใช้งาน</a>
            <a href="manage_markets.php" class="sidebar-link"><i class="bi bi-shop me-2"></i> จัดการตลาดทั้งหมด</a>
            
            <hr class="text-secondary mt-5">
            <a href="index.php" class="btn btn-outline-light w-100 rounded-pill"><i class="bi bi-house-door"></i> กลับหน้าแผนที่</a>
        </div>

        <div class="flex-grow-1 p-4 w-100">
            
            <div class="d-md-none bg-dark text-white p-3 rounded-3 mb-4 d-flex justify-content-between align-items-center">
                <h5 class="m-0 fw-bold"><i class="bi bi-people-fill"></i> จัดการผู้ใช้</h5>
                <a href="admin_dashboard.php" class="btn btn-sm btn-outline-light rounded-pill">กลับหน้าหลัก</a>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold text-dark m-0">จัดการบัญชีผู้ใช้งาน</h3>
                <span class="badge bg-primary rounded-pill px-3 py-2">รวม <?php echo $users_res->num_rows; ?> บัญชี</span>
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
                                    <th class="ps-4 py-3">ชื่อผู้ใช้ (Username)</th>
                                    <th>สถานะปัจจุบัน (Role)</th>
                                    <th>เปลี่ยนระดับสิทธิ์</th>
                                    <th class="text-center pe-4">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users_res && $users_res->num_rows > 0): ?>
                                    <?php while($u = $users_res->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold">
                                                <i class="bi bi-person-circle text-secondary me-1"></i> @<?php echo htmlspecialchars($u['username']); ?>
                                                <?php if($u['username'] == $current_admin): ?>
                                                    <span class="badge bg-danger ms-1" style="font-size: 0.6rem;">(คุณ)</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    if($u['role'] == 'admin') echo '<span class="badge bg-danger rounded-pill"><i class="bi bi-star-fill"></i> Admin</span>';
                                                    elseif($u['role'] == 'market_owner') echo '<span class="badge bg-primary rounded-pill"><i class="bi bi-briefcase-fill"></i> เจ้าของตลาด</span>';
                                                    elseif($u['role'] == 'shop') echo '<span class="badge bg-info text-dark rounded-pill"><i class="bi bi-shop"></i> พ่อค้า/แม่ค้า</span>';
                                                    else echo '<span class="badge bg-secondary rounded-pill"><i class="bi bi-person"></i> ผู้ใช้ทั่วไป</span>';
                                                ?>
                                            </td>
                                            
                                            <td style="width: 250px;">
                                                <?php if($u['username'] != $current_admin): ?>
                                                    <form method="post" class="d-flex align-items-center gap-2">
                                                        <input type="hidden" name="action" value="change_role">
                                                        <input type="hidden" name="target_user" value="<?php echo htmlspecialchars($u['username']); ?>">
                                                        <select name="new_role" class="form-select form-select-sm shadow-sm" onchange="this.form.submit()">
                                                            <option value="user" <?php if($u['role']=='user') echo 'selected'; ?>>ผู้ใช้ทั่วไป</option>
                                                            <option value="shop" <?php if($u['role']=='shop') echo 'selected'; ?>>พ่อค้า/แม่ค้า</option>
                                                            <option value="market_owner" <?php if($u['role']=='market_owner') echo 'selected'; ?>>เจ้าของตลาด</option>
                                                            <option value="admin" <?php if($u['role']=='admin') echo 'selected'; ?>>Admin (ดูแลระบบ)</option>
                                                        </select>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small">ผู้ดูแลระบบสูงสุด</span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="text-center pe-4">
                                                <?php if($u['username'] != $current_admin): ?>
                                                    <form method="post" onsubmit="return confirm('⚠️ แน่ใจหรือไม่ที่จะลบบัญชี @<?php echo $u['username']; ?> ถาวร?');">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="target_user" value="<?php echo htmlspecialchars($u['username']); ?>">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill fw-bold">
                                                            <i class="bi bi-trash-fill"></i> ลบบัญชี
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">ไม่พบข้อมูลผู้ใช้งาน</td></tr>
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