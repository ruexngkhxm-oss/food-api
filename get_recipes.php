<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendJsonResponse(["status" => "ok"]);
}

$db = getDb();
$stmt = $db->query("SELECT * FROM recipes ORDER BY id DESC");
$recipes = $stmt->fetchAll();

foreach ($recipes as &$r) {
    if (isset($r['ingredients']) && is_string($r['ingredients'])) {
        $r['ingredients'] = json_decode($r['ingredients'], true) ?? $r['ingredients'];
    }
    if (isset($r['steps']) && is_string($r['steps'])) {
        $r['steps'] = json_decode($r['steps'], true) ?? $r['steps'];
    }
}

sendJsonResponse([
    "success" => true,
    "data" => $recipes,
    "recipes" => $recipes
]);
?>
