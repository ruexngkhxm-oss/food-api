<?php
// Shared Database connection helper using SQLite (no setup required)
function getDb() {
    static $db = null;
    if ($db === null) {
        $dbPath = __DIR__ . '/database.sqlite';
        $db = new PDO('sqlite:' . $dbPath);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Auto-create tables if they don't exist
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE,
                full_name TEXT,
                email TEXT,
                password TEXT,
                role TEXT DEFAULT 'user',
                avatar_url TEXT
            );

            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                icon TEXT
            );

            CREATE TABLE IF NOT EXISTS dietary_tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT
            );

            CREATE TABLE IF NOT EXISTS recipes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT,
                description TEXT,
                category_id INTEGER,
                chef_name TEXT,
                image_url TEXT,
                cooking_time INTEGER,
                servings INTEGER,
                difficulty TEXT,
                ingredients TEXT,
                steps TEXT,
                status TEXT DEFAULT 'published',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                recipe_id INTEGER,
                author_name TEXT,
                comment_text TEXT,
                rating INTEGER DEFAULT 5,
                chef_reply TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Seed initial data if empty
        $stmt = $db->query("SELECT COUNT(*) as count FROM categories");
        if ($stmt->fetch()['count'] == 0) {
            $db->exec("
                INSERT INTO categories (name, icon) VALUES 
                ('อาหารจานเดียว', '🍲'),
                ('ของหวาน', '🍰'),
                ('เครื่องดื่ม', '🥤'),
                ('อาหารคลีน', '🥗');

                INSERT INTO dietary_tags (name) VALUES 
                ('🕌 ฮาลาล (Halal)'),
                ('🥗 อาหารคลีน (Clean Food)'),
                ('🥑 คีโต (Keto)'),
                ('🌱 มังสวิรัติ (Vegetarian)'),
                ('🥦 อาหารเจ (Vegan)'),
                ('🥩 โลว์คาร์บ (Low Carb)');

                INSERT INTO users (username, full_name, email, password, role, avatar_url) VALUES
                ('admin', 'เตวรากรหมู่ 6', 'admin@chef.com', '123456', 'chef', 'https://images.unsplash.com/photo-1577219491135-ce391730fb2c?auto=format&fit=crop&w=400&q=80');

                INSERT INTO recipes (title, description, category_id, chef_name, image_url, cooking_time, servings, difficulty, ingredients, steps) VALUES
                ('ต้มยำกุ้งน้ำข้น', 'ต้มยำกุ้งสูตรเด็ด รสชาติเข้มข้น หอมเครื่องต้มยำสดใหม่', 1, 'เตวรากรหมู่ 6', 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=800&q=80', 25, 2, 'ปานกลาง', '[\"กุ้งสด 500g\", \"ข่า ตะไคร้ ใบมะกรูด\", \"พริกเผา 2 ชต.\", \"นมข้นจืด 1/2 ถ้วย\"]', '[\"ต้มน้ำให้เดือด ใส่ข่า ตะไคร้ ใบมะกรูด\", \"ใส่มะขามเปียก พริกเผา และกุ้ง\", \"เติมนมข้นจืด ยกลง ปรุงรสตามชอบ\"]');
            ");
        }
    }
    return $db;
}

function sendJsonResponse($data, $statusCode = 200) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}
?>
