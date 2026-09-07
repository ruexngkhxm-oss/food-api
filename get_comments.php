<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendJsonResponse(["status" => "ok"]);
}

$recipeId = intval($_GET['recipe_id'] ?? 0);
$db = getDb();

if ($recipeId > 0) {
    $stmt = $db->prepare("SELECT * FROM comments WHERE recipe_id = :r ORDER BY id DESC");
    $stmt->execute([':r' => $recipeId]);
} else {
    $stmt = $db->query("SELECT * FROM comments ORDER BY id DESC");
}

sendJsonResponse([
    "success" => true,
    "data" => $stmt->fetchAll()
]);
?>
