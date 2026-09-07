<?php
/**
 * db.php
 * เชื่อมต่อฐานข้อมูล MySQL/MariaDB ด้วย mysqli + ตั้งค่า Header กลางสำหรับทุก endpoint
 */

// ----- Response headers (ใช้ร่วมกันทุกไฟล์ API) -----
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// ตอบ Preflight request ของ CORS แล้วจบการทำงานทันที
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ----- Database configuration -----
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'food_api');

// ลองเชื่อมต่อผ่าน socket (localhost) ก่อน แล้วค่อยลอง TCP (127.0.0.1)
$conn = @mysqli_connect('localhost', DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    $conn = @mysqli_connect('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
}

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้: ' . mysqli_connect_error(),
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

mysqli_set_charset($conn, 'utf8mb4');

/**
 * ฟังก์ชันช่วยส่ง JSON response พร้อม HTTP status code แล้วจบการทำงาน
 */
function send_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * ฟังก์ชันช่วยอ่าน JSON body ของ request (สำหรับ POST ที่ส่งเป็น application/json)
 */
function get_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
?>
