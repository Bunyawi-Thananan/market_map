# Market Map

เว็บแผนที่ตลาดนัด (PHP + MySQL + Leaflet + Bootstrap)

## วิธีติดตั้ง
1. ติดตั้ง XAMPP แล้ววางโฟลเดอร์โปรเจกต์ไว้ใน `htdocs/market_map`
2. เปิด phpMyAdmin แล้ว import ไฟล์ `schema.sql` (จะสร้างฐานข้อมูล `market_map` ให้)
3. คัดลอก `db.example.php` เป็น `db.php` แล้วแก้ค่าให้ตรงกับเครื่อง
4. สร้างโฟลเดอร์ `uploads/documents` ถ้ายังไม่มี
5. เปิด http://localhost/market_map
6. สมัครสมาชิกผ่านหน้า register แล้วเปลี่ยน `role` เป็น `admin` ในตาราง `users` เพื่อสร้างผู้ดูแลคนแรก
