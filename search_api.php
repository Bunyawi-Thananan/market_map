<?php
include 'db.php';

$q = isset($_GET['q']) ? $conn->real_escape_string(trim($_GET['q'])) : '';
$results = [];

if ($q !== '') {
    // 1. ค้นหาจาก "ชื่อตลาด"
    $sql_m = "SELECT id, name, lat, lng, 'market' as type, '' as category FROM markets WHERE status = 'approved' AND name LIKE '%$q%' LIMIT 5";
    $res_m = $conn->query($sql_m);
    if($res_m) {
        while($row = $res_m->fetch_assoc()) {
            $results[] = $row;
        }
    }

    // 2. ค้นหาจาก "ชื่อร้านค้า" หรือ "หมวดหมู่ร้านค้า"
    // (ดึงพิกัด lat, lng มาจากตลาดที่ร้านนั้นตั้งอยู่)
    $check_shops = $conn->query("SHOW TABLES LIKE 'shops'");
    if ($check_shops->num_rows > 0) {
        $sql_s = "SELECT s.id, s.shop_name as name, s.category, m.lat, m.lng, m.name as market_name, 'shop' as type 
                  FROM shops s 
                  JOIN markets m ON s.market_id = m.id 
                  WHERE m.status = 'approved' AND (s.shop_name LIKE '%$q%' OR s.category LIKE '%$q%') LIMIT 5";
        
        $res_s = $conn->query($sql_s);
        if($res_s) {
            while($row = $res_s->fetch_assoc()) {
                // เติมวงเล็บให้รู้ว่าร้านนี้อยู่ตลาดไหน
                $row['name'] = $row['name'] . ' <small class="text-muted">(ตลาด: ' . $row['market_name'] . ')</small>';
                $results[] = $row;
            }
        }
    }
}

header('Content-Type: application/json');
echo json_encode($results);
?>