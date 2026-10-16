<?php
session_start();
include 'db.php';

// 1. เช็คสิทธิ์: ต้องเป็น Admin เท่านั้นถึงเข้าได้
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo "<script>alert('ไม่มีสิทธิ์เข้าถึง! เฉพาะผู้ดูแลระบบเท่านั้น'); window.location='index.php';</script>";
    exit();
}

// 2. ดึงข้อมูลสถิติ (Analytics) มาโชว์
// นับจำนวนผู้ใช้งาน
$users_count = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];

// นับจำนวนตลาด (เฉพาะที่อนุมัติแล้ว)
$markets_count = $conn->query("SELECT COUNT(*) as total FROM markets WHERE status='approved'")->fetch_assoc()['total'];

// นับจำนวนร้านค้าทั้งหมด
$shops_count = $conn->query("SELECT COUNT(*) as total FROM shops")->fetch_assoc()['total'];

// นับงานที่รอแอดมินตรวจสอบ (Pending Tasks)
$pending_m = $conn->query("SELECT COUNT(*) as total FROM markets WHERE status='pending'")->fetch_assoc()['total'];
$pending_u = $conn->query("SELECT COUNT(*) as total FROM verify_requests WHERE status='pending'")->fetch_assoc()['total'];
$pending_r = $conn->query("SELECT COUNT(*) as total FROM reports WHERE status='pending'")->fetch_assoc()['total'];
$total_pending = $pending_m + $pending_u + $pending_r;

// ดึงรายชื่อตลาด 5 อันดับล่าสุดที่เพิ่งเพิ่มเข้ามา
$recent_markets = $conn->query("SELECT name, created_by, created_at, status FROM markets ORDER BY created_at DESC LIMIT 5");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MarketMap</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        body { background-color: #f4f6f9; font-family: 'Prompt', sans-serif; overflow-x: hidden; }
        
        /* สไตล์ Sidebar (เมนูด้านข้าง) */
        .sidebar {
            min-height: 100vh;
            background: #212529;
            color: white;
            transition: all 0.3s;
        }
        .sidebar-link {
            color: #adb5bd;
            text-decoration: none;
            padding: 12px 20px;
            display: block;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: 0.2s;
        }
        .sidebar-link:hover, .sidebar-link.active {
            background-color: #0d6efd;
            color: white;
        }
        
        /* สไตล์การ์ดสถิติ */
        .stat-card { border-radius: 15px; border: none; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .icon-box { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; }
    </style>
</head>
<body>

    <div class="d-flex">
        <div class="sidebar p-3 d-none d-md-block" style="width: 260px;">
            <h4 class="text-white fw-bold mb-4 text-center mt-2">
                <i class="bi bi-shield-lock-fill text-warning"></i> Admin Panel
            </h4>
            
            <p class="text-muted small text-uppercase fw-bold mb-2 px-3">เมนูหลัก</p>
            <a href="admin_dashboard.php" class="sidebar-link active"><i class="bi bi-grid-fill me-2"></i> ภาพรวม (Dashboard)</a>
            <a href="admin_verify.php" class="sidebar-link d-flex justify-content-between align-items-center">
                <span><i class="bi bi-check-square-fill me-2"></i> ศูนย์ตรวจสอบ</span>
                <?php if($total_pending > 0): ?>
                    <span class="badge bg-danger rounded-pill"><?php echo $total_pending; ?></span>
                <?php endif; ?>
            </a>
            
            <p class="text-muted small text-uppercase fw-bold mt-4 mb-2 px-3">จัดการข้อมูล</p>
            <a href="manage_users.php" class="sidebar-link"><i class="bi bi-people-fill me-2"></i> จัดการผู้ใช้งาน</a>
            <a href="manage_markets.php" class="sidebar-link"><i class="bi bi-shop me-2"></i> จัดการตลาดทั้งหมด</a>
            
            <hr class="text-secondary mt-5">
            <a href="index.php" class="btn btn-outline-light w-100 rounded-pill"><i class="bi bi-house-door"></i> กลับหน้าแผนที่</a>
        </div>

        <div class="flex-grow-1 p-4 w-100">
            
            <div class="d-md-none bg-dark text-white p-3 rounded-3 mb-4 d-flex justify-content-between align-items-center">
                <h5 class="m-0 fw-bold"><i class="bi bi-shield-lock-fill text-warning"></i> Admin Panel</h5>
                <a href="index.php" class="btn btn-sm btn-outline-light rounded-pill">กลับแผนที่</a>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold text-dark m-0">ภาพรวมระบบ (Overview)</h3>
                <div class="text-muted small"><i class="bi bi-person-circle"></i> สวัสดี, แอดมิน <?php echo $_SESSION['user_id']; ?></div>
            </div>

            <div class="row g-4 mb-5">
                
                <div class="col-6 col-lg-3">
                    <div class="card stat-card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div><p class="text-muted mb-0 fw-bold">ผู้ใช้งานทั้งหมด</p><h2 class="fw-bold m-0"><?php echo number_format($users_count); ?></h2></div>
                                <div class="icon-box bg-primary bg-opacity-10 text-primary"><i class="bi bi-people-fill"></i></div>
                            </div>
                            <small class="text-muted">สมาชิกลงทะเบียนแล้ว</small>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card stat-card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div><p class="text-muted mb-0 fw-bold">ตลาดบนแผนที่</p><h2 class="fw-bold m-0"><?php echo number_format($markets_count); ?></h2></div>
                                <div class="icon-box bg-success bg-opacity-10 text-success"><i class="bi bi-pin-map-fill"></i></div>
                            </div>
                            <small class="text-muted">ตลาดที่อนุมัติแล้ว</small>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card stat-card shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div><p class="text-muted mb-0 fw-bold">ร้านค้าทั้งหมด</p><h2 class="fw-bold m-0"><?php echo number_format($shops_count); ?></h2></div>
                                <div class="icon-box bg-info bg-opacity-10 text-info"><i class="bi bi-shop"></i></div>
                            </div>
                            <small class="text-muted">ร้านค้าในทุกตลาด</small>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-lg-3">
                    <div class="card stat-card shadow-sm h-100 border border-danger border-opacity-25" style="background-color: #fff5f5;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div><p class="text-danger mb-0 fw-bold">รอตรวจสอบ</p><h2 class="fw-bold m-0 text-danger"><?php echo number_format($total_pending); ?></h2></div>
                                <div class="icon-box bg-danger bg-opacity-10 text-danger"><i class="bi bi-bell-fill"></i></div>
                            </div>
                            <a href="admin_verify.php" class="btn btn-sm btn-danger w-100 rounded-pill mt-1 fw-bold">ไปจัดการเลย <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>

            </div>

            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
                    <h5 class="fw-bold"><i class="bi bi-clock-history"></i> ตลาด 5 อันดับล่าสุดที่ถูกเพิ่ม</h5>
                </div>
                <div class="card-body px-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="text-muted small">
                                <tr>
                                    <th>ชื่อตลาด</th>
                                    <th>เพิ่มโดย</th>
                                    <th>วันที่เพิ่ม</th>
                                    <th>สถานะ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_markets && $recent_markets->num_rows > 0): ?>
                                    <?php while($m = $recent_markets->fetch_assoc()): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($m['name']); ?></td>
                                            <td>@<?php echo htmlspecialchars($m['created_by']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($m['created_at'])); ?></td>
                                            <td>
                                                <?php if($m['status'] == 'approved'): ?>
                                                    <span class="badge bg-success rounded-pill">อนุมัติแล้ว</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark rounded-pill">รอตรวจสอบ</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-3">ยังไม่มีข้อมูลตลาด</td></tr>
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