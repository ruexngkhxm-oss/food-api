<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendJsonResponse(["status" => "ok"]);
}

$db = getDb();
$stmt = $db->query("SELECT * FROM dietary_tags");
sendJsonResponse([
    "success" => true,
    "data" => $stmt->fetchAll()
]);
?>
