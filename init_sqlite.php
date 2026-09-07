<?php
/**
 * Initializer script to convert schema.sql into a fully pre-populated SQLite database.
 */
$sqliteFile = __DIR__ . '/database.sqlite';

if (file_exists($sqliteFile)) {
    unlink($sqliteFile);
}

$pdo = new PDO('sqlite:' . $sqliteFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Create Tables
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  email TEXT NOT NULL UNIQUE,
  password TEXT NOT NULL,
  full_name TEXT NOT NULL,
  avatar_url TEXT DEFAULT NULL,
  bio TEXT DEFAULT NULL,
  role TEXT NOT NULL DEFAULT 'user',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  icon TEXT DEFAULT NULL,
  sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS recipes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  category_id INTEGER DEFAULT NULL,
  title TEXT NOT NULL,
  description TEXT DEFAULT NULL,
  image_url TEXT DEFAULT NULL,
  prep_time INTEGER NOT NULL DEFAULT 0,
  servings INTEGER NOT NULL DEFAULT 1,
  is_featured INTEGER NOT NULL DEFAULT 0,
  view_count INTEGER NOT NULL DEFAULT 0,
  ingredients TEXT DEFAULT NULL,
  instructions TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS recipe_ingredients (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  recipe_id INTEGER NOT NULL,
  ingredient_name TEXT NOT NULL,
  quantity TEXT DEFAULT NULL,
  order_no INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS recipe_steps (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  recipe_id INTEGER NOT NULL,
  step_no INTEGER NOT NULL,
  description TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS bookmarks (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  recipe_id INTEGER NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(user_id, recipe_id)
);

CREATE TABLE IF NOT EXISTS follows (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  chef_id INTEGER NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(user_id, chef_id)
);

CREATE TABLE IF NOT EXISTS dietary_tags (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  icon TEXT DEFAULT NULL,
  sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS recipe_dietary_tags (
  recipe_id INTEGER NOT NULL,
  tag_id INTEGER NOT NULL,
  PRIMARY KEY (recipe_id, tag_id)
);

CREATE TABLE IF NOT EXISTS recipe_reviews (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  recipe_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  parent_id INTEGER DEFAULT NULL,
  rating INTEGER DEFAULT NULL,
  comment TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  title TEXT NOT NULL,
  body TEXT NOT NULL,
  is_read INTEGER NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
");

// 2. Insert Seed Users
$pdo->exec("
INSERT INTO users (id, username, email, password, full_name, avatar_url, bio, role) VALUES
(1, 'mae_krua', 'mae.krua@example.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'แม่ครัวหัวป่าก์', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=300&q=80', 'รักการทำอาหารไทยแท้ๆ แบ่งปันสูตรจากรุ่นสู่รุ่น', 'user'),
(2, 'chef_ple', 'chef.ple@example.com', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'เชฟเปิ้ล', 'https://images.unsplash.com/photo-1583394293214-28ded15ee548?w=300&q=80', 'เชฟร้านอาหารไทย 10 ปี ชอบคิดค้นเมนูใหม่ๆ', 'chef'),
(3, 'chef_pom', 'admin@gmail', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'เชฟเตวรากรหมู่ 6', 'https://images.unsplash.com/photo-1577219491135-ce391730fb2c?w=300&q=80', 'เชฟใหญ่ประจำห้องครัว เชฟเตวรากรหมู่ 6', 'chef');
");

// 3. Insert Seed Categories
$pdo->exec("
INSERT INTO categories (id, name, icon, sort_order) VALUES
(1, 'ต้ม', '🍲', 1),
(2, 'ผัด', '🍳', 2),
(3, 'แกง', '🥘', 3),
(4, 'ทอด', '🍤', 4),
(5, 'ของหวาน', '🍮', 5),
(6, 'ยำ', '🥗', 6),
(7, 'ข้าว', '🍚', 7);
");

// 4. Insert Seed Recipes
$pdo->exec("
INSERT INTO recipes (id, user_id, category_id, title, description, image_url, prep_time, servings, is_featured) VALUES
(1, 1, 2, 'ผัดไทยกุ้งสด', 'ผัดไทยสูตรต้นตำรับ เส้นนุ่มเหนียวกำลังดี รสเปรี้ยวหวานเค็มลงตัว ใส่กุ้งสดตัวโตให้ความหวานธรรมชาติ', 'https://images.unsplash.com/photo-1559314809-0d155014e29e?w=1200&q=80', 30, 2, 1),
(2, 1, 1, 'ต้มยำกุ้ง', 'ต้มยำกุ้งน้ำข้น รสจัดจ้าน เปรี้ยวเผ็ดเค็มหวานครบรส หอมเครื่องต้มยำสมุนไพรไทย', 'https://images.unsplash.com/photo-1548943487-a2e4e43b4853?w=1200&q=80', 25, 3, 1),
(3, 2, 3, 'แกงเขียวหวานไก่', 'แกงเขียวหวานสูตรเข้มข้น หอมกะทิ เผ็ดกำลังดี ใส่มะเขือเปราะและใบโหระพาหอมสดชื่น', 'https://images.unsplash.com/photo-1455619452474-d2be8b1e70cd?w=1200&q=80', 40, 4, 0),
(4, 2, 7, 'ข้าวผัดไข่', 'เมนูง่ายๆ ทำได้ไวใน 15 นาที ข้าวสวยหอมผัดกับไข่เยิ้มๆ เหมาะเป็นมื้อด่วนสำหรับทุกวัน', 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?w=1200&q=80', 15, 1, 1),
(5, 1, 2, 'หมูกระเทียมพริกไทย', 'หมูสไลซ์นุ่มๆ ผัดกับกระเทียมเจียวหอมกรอบและพริกไทยสด กินคู่ข้าวสวยร้อนๆ อร่อยเข้ากันสุดๆ', 'https://images.unsplash.com/photo-1544025162-d76694265947?w=1200&q=80', 20, 2, 0);
");

// 5. Insert Seed Recipe Ingredients
$pdo->exec("
INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity, order_no) VALUES
(1, 'เส้นจันท์แช่น้ำ', '200 กรัม', 1), (1, 'กุ้งสดแกะเปลือก', '10 ตัว', 2), (1, 'เต้าหู้เหลืองหั่นเต๋า', '1/2 ก้อน', 3), (1, 'ไข่ไก่', '2 ฟอง', 4), (1, 'ถั่วงอก', '1 ถ้วย', 5), (1, 'ใบกุยช่ายหั่นท่อน', '1/2 ถ้วย', 6), (1, 'ถั่วลิสงคั่วบด', '3 ช้อนโต๊ะ', 7), (1, 'กุ้งแห้งป่น', '2 ช้อนโต๊ะ', 8), (1, 'หัวไชโป๊วสับ', '2 ช้อนโต๊ะ', 9), (1, 'น้ำมะขามเปียก', '3 ช้อนโต๊ะ', 10), (1, 'น้ำปลา', '2 ช้อนโต๊ะ', 11), (1, 'น้ำตาลปี๊บ', '3 ช้อนโต๊ะ', 12), (1, 'พริกป่น', '1 ช้อนชา', 13), (1, 'น้ำมันพืช', '3 ช้อนโต๊ะ', 14),
(2, 'กุ้งแม่น้ำหรือกุ้งขาวตัวใหญ่', '8 ตัว', 1), (2, 'ตะไคร้ทุบหั่นท่อน', '3 ต้น', 2), (2, 'ข่าหั่นแว่น', '5 แว่น', 3), (2, 'ใบมะกรูดฉีก', '5 ใบ', 4), (2, 'พริกขี้หนูทุบ', '10 เม็ด', 5), (2, 'เห็ดฟางผ่าครึ่ง', '1 ถ้วย', 6), (2, 'มะเขือเทศลูกเล็กผ่าครึ่ง', '5 ลูก', 7), (2, 'น้ำพริกเผา', '2 ช้อนโต๊ะ', 8), (2, 'นมข้นจืดหรือกะทิ', '1/2 ถ้วย', 9), (2, 'น้ำปลา', '3 ช้อนโต๊ะ', 10), (2, 'น้ำมะนาว', '4 ช้อนโต๊ะ', 11), (2, 'น้ำซุปหรือน้ำเปล่า', '4 ถ้วย', 12), (2, 'ผักชีซอย', '1 ช้อนโต๊ะ', 13),
(3, 'เนื้อไก่หั่นชิ้นพอดีคำ', '400 กรัม', 1), (3, 'พริกแกงเขียวหวาน', '3 ช้อนโต๊ะ', 2), (3, 'หัวกะทิ', '1 ถ้วย', 3), (3, 'หางกะทิ', '2 ถ้วย', 4), (3, 'มะเขือเปราะผ่าซีก', '10 ลูก', 5), (3, 'มะเขือพวง', '1/2 ถ้วย', 6), (3, 'พริกชี้ฟ้าแดงหั่นเฉียง', '2 เม็ด', 7), (3, 'ใบโหระพา', '1 กำมือ', 8), (3, 'ใบมะกรูดฉีก', '3 ใบ', 9), (3, 'น้ำปลา', '2 ช้อนโต๊ะ', 10), (3, 'น้ำตาลปี๊บ', '1 ช้อนโต๊ะ', 11),
(4, 'ข้าวสวยค้างคืน', '1 จาน', 1), (4, 'ไข่ไก่', '2 ฟอง', 2), (4, 'กระเทียมสับ', '1 ช้อนโต๊ะ', 3), (4, 'หอมใหญ่หั่นเต๋า', '1/4 หัว', 4), (4, 'ต้นหอมซอย', '2 ช้อนโต๊ะ', 5), (4, 'ซีอิ๊วขาว', '1 ช้อนโต๊ะ', 6), (4, 'ซอสปรุงรส', '1 ช้อนโต๊ะ', 7), (4, 'น้ำมันพืช', '2 ช้อนโต๊ะ', 8), (4, 'พริกไทยป่น', '1/4 ช้อนชา', 9),
(5, 'หมูสันคอสไลซ์บาง', '300 กรัม', 1), (5, 'กระเทียมสับหยาบ', '5 ช้อนโต๊ะ', 2), (5, 'รากผักชีสับ', '1 ช้อนโต๊ะ', 3), (5, 'พริกไทยอ่อนทั้งช่อ', '2 ช่อ', 4), (5, 'พริกไทยป่น', '1 ช้อนชา', 5), (5, 'ซีอิ๊วขาว', '2 ช้อนโต๊ะ', 6), (5, 'ซอสหอยนางรม', '1 ช้อนโต๊ะ', 7), (5, 'น้ำตาลทราย', '1 ช้อนชา', 8), (5, 'น้ำมันพืช', '3 ช้อนโต๊ะ', 9);
");

// 6. Insert Seed Recipe Steps
$pdo->exec("
INSERT INTO recipe_steps (recipe_id, step_no, description) VALUES
(1, 1, 'แช่เส้นจันท์ในน้ำอุณหภูมิห้องประมาณ 30-40 นาทีจนเส้นนุ่ม สะเด็ดน้ำพักไว้'), (1, 2, 'ผสมน้ำมะขามเปียก น้ำปลา และน้ำตาลปี๊บ คนให้เข้ากันเป็นน้ำซอสผัดไทย'), (1, 3, 'ตั้งกระทะใส่น้ำมัน ผัดกุ้งให้สุก ตักพักไว้'), (1, 4, 'ใส่น้ำมันเพิ่ม ผัดเต้าหู้ กุ้งแห้ง และหัวไชโป๊วให้หอม'), (1, 5, 'ตอกไข่ลงไป รอให้ขอบไข่สุกเล็กน้อยแล้วคนให้กระจาย'), (1, 6, 'ใส่เส้นจันท์ที่แช่ไว้ลงผัด ราดน้ำซอสผัดไทย ผัดให้เส้นเข้ากับซอสจนแห้งพอดี'), (1, 7, 'ใส่ถั่วงอก ใบกุยช่าย และกุ้งที่ผัดไว้ ผัดเร็วๆ ให้เข้ากัน'), (1, 8, 'โรยถั่วลิสงคั่วบดและพริกป่น ตักใส่จาน เสิร์ฟพร้อมถั่วงอกสดและมะนาว'),
(2, 1, 'ต้มน้ำซุปให้เดือด ใส่ตะไคร้ ข่า ใบมะกรูด ต้มให้หอมประมาณ 3-5 นาที'), (2, 2, 'ใส่เห็ดฟางและมะเขือเทศ ต้มจนเห็ดสุก'), (2, 3, 'ใส่หัวกุ้งลงต้มก่อนเพื่อให้ได้น้ำซุปหวานจากมันกุ้ง'), (2, 4, 'ใส่น้ำพริกเผาและนมข้นจืด คนให้ละลายเข้ากัน'), (2, 5, 'ใส่ตัวกุ้งลงต้มจนสุก (ระวังอย่าต้มนานเกินไปกุ้งจะเหนียว)'), (2, 6, 'ปรุงรสด้วยน้ำปลา ใส่พริกขี้หนูทุบ ปิดไฟ'), (2, 7, 'บีบน้ำมะนาวลงไปหลังปิดไฟ คนให้เข้ากัน'), (2, 8, 'ตักใส่ชาม โรยผักชี เสิร์ฟร้อนๆ'),
(3, 1, 'ตั้งกระทะไฟกลาง ใส่หัวกะทิลงเคี่ยวจนแตกมัน'), (3, 2, 'ใส่พริกแกงเขียวหวานลงผัดกับหัวกะทิจนหอม'), (3, 3, 'ใส่เนื้อไก่ลงผัดให้เนื้อไก่สุกและเคลือบพริกแกงทั่วชิ้น'), (3, 4, 'เติมหางกะทิ ตั้งไฟให้เดือด'), (3, 5, 'ใส่มะเขือเปราะและมะเขือพวง ต้มจนมะเขือสุกนุ่ม'), (3, 6, 'ปรุงรสด้วยน้ำปลาและน้ำตาลปี๊บ'), (3, 7, 'ใส่พริกชี้ฟ้า ใบมะกรูด และใบโหระพา คนเบาๆ แล้วปิดไฟทันที'), (3, 8, 'ตักใส่ถ้วย เสิร์ฟพร้อมข้าวสวยร้อนๆ'),
(4, 1, 'ตั้งกระทะใส่น้ำมัน ผัดกระเทียมสับให้หอม'), (4, 2, 'ใส่หอมใหญ่ผัดพอสลด'), (4, 3, 'ตอกไข่ลงไปคนให้เป็นไข่คนหยาบๆ'), (4, 4, 'ใส่ข้าวสวยลงคลุกเคล้ากับไข่ให้ทั่ว'), (4, 5, 'ปรุงรสด้วยซีอิ๊วขาวและซอสปรุงรส ผัดจนข้าวร่วนและเข้าเนื้อกัน'), (4, 6, 'โรยพริกไทยป่นและต้นหอม ผัดเร็วๆ อีกครั้งแล้วปิดไฟ'), (4, 7, 'ตักใส่จาน เสิร์ฟพร้อมแตงกวาและมะนาว'),
(5, 1, 'หมักหมูกับรากผักชีสับ กระเทียมครึ่งหนึ่ง และซีอิ๊วขาว ทิ้งไว้ 15 นาที'), (5, 2, 'ตั้งกระทะใส่น้ำมัน เจียวกระเทียมที่เหลือให้เหลืองหอม ตักขึ้นพักไว้ครึ่งหนึ่งสำหรับโรยหน้า'), (5, 3, 'ใส่หมูที่หมักไว้ลงผัดในน้ำมันกระเทียมจนหมูสุก'), (5, 4, 'ใส่พริกไทยอ่อน ผัดให้สุกทั่ว'), (5, 5, 'ปรุงรสด้วยซอสหอยนางรมและน้ำตาลทราย ผัดให้เข้ากัน'), (5, 6, 'ตักใส่จาน โรยกระเทียมเจียวและพริกไทยป่น เสิร์ฟทันที');
");

// 7. Insert Dietary Tags
$pdo->exec("
INSERT INTO dietary_tags (id, name, icon, sort_order) VALUES
(1, 'คีโต', '🥑', 1), (2, 'คลีน', '🥗', 2), (3, 'มังสวิรัติ', '🥬', 3),
(4, 'ฮาลาล', '🌙', 4), (5, 'ไม่ใส่ผงชูรส', '🍃', 5), (6, 'แคลอรีต่ำ', '⚡', 6);

INSERT INTO recipe_dietary_tags (recipe_id, tag_id) VALUES
(1, 4), (1, 5), (2, 1), (2, 5), (2, 6), (3, 4), (3, 5), (4, 2), (4, 3), (4, 5), (5, 1), (5, 5);
");

// 8. Insert Recipe Reviews
$pdo->exec("
INSERT INTO recipe_reviews (id, recipe_id, user_id, parent_id, rating, comment) VALUES
(1, 1, 2, NULL, 5, 'สูตรผัดไทยนี้อร่อยมากครับ เส้นเหนียวนุ่มกำลังดี กุ้งสดหวานฉ่ำมากครับ!'),
(2, 1, 1, 1, NULL, 'ขอบคุณมากๆ เลยค่ะลองทำทานดูแล้วติชมได้เสมอนะคะ'),
(3, 2, 2, NULL, 5, 'ต้มยำกุ้งรสชาติต้นตำรับ เผ็ดแซ่บสะใจมากครับ!'),
(4, 2, 3, NULL, 5, 'เชฟป้อมคอนเฟิร์มว่าน้ำซุปต้มยำสูตรนี้กลมกล่อมสมุนไพรลงตัวมากครับ');
");

echo "SQLite database initialized successfully!\n";
?>
