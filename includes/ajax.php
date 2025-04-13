<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ajax handler for Just Log
 */

class JustLogAjax {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        
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
        
        // Get logs
        $result = $this->db->get_logs($page, $per_page, $search);
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
        
        $result = $this->db->clear_logs();
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Logs cleared successfully'
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Error clearing logs'
            ));
        }
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
            $meta = json_decode($log->meta_data, true);
            $file = isset($meta['file']) ? $meta['file'] : 'N/A';
            $line = isset($meta['line']) ? $meta['line'] : 'N/A';
            $function = isset($meta['function']) ? $meta['function'] : 'N/A';
            $class = isset($meta['class']) ? $meta['class'] : '';
            
            $formatted_date = date('M j, Y H:i:s', strtotime($log->timestamp));
            
            $html .= '<div class="jhl-log-entry">';
            $html .= '<div class="jhl-log-time">' . esc_html($formatted_date) . '</div>';
            
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
}