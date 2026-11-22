<?php
declare(strict_types=1);

/**
 * Supabase PostgreSQL mysqli Compatibility Adapter
 * Balla' Fresh Agency
 */

class SupabaseMysqliResultAdapter {
    private array $rows;
    private int $cursor = 0;
    public int $num_rows = 0;

    public function __construct(array $rows) {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc(): ?array {
        if ($this->cursor < $this->num_rows) {
            $row = $this->rows[$this->cursor];
            $this->cursor++;
            return $row;
        }
        return null;
    }

    public function fetch_row(): ?array {
        if ($this->cursor < $this->num_rows) {
            $row = array_values($this->rows[$this->cursor]);
            $this->cursor++;
            return $row;
        }
        return null;
    }

    public function fetch_all(int $mode = MYSQLI_NUM): array {
        if ($mode === MYSQLI_ASSOC) {
            return $this->rows;
        }
        return array_map('array_values', $this->rows);
    }

    public function free(): void {
        $this->rows = [];
        $this->num_rows = 0;
        $this->cursor = 0;
    }

    public function close(): void {
        $this->free();
    }
}

class SupabaseMysqliStmtAdapter {
    private PDO $pdo;
    private PDOStatement $stmt;
    private array $boundParams = [];
    private array $boundResults = [];
    public int $insert_id = 0;
    public int $affected_rows = 0;
    public string $error = '';
    public int $errno = 0;
    private ?array $lastResultRows = null;
    private int $resultCursor = 0;

    public function __construct(PDO $pdo, PDOStatement $stmt) {
        $this->pdo = $pdo;
        $this->stmt = $stmt;
    }

    public function bind_param(string $types, &...$args): bool {
        $this->boundParams = [];
        foreach ($args as $i => &$arg) {
            $this->boundParams[$i] = &$arg;
        }
        return true;
    }

    public function execute(?array $params = null): bool {
        try {
            $execParams = [];
            if ($params !== null) {
                $execParams = $params;
            } elseif (!empty($this->boundParams)) {
                foreach ($this->boundParams as $p) {
                    $execParams[] = $p;
                }
            }

            $success = $this->stmt->execute($execParams);
            $this->affected_rows = $this->stmt->rowCount();

            // Coba ambil lastval / insert_id jika query berupa INSERT
            try {
                $lastId = (int)$this->pdo->lastInsertId();
                if ($lastId > 0) {
                    $this->insert_id = $lastId;
                }
            } catch (Throwable $e) {}

            return $success;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->errno = (int)$e->getCode();
            error_log("SupabaseStmt Error: " . $this->error);
            return false;
        }
    }

    public function get_result(): SupabaseMysqliResultAdapter {
        $rows = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
        return new SupabaseMysqliResultAdapter($rows ?: []);
    }

    public function bind_result(&...$vars): bool {
        $this->boundResults = [];
        foreach ($vars as $i => &$v) {
            $this->boundResults[$i] = &$v;
        }
        return true;
    }

    public function fetch(): ?bool {
        if ($this->lastResultRows === null) {
            $this->lastResultRows = $this->stmt->fetchAll(PDO::FETCH_NUM);
            $this->resultCursor = 0;
        }

        if ($this->resultCursor < count($this->lastResultRows)) {
            $row = $this->lastResultRows[$this->resultCursor++];
            foreach ($this->boundResults as $i => &$var) {
                $var = $row[$i] ?? null;
            }
            return true;
        }
        return null;
    }

    public function close(): bool {
        $this->stmt->closeCursor();
        return true;
    }
}

class SupabaseMysqliAdapter {
    private PDO $pdo;
    public int $insert_id = 0;
    public int $affected_rows = 0;
    public string $error = '';
    public int $errno = 0;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    private function translateSql(string $sql): string {
        // Ganti backticks MySQL `table` menjadi PostgreSQL quotes "table"
        $sql = preg_replace('/`([^`]+)`/', '"$1"', $sql);

        // Terjemahkan SHOW TABLES LIKE 'xyz'
        if (preg_match("/SHOW\s+TABLES\s+LIKE\s+'([^']+)'/i", $sql, $m)) {
            return "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name = '{$m[1]}'";
        }

        // Terjemahkan SHOW COLUMNS FROM xyz
        if (preg_match("/SHOW\s+COLUMNS\s+FROM\s+\"?([a-zA-Z0-9_]+)\"?/i", $sql, $m)) {
            return "SELECT column_name AS \"Field\", data_type AS \"Type\", is_nullable AS \"Null\", column_default AS \"Default\" FROM information_schema.columns WHERE table_schema = 'public' AND table_name = '{$m[1]}'";
        }

        // Terjemahkan GROUP_CONCAT(x ORDER BY y SEPARATOR 'z')
        $sql = preg_replace(
            '/GROUP_CONCAT\s*\(\s*(.*?)\s+ORDER\s+BY\s+(.*?)\s+SEPARATOR\s+([^\)]+)\)/is',
            'STRING_AGG($1, $3 ORDER BY $2)',
            $sql
        );

        // Terjemahkan GROUP_CONCAT(x SEPARATOR 'z')
        $sql = preg_replace(
            '/GROUP_CONCAT\s*\(\s*(.*?)\s+SEPARATOR\s+([^\)]+)\)/is',
            'STRING_AGG($1, $2)',
            $sql
        );

        return $sql;
    }

    public function prepare(string $sql) {
        try {
            $translated = $this->translateSql($sql);
            $stmt = $this->pdo->prepare($translated);
            return new SupabaseMysqliStmtAdapter($this->pdo, $stmt);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->errno = (int)$e->getCode();
            error_log("Supabase prepare error: " . $this->error . " | Query: " . $sql);
            return false;
        }
    }

    public function query(string $sql) {
        try {
            $translated = $this->translateSql($sql);
            $stmt = $this->pdo->query($translated);
            if ($stmt === false) {
                return false;
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->affected_rows = $stmt->rowCount();
            return new SupabaseMysqliResultAdapter($rows ?: []);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->errno = (int)$e->getCode();
            error_log("Supabase query error: " . $this->error . " | Query: " . $sql);
            return false;
        }
    }

    public function set_charset(string $charset): bool {
        return true;
    }

    public function real_escape_string(string $string): string {
        $quoted = $this->pdo->quote($string);
        // Hapus tanda petik pembuka dan penutup
        return substr($quoted, 1, -1);
    }

    public function escape_string(string $string): string {
        return $this->real_escape_string($string);
    }

    public function begin_transaction(): bool {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool {
        return $this->pdo->commit();
    }

    public function rollback(): bool {
        return $this->pdo->rollBack();
    }

    public function close(): bool {
        return true;
    }
}

