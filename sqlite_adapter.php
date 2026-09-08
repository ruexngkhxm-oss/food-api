<?php
/**
 * SQLite Mysqli Adapter & Polyfill for PHP
 * Provides full mysqli_* compatibility layer when mysqli extension is missing (e.g. Render.com PHP)
 */

class SqliteDbStmt {
    public $stmt;
    public $params = [];
    public $resultRows = null;
    public $currentIndex = 0;
    public $error = '';

    public $affectedRows = 0;

    public function __construct($stmt) {
        $this->stmt = $stmt;
    }

    public function execute() {
        try {
            $res = $this->stmt->execute($this->params);
            if (!$res) {
                $err = $this->stmt->errorInfo();
                $this->error = $err[2] ?? 'SQLite execution failed';
                return false;
            }
            if ($this->stmt) {
                $this->affectedRows = $this->stmt->rowCount();
                if (strpos(strtoupper(trim($this->stmt->queryString)), 'SELECT') === 0) {
                    $this->resultRows = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
                    $this->currentIndex = 0;
                }
            }
            return $res;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function fetchAssoc() {
        if (is_array($this->resultRows)) {
            if ($this->currentIndex < count($this->resultRows)) {
                return $this->resultRows[$this->currentIndex++];
            }
            return null;
        }
        return null;
    }

    public function numRows() {
        return is_array($this->resultRows) ? count($this->resultRows) : 0;
    }
}

class SqliteDbConn {
    public $pdo;
    public $error = '';

    public function __construct($path) {
        if (file_exists($path)) {
            @chmod($path, 0777);
        }
        @chmod(dirname($path), 0777);
        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        try {
            $this->pdo->exec('PRAGMA busy_timeout = 5000;');
        } catch (Throwable $e) {}
    }

    public function prepare($sql) {
        $sql = str_replace('RAND()', 'RANDOM()', $sql);
        try {
            $stmt = $this->pdo->prepare($sql);
            return new SqliteDbStmt($stmt);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function query($sql) {
        $sql = str_replace('RAND()', 'RANDOM()', $sql);
        try {
            $stmt = $this->pdo->query($sql);
            if ($stmt) {
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $obj = new SqliteDbStmt($stmt);
                $obj->resultRows = $rows;
                return $obj;
            }
            return false;
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function insertId() {
        return (int)$this->pdo->lastInsertId();
    }
}

// ----- Universal DB Functions -----

function db_prepare($conn, $sql) {
    if ($conn instanceof SqliteDbConn) {
        return $conn->prepare($sql);
    }
    if (function_exists('mysqli_prepare')) {
        return @mysqli_prepare($conn, $sql);
    }
    return false;
}

function db_bind_param($stmtObj, $types, &...$params) {
    if ($stmtObj instanceof SqliteDbStmt) {
        $stmtObj->params = $params;
        return true;
    }
    if (function_exists('mysqli_stmt_bind_param')) {
        return @mysqli_stmt_bind_param($stmtObj, $types, ...$params);
    }
    return false;
}

function db_execute($stmtObj) {
    if ($stmtObj instanceof SqliteDbStmt) {
        return $stmtObj->execute();
    }
    if (function_exists('mysqli_stmt_execute')) {
        return @mysqli_stmt_execute($stmtObj);
    }
    return false;
}

function db_get_result($stmtObj) {
    if ($stmtObj instanceof SqliteDbStmt) {
        return $stmtObj;
    }
    if (function_exists('mysqli_stmt_get_result')) {
        return @mysqli_stmt_get_result($stmtObj);
    }
    return $stmtObj;
}

function db_fetch_assoc($stmtObj) {
    if ($stmtObj instanceof SqliteDbStmt) {
        return $stmtObj->fetchAssoc();
    }
    if (function_exists('mysqli_fetch_assoc')) {
        return @mysqli_fetch_assoc($stmtObj);
    }
    return null;
}

function db_num_rows($stmtObj) {
    if ($stmtObj instanceof SqliteDbStmt) {
        return $stmtObj->numRows();
    }
    if (function_exists('mysqli_num_rows')) {
        return @mysqli_num_rows($stmtObj);
    }
    if (function_exists('mysqli_stmt_num_rows')) {
        return @mysqli_stmt_num_rows($stmtObj);
    }
    return 0;
}

function db_insert_id($conn) {
    if ($conn instanceof SqliteDbConn) {
        return $conn->insertId();
    }
    if (function_exists('mysqli_insert_id')) {
        return @mysqli_insert_id($conn);
    }
    return 0;
}

function db_error($conn, $stmtObj = null) {
    if ($stmtObj && $stmtObj instanceof SqliteDbStmt && !empty($stmtObj->error)) {
        return $stmtObj->error;
    }
    if ($conn instanceof SqliteDbConn) {
        return $conn->error;
    }
    if ($stmtObj && is_object($stmtObj) && isset($stmtObj->error) && !empty($stmtObj->error)) {
        return $stmtObj->error;
    }
    if (function_exists('mysqli_error')) {
        return @mysqli_error($conn);
    }
    return '';
}

function db_real_escape_string($conn, $string) {
    if ($conn instanceof SqliteDbConn) {
        return str_replace("'", "''", $string);
    }
    if (function_exists('mysqli_real_escape_string')) {
        return @mysqli_real_escape_string($conn, $string);
    }
    return addslashes($string);
}

function db_affected_rows($stmtObj) {
    if ($stmtObj instanceof SqliteDbStmt) {
        return $stmtObj->affectedRows;
    }
    if (function_exists('mysqli_stmt_affected_rows')) {
        return @mysqli_stmt_affected_rows($stmtObj);
    }
    return 1;
}

function db_close($conn) {
    return true;
}
function db_stmt_close($stmtObj) {
    return true;
}

// ----- Polyfill Standard mysqli_* Functions if Extension is Missing -----

if (!function_exists('mysqli_connect')) {
    function mysqli_connect($host = null, $user = null, $pass = null, $db = null) {
        $sqlitePath = __DIR__ . '/database.sqlite';
        if (!file_exists($sqlitePath)) {
            require_once __DIR__ . '/init_sqlite.php';
        }
        return new SqliteDbConn($sqlitePath);
    }
    function mysqli_set_charset($conn, $charset) { return true; }
    function mysqli_close($conn) { return true; }
    function mysqli_prepare($conn, $sql) { return db_prepare($conn, $sql); }
    function mysqli_stmt_bind_param($stmtObj, $types, &...$params) { return db_bind_param($stmtObj, $types, ...$params); }
    function mysqli_stmt_execute($stmtObj) { return db_execute($stmtObj); }
    function mysqli_stmt_get_result($stmtObj) { return db_get_result($stmtObj); }
    function mysqli_stmt_store_result($stmtObj) { return true; }
    function mysqli_stmt_num_rows($stmtObj) { return db_num_rows($stmtObj); }
    function mysqli_stmt_affected_rows($stmtObj) { return db_affected_rows($stmtObj); }
    function mysqli_stmt_close($stmtObj) { return true; }
    function mysqli_fetch_assoc($stmtObj) { return db_fetch_assoc($stmtObj); }
    function mysqli_fetch_all($stmtObj, $mode = 1) {
        if ($stmtObj instanceof SqliteDbStmt && is_array($stmtObj->resultRows)) {
            return $stmtObj->resultRows;
        }
        return [];
    }
    function mysqli_insert_id($conn) { return db_insert_id($conn); }
    function mysqli_query($conn, $sql) {
        if ($conn instanceof SqliteDbConn) {
            return $conn->query($sql);
        }
        return false;
    }
    function mysqli_num_rows($resObj) { return db_num_rows($resObj); }
    function mysqli_error($conn) { return db_error($conn); }
    function mysqli_connect_error() { return ''; }
    function mysqli_real_escape_string($conn, $string) { return db_real_escape_string($conn, $string); }
}
?>
