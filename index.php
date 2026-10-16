<?php
session_start();

// ระบบ Auto Logout (20 นาที)
if (isset($_SESSION['user_id'])) {
    $timeout = 1200; 
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout)) {
        session_unset(); session_destroy();
        header("Location: login.php?msg=timeout"); exit();
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}

$is_logged_in = isset($_SESSION['user_id']);
$current_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
$current_user_id = $is_logged_in ? $_SESSION['user_id'] : '';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarketMap - แผนที่ตลาด</title>
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/leaflet.css">
    <link rel="stylesheet" href="css/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    
    <style>
        body { padding: 0; margin: 0; }
        html, body, #map { height: 100vh; width: 100vw; }
        
        .leaflet-marker-icon { background: transparent !important; border: none !important; }
        .leaflet-shadow-pane { display: none !important; }
        .leaflet-popup-content-wrapper { padding: 0; overflow: hidden; border-radius: 15px; }
        .leaflet-popup-content { margin: 0; width: 280px !important; }
        .popup-card p { font-size: 0.9rem; margin-bottom: 5px; }

        #btn-locate {
            position: fixed; bottom: 30px; right: 20px; z-index: 1000; 
            width: 50px; height: 50px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            border: 2px solid #fff; background-color: white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2); cursor: pointer; transition: all 0.2s;
        }
        #btn-locate:hover { background-color: #f8f9fa; transform: scale(1.05); }

        /* ตกแต่งกล่องค้นหาลอยตัว */
        #search-container {
            position: fixed; top: 75px; left: 50%; transform: translateX(-50%);
            z-index: 1000; width: 90%; max-width: 450px;
        }
        .search-results-box {
            position: absolute; width: 100%; top: 100%; left: 0;
            background: white; border-radius: 0 0 10px 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            max-height: 300px; overflow-y: auto; display: none;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-light bg-white px-3 py-2 shadow-sm fixed-top d-flex justify-content-between align-items-center" style="z-index: 1001; width: 100%;">
        <span class="navbar-brand mb-0 h1 text-success fw-bold" style="font-size: 1.4rem;">
            <i class="bi bi-geo-alt-fill text-danger"></i> MarketMap
        </span>
        
        <div class="d-flex align-items-center gap-2">
            <?php if ($is_logged_in): ?>
                <span class="me-2 d-none d-md-inline text-muted small">
                    สวัสดี, <strong><?php echo htmlspecialchars($current_user_id); ?></strong>
                    <?php if($current_role == 'admin'): ?> <span class="badge bg-danger rounded-pill ms-1">Admin</span>
                    <?php elseif($current_role == 'market_owner'): ?> <span class="badge bg-primary rounded-pill ms-1">เจ้าของตลาด</span>
                    <?php elseif($current_role == 'shop'): ?> <span class="badge bg-info text-dark rounded-pill ms-1">พ่อค้า/แม่ค้า</span>
                    <?php endif; ?>
                </span>

                <?php if ($current_role == 'admin'): ?>
                    <a href="admin_dashboard.php" class="btn btn-dark btn-sm fw-bold rounded-pill shadow-sm"><i class="bi bi-speedometer2"></i> แผงควบคุม (Admin)</a>
                    <a href="add_market.php" class="btn btn-success btn-sm fw-bold rounded-pill shadow-sm"><i class="bi bi-plus-circle"></i> เพิ่มตลาด</a>
                <?php elseif ($current_role == 'market_owner'): ?>
                    <a href="add_market.php" class="btn btn-success btn-sm fw-bold rounded-pill shadow-sm"><i class="bi bi-plus-circle"></i> เพิ่มตลาด</a>
                <?php elseif ($current_role == 'user'): ?>
                    <a href="request_owner.php" class="btn btn-outline-primary btn-sm fw-bold rounded-pill shadow-sm"><i class="bi bi-file-earmark-person"></i> ขอเป็นเจ้าของ</a>
                    <a href="add_market.php" class="btn btn-outline-success btn-sm fw-bold rounded-pill shadow-sm"><i class="bi bi-pin-map"></i> เพิ่มตลาดชุมชน</a>
                <?php endif; ?>

                <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill ms-1" title="ออกจากระบบ"><i class="bi bi-box-arrow-right"></i> ออก</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-success btn-sm rounded-pill px-3 fw-bold shadow-sm">เข้าสู่ระบบ</a>
                <a href="register.php" class="btn btn-outline-success btn-sm rounded-pill px-3 fw-bold d-none d-sm-inline">สมัครสมาชิก</a>
            <?php endif; ?>
        </div>
    </nav>

    <div id="search-container">
        <div class="input-group shadow border rounded-pill bg-white overflow-hidden" style="border-color: #dee2e6 !important;">
            <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-success"></i></span>
            <input type="text" id="search-input" class="form-control border-0 shadow-none py-2" placeholder="ค้นหาตลาด, ร้านค้า, หรือหมวดหมู่อาหาร..." autocomplete="off">
            <button class="btn btn-light text-muted border-0 pe-3" id="clear-search" style="display:none;" onclick="clearSearch()"><i class="bi bi-x-circle-fill"></i></button>
        </div>
        <div id="search-results" class="search-results-box list-group"></div>
    </div>

    <div id="map" style="margin-top: 56px; height: calc(100vh - 56px);"></div>

    <button id="btn-locate" onclick="locateUser()" title="ตำแหน่งของฉัน">
        <i class="bi bi-crosshair" style="font-size: 1.5rem; color: #4285F4;"></i>
    </button>

    <script src="js/bootstrap.bundle.min.js"></script>
    <script src="js/leaflet.js"></script>

    <script>
        var map = L.map('map').setView([13.7563, 100.5018], 10); 
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', { attribution: 'OpenStreetMap' }).addTo(map);

        const currentUserId = "<?php echo $current_user_id; ?>";
        var userMarker, userCircle;

        // ==========================================
        // ระบบค้นหา (Search System)
        // ==========================================
        const searchInput = document.getElementById('search-input');
        const searchResults = document.getElementById('search-results');
        const clearBtn = document.getElementById('clear-search');

        searchInput.addEventListener('input', function() {
            let q = this.value.trim();
            if(q.length > 0) { clearBtn.style.display = 'block'; } else { clearBtn.style.display = 'none'; }
            
            if(q.length < 2) { searchResults.style.display = 'none'; return; }

            fetch('search_api.php?q=' + encodeURIComponent(q))
            .then(res => res.json())
            .then(data => {
                searchResults.innerHTML = '';
                if(data.length === 0) {
                    searchResults.innerHTML = '<div class="p-3 text-center text-muted small">ไม่พบผลลัพธ์ที่คุณค้นหา</div>';
                } else {
                    data.forEach(item => {
                        let icon = item.type === 'market' ? '<i class="bi bi-shop text-success fs-5"></i>' : '<i class="bi bi-basket2-fill text-warning fs-5"></i>';
                        let badge = item.category ? `<span class="badge bg-light text-dark border ms-2" style="font-size:0.65rem;">${item.category}</span>` : '';
                        let div = document.createElement('a');
                        div.href = "#";
                        div.className = "list-group-item list-group-item-action d-flex align-items-center gap-2 border-0 border-bottom py-3";
                        div.innerHTML = `<div>${icon}</div> <div class="flex-grow-1 text-truncate">${item.name} ${badge}</div>`;
                        
                        div.onclick = function(e) {
                            e.preventDefault();
                            map.flyTo([item.lat, item.lng], 16, { animate: true, duration: 1.5 }); // บินไปหาพิกัด
                            searchResults.style.display = 'none';
                            searchInput.value = item.name.replace(/(<([^>]+)>)/ig,""); // เอา HTML tag ออกจากช่อง input
                        };
                        searchResults.appendChild(div);
                    });
                }
                searchResults.style.display = 'block';
            });
        });

        function clearSearch() {
            searchInput.value = '';
            searchResults.style.display = 'none';
            clearBtn.style.display = 'none';
        }

        // ซ่อนผลลัพธ์เวลาคลิกที่อื่น
        document.addEventListener('click', function(event) {
            if (!document.getElementById('search-container').contains(event.target)) {
                searchResults.style.display = 'none';
            }
        });

        // ==========================================
        // ระบบพิกัดปัจจุบัน และดึงหมุดตลาด (โค้ดเดิม)
        // ==========================================
        function locateUser() {
            document.querySelector('#btn-locate i').className = 'spinner-border text-primary spinner-border-sm';
            map.locate({setView: true, maxZoom: 15});
        }

        map.on('locationfound', function(e) {
            if (userMarker) { map.removeLayer(userMarker); map.removeLayer(userCircle); }
            var userIcon = L.divIcon({
                className: 'custom-user-icon',
                html: '<div style="background-color: #4285F4; width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.5);"></div>',
                iconSize: [16, 16], iconAnchor: [8, 8]
            });
            userMarker = L.marker(e.latlng, {icon: userIcon}).addTo(map)
            .bindPopup("<div style='padding: 5px 10px; text-align: center; font-weight: bold; color: #4285F4; white-space: nowrap;'><i class='bi bi-person-fill'></i> คุณอยู่ที่นี่</div>").openPopup();
            userCircle = L.circle(e.latlng, e.accuracy/2, { color: '#4285F4', fillColor: '#4285F4', fillOpacity: 0.15, weight: 1 }).addTo(map);
            document.querySelector('#btn-locate i').className = 'bi bi-crosshair';
            document.querySelector('#btn-locate i').style.color = '#4285F4';
        });

        map.on('locationerror', function(e) {
            document.querySelector('#btn-locate i').className = 'bi bi-crosshair';
            document.querySelector('#btn-locate i').style.color = '#4285F4';
        });

        map.locate({setView: true, maxZoom: 15});

        fetch('get_markets.php')
        .then(response => response.json())
        .then(res => {
            const markets = res.data;
            const isLoggedIn = res.is_logged_in;
            const userRole = res.role;

            const verifiedIcon = new L.Icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] });
            const communityIcon = new L.Icon({ iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-orange.png', shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png', iconSize: [25, 41], iconAnchor: [12, 41], popupAnchor: [1, -34], shadowSize: [41, 41] });

            markets.forEach(market => {
                let badgeHTML = market.is_verified == 1 ? `<span class="badge bg-primary ms-1 shadow-sm" style="font-size: 0.65rem;"><i class="bi bi-patch-check-fill"></i> ทางการ</span>` : `<span class="badge bg-warning text-dark ms-1 shadow-sm" style="font-size: 0.65rem;"><i class="bi bi-people-fill"></i> ชุมชน</span>`;
                let popupHTML = `<div class="popup-card"><div class="bg-success text-white text-center py-2 px-3 d-flex justify-content-center align-items-center"><h6 class="fw-bold m-0">${market.name || 'ไม่มีชื่อ'}</h6>${badgeHTML}</div><div class="p-3 bg-white">`;
                
                if (market.image && market.image.trim() !== "") {
                    popupHTML += `<div class="text-center mb-2"><img src="uploads/${market.image}" class="img-fluid rounded shadow-sm" style="width: 100%; height: 130px; object-fit: cover;"></div>`;
                }

                popupHTML += `<p class="mb-1 small"><i class="bi bi-clock"></i> <strong>เวลา:</strong> ${market.open_time || '-'}</p>
                              <p class="small mb-3"><i class="bi bi-info-circle text-success"></i> ${market.details || 'ไม่มีรายละเอียด'}</p>
                              <div class="bg-light p-2 rounded mb-3 text-center border border-secondary border-opacity-25 shadow-sm"><small class="text-muted" style="font-size: 0.75rem;">📍 ค้นพบโดย: <strong class="text-primary">@${market.created_by}</strong></small></div>
                              <a href="https://www.google.com/maps/dir/?api=1&destination=${market.lat},${market.lng}" target="_blank" class="btn btn-success btn-sm w-100 text-white mb-2 rounded-pill shadow-sm">
                                <i class="bi bi-cursor-fill"></i> นำทาง
                              </a>
                              <a href="market_detail.php?id=${market.id}" class="btn btn-info btn-sm w-100 text-white mb-2 rounded-pill shadow-sm"><i class="bi bi-shop"></i> ดูร้านค้าในตลาด</a>`;

                if (isLoggedIn && (userRole === 'admin' || currentUserId === market.created_by)) {
                    popupHTML += `<hr class="my-2"><a href="edit_market.php?id=${market.id}" class="btn btn-outline-warning w-100 rounded-pill btn-sm shadow-sm"><i class="bi bi-pencil-square"></i> จัดการข้อมูลตลาด</a>`;
                }

                popupHTML += `<div class="text-center mt-3 pt-2 border-top"><a href="report_market.php?id=${market.id}" class="text-danger small text-decoration-none fw-bold"><i class="bi bi-exclamation-triangle-fill"></i> แจ้งข้อมูลผิดพลาด</a></div></div></div>`;

                L.marker([market.lat, market.lng], {icon: market.is_verified == 1 ? verifiedIcon : communityIcon}).addTo(map).bindPopup(popupHTML);
            });
        });
    </script>
</body>
</html>