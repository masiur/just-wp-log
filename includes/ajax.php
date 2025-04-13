<?php
if (!defined('ABSPATH')) {
    exit;
}

class JustLogAjax {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        add_action('wp_ajax_just_log_search', array($this, 'handle_search'));
        add_action('wp_ajax_just_log_clear', array($this, 'handle_clear'));
    }
    
    public function handle_search() {
        // Check nonce
        if (!check_ajax_referer('just_log_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid security token');
        }
        
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : 10;
        
        $logs = $this->db->get_logs($page, $per_page, $search);
        
        ob_start();
        if (empty($logs['entries'])) {
            echo '<div class="notice notice-info"><p>No logs found.</p></div>';
        } else {
            foreach ($logs['entries'] as $log) {
                ?>
                <div class="log-entry card">
                    <div class="log-time"><strong>Time:</strong> <?php echo esc_html($log['timestamp']); ?></div>
                    <div class="log-meta"><strong>File:</strong> <?php echo esc_html($log['file']); ?> (Line: <?php echo esc_html($log['line']); ?>)</div>
                    <div class="log-meta"><strong>Function:</strong> <?php echo esc_html($log['class'] . '::' . $log['function']); ?></div>
                    <pre class="log-content"><?php echo esc_html($log['message']); ?></pre>
                </div>
                <?php
            }
            
            // Pagination
            $total_pages = ceil($logs['total'] / $per_page);
            if ($total_pages > 1) {
                ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links([
                            'base' => '#%#%',
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $page,
                            'mid_size' => 2
                        ]);
                        ?>
                    </div>
                </div>
                <?php
            }
        }
        $html = ob_get_clean();
        
        wp_send_json_success([
            'html' => $html,
            'total' => $logs['total'],
            'pages' => ceil($logs['total'] / $per_page)
        ]);
    }
    
    public function handle_clear() {
        // Check nonce and capabilities
        if (!check_ajax_referer('just_log_nonce', 'nonce', false) || !current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $result = $this->db->clear_logs();
        if ($result) {
            wp_send_json_success('Logs cleared successfully');
        } else {
            wp_send_json_error('Failed to clear logs');
        }
    }
}