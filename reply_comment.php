<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendJsonResponse(["status" => "ok"]);
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$commentId = intval($input['comment_id'] ?? 0);
$replyText = trim($input['reply_text'] ?? $input['reply'] ?? '');

if ($commentId <= 0 || empty($replyText)) {
    sendJsonResponse(["success" => false, "message" => "ข้อมูลไม่ถูกต้อง"], 400);
}

$db = getDb();
$stmt = $db->prepare("UPDATE comments SET chef_reply = :r WHERE id = :id");
$stmt->execute([':r' => $replyText, ':id' => $commentId]);

sendJsonResponse([
    "success" => true,
    "message" => "ตอบกลับคอมเมนต์เรียบร้อยแล้ว"
]);
?>
