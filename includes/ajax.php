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
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 10;
        
        // Get logs from the selected storage
        $result = $this->storage->get_logs($page, $per_page, $search);
        
        if (isset($result['error'])) {
            wp_send_json_error(array(
                'message' => esc_html($result['error'])
            ));
        }
        
        $logs = isset($result['items']) ? $result['items'] : array();
        $total = isset($result['total']) ? $result['total'] : 0;
        
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
            // Handle meta_data: decode if string, use as-is if already array/object
            if (isset($log->meta_data) && is_string($log->meta_data)) {
                $meta = json_decode($log->meta_data, true);
            } else if (isset($log->meta_data) && is_array($log->meta_data)) {
                $meta = $log->meta_data;
            } else if (isset($log->meta_data) && is_object($log->meta_data)) {
                $meta = (array)$log->meta_data;
            } else {
                $meta = [];
            }
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
            
            
            // Add the file/line info as a colored line at the top
            $html .= '<div class="jhl-log-entry">';
            $html .= '<div class="jhl-log-time">' . esc_html($formatted_date) . 
                     ' <span class="jhl-log-time-diff">(' . esc_html($human_time_diff) . ')</span>' . 
                     '<span class="jhl-log-time-local" data-timestamp="' . $timestamp_utc . '"></span></div>';
            
            $html .= '<div class="jhl-log-fileline" style="color:#0073aa;padding-bottom:2px;"># ' . esc_html($file) . ', line ' . esc_html($line) . '</div>';

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
                $html .= '<a href="javascript:void(0);" class="jhl-page-numbers prev" data-page="' . esc_attr($current_page - 1) . '" aria-label="Previous page">« Prev</a>';
            } else {
                $html .= '<span class="jhl-page-numbers prev disabled" aria-disabled="true">« Prev</span>';
            }
            
            // First page always visible
            $firstPageClass = $current_page === 1 ? 'current' : '';
            if ($current_page === 1) {
                $html .= '<span class="jhl-page-numbers ' . esc_attr($firstPageClass) . '" aria-current="page">' . esc_html('1') . '</span>';
            } else {
                $html .= '<a href="javascript:void(0);" class="jhl-page-numbers" data-page="1">' . esc_html('1') . '</a>';
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
                    $html .= '<span class="jhl-page-numbers current" aria-current="page">' . esc_html($i) . '</span>';
                } else {
                    $html .= '<a href="javascript:void(0);" class="jhl-page-numbers" data-page="' . esc_attr($i) . '">' . esc_html($i) . '</a>';
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
                    $html .= '<span class="jhl-page-numbers ' . esc_attr($lastPageClass) . '" aria-current="page">' . esc_html($total_pages) . '</span>';
                } else {
                    $html .= '<a href="javascript:void(0);" class="jhl-page-numbers" data-page="' . esc_attr($total_pages) . '">' . esc_html($total_pages) . '</a>';
                }
            }
            
            // Next page
            if ($current_page < $total_pages) {
                $html .= '<a href="javascript:void(0);" class="jhl-page-numbers next" data-page="' . esc_attr($current_page + 1) . '" aria-label="Next page">Next »</a>';
            } else {
                $html .= '<span class="jhl-page-numbers next disabled" aria-disabled="true">Next »</span>';
            }
            
            $html .= '</div>';
            
            // Page info
            $html .= '<div class="jhl-page-info">' . sprintf(
                /* translators: 1: current page, 2: total pages */
                esc_html__('Page %1$s of %2$s', 'just-log'),
                esc_html($current_page),
                esc_html($total_pages)
            ) . '</div>';
            
            $html .= '</div>';
        }
        
        // Add total count info
        $html .= '<div class="jhl-results-count">' . sprintf(
            /* translators: 1: number of logs shown, 2: total logs */
            esc_html__('Showing %1$s of %2$s log entries', 'just-log'),
            esc_html(count($logs)),
            esc_html($total)
        ) . '</div>';
        
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