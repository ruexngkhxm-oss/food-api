<?php
/**
 * SQLite Mysqli Adapter for PHP
 * Transparently handles both MySQL (mysqli) and SQLite (PDO) connections.
 */

class SqliteDbStmt {
    public $stmt;
    public $params = [];
    public $resultRows = null;
    public $currentIndex = 0;
    public $error = '';

    public function __construct($stmt) {
        $this->stmt = $stmt;
    }

    public function execute() {
        try {
            $res = $this->stmt->execute($this->params);
            if (strpos(strtoupper(trim($this->stmt->queryString)), 'SELECT') === 0) {
                $this->resultRows = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->currentIndex = 0;
            }
            return $res;
        } catch (Exception $e) {
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
        $this->pdo = new PDO('sqlite:' . $path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function prepare($sql) {
        $sql = str_replace('RAND()', 'RANDOM()', $sql);
        try {
            $stmt = $this->pdo->prepare($sql);
            return new SqliteDbStmt($stmt);
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function insertId() {
        return (int)$this->pdo->lastInsertId();
    }
}

// ----- Universal Database Abstraction Layer (db_*) -----

function db_prepare($conn, $sql) {
    if ($conn instanceof SqliteDbConn) {
        return $conn->prepare($sql);
    }
    return mysqli_prepare($conn, $sql);
}

function db_bind_param($stmtObj, $types, &...$params) {
    if ($stmtObj instanceof SqliteDbStmt) {
        $stmtObj->params = $params;
        return true;
    }
    return mysqli_stmt_bind_param($stmtObj, $types, ...$params);
}

function db_execute($stmtObj) {
    if ($stmtObj instanceof SqliteDbStmt) {
        return $stmtObj->execute();
    }
    return mysqli_stmt_execute($stmtObj);
}

function db_get_result($stmtObj) {
    if ($stmtObj instanceof SqliteDbStmt) {
        return $stmtObj;
    }
    return mysqli_stmt_get_result($stmtObj);
}

function db_fetch_assoc($stmtObj) {
    if ($stmtObj instanceof SqliteDbStmt) {
        return $stmtObj->fetchAssoc();
    }
    return mysqli_fetch_assoc($stmtObj);
}

function db_num_rows($stmtObj) {
    if ($stmtObj instanceof SqliteDbStmt) {
        return $stmtObj->numRows();
    }
    return mysqli_num_rows($stmtObj);
}

function db_stmt_close($stmtObj) {
    return true;
}

function db_insert_id($conn) {
    if ($conn instanceof SqliteDbConn) {
        return $conn->insertId();
    }
    return mysqli_insert_id($conn);
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
    return mysqli_error($conn);
}

function db_real_escape_string($conn, $string) {
    if ($conn instanceof SqliteDbConn) {
        return str_replace("'", "''", $string);
    }
    return mysqli_real_escape_string($conn, $string);
}

function db_close($conn) {
    return true;
}
?>
