<?php
/**
 * SQLite Mysqli Adapter for PHP
 * Transparently wraps SQLite database calls inside standard mysqli_* functions
 */

class SqliteDbStmt {
    public $stmt;
    public $params = [];
    public $resultRows = null;
    public $currentIndex = 0;

    public function __construct($stmt) {
        $this->stmt = $stmt;
    }
}

class SqliteDbConn {
    public $pdo;
    public function __construct($path) {
        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
}

if (!function_exists('mysqli_connect')) {
    function mysqli_connect($host = null, $user = null, $pass = null, $db = null) {
        $sqlitePath = __DIR__ . '/database.sqlite';
        if (!file_exists($sqlitePath)) {
            require_once __DIR__ . '/init_sqlite.php';
        }
        return new SqliteDbConn($sqlitePath);
    }

    function mysqli_set_charset($conn, $charset) {
        return true;
    }

    function mysqli_close($conn) {
        return true;
    }

    function mysqli_prepare($conn, $sql) {
        if (!$conn || !($conn instanceof SqliteDbConn)) return false;
        $sql = str_replace('RAND()', 'RANDOM()', $sql);
        try {
            $stmt = $conn->pdo->prepare($sql);
            return new SqliteDbStmt($stmt);
        } catch (Exception $e) {
            return false;
        }
    }

    function mysqli_stmt_bind_param($stmtObj, $types, &...$params) {
        if (!($stmtObj instanceof SqliteDbStmt)) return false;
        $stmtObj->params = $params;
        return true;
    }

    function mysqli_stmt_execute($stmtObj) {
        if (!($stmtObj instanceof SqliteDbStmt)) return false;
        try {
            $res = $stmtObj->stmt->execute($stmtObj->params);
            if (strpos(strtoupper(trim($stmtObj->stmt->queryString)), 'SELECT') === 0) {
                $stmtObj->resultRows = $stmtObj->stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmtObj->currentIndex = 0;
            }
            return $res;
        } catch (Exception $e) {
            return false;
        }
    }

    function mysqli_stmt_get_result($stmtObj) {
        return $stmtObj;
    }

    function mysqli_stmt_store_result($stmtObj) {
        return true;
    }

    function mysqli_stmt_num_rows($stmtObj) {
        if (!($stmtObj instanceof SqliteDbStmt)) return 0;
        return is_array($stmtObj->resultRows) ? count($stmtObj->resultRows) : 0;
    }

    function mysqli_stmt_close($stmtObj) {
        return true;
    }

    function mysqli_fetch_assoc($stmtObj) {
        if ($stmtObj instanceof SqliteDbStmt && is_array($stmtObj->resultRows)) {
            if ($stmtObj->currentIndex < count($stmtObj->resultRows)) {
                return $stmtObj->resultRows[$stmtObj->currentIndex++];
            }
            return null;
        }
        return null;
    }

    function mysqli_fetch_all($stmtObj, $mode = 1) {
        if ($stmtObj instanceof SqliteDbStmt && is_array($stmtObj->resultRows)) {
            return $stmtObj->resultRows;
        }
        return [];
    }

    function mysqli_insert_id($conn) {
        if ($conn instanceof SqliteDbConn) {
            return (int)$conn->pdo->lastInsertId();
        }
        return 0;
    }

    function mysqli_query($conn, $sql) {
        if (!($conn instanceof SqliteDbConn)) return false;
        $sql = str_replace('RAND()', 'RANDOM()', $sql);
        $stmt = $conn->pdo->query($sql);
        if ($stmt) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $obj = new SqliteDbStmt($stmt);
            $obj->resultRows = $rows;
            return $obj;
        }
        return false;
    }

    function mysqli_num_rows($resObj) {
        if ($resObj instanceof SqliteDbStmt && is_array($resObj->resultRows)) {
            return count($resObj->resultRows);
        }
        return 0;
    }

    function mysqli_error($conn) {
        return '';
    }

    function mysqli_connect_error() {
        return '';
    }
}
?>
