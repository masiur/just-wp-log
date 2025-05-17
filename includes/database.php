<?php
/**
 * Database handler for Just Log
 * Uses WordPress database instead of SQLite for better compatibility
 */

class JustLogDatabase {
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'just_log_entries';
        
        $this->create_table_if_not_exists();
    }
    
    /**
     * Create the logs table if it doesn't exist
     */
    private function create_table_if_not_exists() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
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
        $this->check_timezone_column();
    }
    
    /**
     * Check if timezone column exists, if not add it
     */
    public function check_timezone_column() {
        global $wpdb;
        
        // Safe way to use table name in prepare statement
        $table_name = $this->table_name;
        
        // Check if timezone column exists
        $has_timezone = $wpdb->get_var($wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} LIKE %s",
            'timezone'
        ));
        
        if (!$has_timezone) {
            // Add timezone column if it doesn't exist
            $wpdb->query($wpdb->prepare("ALTER TABLE {$table_name}
             ADD COLUMN timezone varchar(50) NOT NULL DEFAULT 'UTC' AFTER timestamp"));
        }
    }
    
    /**
     * Insert a log entry
     */
    public function insert_log($timestamp, $message, $meta_data, $timezone = 'UTC') {
        global $wpdb;
        $wpdb->insert(
            $this->table_name,
            array(
                'timestamp' => $timestamp,
                'timezone' => $timezone,
                'message' => $message,
                'meta_data' => json_encode($meta_data)
            ),
            array(
                '%s',
                '%s',
                '%s',
                '%s'
            )
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get logs with pagination and search
     * Accepts either an array of arguments or legacy scalar arguments (page, per_page, search)
     */
    public function get_logs($args = array(), $per_page = null, $search = null) {
        global $wpdb;

        // Backward compatibility: if called with scalar arguments, convert to array
        if (!is_array($args)) {
            $args = array(
                'page' => $args,
                'per_page' => $per_page !== null ? $per_page : 10,
                'search' => $search !== null ? $search : ''
            );
        }
        $args = wp_parse_args($args, array(
            'page' => 1,
            'per_page' => 10,
            'search' => ''
        ));

        // Ensure per_page is at least 1
        $args['per_page'] = max(1, intval($args['per_page']));
        
        $where = array('1=1');
        $prepare_args = array();
        
        if (!empty($args['search'])) {
            $where[] = "(message LIKE %s OR meta_data LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $prepare_args[] = $search;
            $prepare_args[] = $search;
        }
        
        $where = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];
        
        // Prepare query arguments
        $query_args = array_merge($prepare_args, array($args['per_page'], $offset));
        
        // Create the SQL query with placeholders
        $query = "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->table_name} 
            WHERE {$where} 
            ORDER BY timestamp DESC 
            LIMIT %d OFFSET %d";

            
        $h_query = $wpdb->prepare($query, $query_args);
        // Prepare and execute the query
        $results = $wpdb->get_results($h_query);
        
        // Get total rows without using prepare (no placeholders needed)
        $total = $wpdb->get_var("SELECT FOUND_ROWS()");
        
        return array(
            'items' => $results,
            'total' => (int) $total,
            'pages' => ceil($total / $args['per_page'])
        );
    }
    
    /**
     * Clear all logs
     */
    public function clear_logs() {
        global $wpdb;
        // Table names cannot be placeholders in prepare statements
        // Use TRUNCATE directly as the table name is already properly prefixed
        return $wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }
    
    /**
     * Get log by ID
     */
    public function get_log($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }
}