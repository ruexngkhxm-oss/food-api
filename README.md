# food_api — Backend (PHP + mysqli + MySQL)

REST API สำหรับแอปคลังสูตรอาหารสไตล์ Cookpad

## โครงสร้างไฟล์

```
food_api/
├── schema.sql              # โครงสร้างฐานข้อมูล + ข้อมูลตัวอย่าง 5 เมนู
├── db.php                  # การเชื่อมต่อฐานข้อมูล + header กลาง
├── register.php            # POST  สมัครสมาชิก
├── login.php                # POST  เข้าสู่ระบบ
├── get_recipes.php          # GET   รายการสูตร (ค้นหา/กรองหมวดหมู่/แนะนำ/มาใหม่)
├── get_recipe_detail.php    # GET   รายละเอียดสูตร 1 รายการ
├── get_categories.php       # GET   รายการหมวดหมู่อาหาร
├── get_bookmarks.php        # GET   สูตรโปรดของผู้ใช้
└── toggle_bookmark.php      # POST  บันทึก/ยกเลิกบุ๊กมาร์ก
```

## วิธีติดตั้ง (XAMPP / Local)

1. คัดลอกโฟลเดอร์ `food_api/` ไปไว้ที่ `htdocs/food_api/` (XAMPP) หรือ `www/food_api/` (MAMP)
2. เปิด phpMyAdmin แล้วรันไฟล์ `schema.sql` เพื่อสร้างฐานข้อมูลและข้อมูลตัวอย่าง
   - หรือใช้คำสั่ง: `mysql -u root -p < schema.sql`
3. แก้ไขค่าคอนฟิกในไฟล์ `db.php` ให้ตรงกับเซิร์ฟเวอร์ของคุณ:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'food_api');
   ```
4. ทดสอบเรียก API ผ่านเบราว์เซอร์ เช่น `http://localhost/food_api/get_recipes.php`

## Endpoint สรุป

| Endpoint | Method | คำอธิบาย |
|---|---|---|
| `register.php` | POST | `{ username, email, password, full_name }` |
| `login.php` | POST | `{ username_or_email, password }` |
| `get_recipes.php` | GET | query: `q, category_id, featured, latest, user_id, limit` |
| `get_recipe_detail.php` | GET | query: `id, user_id` |
| `get_categories.php` | GET | - |
| `get_bookmarks.php` | GET | query: `user_id` |
| `toggle_bookmark.php` | POST | `{ user_id, recipe_id }` |

## บัญชีผู้ใช้ตัวอย่าง (สำหรับทดสอบ Login)

| Username | Email | Password |
|---|---|---|
| `mae_krua` | mae.krua@example.com | `password123` |
| `chef_ple` | chef.ple@example.com | `password123` |

## ความปลอดภัย

- ทุก query ใช้ **Prepared Statements** (mysqli) ป้องกัน SQL Injection
- รหัสผ่านเก็บด้วย `password_hash()` (BCRYPT) และตรวจสอบด้วย `password_verify()`
- รองรับ CORS สำหรับเรียกจาก Flutter app โดยตรง

## หมายเหตุสำหรับ Production

- เปลี่ยนค่า `DB_USER` / `DB_PASS` ให้เป็นบัญชีเฉพาะ (ไม่ควรใช้ root)
- แนะนำให้เปลี่ยน token แบบง่ายใน `login.php` เป็น JWT จริงเมื่อขึ้น production
- ควรเปิดใช้งาน HTTPS (`https://api.kpt.ac.th`) และจำกัด `Access-Control-Allow-Origin` ให้เจาะจงโดเมนจริงแทน `*`
