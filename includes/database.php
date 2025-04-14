<?php
/**
 * Database handler for Just Log
 * Uses WordPress database instead of SQLite for better compatibility
 */

class JustLogDatabase {
    private $wpdb;
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'just_log_entries';
        
        $this->create_table_if_not_exists();
    }
    
    /**
     * Create the logs table if it doesn't exist
     */
    private function create_table_if_not_exists() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            timezone varchar(50) NOT NULL DEFAULT 'UTC',
            message longtext NOT NULL,
            meta_data longtext NOT NULL,
            PRIMARY KEY (id),
            KEY timestamp_idx (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Check if timezone column exists, if not add it
        $check_column = $this->wpdb->get_results("SHOW COLUMNS FROM {$this->table_name} LIKE 'timezone'");
        if (empty($check_column)) {
            $this->wpdb->query("ALTER TABLE {$this->table_name} ADD COLUMN timezone varchar(50) NOT NULL DEFAULT 'UTC' AFTER timestamp");
        }
    }
    
    /**
     * Insert a log entry
     */
    public function insert_log($timestamp, $message, $meta_data, $timezone = 'UTC') {
        return $this->wpdb->insert(
            $this->table_name,
            array(
                'timestamp' => $timestamp,
                'timezone' => $timezone,
                'message' => $message,
                'meta_data' => is_string($meta_data) ? $meta_data : json_encode($meta_data)
            ),
            array(
                '%s',
                '%s',
                '%s',
                '%s'
            )
        );
    }
    
    /**
     * Get logs with pagination and search
     */
    public function get_logs($page = 1, $per_page = 10, $search = '') {
        $offset = ($page - 1) * $per_page;
        
        $sql = "SELECT * FROM {$this->table_name}";
        $count_sql = "SELECT COUNT(*) FROM {$this->table_name}";
        
        if (!empty($search)) {
            $search = '%' . $this->wpdb->esc_like($search) . '%';
            $sql .= $this->wpdb->prepare(" WHERE message LIKE %s OR meta_data LIKE %s", $search, $search);
            $count_sql .= $this->wpdb->prepare(" WHERE message LIKE %s OR meta_data LIKE %s", $search, $search);
        }
        
        $sql .= " ORDER BY timestamp DESC LIMIT %d OFFSET %d";
        $query = $this->wpdb->prepare($sql, $per_page, $offset);
        
        $results = $this->wpdb->get_results($query);
        $total = $this->wpdb->get_var($count_sql);
        
        return array(
            'logs' => $results,
            'total' => $total
        );
    }
    
    /**
     * Clear all logs
     */
    public function clear_logs() {
        return $this->wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }
    
    /**
     * Get log by ID
     */
    public function get_log($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id)
        );
    }
}