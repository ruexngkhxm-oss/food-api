-- ============================================================
-- food_api / schema.sql
-- คลังสูตรอาหารสไตล์ Cookpad — โครงสร้างฐานข้อมูล + ข้อมูลตัวอย่าง
-- Engine: MySQL 5.7+ / MariaDB 10.3+
-- Charset: utf8mb4 (รองรับภาษาไทยและอิโมจิ)
-- ============================================================

CREATE DATABASE IF NOT EXISTS food_api
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE food_api;

-- ------------------------------------------------------------
-- ตาราง users : สมาชิกผู้ใช้งาน / ผู้เขียนสูตร
-- ------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(100) NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,           -- เก็บด้วย password_hash()
  full_name     VARCHAR(100) NOT NULL,
  avatar_url    VARCHAR(500) DEFAULT NULL,
  bio           VARCHAR(255) DEFAULT NULL,
  role          VARCHAR(20)  NOT NULL DEFAULT 'user', -- user, chef, admin
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- ตาราง categories : หมวดหมู่อาหาร
-- ------------------------------------------------------------
CREATE TABLE categories (
  id      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name    VARCHAR(50) NOT NULL UNIQUE,
  icon    VARCHAR(10) DEFAULT NULL,               -- เก็บ emoji ไว้ใช้แสดงผลใน Flutter
  sort_order INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- ตาราง recipes : สูตรอาหาร
-- ------------------------------------------------------------
CREATE TABLE recipes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  category_id   INT UNSIGNED DEFAULT NULL,
  title         VARCHAR(150) NOT NULL,
  description   TEXT DEFAULT NULL,
  image_url     VARCHAR(500) DEFAULT NULL,
  prep_time     INT UNSIGNED NOT NULL DEFAULT 0,  -- นาที
  servings      INT UNSIGNED NOT NULL DEFAULT 1,  -- จำนวนที่เสิร์ฟได้
  is_featured   TINYINT(1)  NOT NULL DEFAULT 0,   -- ใช้ทำ "สูตรอาหารแนะนำ"
  view_count    INT UNSIGNED NOT NULL DEFAULT 0,  -- ยอดเข้าชมสูตรอาหาร
  ingredients   TEXT DEFAULT NULL,                -- เก็บในรูปแบบ JSON หรือ Text Array
  instructions  TEXT DEFAULT NULL,                -- เก็บในรูปแบบ JSON หรือ Text Array
  created_at    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_recipes_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_recipes_category
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_recipes_title (title),
  INDEX idx_recipes_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- ตาราง recipe_ingredients : วัตถุดิบของแต่ละสูตร
-- ------------------------------------------------------------
CREATE TABLE recipe_ingredients (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipe_id       INT UNSIGNED NOT NULL,
  ingredient_name VARCHAR(150) NOT NULL,
  quantity        VARCHAR(50)  DEFAULT NULL,
  order_no        INT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_ingredients_recipe
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
  INDEX idx_ingredients_recipe (recipe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- ตาราง recipe_steps : ขั้นตอนการทำทีละข้อ
-- ------------------------------------------------------------
CREATE TABLE recipe_steps (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipe_id    INT UNSIGNED NOT NULL,
  step_no      INT UNSIGNED NOT NULL,
  description  TEXT NOT NULL,
  CONSTRAINT fk_steps_recipe
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
  INDEX idx_steps_recipe (recipe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- ตาราง bookmarks : สูตรโปรดของผู้ใช้ (many-to-many users<->recipes)
-- ------------------------------------------------------------
CREATE TABLE bookmarks (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  recipe_id   INT UNSIGNED NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bookmarks_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_bookmarks_recipe
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_user_recipe (user_id, recipe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- ตาราง follows : การติดตามเชฟ (many-to-many users<->chefs)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS follows (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  chef_id     INT UNSIGNED NOT NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_follows_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_follows_chef FOREIGN KEY (chef_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_user_chef (user_id, chef_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- SEED DATA
-- ============================================================

-- ผู้ใช้ตัวอย่าง (รหัสผ่านจริงคือ "password123" ผ่าน password_hash() แบบ BCRYPT)
-- แฮชนี้สร้างจาก PHP: password_hash('password123', PASSWORD_BCRYPT)
INSERT INTO users (username, email, password, full_name, avatar_url, bio, role) VALUES
('mae_krua', 'mae.krua@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'แม่ครัวหัวป่าก์', 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=300&q=80', 'รักการทำอาหารไทยแท้ๆ แบ่งปันสูตรจากรุ่นสู่รุ่น', 'user'),
('chef_ple', 'chef.ple@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'เชฟเปิ้ล', 'https://images.unsplash.com/photo-1583394293214-28ded15ee548?w=300&q=80', 'เชฟร้านอาหารไทย 10 ปี ชอบคิดค้นเมนูใหม่ๆ', 'chef'),
('chef_pom', 'admin@gmail', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'เชฟเตวรากรหมู่ 6', 'https://images.unsplash.com/photo-1577219491135-ce391730fb2c?w=300&q=80', 'เชฟใหญ่ประจำห้องครัว เชฟเตวรากรหมู่ 6', 'chef');

-- หมวดหมู่อาหาร
INSERT INTO categories (name, icon, sort_order) VALUES
('ต้ม', '🍲', 1),
('ผัด', '🍳', 2),
('แกง', '🥘', 3),
('ทอด', '🍤', 4),
('ของหวาน', '🍮', 5),
('ยำ', '🥗', 6),
('ข้าว', '🍚', 7);

-- ------------------------------------------------------------
-- สูตรที่ 1: ผัดไทยกุ้งสด (หมวด ผัด)
-- ------------------------------------------------------------
INSERT INTO recipes (user_id, category_id, title, description, image_url, prep_time, servings, is_featured) VALUES
(1, 2, 'ผัดไทยกุ้งสด', 'ผัดไทยสูตรต้นตำรับ เส้นนุ่มเหนียวกำลังดี รสเปรี้ยวหวานเค็มลงตัว ใส่กุ้งสดตัวโตให้ความหวานธรรมชาติ',
 'https://images.unsplash.com/photo-1559314809-0d155014e29e?w=1200&q=80', 30, 2, 1);
SET @padthai_id = LAST_INSERT_ID();

INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity, order_no) VALUES
(@padthai_id, 'เส้นจันท์แช่น้ำ', '200 กรัม', 1),
(@padthai_id, 'กุ้งสดแกะเปลือก', '10 ตัว', 2),
(@padthai_id, 'เต้าหู้เหลืองหั่นเต๋า', '1/2 ก้อน', 3),
(@padthai_id, 'ไข่ไก่', '2 ฟอง', 4),
(@padthai_id, 'ถั่วงอก', '1 ถ้วย', 5),
(@padthai_id, 'ใบกุยช่ายหั่นท่อน', '1/2 ถ้วย', 6),
(@padthai_id, 'ถั่วลิสงคั่วบด', '3 ช้อนโต๊ะ', 7),
(@padthai_id, 'กุ้งแห้งป่น', '2 ช้อนโต๊ะ', 8),
(@padthai_id, 'หัวไชโป๊วสับ', '2 ช้อนโต๊ะ', 9),
(@padthai_id, 'น้ำมะขามเปียก', '3 ช้อนโต๊ะ', 10),
(@padthai_id, 'น้ำปลา', '2 ช้อนโต๊ะ', 11),
(@padthai_id, 'น้ำตาลปี๊บ', '3 ช้อนโต๊ะ', 12),
(@padthai_id, 'พริกป่น', '1 ช้อนชา', 13),
(@padthai_id, 'น้ำมันพืช', '3 ช้อนโต๊ะ', 14);

INSERT INTO recipe_steps (recipe_id, step_no, description) VALUES
(@padthai_id, 1, 'แช่เส้นจันท์ในน้ำอุณหภูมิห้องประมาณ 30-40 นาทีจนเส้นนุ่ม สะเด็ดน้ำพักไว้'),
(@padthai_id, 2, 'ผสมน้ำมะขามเปียก น้ำปลา และน้ำตาลปี๊บ คนให้เข้ากันเป็นน้ำซอสผัดไทย'),
(@padthai_id, 3, 'ตั้งกระทะใส่น้ำมัน ผัดกุ้งให้สุก ตักพักไว้'),
(@padthai_id, 4, 'ใส่น้ำมันเพิ่ม ผัดเต้าหู้ กุ้งแห้ง และหัวไชโป๊วให้หอม'),
(@padthai_id, 5, 'ตอกไข่ลงไป รอให้ขอบไข่สุกเล็กน้อยแล้วคนให้กระจาย'),
(@padthai_id, 6, 'ใส่เส้นจันท์ที่แช่ไว้ลงผัด ราดน้ำซอสผัดไทย ผัดให้เส้นเข้ากับซอสจนแห้งพอดี'),
(@padthai_id, 7, 'ใส่ถั่วงอก ใบกุยช่าย และกุ้งที่ผัดไว้ ผัดเร็วๆ ให้เข้ากัน'),
(@padthai_id, 8, 'โรยถั่วลิสงคั่วบดและพริกป่น ตักใส่จาน เสิร์ฟพร้อมถั่วงอกสดและมะนาว');

-- ------------------------------------------------------------
-- สูตรที่ 2: ต้มยำกุ้ง (หมวด ต้ม)
-- ------------------------------------------------------------
INSERT INTO recipes (user_id, category_id, title, description, image_url, prep_time, servings, is_featured) VALUES
(1, 1, 'ต้มยำกุ้ง', 'ต้มยำกุ้งน้ำข้น รสจัดจ้าน เปรี้ยวเผ็ดเค็มหวานครบรส หอมเครื่องต้มยำสมุนไพรไทย',
 'https://images.unsplash.com/photo-1548943487-a2e4e43b4853?w=1200&q=80', 25, 3, 1);
SET @tomyum_id = LAST_INSERT_ID();

INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity, order_no) VALUES
(@tomyum_id, 'กุ้งแม่น้ำหรือกุ้งขาวตัวใหญ่', '8 ตัว', 1),
(@tomyum_id, 'ตะไคร้ทุบหั่นท่อน', '3 ต้น', 2),
(@tomyum_id, 'ข่าหั่นแว่น', '5 แว่น', 3),
(@tomyum_id, 'ใบมะกรูดฉีก', '5 ใบ', 4),
(@tomyum_id, 'พริกขี้หนูทุบ', '10 เม็ด', 5),
(@tomyum_id, 'เห็ดฟางผ่าครึ่ง', '1 ถ้วย', 6),
(@tomyum_id, 'มะเขือเทศลูกเล็กผ่าครึ่ง', '5 ลูก', 7),
(@tomyum_id, 'น้ำพริกเผา', '2 ช้อนโต๊ะ', 8),
(@tomyum_id, 'นมข้นจืดหรือกะทิ (ถ้าต้องการน้ำข้น)', '1/2 ถ้วย', 9),
(@tomyum_id, 'น้ำปลา', '3 ช้อนโต๊ะ', 10),
(@tomyum_id, 'น้ำมะนาว', '4 ช้อนโต๊ะ', 11),
(@tomyum_id, 'น้ำซุปหรือน้ำเปล่า', '4 ถ้วย', 12),
(@tomyum_id, 'ผักชีซอย', '1 ช้อนโต๊ะ', 13);

INSERT INTO recipe_steps (recipe_id, step_no, description) VALUES
(@tomyum_id, 1, 'ต้มน้ำซุปให้เดือด ใส่ตะไคร้ ข่า ใบมะกรูด ต้มให้หอมประมาณ 3-5 นาที'),
(@tomyum_id, 2, 'ใส่เห็ดฟางและมะเขือเทศ ต้มจนเห็ดสุก'),
(@tomyum_id, 3, 'ใส่หัวกุ้งลงต้มก่อนเพื่อให้ได้น้ำซุปหวานจากมันกุ้ง'),
(@tomyum_id, 4, 'ใส่น้ำพริกเผาและนมข้นจืด คนให้ละลายเข้ากัน'),
(@tomyum_id, 5, 'ใส่ตัวกุ้งลงต้มจนสุก (ระวังอย่าต้มนานเกินไปกุ้งจะเหนียว)'),
(@tomyum_id, 6, 'ปรุงรสด้วยน้ำปลา ใส่พริกขี้หนูทุบ ปิดไฟ'),
(@tomyum_id, 7, 'บีบน้ำมะนาวลงไปหลังปิดไฟ (เพื่อไม่ให้รสขมและกลิ่นฉุน) คนให้เข้ากัน'),
(@tomyum_id, 8, 'ตักใส่ชาม โรยผักชี เสิร์ฟร้อนๆ');

-- ------------------------------------------------------------
-- สูตรที่ 3: แกงเขียวหวานไก่ (หมวด แกง)
-- ------------------------------------------------------------
INSERT INTO recipes (user_id, category_id, title, description, image_url, prep_time, servings, is_featured) VALUES
(2, 3, 'แกงเขียวหวานไก่', 'แกงเขียวหวานสูตรเข้มข้น หอมกะทิ เผ็ดกำลังดี ใส่มะเขือเปราะและใบโหระพาหอมสดชื่น',
 'https://images.unsplash.com/photo-1455619452474-d2be8b1e70cd?w=1200&q=80', 40, 4, 0);
SET @curry_id = LAST_INSERT_ID();

INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity, order_no) VALUES
(@curry_id, 'เนื้อไก่หั่นชิ้นพอดีคำ', '400 กรัม', 1),
(@curry_id, 'พริกแกงเขียวหวาน', '3 ช้อนโต๊ะ', 2),
(@curry_id, 'หัวกะทิ', '1 ถ้วย', 3),
(@curry_id, 'หางกะทิ', '2 ถ้วย', 4),
(@curry_id, 'มะเขือเปราะผ่าซีก', '10 ลูก', 5),
(@curry_id, 'มะเขือพวง', '1/2 ถ้วย', 6),
(@curry_id, 'พริกชี้ฟ้าแดงหั่นเฉียง', '2 เม็ด', 7),
(@curry_id, 'ใบโหระพา', '1 กำมือ', 8),
(@curry_id, 'ใบมะกรูดฉีก', '3 ใบ', 9),
(@curry_id, 'น้ำปลา', '2 ช้อนโต๊ะ', 10),
(@curry_id, 'น้ำตาลปี๊บ', '1 ช้อนโต๊ะ', 11);

INSERT INTO recipe_steps (recipe_id, step_no, description) VALUES
(@curry_id, 1, 'ตั้งกระทะไฟกลาง ใส่หัวกะทิลงเคี่ยวจนแตกมัน'),
(@curry_id, 2, 'ใส่พริกแกงเขียวหวานลงผัดกับหัวกะทิจนหอม'),
(@curry_id, 3, 'ใส่เนื้อไก่ลงผัดให้เนื้อไก่สุกและเคลือบพริกแกงทั่วชิ้น'),
(@curry_id, 4, 'เติมหางกะทิ ตั้งไฟให้เดือด'),
(@curry_id, 5, 'ใส่มะเขือเปราะและมะเขือพวง ต้มจนมะเขือสุกนุ่ม'),
(@curry_id, 6, 'ปรุงรสด้วยน้ำปลาและน้ำตาลปี๊บ'),
(@curry_id, 7, 'ใส่พริกชี้ฟ้า ใบมะกรูด และใบโหระพา คนเบาๆ แล้วปิดไฟทันที'),
(@curry_id, 8, 'ตักใส่ถ้วย เสิร์ฟพร้อมข้าวสวยร้อนๆ');

-- ------------------------------------------------------------
-- สูตรที่ 4: ข้าวผัดไข่ (หมวด ข้าว)
-- ------------------------------------------------------------
INSERT INTO recipes (user_id, category_id, title, description, image_url, prep_time, servings, is_featured) VALUES
(2, 7, 'ข้าวผัดไข่', 'เมนูง่ายๆ ทำได้ไวใน 15 นาที ข้าวสวยหอมผัดกับไข่เยิ้มๆ เหมาะเป็นมื้อด่วนสำหรับทุกวัน',
 'https://images.unsplash.com/photo-1603133872878-684f208fb84b?w=1200&q=80', 15, 1, 1);
SET @friedrice_id = LAST_INSERT_ID();

INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity, order_no) VALUES
(@friedrice_id, 'ข้าวสวยค้างคืน', '1 จาน', 1),
(@friedrice_id, 'ไข่ไก่', '2 ฟอง', 2),
(@friedrice_id, 'กระเทียมสับ', '1 ช้อนโต๊ะ', 3),
(@friedrice_id, 'หอมใหญ่หั่นเต๋า', '1/4 หัว', 4),
(@friedrice_id, 'ต้นหอมซอย', '2 ช้อนโต๊ะ', 5),
(@friedrice_id, 'ซีอิ๊วขาว', '1 ช้อนโต๊ะ', 6),
(@friedrice_id, 'ซอสปรุงรส', '1 ช้อนโต๊ะ', 7),
(@friedrice_id, 'น้ำมันพืช', '2 ช้อนโต๊ะ', 8),
(@friedrice_id, 'พริกไทยป่น', '1/4 ช้อนชา', 9);

INSERT INTO recipe_steps (recipe_id, step_no, description) VALUES
(@friedrice_id, 1, 'ตั้งกระทะใส่น้ำมัน ผัดกระเทียมสับให้หอม'),
(@friedrice_id, 2, 'ใส่หอมใหญ่ผัดพอสลด'),
(@friedrice_id, 3, 'ตอกไข่ลงไปคนให้เป็นไข่คนหยาบๆ'),
(@friedrice_id, 4, 'ใส่ข้าวสวยลงคลุกเคล้ากับไข่ให้ทั่ว'),
(@friedrice_id, 5, 'ปรุงรสด้วยซีอิ๊วขาวและซอสปรุงรส ผัดจนข้าวร่วนและเข้าเนื้อกัน'),
(@friedrice_id, 6, 'โรยพริกไทยป่นและต้นหอม ผัดเร็วๆ อีกครั้งแล้วปิดไฟ'),
(@friedrice_id, 7, 'ตักใส่จาน เสิร์ฟพร้อมแตงกวาและมะนาว');

-- ------------------------------------------------------------
-- สูตรที่ 5: หมูกระเทียมพริกไทย (หมวด ผัด)
-- ------------------------------------------------------------
INSERT INTO recipes (user_id, category_id, title, description, image_url, prep_time, servings, is_featured) VALUES
(1, 2, 'หมูกระเทียมพริกไทย', 'หมูสไลซ์นุ่มๆ ผัดกับกระเทียมเจียวหอมกรอบและพริกไทยสด กินคู่ข้าวสวยร้อนๆ อร่อยเข้ากันสุดๆ',
 'https://images.unsplash.com/photo-1544025162-d76694265947?w=1200&q=80', 20, 2, 0);
SET @moukratiam_id = LAST_INSERT_ID();

INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity, order_no) VALUES
(@moukratiam_id, 'หมูสันคอสไลซ์บาง', '300 กรัม', 1),
(@moukratiam_id, 'กระเทียมสับหยาบ', '5 ช้อนโต๊ะ', 2),
(@moukratiam_id, 'รากผักชีสับ', '1 ช้อนโต๊ะ', 3),
(@moukratiam_id, 'พริกไทยอ่อนทั้งช่อ', '2 ช่อ', 4),
(@moukratiam_id, 'พริกไทยป่น', '1 ช้อนชา', 5),
(@moukratiam_id, 'ซีอิ๊วขาว', '2 ช้อนโต๊ะ', 6),
(@moukratiam_id, 'ซอสหอยนางรม', '1 ช้อนโต๊ะ', 7),
(@moukratiam_id, 'น้ำตาลทราย', '1 ช้อนชา', 8),
(@moukratiam_id, 'น้ำมันพืช', '3 ช้อนโต๊ะ', 9);

INSERT INTO recipe_steps (recipe_id, step_no, description) VALUES
(@moukratiam_id, 1, 'หมักหมูกับรากผักชีสับ กระเทียมครึ่งหนึ่ง และซีอิ๊วขาว ทิ้งไว้ 15 นาที'),
(@moukratiam_id, 2, 'ตั้งกระทะใส่น้ำมัน เจียวกระเทียมที่เหลือให้เหลืองหอม ตักขึ้นพักไว้ครึ่งหนึ่งสำหรับโรยหน้า'),
(@moukratiam_id, 3, 'ใส่หมูที่หมักไว้ลงผัดในน้ำมันกระเทียมจนหมูสุก'),
(@moukratiam_id, 4, 'ใส่พริกไทยอ่อน ผัดให้สุกทั่ว'),
(@moukratiam_id, 5, 'ปรุงรสด้วยซอสหอยนางรมและน้ำตาลทราย ผัดให้เข้ากัน'),
(@moukratiam_id, 6, 'ตักใส่จาน โรยกระเทียมเจียวและพริกไทยป่น เสิร์ฟทันที');

-- ------------------------------------------------------------
-- ตาราง dietary_tags : ป้ายกำกับสายสุขภาพ (Keto, Clean, Veg, Halal, etc.)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS dietary_tags (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(50) NOT NULL UNIQUE,
  icon       VARCHAR(10) DEFAULT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- ตาราง recipe_dietary_tags : เชื่อมโยงสูตรอาหารกับป้ายกำกับสายสุขภาพ
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS recipe_dietary_tags (
  recipe_id  INT UNSIGNED NOT NULL,
  tag_id     INT UNSIGNED NOT NULL,
  PRIMARY KEY (recipe_id, tag_id),
  CONSTRAINT fk_rdt_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
  CONSTRAINT fk_rdt_tag FOREIGN KEY (tag_id) REFERENCES dietary_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SEED DIETARY TAGS
INSERT IGNORE INTO dietary_tags (id, name, icon, sort_order) VALUES
(1, 'คีโต', '🥑', 1),
(2, 'คลีน', '🥗', 2),
(3, 'มังสวิรัติ', '🥬', 3),
(4, 'ฮาลาล', '🌙', 4),
(5, 'ไม่ใส่ผงชูรส', '🍃', 5),
(6, 'แคลอรีต่ำ', '⚡', 6);

-- SEED SAMPLE TAGS FOR RECIPES
INSERT IGNORE INTO recipe_dietary_tags (recipe_id, tag_id) VALUES
(1, 4), (1, 5),
(2, 1), (2, 5), (2, 6),
(3, 4), (3, 5),
(4, 2), (4, 3), (4, 5),
(5, 1), (5, 5);

-- ------------------------------------------------------------
-- ตาราง recipe_reviews : คอมเมนต์, ตอบกลับ และการให้ดาว 1-5 ดาว
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS recipe_reviews (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipe_id  INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  parent_id  INT UNSIGNED DEFAULT NULL,
  rating     TINYINT UNSIGNED DEFAULT NULL,
  comment    TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rr_recipe FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
  CONSTRAINT fk_rr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_rr_parent FOREIGN KEY (parent_id) REFERENCES recipe_reviews(id) ON DELETE CASCADE,
  INDEX idx_rr_recipe (recipe_id),
  INDEX idx_rr_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SEED REVIEWS
INSERT IGNORE INTO recipe_reviews (id, recipe_id, user_id, parent_id, rating, comment) VALUES
(1, 1, 2, NULL, 5, 'สูตรผัดไทยนี้อร่อยมากครับ เส้นเหนียวนุ่มกำลังดี กุ้งสดหวานฉ่ำมากครับ!'),
(2, 1, 1, 1, NULL, 'ขอบคุณมากๆ เลยค่ะลองทำทานดูแล้วติชมได้เสมอนะคะ'),
(3, 2, 2, NULL, 5, 'ต้มยำกุ้งรสชาติต้นตำรับ เผ็ดแซ่บสะใจมากครับ!'),
(4, 2, 4, NULL, 5, 'เชฟป้อมคอนเฟิร์มว่าน้ำซุปต้มยำสูตรนี้กลมกล่อมสมุนไพรลงตัวมากครับ');

-- ------------------------------------------------------------
-- ตาราง notifications : การแจ้งเตือนสำหรับผู้ใช้งาน
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  title       VARCHAR(150) NOT NULL,
  body        TEXT NOT NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- จบไฟล์ schema.sql
-- ============================================================


