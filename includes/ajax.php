<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajax handler for Just Log
 */

class JustLogAjax {
    private $storage;
    private $settings;
    
    public function __construct($db) {
        $this->settings = new JustLogSettings();
        
        // Initialize storage based on settings
        if ($this->settings->get_setting('storage_type') === 'file') {
            $this->storage = new JustLogFileStorage($this->settings);
        } else {
            $this->storage = $db;
        }
        
        // Add Ajax endpoints
        add_action('wp_ajax_just_log_search', array($this, 'search_logs'));
        add_action('wp_ajax_just_log_clear', array($this, 'clear_logs'));
    }
    
    /**
     * Search logs and return paginated results
     */
    public function search_logs() {
        // Check nonce
        if (!check_ajax_referer('just_log_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => 'Invalid nonce'
            ));
        }
        
        // Get parameters
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
        
        // Get logs from the selected storage
        $result = $this->storage->get_logs($page, $per_page, $search);
        
        if (isset($result['error'])) {
            wp_send_json_error(array(
                'message' => esc_html($result['error'])
            ));
        }
        
        $logs = $result['logs'];
        $total = $result['total'];
        
        // Format logs for display
        $html = $this->format_logs_html($logs, $page, $per_page, $total, $search);
        
        wp_send_json_success(array(
            'html' => $html
        ));
    }
    
    /**
     * Clear all logs
     */
    public function clear_logs() {
        // Check nonce
        if (!check_ajax_referer('just_log_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => 'Invalid nonce'
            ));
        }
        
        $result = $this->storage->clear_logs();
        
        if ($result === false) {
            $error = method_exists($this->storage, 'get_last_error') ? 
                    $this->storage->get_last_error() : 
                    'Error clearing logs';
                    
            wp_send_json_error(array(
                'message' => esc_html($error)
            ));
        }
        
        wp_send_json_success(array(
            'message' => 'Logs cleared successfully'
        ));
    }
    
    /**
     * Format logs for HTML display
     */
    private function format_logs_html($logs, $current_page, $per_page, $total, $search) {
        $html = '';
        
        if (empty($logs)) {
            $html .= '<div class="notice notice-info"><p>No logs found.</p></div>';
            return $html;
        }
        
        // Format each log
        foreach ($logs as $log) {
            $meta = is_string($log->meta_data) ? json_decode($log->meta_data, true) : $log->meta_data;
            $file = isset($meta['file']) ? $meta['file'] : 'N/A';
            $line = isset($meta['line']) ? $meta['line'] : 'N/A';
            $function = isset($meta['function']) ? $meta['function'] : 'N/A';
            $class = isset($meta['class']) ? $meta['class'] : '';
            
            // Convert timestamp to datetime with proper timezone
            $timezone = new DateTimeZone(!empty($log->timezone) ? $log->timezone : 'UTC');
            $datetime = new DateTime($log->timestamp, $timezone);
            $datetime->setTimezone(wp_timezone()); // Convert to site's timezone
            
            $formatted_date = $datetime->format('M j, Y H:i:s');
            $timestamp_utc = $datetime->setTimezone(new DateTimeZone('UTC'))->getTimestamp();
            
            // Get human time diff
            $human_time_diff = $this->get_human_time_diff_from_datetime($datetime);
            
            $html .= '<div class="jhl-log-entry">';
            $html .= '<div class="jhl-log-time">' . esc_html($formatted_date) . 
                     ' <span class="jhl-log-time-diff">(' . esc_html($human_time_diff) . ')</span>' . 
                     '<span class="jhl-log-time-local" data-timestamp="' . $timestamp_utc . '"></span></div>';
            
            if ($class) {
                $html .= '<div class="jhl-log-meta">From: ' . esc_html($class . '::' . $function) . '() at ' . esc_html(basename($file)) . ':' . esc_html($line) . '</div>';
            } else {
                $html .= '<div class="jhl-log-meta">From: ' . esc_html($function) . '() at ' . esc_html(basename($file)) . ':' . esc_html($line) . '</div>';
            }
            
            // Display the log content
            $html .= '<div class="jhl-log-content">';
            $html .= '<pre class="jhl-log-data">' . esc_html($log->message) . '</pre>';
            $html .= '</div>';
            $html .= '</div>';
        }
        
        // Add pagination
        $total_pages = ceil($total / $per_page);
        
        if ($total_pages > 1) {
            $html .= '<div class="jhl-pagination-wrapper">';
            $html .= '<div class="jhl-pagination-container">';
            
            // Previous page
            if ($current_page > 1) {
                $html .= '<a href="javascript:void(0);" class="jhl-page-numbers prev" data-page="'. ($current_page - 1) .'" aria-label="Previous page">« Prev</a>';
            } else {
                $html .= '<span class="jhl-page-numbers prev disabled" aria-disabled="true">« Prev</span>';
            }
            
            // First page always visible
            $firstPageClass = $current_page === 1 ? 'current' : '';
            if ($current_page === 1) {
                $html .= '<span class="jhl-page-numbers '. $firstPageClass .'" aria-current="page">1</span>';
            } else {
                $html .= '<a href="javascript:void(0);" class="jhl-page-numbers" data-page="1">1</a>';
            }
            
            // Ellipsis after first page
            if ($current_page > 4) {
                $html .= '<span class="jhl-page-numbers dots" aria-hidden="true">…</span>';
            }
            
            // Pages around current page
            $startPage = max(2, $current_page - 2);
            $endPage = min($total_pages - 1, $current_page + 2);
            
            for ($i = $startPage; $i <= $endPage; $i++) {
                if ($i === 1 || $i === $total_pages) continue; // Skip first and last pages as they're always shown
                
                if ($i === $current_page) {
                    $html .= '<span class="jhl-page-numbers current" aria-current="page">'. $i .'</span>';
                } else {
                    $html .= '<a href="javascript:void(0);" class="jhl-page-numbers" data-page="'. $i .'">'. $i .'</a>';
                }
            }
            
            // Ellipsis before last page
            if ($current_page < $total_pages - 3) {
                $html .= '<span class="jhl-page-numbers dots" aria-hidden="true">…</span>';
            }
            
            // Last page always visible if more than one page
            if ($total_pages > 1) {
                $lastPageClass = $current_page === $total_pages ? 'current' : '';
                if ($current_page === $total_pages) {
                    $html .= '<span class="jhl-page-numbers '. $lastPageClass .'" aria-current="page">'. $total_pages .'</span>';
                } else {
                    $html .= '<a href="javascript:void(0);" class="jhl-page-numbers" data-page="'. $total_pages .'">'. $total_pages .'</a>';
                }
            }
            
            // Next page
            if ($current_page < $total_pages) {
                $html .= '<a href="javascript:void(0);" class="jhl-page-numbers next" data-page="'. ($current_page + 1) .'" aria-label="Next page">Next »</a>';
            } else {
                $html .= '<span class="jhl-page-numbers next disabled" aria-disabled="true">Next »</span>';
            }
            
            $html .= '</div>';
            
            // Page info
            $html .= '<div class="jhl-page-info">Page '. $current_page .' of '. $total_pages .'</div>';
            
            $html .= '</div>';
        }
        
        // Add total count info
        $html .= '<div class="jhl-results-count">Showing '. count($logs) .' of '. $total .' log entries</div>';
        
        return $html;
    }
    
    /**
     * Get human readable time difference from a DateTime object
     */
    private function get_human_time_diff_from_datetime($datetime) {
        $now = new DateTime('now', wp_timezone());
        $timestamp = $datetime->getTimestamp();
        $now_timestamp = $now->getTimestamp();
        
        if ($timestamp > $now_timestamp) {
            // Future time (rare case, maybe timezone issues)
            $diff = $timestamp - $now_timestamp;
            if ($diff < 60) {
                return 'in a few seconds';
            } elseif ($diff < 3600) {
                $mins = round($diff / 60);
                return 'in ' . $mins . ' ' . ($mins == 1 ? 'minute' : 'minutes');
            } elseif ($diff < 86400) {
                $hours = round($diff / 3600);
                return 'in ' . $hours . ' ' . ($hours == 1 ? 'hour' : 'hours');
            } else {
                $days = round($diff / 86400);
                return 'in ' . $days . ' ' . ($days == 1 ? 'day' : 'days');
            }
        } else {
            // Past time (normal case)
            $diff = $now_timestamp - $timestamp;
            if ($diff < 60) {
                return 'just now';
            } elseif ($diff < 3600) {
                $mins = round($diff / 60);
                return $mins . ' ' . ($mins == 1 ? 'minute' : 'minutes') . ' ago';
            } elseif ($diff < 86400) {
                $hours = round($diff / 3600);
                return $hours . ' ' . ($hours == 1 ? 'hour' : 'hours') . ' ago';
            } elseif ($diff < 604800) { // 7 days
                $days = round($diff / 86400);
                return $days . ' ' . ($days == 1 ? 'day' : 'days') . ' ago';
            } elseif ($diff < 2592000) { // 30 days
                $weeks = round($diff / 604800);
                return $weeks . ' ' . ($weeks == 1 ? 'week' : 'weeks') . ' ago';
            } elseif ($diff < 31536000) { // 365 days
                $months = round($diff / 2592000);
                return $months . ' ' . ($months == 1 ? 'month' : 'months') . ' ago';
            } else {
                $years = round($diff / 31536000);
                return $years . ' ' . ($years == 1 ? 'year' : 'years') . ' ago';
            }
        }
    }
}