<?php
/**
 * db.php
 * เชื่อมต่อฐานข้อมูล MySQL (ถ้ามี) หรือ SQLite (สำหรับ Render.com และการใช้งานแบบ Offline)
 */

// ----- Response headers (ใช้ร่วมกันทุกไฟล์ API) -----
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/sqlite_adapter.php';

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'food_api');

// ลองเชื่อมต่อ MySQL ก่อน ถ้าไม่เจอ (เช่น ปิด Laragon หรือรันบน Render) จะใช้ SQLite อัตโนมัติ!
$conn = null;
if (function_exists('mysqli_connect')) {
    try {
        $conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    } catch (Throwable $e) {
        $conn = false;
    }
}

if (!$conn) {
    // ใช้ SQLite database.sqlite ที่โหลดข้อมูลจริงเรียบร้อยแล้ว
    $sqlitePath = __DIR__ . '/database.sqlite';
    if (!file_exists($sqlitePath)) {
        require_once __DIR__ . '/init_sqlite.php';
    }
    $conn = new SqliteDbConn($sqlitePath);
}

function send_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function get_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
?>
