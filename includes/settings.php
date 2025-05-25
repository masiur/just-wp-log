<?php
/**
 * Settings handler for Just Log
 */

class JustLogSettings {
    const OPTION_KEY = 'just_log_settings';
    
    private $settings;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $default_file_path = trailingslashit($upload_dir['basedir']) . 'just-log/jl.txt';
        $this->settings = get_option(self::OPTION_KEY, [
            'storage_type' => 'mysql', // mysql or file
            'file_path' => $default_file_path
        ]);
        
        add_action('admin_init', array($this, 'register_settings'));
        
        // Create log directory if file storage is selected
        if ($this->get_setting('storage_type') === 'file') {
            $this->ensure_log_directory();
        }
    }
    
    private function ensure_log_directory() {
        $file_path = $this->get_setting('file_path');
        if (!$file_path) return;
        
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        WP_Filesystem();
        global $wp_filesystem;
        
        $dir = dirname($file_path);
        if (!$wp_filesystem->exists($dir)) {
            wp_mkdir_p($dir);
        }
        
        if (!$wp_filesystem->exists($file_path)) {
            $wp_filesystem->put_contents($file_path, '');
        }
        
        if (!$wp_filesystem->is_writable($file_path)) {
            if (function_exists('add_action') && function_exists('__') && function_exists('esc_html')) {
                add_action('admin_notices', function() use ($file_path) {
                    /* translators: %s: File path that is not writable */
                    $message = __('Just Log: Log file is not writable. Please check permissions for: %s', 'just-log');
                    echo wp_kses_post('<div class="notice notice-error"><p>' . 
                         sprintf($message, esc_html($this->get_setting('file_path'))) . 
                         '</p></div>');
                });
            }
        }
    }
    
    public function register_settings() {
        register_setting(
            'just_log_settings', 
            self::OPTION_KEY,
            array(
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );
        
        add_settings_section(
            'just_log_storage_section',
            __('Storage Settings', 'just-log'),
            array($this, 'storage_section_callback'),
            'just-log-settings'
        );
        
        add_settings_field(
            'storage_type',
            __('Storage Type', 'just-log'),
            array($this, 'storage_type_callback'),
            'just-log-settings',
            'just_log_storage_section'
        );
        
        add_settings_field(
            'file_path',
            __('File Path', 'just-log'),
            array($this, 'file_path_callback'),
            'just-log-settings',
            'just_log_storage_section'
        );
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        if (isset($input['storage_type'])) {
            $sanitized['storage_type'] = sanitize_text_field($input['storage_type']);
        }
        if (isset($input['file_path'])) {
            $sanitized['file_path'] = sanitize_text_field($input['file_path']);
        }
        return $sanitized;
    }
    
    public function storage_section_callback() {
        echo wp_kses_post('<p>' . esc_html__('Configure how Just Log stores your log data.', 'just-log') . '</p>');
    }
    
    public function storage_type_callback() {
        $type = isset($this->settings['storage_type']) ? $this->settings['storage_type'] : 'mysql';
        ?>
        <select name="<?php echo esc_attr(self::OPTION_KEY); ?>[storage_type]" id="storage_type">
            <option value="mysql" <?php selected($type, 'mysql'); ?>><?php esc_html_e('MySQL Database', 'just-log'); ?></option>
            <option value="file" <?php selected($type, 'file'); ?>><?php esc_html_e('File System', 'just-log'); ?></option>
        </select>
        <?php
    }
    
    public function file_path_callback() {
        $upload_dir = wp_upload_dir();
        $default_file_path = trailingslashit($upload_dir['basedir']) . 'just-log/jl.txt';
        $path = isset($this->settings['file_path']) ? $this->settings['file_path'] : $default_file_path;
        ?>
        <input type="text" name="<?php echo esc_attr(self::OPTION_KEY); ?>[file_path]" value="<?php echo esc_attr($path); ?>" class="regular-text">
        <p class="description"><?php esc_html_e('Full path where log file will be stored when using file storage.', 'just-log'); ?></p>
        <?php
    }
    
    public function get_setting($key) {
        return isset($this->settings[$key]) ? $this->settings[$key] : null;
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Just Log Settings', 'just-log'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('just_log_settings');
                do_settings_sections('just-log-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}