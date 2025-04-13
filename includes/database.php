<?php
if (!defined('ABSPATH')) {
    exit;
}

class JustLogDatabase {
    private $db;
    private $db_file;
    
    public function __construct() {
        $this->db_file = WP_CONTENT_DIR . '/jl.db';
        $this->init_db();
    }
    
    private function init_db() {
        try {
            $this->db = new SQLite3($this->db_file);
            $this->db->exec('
                CREATE TABLE IF NOT EXISTS logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    timestamp TEXT,
                    message TEXT,
                    file TEXT,
                    line INTEGER,
                    function TEXT,
                    class TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ');
        } catch (Exception $e) {
            error_log('SQLite error: ' . $e->getMessage());
        }
    }
    
    public function insert_log($timestamp, $message, $caller) {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO logs (timestamp, message, file, line, function, class)
                VALUES (:timestamp, :message, :file, :line, :function, :class)
            ');
            
            $stmt->bindValue(':timestamp', $timestamp, SQLITE3_TEXT);
            $stmt->bindValue(':message', $message, SQLITE3_TEXT);
            $stmt->bindValue(':file', $caller['file'], SQLITE3_TEXT);
            $stmt->bindValue(':line', $caller['line'], SQLITE3_INTEGER);
            $stmt->bindValue(':function', $caller['function'], SQLITE3_TEXT);
            $stmt->bindValue(':class', $caller['class'], SQLITE3_TEXT);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log('SQLite insert error: ' . $e->getMessage());
            return false;
        }
    }
    
    public function get_logs($page = 1, $per_page = 10, $search = '') {
        try {
            $offset = ($page - 1) * $per_page;
            
            // Count total matching records
            if (!empty($search)) {
                $count_stmt = $this->db->prepare('
                    SELECT COUNT(*) as total FROM logs 
                    WHERE message LIKE :search 
                    OR file LIKE :search 
                    OR function LIKE :search 
                    OR class LIKE :search
                ');
                $count_stmt->bindValue(':search', '%' . $search . '%', SQLITE3_TEXT);
            } else {
                $count_stmt = $this->db->prepare('SELECT COUNT(*) as total FROM logs');
            }
            
            $count_result = $count_stmt->execute();
            $total = $count_result->fetchArray(SQLITE3_ASSOC)['total'];
            
            // Get paginated results
            if (!empty($search)) {
                $stmt = $this->db->prepare('
                    SELECT * FROM logs 
                    WHERE message LIKE :search 
                    OR file LIKE :search 
                    OR function LIKE :search 
                    OR class LIKE :search
                    ORDER BY id DESC LIMIT :limit OFFSET :offset
                ');
                $stmt->bindValue(':search', '%' . $search . '%', SQLITE3_TEXT);
                $stmt->bindValue(':limit', $per_page, SQLITE3_INTEGER);
                $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
            } else {
                $stmt = $this->db->prepare('
                    SELECT * FROM logs 
                    ORDER BY id DESC LIMIT :limit OFFSET :offset
                ');
                $stmt->bindValue(':limit', $per_page, SQLITE3_INTEGER);
                $stmt->bindValue(':offset', $offset, SQLITE3_INTEGER);
            }
            
            $result = $stmt->execute();
            
            $logs = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $logs[] = $row;
            }
            
            return [
                'entries' => $logs,
                'total' => $total
            ];
        } catch (Exception $e) {
            error_log('SQLite query error: ' . $e->getMessage());
            return [
                'entries' => [],
                'total' => 0
            ];
        }
    }
    
    public function clear_logs() {
        try {
            return $this->db->exec('DELETE FROM logs');
        } catch (Exception $e) {
            error_log('SQLite clear error: ' . $e->getMessage());
            return false;
        }
    }
}