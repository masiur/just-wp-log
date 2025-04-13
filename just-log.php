<?php
/**
 * Plugin Name: Just Log
 * Description: A simple log viewer for WordPress with SQLite storage and real-time search
 * Version: 1.0
 * Author: Masiur
 * Author URI: www.MasiurSiddiki.com
 * Text Domain: just-log
 * Domain Path: /languages
 * License: GPL v2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include required files
require_once plugin_dir_path(__FILE__) . 'includes/database.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax.php';

// Global logging function
function just_log(...$data) {
    static $db = null;
    
    if ($db === null) {
        $db = new JustLogDatabase();
    }
    
    $logMessages = [];
    
    // Iterate over each argument and prepare the log messages
    foreach ($data as $item) {
        $logMessages[] = json_encode($item, JSON_PRETTY_PRINT);
    }
    
    // Get the backtrace and caller details
    $backtrace = debug_backtrace();
    $caller = isset($backtrace[0]) ? $backtrace[0] : [];
    $caller1 = isset($backtrace[1]) ? $backtrace[1] : [];
    
    // Prepare the caller information
    $calledBy = [
        'file' => isset($caller['file']) ? $caller['file'] : 'N/A',
        'line' => isset($caller['line']) ? $caller['line'] : 'N/A',
        'function' => isset($caller1['function']) ? $caller1['function'] : 'N/A',
        'class' => isset($caller1['class']) ? $caller1['class'] : 'N/A',
    ];
    
    // Insert log into database
    $timestamp = date('Y-m-d H:i:s');
    $message = implode(PHP_EOL, $logMessages);
    $db->insert_log($timestamp, $message, $calledBy);
}

class JustLog {
    private $per_page = 10;
    private $db;
    private $ajax;
    
    public function __construct() {
        $this->db = new JustLogDatabase();
        $this->ajax = new JustLogAjax($this->db);
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Just Log',
            'Just Log',
            'manage_options',
            'just-log-viewer',
            array($this, 'display_logs'),
            'dashicons-list-view'
        );
    }
    
    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_just-log-viewer') {
            return;
        }
        
        wp_enqueue_style('just-log-viewer', plugins_url('css/style.css', __FILE__));
        wp_enqueue_style('dashicons');
        wp_enqueue_script('just-log-viewer', plugins_url('js/script.js', __FILE__), array('jquery'), '1.0.0', true);
        
        wp_localize_script('just-log-viewer', 'justLogData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('just_log_nonce'),
            'perPage' => $this->per_page
        ));
    }
    
    public function display_logs() {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Just Log Viewer</h1>
            
            <div class="log-controls">
                <div class="pagination-container" style="float: left;">
                    <!-- Pagination will be displayed here -->
                </div>
                <div class="search-box">
                    <input type="search" id="log-search" placeholder="Search logs..." value="">
                    <button id="search-logs" class="button button-primary">Search</button>
                    <button id="reset-search" class="button button-secondary">Reset Search</button>
                </div>
                <div class="action-buttons">
                    <button id="refresh-logs" class="button button-refresh">Refresh</button>
                    <button id="clear-logs" class="button button-danger">Reset Logs</button>
                </div>
            </div>
            <div class="log-view-container">
                <div id="log-container">
                    <div class="spinner-container">
                        <span class="spinner is-active"></span>
                    </div>
                </div>
                <div class="scroll-buttons">
                    <button id="scroll-top" class="button button-secondary">Scroll to Top</button>
                    <button id="scroll-bottom" class="button button-secondary">Scroll to Bottom</button>
                </div>
            </div>
            
            <!-- Floating Scroll Buttons -->
            <div class="floating-scroll">
                <button id="floating-scroll-top" title="Scroll to Top">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 4l-8 8h5v8h6v-8h5z"></path>
                    </svg>
                </button>
                <button id="floating-scroll-bottom" title="Scroll to Bottom">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 20l8-8h-5V4h-6v8H4z"></path>
                    </svg>
                </button>
            </div>
            
            <div class="just-log-footer">
                Just Log v1.1.0 | Created with <span style="color: #e25555;">♥</span> by Masiur
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    new JustLog();
});