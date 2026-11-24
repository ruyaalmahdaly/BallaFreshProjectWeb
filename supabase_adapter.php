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

    public function __construct(array $rows = []) {
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
    private ?SupabaseMysqliAdapter $adapter = null;
    private array $boundParams = [];
    private array $boundResults = [];
    public int $insert_id = 0;
    public int $affected_rows = 0;
    public string $error = '';
    public int $errno = 0;
    private ?array $lastResultRows = null;
    private int $resultCursor = 0;
    private bool $isInsert = false;
    private string $tableName = '';

    public function __construct(PDO $pdo, PDOStatement $stmt, ?SupabaseMysqliAdapter $adapter = null, string $sql = '') {
        $this->pdo = $pdo;
        $this->stmt = $stmt;
        $this->adapter = $adapter;
        if (preg_match('/^\s*INSERT\s+INTO\s+["`]?([a-zA-Z0-9_]+)["`]?/i', $sql, $m)) {
            $this->isInsert = true;
            $this->tableName = strtolower($m[1]);
        }
    }

    public function bind_param(string $types, &...$args): bool {
        $this->boundParams = [];
        foreach ($args as $i => &$arg) {
            $this->boundParams[$i] = &$arg;
        }
        unset($arg);
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
            if ($this->isInsert) {
                $lastId = 0;
                // 1. Coba lastInsertId standar
                try {
                    $lid = (int)$this->pdo->lastInsertId();
                    if ($lid > 0) {
                        $lastId = $lid;
                    }
                } catch (Throwable $e) {}

                // 2. Jika belum berhasil dan nama tabel terdeteksi, coba dengan sequence tabel_{id}_seq
                if ($lastId <= 0 && $this->tableName !== '') {
                    try {
                        $lid = (int)$this->pdo->lastInsertId($this->tableName . '_id_seq');
                        if ($lid > 0) {
                            $lastId = $lid;
                        }
                    } catch (Throwable $e) {}
                }

                // 3. Fallback coba SELECT LASTVAL() dari sesi PostgreSQL saat ini
                if ($lastId <= 0) {
                    try {
                        $lvStmt = $this->pdo->query("SELECT LASTVAL()");
                        if ($lvStmt) {
                            $lid = (int)$lvStmt->fetchColumn();
                            if ($lid > 0) {
                                $lastId = $lid;
                            }
                        }
                    } catch (Throwable $e) {}
                }

                if ($lastId > 0) {
                    $this->insert_id = $lastId;
                    if ($this->adapter !== null) {
                        $this->adapter->insert_id = $lastId;
                    }
                }
            }

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
    public ?string $connect_error = null;
    public int $connect_errno = 0;
    public int $insert_id = 0;
    public int $affected_rows = 0;
    public string $error = '';
    public int $errno = 0;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function __get(string $name) {
        if ($name === 'insert_id') {
            if ($this->insert_id > 0) {
                return $this->insert_id;
            }
            try {
                $lid = (int)$this->pdo->lastInsertId();
                if ($lid > 0) {
                    $this->insert_id = $lid;
                }
                return $this->insert_id;
            } catch (Throwable $e) {
                return $this->insert_id;
            }
        }
        return null;
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
            return new SupabaseMysqliStmtAdapter($this->pdo, $stmt, $this, $translated);
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
        try {
            if ($this->pdo->inTransaction()) {
                return true;
            }
            return $this->pdo->beginTransaction();
        } catch (Throwable $e) {
            error_log("Supabase begin_transaction error: " . $e->getMessage());
            return false;
        }
    }

    public function commit(): bool {
        try {
            if ($this->pdo->inTransaction()) {
                return $this->pdo->commit();
            }
            return true;
        } catch (Throwable $e) {
            error_log("Supabase commit error: " . $e->getMessage());
            return false;
        }
    }

    public function rollback(): bool {
        try {
            if ($this->pdo->inTransaction()) {
                return $this->pdo->rollBack();
            }
            return true;
        } catch (Throwable $e) {
            error_log("Supabase rollback error: " . $e->getMessage());
            return false;
        }
    }

    public function close(): bool {
        return true;
    }
}

/**
 * Supabase REST API mysqli Compatibility Adapter (Fallback when direct PDO PostgreSQL is not connected)
 * Balla' Fresh Agency
 */
class SupabaseRestStmtAdapter {
    private string $sql;
    private ?SupabaseRestAdapter $adapter;
    private array $boundParams = [];
    private array $boundResults = [];
    private ?array $lastResultRows = null;
    private int $resultCursor = 0;
    public int $insert_id = 0;
    public int $affected_rows = 0;
    public string $error = '';
    public int $errno = 0;

    public function __construct(string $sql, ?SupabaseRestAdapter $adapter = null) {
        $this->sql = trim($sql);
        $this->adapter = $adapter;
    }

    public function bind_param(string $types, &...$args): bool {
        $this->boundParams = [];
        foreach ($args as $i => &$arg) {
            $this->boundParams[$i] = &$arg;
        }
        unset($arg);
        return true;
    }

    public function execute(?array $params = null): bool {
        $this->error = '';
        $this->errno = 0;
        $this->lastResultRows = null;
        $this->resultCursor = 0;

        $execParams = [];
        if ($params !== null) {
            $execParams = $params;
        } elseif (!empty($this->boundParams)) {
            foreach ($this->boundParams as $p) {
                $execParams[] = $p;
            }
        }

        $sql = $this->sql;

        // 1. INSERT INTO <table> (<cols>) VALUES (<vals>)
        if (preg_match('/^\s*INSERT\s+INTO\s+["`]?([a-zA-Z0-9_]+)["`]?\s*\(([^)]+)\)\s*VALUES\s*\(([^)]+)\)/is', $sql, $m)) {
            $table = strtolower(trim($m[1]));
            $rawCols = explode(',', $m[2]);
            $cols = [];
            foreach ($rawCols as $rc) {
                $cols[] = trim(str_replace(['"', '`'], '', $rc));
            }

            $payload = [];
            foreach ($cols as $idx => $col) {
                $payload[$col] = $execParams[$idx] ?? null;
            }

            $res = supabaseRestRequest($table, 'POST', $payload, ['Prefer: return=representation']);
            if ($res['success']) {
                $data = $res['data'];
                if (is_array($data) && !empty($data[0])) {
                    $row = $data[0];
                    $lid = (int)($row['id'] ?? 0);
                    if ($lid > 0) {
                        $this->insert_id = $lid;
                        if ($this->adapter) {
                            $this->adapter->insert_id = $lid;
                        }
                    }
                    $this->lastResultRows = $data;
                }
                $this->affected_rows = is_array($data) ? count($data) : 1;
                return true;
            } else {
                $this->error = (string)($res['error'] ?? 'Supabase REST insert failed');
                $this->errno = 500;
                if ($this->adapter) {
                    $this->adapter->error = $this->error;
                    $this->adapter->errno = 500;
                }
                error_log("SupabaseRest insert error on {$table}: " . $this->error);
                return false;
            }
        }

        // 2. UPDATE <table> SET <assignments> WHERE <conditions>
        if (preg_match('/^\s*UPDATE\s+["`]?([a-zA-Z0-9_]+)["`]?\s+SET\s+(.*?)\s+WHERE\s+(.*)/is', $sql, $m)) {
            $table = strtolower(trim($m[1]));
            $rawSets = $m[2];
            $whereClause = trim($m[3]);

            $setCols = [];
            if (preg_match_all('/["`]?([a-zA-Z0-9_]+)["`]?\s*=\s*(?:\?|COALESCE\([^)]*\))/i', $rawSets, $setMatches)) {
                $setCols = $setMatches[1];
            }

            $payload = [];
            $paramIndex = 0;
            foreach ($setCols as $c) {
                if (isset($execParams[$paramIndex])) {
                    $payload[$c] = $execParams[$paramIndex];
                    $paramIndex++;
                }
            }

            $endpoint = $table;
            if (preg_match('/(?:u\.)?id\s*=\s*\?/i', $whereClause)) {
                $targetId = (int)($execParams[$paramIndex] ?? 0);
                $endpoint .= '?id=eq.' . $targetId;
            } elseif (preg_match('/(?:c\.)?email\s*=\s*\?/i', $whereClause)) {
                $targetEmail = rawurlencode((string)($execParams[$paramIndex] ?? ''));
                $endpoint .= '?email=eq.' . $targetEmail;
            }

            $res = supabaseRestRequest($endpoint, 'PATCH', $payload, ['Prefer: return=representation']);
            if ($res['success']) {
                $this->affected_rows = 1;
                return true;
            } else {
                $this->error = (string)($res['error'] ?? 'Supabase REST update failed');
                if ($this->adapter) {
                    $this->adapter->error = $this->error;
                    $this->adapter->errno = 500;
                }
                return false;
            }
        }

        // 3. SELECT ... FROM <table> WHERE ...
        if (preg_match('/^\s*SELECT\s+(.*?)\s+FROM\s+["`]?([a-zA-Z0-9_]+)["`]?(?:\s+(?:AS\s+)?[a-zA-Z0-9_]+)?(?:\s+LEFT\s+JOIN.*?)?(?:\s+WHERE\s+(.*?))?(?:\s+ORDER\s+BY\s+.*?)?(?:\s+LIMIT\s+\d+)?$/is', $sql, $m)) {
            $table = strtolower(trim($m[2]));
            $where = trim($m[3] ?? '');

            $queryParams = ['select=*'];
            if (preg_match('/LIMIT\s+(\d+)/i', $sql, $lim)) {
                $queryParams[] = 'limit=' . $lim[1];
            }
            if (preg_match('/ORDER\s+BY\s+([a-zA-Z0-9_.]+)(?:\s+(ASC|DESC))?/i', $sql, $ord)) {
                $dir = strtolower($ord[2] ?? 'asc');
                $queryParams[] = 'order=' . str_replace(['u.', 's.', 'c.'], '', $ord[1]) . '.' . $dir;
            }

            if (!empty($where)) {
                if (preg_match('/email\s*=\s*\?/i', $where)) {
                    $queryParams[] = 'email=eq.' . rawurlencode((string)($execParams[0] ?? ''));
                } elseif (preg_match('/id\s*=\s*\?/i', $where)) {
                    $queryParams[] = 'id=eq.' . (int)($execParams[0] ?? 0);
                } elseif (preg_match('/order_number\s*=\s*\?/i', $where)) {
                    $queryParams[] = 'order_number=eq.' . rawurlencode((string)($execParams[0] ?? ''));
                } elseif (preg_match('/user_id\s*=\s*\?/i', $where)) {
                    $queryParams[] = 'user_id=eq.' . (int)($execParams[0] ?? 0);
                }
            }

            $endpoint = $table . '?' . implode('&', $queryParams);
            $res = supabaseRestRequest($endpoint, 'GET');
            if ($res['success']) {
                $this->lastResultRows = is_array($res['data']) ? $res['data'] : [];
                $this->affected_rows = count($this->lastResultRows);
                return true;
            } else {
                $this->error = (string)($res['error'] ?? 'Supabase REST select failed');
                if ($this->adapter) {
                    $this->adapter->error = $this->error;
                    $this->adapter->errno = 500;
                }
                $this->lastResultRows = [];
                return true; // Tidak fatal agar aplikasi tidak crash
            }
        }

        // Default: query tidak dikenali, buat array kosong
        $this->lastResultRows = [];
        return true;
    }

    public function get_result(): SupabaseMysqliResultAdapter {
        return new SupabaseMysqliResultAdapter($this->lastResultRows ?: []);
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
            $this->lastResultRows = [];
            $this->resultCursor = 0;
        }

        if ($this->resultCursor < count($this->lastResultRows)) {
            $row = array_values($this->lastResultRows[$this->resultCursor++]);
            foreach ($this->boundResults as $i => &$var) {
                $var = $row[$i] ?? null;
            }
            return true;
        }
        return null;
    }

    public function close(): bool {
        $this->lastResultRows = null;
        $this->resultCursor = 0;
        return true;
    }
}

class SupabaseRestAdapter {
    public ?string $connect_error = null;
    public int $connect_errno = 0;
    public int $insert_id = 0;
    public int $affected_rows = 0;
    public string $error = '';
    public int $errno = 0;

    public function prepare(string $sql) {
        return new SupabaseRestStmtAdapter($sql, $this);
    }

    public function query(string $sql) {
        $stmt = new SupabaseRestStmtAdapter($sql, $this);
        $stmt->execute();
        return $stmt->get_result();
    }

    public function set_charset(string $charset): bool {
        return true;
    }

    public function real_escape_string(string $string): string {
        return addslashes($string);
    }

    public function escape_string(string $string): string {
        return $this->real_escape_string($string);
    }

    public function begin_transaction(): bool {
        return true;
    }

    public function commit(): bool {
        return true;
    }

    public function rollback(): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }
}

