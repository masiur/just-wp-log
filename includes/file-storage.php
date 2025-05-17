<?php
/**
 * File storage handler for Just Log
 */

class JustLogFileStorage {
    private $settings;
    private $file_path;
    private $error;
    
    public function __construct($settings) {
        $this->settings = $settings;
        $this->file_path = $settings->get_setting('file_path');
        $this->error = null;
        
        if (!$this->validate_file_path()) {
            $this->error = 'Invalid or inaccessible log file path';
        }
    }
    
    private function validate_file_path() {
        if (empty($this->file_path)) {
            $this->error = esc_html__('Log file path is not set', 'just-log');
            return false;
        }
        
        // Initialize WP_Filesystem
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
        global $wp_filesystem;
        
        $dir = dirname($this->file_path);
        
        // Check directory permissions and existence
        if (!$wp_filesystem->exists($dir)) {
            if (!wp_mkdir_p($dir)) {
                // Translators: %s is the directory path that cannot be created
                $this->error = sprintf(
                    esc_html__('Unable to create log directory: %s', 'just-log'),
                    esc_html($dir)
                );
                return false;
            }
        }
        
        if (!$wp_filesystem->is_writable($dir)) {
            // Try to fix directory permissions
            $wp_filesystem->chmod($dir, 0755);
            if (!$wp_filesystem->is_writable($dir)) {
                // Translators: First %s is the directory path, second %s is the permission value
                $this->error = sprintf(
                    esc_html__('Directory %s is not writable. Permissions: %s', 'just-log'),
                    esc_html($dir),
                    esc_html(substr(sprintf('%o', $wp_filesystem->getchmod($dir)), -4))
                );
                return false;
            }
        }
        
        // Handle file creation and permissions
        if (!$wp_filesystem->exists($this->file_path)) {
            if (!$wp_filesystem->put_contents($this->file_path, '')) {
                // Translators: First %s is the file path, second %s is the error message
                $this->error = sprintf(
                    esc_html__('Cannot create file %s.', 'just-log'),
                    esc_html($this->file_path)
                );
                return false;
            }
            $wp_filesystem->chmod($this->file_path, 0644);
        }
        
        if (!$wp_filesystem->is_writable($this->file_path)) {
            // Try to fix file permissions
            $wp_filesystem->chmod($this->file_path, 0644);
            if (!$wp_filesystem->is_writable($this->file_path)) {
                // Translators: First %s is the file path, second %s is the permission value
                $this->error = sprintf(
                    esc_html__('File %s is not writable. Permissions: %s', 'just-log'),
                    esc_html($this->file_path),
                    esc_html(substr(sprintf('%o', $wp_filesystem->getchmod($this->file_path)), -4))
                );
                return false;
            }
        }
        
        return true;
    }
    
    private function ensure_file_exists() {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
        global $wp_filesystem;
        
        if (!$wp_filesystem->exists($this->file_path)) {
            $dir = dirname($this->file_path);
            if (!$wp_filesystem->exists($dir)) {
                if (!wp_mkdir_p($dir)) {
                    // Translators: %s is the directory path that cannot be created
                    throw new Exception(sprintf(
                        esc_html__('Unable to create log directory: %s', 'just-log'),
                        esc_html($dir)
                    ));
                }
                $wp_filesystem->chmod($dir, 0755);
            }
            if (!$wp_filesystem->put_contents($this->file_path, '')) {
                // Translators: %s is the file path that cannot be created
                throw new Exception(sprintf(
                    esc_html__('Unable to create log file: %s', 'just-log'),
                    esc_html($this->file_path)
                ));
            }
            $wp_filesystem->chmod($this->file_path, 0644);
        }
        
        if (!$wp_filesystem->is_writable($this->file_path)) {
            // Translators: %s is the file path that is not writable
            throw new Exception(sprintf(
                esc_html__('Log file is not writable: %s. Please check permissions.', 'just-log'),
                esc_html($this->file_path)
            ));
        }
    }
    
    public function insert_log($timestamp, $message, $meta_data, $timezone = 'UTC') {
        if ($this->error) {
            return false;
        }
        
        $this->ensure_file_exists();
        
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
        global $wp_filesystem;
        
        $log_entry = json_encode([
            'timestamp' => $timestamp,
            'timezone' => $timezone,
            'message' => $message,
            'meta_data' => $meta_data
        ]) . "\n";
        
        return $wp_filesystem->put_contents($this->file_path, $log_entry, FS_CHMOD_FILE, FILE_APPEND);
    }
    
    public function get_logs($page = 1, $per_page = 10, $search = '') {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
        global $wp_filesystem;
        
        if ($this->error || !$wp_filesystem->exists($this->file_path)) {
            return [
                'items' => [],
                'total' => 0,
                'error' => $this->error ?: 'Log file not found'
            ];
        }
        
        try {
            $logs = [];
            $total = 0;
            $start = ($page - 1) * $per_page;
            
            // Read the entire file
            $content = $wp_filesystem->get_contents($this->file_path);
            if ($content === false) {
                throw new Exception('Failed to read log file');
            }
            
            // Process each line
            $temp_logs = [];
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }
                
                $log = json_decode($line);
                if ($log && (!$search || $this->log_matches_search($log, $search))) {
                    $temp_logs[] = $log;
                    $total++;
                }
            }
            
            // Sort by timestamp in descending order
            usort($temp_logs, function($a, $b) {
                return strtotime($b->timestamp) - strtotime($a->timestamp);
            });
            
            // Get the requested page of logs
            $logs = array_slice($temp_logs, $start, $per_page);
            
            return [
                'items' => $logs,
                'total' => $total
            ];
            
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return [
                'items' => [],
                'total' => 0,
                'error' => $this->error
            ];
        }
    }
    
    private function log_matches_search($log, $search) {
        if (!is_string($search)) return true;
        
        $search = strtolower(trim($search));
        if (empty($search)) return true;
        
        $message = strtolower(json_encode($log->message));
        $meta = strtolower(json_encode($log->meta_data));
        
        return strpos($message, $search) !== false || 
               strpos($meta, $search) !== false;
    }
    
    public function clear_logs() {
        if ($this->error) {
            return false;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
        global $wp_filesystem;

        // If file does not exist, treat as already cleared (success)
        if (!$wp_filesystem->exists($this->file_path)) {
            return true;
        }
        
        if (!$wp_filesystem->put_contents($this->file_path, '')) {
            $this->error = 'Failed to clear log file';
            return false;
        }
        
        return true;
    }
    
    public function get_last_error() {
        return $this->error;
    }
}