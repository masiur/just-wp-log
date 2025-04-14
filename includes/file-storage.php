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
        if (!$this->file_path) {
            return false;
        }
        
        $dir = dirname($this->file_path);
        
        // Check if directory exists or can be created
        if (!file_exists($dir) && !wp_mkdir_p($dir)) {
            return false;
        }
        
        // Create file if it doesn't exist
        if (!file_exists($this->file_path)) {
            if (file_put_contents($this->file_path, '') === false) {
                return false;
            }
        }
        
        // Check if file is writable
        return is_writable($this->file_path);
    }
    
    public function insert_log($timestamp, $message, $meta_data, $timezone = 'UTC') {
        if ($this->error) {
            return false;
        }
        
        $log_entry = json_encode([
            'timestamp' => $timestamp,
            'timezone' => $timezone,
            'message' => $message,
            'meta_data' => is_string($meta_data) ? $meta_data : json_encode($meta_data)
        ]);
        
        if ($log_entry === false) {
            $this->error = 'Failed to encode log data';
            return false;
        }
        
        $log_entry .= "\n";
        
        if (file_put_contents($this->file_path, $log_entry, FILE_APPEND | LOCK_EX) === false) {
            $this->error = 'Failed to write to log file';
            return false;
        }
        
        return true;
    }
    
    public function get_logs($page = 1, $per_page = 10, $search = '') {
        if ($this->error || !file_exists($this->file_path)) {
            return [
                'logs' => [],
                'total' => 0,
                'error' => $this->error ?: 'Log file not found'
            ];
        }
        
        try {
            $logs = [];
            $total = 0;
            $start = ($page - 1) * $per_page;
            
            $handle = fopen($this->file_path, 'r');
            if ($handle === false) {
                throw new Exception('Failed to open log file');
            }
            
            $temp_logs = [];
            while (($line = fgets($handle)) !== false) {
                $log = json_decode($line);
                if ($log && (!$search || $this->log_matches_search($log, $search))) {
                    $temp_logs[] = $log;
                    $total++;
                }
            }
            fclose($handle);
            
            // Sort by timestamp in descending order
            usort($temp_logs, function($a, $b) {
                return strtotime($b->timestamp) - strtotime($a->timestamp);
            });
            
            // Get the requested page of logs
            $logs = array_slice($temp_logs, $start, $per_page);
            
            return [
                'logs' => $logs,
                'total' => $total
            ];
            
        } catch (Exception $e) {
            $this->error = $e->getMessage();
            return [
                'logs' => [],
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
        
        if (file_put_contents($this->file_path, '') === false) {
            $this->error = 'Failed to clear log file';
            return false;
        }
        
        return true;
    }
    
    public function get_last_error() {
        return $this->error;
    }
}