<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/upload_image.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendJsonResponse(["status" => "ok"]);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$title = trim($input['title'] ?? '');
$description = trim($input['description'] ?? '');
$chefName = trim($input['chef_name'] ?? 'เตวรากรหมู่ 6');
$categoryId = intval($input['category_id'] ?? 1);
$cookingTime = intval($input['cooking_time'] ?? 20);
$servings = intval($input['servings'] ?? 2);
$difficulty = trim($input['difficulty'] ?? 'ปานกลาง');
$imageUrl = trim($input['image_url'] ?? '');

// If an image file was uploaded with the request
if (isset($_FILES['image']) || isset($_FILES['file'])) {
    $file = $_FILES['image'] ?? $_FILES['file'];
    $uploadDir = __DIR__ . '/uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $filename = uniqid('img_', true) . '.jpg';
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                   (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $imageUrl = "{$protocol}://{$host}/uploads/{$filename}";
    }
}

if (empty($imageUrl)) {
    $imageUrl = 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=800&q=80';
}

$ingredients = is_array($input['ingredients'] ?? null) ? json_encode($input['ingredients'], JSON_UNESCAPED_UNICODE) : ($input['ingredients'] ?? '[]');
$steps = is_array($input['steps'] ?? null) ? json_encode($input['steps'], JSON_UNESCAPED_UNICODE) : ($input['steps'] ?? '[]');

$db = getDb();
$stmt = $db->prepare("INSERT INTO recipes (title, description, category_id, chef_name, image_url, cooking_time, servings, difficulty, ingredients, steps) VALUES (:t, :d, :c, :cn, :img, :ct, :s, :dif, :ing, :stp)");
$stmt->execute([
    ':t' => $title ?: 'สูตรอาหารเด็ด',
    ':d' => $description,
    ':c' => $categoryId,
    ':cn' => $chefName,
    ':img' => $imageUrl,
    ':ct' => $cookingTime,
    ':s' => $servings,
    ':dif' => $difficulty,
    ':ing' => $ingredients,
    ':stp' => $steps
]);

$newId = $db->lastInsertId();

sendJsonResponse([
    "success" => true,
    "message" => "เพิ่มสูตรอาหารเรียบร้อยแล้ว",
    "recipe_id" => $newId,
    "data" => [
        "id" => $newId,
        "title" => $title,
        "image_url" => $imageUrl
    ]
]);
?>
