<?php
session_start();
include 'db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}
$market_id = intval($_GET['id']);

// 1. ดึงข้อมูลตลาด
$sql_market = "SELECT * FROM markets WHERE id = $market_id";
$result_market = $conn->query($sql_market);

if ($result_market->num_rows == 0) {
    echo "<script>alert('ไม่พบข้อมูลตลาดนี้'); window.location='index.php';</script>";
    exit();
}
$market = $result_market->fetch_assoc();

// 2. ดึงข้อมูลร้านค้าทั้งหมด
$sql_shops = "SELECT * FROM shops WHERE market_id = $market_id ORDER BY created_at DESC";
$result_shops = $conn->query($sql_shops);
$shops = [];
if ($result_shops && $result_shops->num_rows > 0) {
    while($row = $result_shops->fetch_assoc()) {
        $shops[] = $row;
    }
}

$is_logged_in = isset($_SESSION['user_id']);
$current_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';

// หมวดหมู่ร้านค้ามาตรฐาน
$categories = ['ผักสด', 'ผลไม้', 'ของสด/เนื้อสัตว์', 'อาหารปรุงสำเร็จ', 'ขนม/ของหวาน', 'เครื่องดื่ม', 'เสื้อผ้า/ของใช้', 'ทั่วไป'];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($market['name']); ?> - MarketMap</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        body { background-color: #f0f2f5; padding-bottom: 50px; }
        .market-header { 
            background: linear-gradient(135deg, #198754 0%, #20c997 100%); 
            color: white; padding: 50px 20px; border-radius: 0 0 30px 30px; 
            box-shadow: 0 10px 20px rgba(25, 135, 84, 0.2); 
        }
        
        /* ดีไซน์การ์ดร้านค้าของคุณ (คงไว้เหมือนเดิม) */
        .shop-card { 
            border: none; border-radius: 15px; overflow: hidden; transition: all 0.3s ease; 
        }
        .shop-card:hover { 
            transform: translateY(-5px); box-shadow: 0 15px 30px rgba(0,0,0,0.1) !important; 
        }
        .shop-img-wrapper {
            width: 130px; min-width: 130px; height: 130px; overflow: hidden; border-radius: 10px;
        }
        .shop-img { width: 100%; height: 100%; object-fit: cover; }
        .shop-img-placeholder {
            width: 100%; height: 100%; background-color: #e9ecef; display: flex;
            align-items: center; justify-content: center; color: #adb5bd; font-size: 2.5rem;
        }
        .shop-description {
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }

        /* ตกแต่งระบบค้นหาหมวดหมู่ที่เพิ่มมาใหม่ */
        .category-chip { cursor: pointer; transition: all 0.2s; white-space: nowrap; }
        .category-chip:hover, .category-chip.active {
            background-color: #198754 !important; color: white !important; border-color: #198754 !important;
        }
        .hidden-shop { display: none !important; }
    </style>
</head>
<body>

    <nav class="navbar navbar-light bg-white px-3 shadow-sm sticky-top">
        <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill">
            <i class="bi bi-arrow-left"></i> หน้าแผนที่
        </a>
        <span class="navbar-brand mb-0 h1 text-success fw-bold"><i class="bi bi-geo-alt-fill"></i> MarketMap</span>
    </nav>

    <div class="market-header mb-4 text-center">
        <div class="container">
            <h1 class="fw-bold mb-3"><?php echo htmlspecialchars($market['name']); ?></h1>
            <p class="mb-2 fs-5"><i class="bi bi-clock-history"></i> เปิด: <?php echo htmlspecialchars($market['open_time']); ?></p>
            <p class="mb-3 text-light opacity-75"><i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($market['details']); ?></p>
        </div>
    </div>

    <div class="container pb-5" style="max-width: 800px;">
        
        <div class="card shadow-sm border-0 rounded-4 mb-4 p-3 sticky-top" style="top: 70px; z-index: 100;">
            <div class="input-group mb-3">
                <span class="input-group-text bg-white border-end-0 text-success"><i class="bi bi-search"></i></span>
                <input type="text" id="searchShopInput" class="form-control border-start-0 shadow-none py-2" placeholder="ค้นหาร้านค้า หรือ สินค้าในตลาดนี้..." onkeyup="filterShops()">
            </div>
            <div class="d-flex overflow-auto pb-2 gap-2" id="category-container" style="scrollbar-width: none;">
                <span class="badge bg-white text-success border border-success category-chip active p-2" onclick="setCategory('ทั้งหมด', this)"><i class="bi bi-grid-fill"></i> ทั้งหมด</span>
                <?php foreach($categories as $cat): ?>
                    <span class="badge bg-white text-secondary border category-chip p-2" onclick="setCategory('<?php echo $cat; ?>', this)"><?php echo $cat; ?></span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4 px-2">
            <h4 class="fw-bold text-dark m-0"><i class="bi bi-shop"></i> ร้านค้าทั้งหมด</h4>
            <span class="badge bg-dark rounded-pill fs-6 py-2 px-3"><span id="shopCount"><?php echo count($shops); ?></span> ร้าน</span>
        </div>

        <?php if ($is_logged_in): 
            $is_this_market_owner = ($_SESSION['user_id'] === $market['created_by']);
            $is_admin = ($current_role === 'admin');
            
            // เช็คสถานะคำร้องของ User คนนี้
            $is_approved_seller = false;
            $has_pending = false;
            $req_check = $conn->query("SELECT status FROM shop_requests WHERE market_id=$market_id AND username='{$_SESSION['user_id']}'");
            if ($req_check && $req_check->num_rows > 0) {
                $status = $req_check->fetch_assoc()['status'];
                if ($status == 'approved') $is_approved_seller = true;
                if ($status == 'pending') $has_pending = true;
            }

            // นับจำนวนคำร้องที่รออนุมัติ (ให้เจ้าของตลาดเห็น)
            $pending_count = 0;
            if ($is_this_market_owner || $is_admin) {
                $pending_count = $conn->query("SELECT COUNT(*) as c FROM shop_requests WHERE market_id=$market_id AND status='pending'")->fetch_assoc()['c'];
            }
        ?>
            <div class="card mb-4 border-0 shadow-sm" style="background-color: #f8f9fa; border-radius: 15px;">
                <div class="card-body text-center py-3">
                    
                    <?php if ($is_this_market_owner || $is_admin): ?>
                        <h6 class="text-dark fw-bold mb-3"><i class="bi bi-gear-fill"></i> จัดการร้านค้า (มุมมองเจ้าของตลาด)</h6>
                        <a href="add_shop.php?market_id=<?php echo $market_id; ?>" class="btn btn-success btn-sm rounded-pill px-3 shadow-sm me-2"><i class="bi bi-plus-circle"></i> เพิ่มร้านค้าให้ลูกบ้าน</a>
                        <a href="manage_shop_requests.php?market_id=<?php echo $market_id; ?>" class="btn btn-warning btn-sm rounded-pill px-3 shadow-sm">
                            <i class="bi bi-person-lines-fill"></i> คำร้องขอเปิดร้าน 
                            <?php if($pending_count > 0): ?><span class="badge bg-danger rounded-pill ms-1"><?php echo $pending_count; ?></span><?php endif; ?>
                        </a>
                    
                    <?php elseif ($is_approved_seller): ?>
                        <h6 class="text-success fw-bold mb-2"><i class="bi bi-patch-check-fill"></i> คุณได้รับอนุมัติให้ขายในตลาดนี้แล้ว!</h6>
                        <a href="add_shop.php?market_id=<?php echo $market_id; ?>" class="btn btn-success btn-sm rounded-pill px-4 shadow-sm"><i class="bi bi-plus-circle"></i> เพิ่มร้านค้าของคุณเลย</a>
                    
                    <?php elseif ($has_pending): ?>
                        <div class="text-warning fw-bold"><i class="bi bi-hourglass-split"></i> คำร้องขอเปิดร้านของคุณกำลังรอเจ้าของตลาดอนุมัติครับ</div>
                    
                    <?php else: ?>
                        <h6 class="text-dark fw-bold mb-3">ต้องการลงขายของในตลาดนี้ใช่ไหม?</h6>
                        <a href="request_shop.php?market_id=<?php echo $market_id; ?>" class="btn btn-primary btn-sm rounded-pill px-4 shadow-sm"><i class="bi bi-shop"></i> ยื่นคำร้องขอเปิดร้าน</a>
                    <?php endif; ?>

                </div>
            </div>
        <?php endif; ?>

        <div class="row gx-0 gy-3" id="shopList">
            <?php if (count($shops) > 0): ?>
                <?php foreach($shops as $shop): 
                    // รองรับทั้งชื่อคอลัมน์เก่าและใหม่
                    $s_name = $shop['shop_name'] ?? 'ไม่มีชื่อร้าน';
                    $s_desc = $shop['details'] ?? ($shop['description'] ?? '');
                    $s_owner = $shop['created_by'] ?? ($shop['owner_username'] ?? 'ไม่ระบุ');
                    $s_cat = $shop['category'] ?? 'ทั่วไป';
                ?>
                    <div class="col-12 shop-item" 
                         data-name="<?php echo strtolower(htmlspecialchars($s_name)); ?>" 
                         data-details="<?php echo strtolower(htmlspecialchars($s_desc)); ?>"
                         data-category="<?php echo htmlspecialchars($s_cat); ?>">
                        
                        <div class="card shop-card shadow-sm">
                            <div class="card-body p-3 d-flex flex-row align-items-center">
                                
                                <div class="shop-img-wrapper me-3 shadow-sm">
                                    <?php if(!empty($shop['image'])): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($shop['image']); ?>" class="shop-img" alt="รูปร้าน">
                                    <?php else: ?>
                                        <div class="shop-img-placeholder"><i class="bi bi-shop"></i></div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h5 class="text-success fw-bold mb-1"><?php echo htmlspecialchars($s_name); ?></h5>
                                        
                                        <?php if ($is_logged_in && ($_SESSION['user_id'] == $s_owner || $current_role == 'admin')): ?>
                                            <a href="edit_shop.php?id=<?php echo $shop['id']; ?>" class="btn btn-outline-warning btn-sm rounded-pill px-3" style="font-size: 0.8rem;">
                                                <i class="bi bi-pencil-square"></i> แก้ไข
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <span class="badge bg-light text-dark border"><i class="bi bi-tag-fill text-warning"></i> <?php echo htmlspecialchars($s_cat); ?></span>
                                    </div>
                                    
                                    <p class="text-secondary mb-2 shop-description" style="font-size: 0.95rem;">
                                        <?php echo nl2br(htmlspecialchars($s_desc)); ?>
                                    </p>
                                    
                                    <div class="mt-auto text-end">
                                        <small class="text-muted" style="font-size: 0.75rem;">เพิ่มโดย @<?php echo htmlspecialchars($s_owner); ?></small>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 bg-white rounded-3 shadow-sm mt-3" id="noShopMessage">
                    <i class="bi bi-basket2 text-muted" style="font-size: 4rem;"></i>
                    <h4 class="mt-3 text-muted fw-bold">ยังไม่มีร้านค้าในตลาดนี้</h4>
                    <p class="text-secondary">มาเป็นร้านแรกที่เพิ่มข้อมูลกันเถอะ!</p>
                </div>
            <?php endif; ?>
            
            <div class="col-12 text-center py-5 d-none bg-white rounded-3 shadow-sm" id="notFoundMessage">
                <i class="bi bi-search fs-1 text-muted d-block mb-3"></i>
                <h5 class="text-muted fw-bold">ไม่พบร้านค้าหรือสินค้าที่คุณค้นหา</h5>
            </div>
        </div>

    </div>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script>
        let currentCategory = 'ทั้งหมด';

        function setCategory(cat, element) {
            currentCategory = cat;
            let chips = document.querySelectorAll('.category-chip');
            chips.forEach(c => {
                c.classList.remove('active', 'text-success', 'border-success');
                c.classList.add('text-secondary');
            });
            element.classList.add('active', 'text-success', 'border-success');
            element.classList.remove('text-secondary');
            filterShops();
        }

        function filterShops() {
            let searchInput = document.getElementById('searchShopInput').value.toLowerCase();
            let shopItems = document.querySelectorAll('.shop-item');
            let visibleCount = 0;

            shopItems.forEach(item => {
                let name = item.getAttribute('data-name');
                let details = item.getAttribute('data-details');
                let category = item.getAttribute('data-category');

                let matchCategory = (currentCategory === 'ทั้งหมด' || category === currentCategory);
                let matchSearch = (name.includes(searchInput) || details.includes(searchInput));

                if (matchCategory && matchSearch) {
                    item.classList.remove('hidden-shop');
                    visibleCount++;
                } else {
                    item.classList.add('hidden-shop');
                }
            });

            document.getElementById('shopCount').innerText = visibleCount;
            let notFoundMsg = document.getElementById('notFoundMessage');
            
            if (visibleCount === 0 && shopItems.length > 0) {
                notFoundMsg.classList.remove('d-none');
            } else {
                notFoundMsg.classList.add('d-none');
            }
        }
    </script>
</body>
</html>