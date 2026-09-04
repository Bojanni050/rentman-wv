<?php
if (!defined('ABSPATH')) {
    exit;
}

class RAC_Settings {

    private static $instance = null;
    private $api_client = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_test_connection']);
        add_action('admin_init', [$this, 'handle_clear_cache']);
    }

    private function get_api_client() {
        if ($this->api_client === null) {
            $this->api_client = new RAC_API_Client();
        }
        return $this->api_client;
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Rentman Calendar', 'rentman-availability-calendar'),
            __('Rentman Calendar', 'rentman-availability-calendar'),
            'manage_options',
            'rentman-availability-calendar',
            [$this, 'render_settings_page'],
            'dashicons-calendar-alt',
            80
        );
    }

    public function register_settings() {
        register_setting(
            'rac_settings_group',
            RAC_OPTION_KEY,
            [$this, 'sanitize_settings']
        );

        add_settings_section(
            'rac_main_section',
            __('API Configuration', 'rentman-availability-calendar'),
            [$this, 'render_section_info'],
            'rentman-availability-calendar'
        );

        add_settings_field(
            'api_token',
            __('API Token', 'rentman-availability-calendar'),
            [$this, 'render_token_field'],
            'rentman-availability-calendar',
            'rac_main_section'
        );

        add_settings_field(
            'cache_minutes',
            __('Cache Duration (minutes)', 'rentman-availability-calendar'),
            [$this, 'render_cache_field'],
            'rentman-availability-calendar',
            'rac_main_section'
        );

        add_settings_field(
            'shortcode_info',
            __('Shortcode', 'rentman-availability-calendar'),
            [$this, 'render_shortcode_field'],
            'rentman-availability-calendar',
            'rac_main_section'
        );

        add_settings_section(
            'rac_gf_section',
            __('Gravity Forms Integration', 'rentman-availability-calendar'),
            [$this, 'render_gf_section_info'],
            'rentman-availability-calendar'
        );

        add_settings_field(
            'gf_enabled',
            __('Enable Integration', 'rentman-availability-calendar'),
            [$this, 'render_gf_enabled_field'],
            'rentman-availability-calendar',
            'rac_gf_section'
        );

        add_settings_field(
            'gf_form_id',
            __('Gravity Forms Form ID', 'rentman-availability-calendar'),
            [$this, 'render_gf_form_id_field'],
            'rentman-availability-calendar',
            'rac_gf_section'
        );

        add_settings_field(
            'gf_date_field_id',
            __('Date Field ID', 'rentman-availability-calendar'),
            [$this, 'render_gf_date_field_id_field'],
            'rentman-availability-calendar',
            'rac_gf_section'
        );

        add_settings_field(
            'gf_block_unavailable',
            __('Block Unavailable Dates', 'rentman-availability-calendar'),
            [$this, 'render_gf_block_unavailable_field'],
            'rentman-availability-calendar',
            'rac_gf_section'
        );

        add_settings_field(
            'gf_msg_available',
            __('Available Message', 'rentman-availability-calendar'),
            [$this, 'render_gf_msg_available_field'],
            'rentman-availability-calendar',
            'rac_gf_section'
        );

        add_settings_field(
            'gf_msg_limited',
            __('Limited Availability Message', 'rentman-availability-calendar'),
            [$this, 'render_gf_msg_limited_field'],
            'rentman-availability-calendar',
            'rac_gf_section'
        );

        add_settings_field(
            'gf_msg_unavailable',
            __('Unavailable Message', 'rentman-availability-calendar'),
            [$this, 'render_gf_msg_unavailable_field'],
            'rentman-availability-calendar',
            'rac_gf_section'
        );
    }

    public function sanitize_settings($input) {
        $sanitized = [];

        $sanitized['api_token'] = isset($input['api_token'])
            ? sanitize_text_field($input['api_token'])
            : '';

        $sanitized['cache_minutes'] = isset($input['cache_minutes'])
            ? max(1, absint($input['cache_minutes']))
            : RAC_DEFAULT_CACHE_MINUTES;

        $sanitized['gf_enabled'] = isset($input['gf_enabled']) ? (bool) $input['gf_enabled'] : false;
        $sanitized['gf_form_id'] = isset($input['gf_form_id']) ? absint($input['gf_form_id']) : 0;
        $sanitized['gf_date_field_id'] = isset($input['gf_date_field_id']) ? absint($input['gf_date_field_id']) : 0;
        $sanitized['gf_block_unavailable'] = isset($input['gf_block_unavailable']) ? (bool) $input['gf_block_unavailable'] : true;
        $sanitized['gf_msg_available'] = isset($input['gf_msg_available']) ? sanitize_text_field($input['gf_msg_available']) : __('Deze datum is beschikbaar.', 'rentman-availability-calendar');
        $sanitized['gf_msg_limited'] = isset($input['gf_msg_limited']) ? sanitize_text_field($input['gf_msg_limited']) : __('Voor deze datum is nog beperkte beschikbaarheid.', 'rentman-availability-calendar');
        $sanitized['gf_msg_unavailable'] = isset($input['gf_msg_unavailable']) ? sanitize_text_field($input['gf_msg_unavailable']) : __('Helaas is deze datum niet beschikbaar.', 'rentman-availability-calendar');

        $this->get_api_client()->clear_cache();

        return $sanitized;
    }

    public function render_section_info() {
        echo '<p>' . esc_html__('Configure your Rentman API connection below.', 'rentman-availability-calendar') . '</p>';
    }

    public function render_token_field() {
        $settings = get_option(RAC_OPTION_KEY, []);
        $token = isset($settings['api_token']) ? $settings['api_token'] : '';
        echo '<input type="password" name="' . esc_attr(RAC_OPTION_KEY) . '[api_token]" value="' . esc_attr($token) . '" class="regular-text" placeholder="' . esc_attr__('Enter your Rentman API token', 'rentman-availability-calendar') . '" />';
        echo '<p class="description">' . esc_html__('Generate your token in Rentman under Settings > Configuration > Account > Integrations > API.', 'rentman-availability-calendar') . '</p>';
    }

    public function render_cache_field() {
        $settings = get_option(RAC_OPTION_KEY, []);
        $minutes = isset($settings['cache_minutes']) ? $settings['cache_minutes'] : RAC_DEFAULT_CACHE_MINUTES;
        echo '<input type="number" name="' . esc_attr(RAC_OPTION_KEY) . '[cache_minutes]" value="' . esc_attr($minutes) . '" min="1" max="1440" class="small-text" />';
        echo '<p class="description">' . esc_html__('How long to cache appointment data before re-fetching from the API (1-1440 minutes).', 'rentman-availability-calendar') . '</p>';
    }

    public function render_shortcode_field() {
        echo '<code>[rentman_calendar]</code>';
        echo '<p class="description">' . esc_html__('Add this shortcode to any page or post to display the availability calendar.', 'rentman-availability-calendar') . '</p>';
    }

    public function render_gf_section_info() {
        $gf_active = class_exists('GFForms');
        echo '<p>' . esc_html__('Configure the Gravity Forms integration for realtime availability checks when a visitor selects an event date.', 'rentman-availability-calendar') . '</p>';
        if (!$gf_active) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Gravity Forms is not active. Install and activate the Gravity Forms plugin to use this integration.', 'rentman-availability-calendar') . '</p></div>';
        }
    }

    public function render_gf_enabled_field() {
        $settings = get_option(RAC_OPTION_KEY, []);
        $enabled = isset($settings['gf_enabled']) ? (bool) $settings['gf_enabled'] : false;
        echo '<label><input type="checkbox" name="' . esc_attr(RAC_OPTION_KEY) . '[gf_enabled]" value="1" ' . checked($enabled, true, false) . ' /> ' . esc_html__('Enable realtime availability check in Gravity Forms', 'rentman-availability-calendar') . '</label>';
    }

    public function render_gf_form_id_field() {
        $settings = get_option(RAC_OPTION_KEY, []);
        $form_id = isset($settings['gf_form_id']) ? $settings['gf_form_id'] : 0;
        echo '<input type="number" name="' . esc_attr(RAC_OPTION_KEY) . '[gf_form_id]" value="' . esc_attr($form_id) . '" class="small-text" min="0" />';
        echo '<p class="description">' . esc_html__('The ID of the Gravity Forms form that contains the date field. Use 0 to apply to all forms.', 'rentman-availability-calendar') . '</p>';
    }

    public function render_gf_date_field_id_field() {
        $settings = get_option(RAC_OPTION_KEY, []);
        $field_id = isset($settings['gf_date_field_id']) ? $settings['gf_date_field_id'] : 0;
        echo '<input type="number" name="' . esc_attr(RAC_OPTION_KEY) . '[gf_date_field_id]" value="' . esc_attr($field_id) . '" class="small-text" min="0" />';
        echo '<p class="description">' . esc_html__('The field ID of the date field in the Gravity Form. Use 0 to auto-detect date fields.', 'rentman-availability-calendar') . '</p>';
    }

    public function render_gf_block_unavailable_field() {
        $settings = get_option(RAC_OPTION_KEY, []);
        $block = isset($settings['gf_block_unavailable']) ? (bool) $settings['gf_block_unavailable'] : true;
        echo '<label><input type="checkbox" name="' . esc_attr(RAC_OPTION_KEY) . '[gf_block_unavailable]" value="1" ' . checked($block, true, false) . ' /> ' . esc_html__('Block form submission when the selected date is unavailable', 'rentman-availability-calendar') . '</label>';
    }

    public function render_gf_msg_available_field() {
        $settings = get_option(RAC_OPTION_KEY, []);
        $msg = isset($settings['gf_msg_available']) ? $settings['gf_msg_available'] : __('Deze datum is beschikbaar.', 'rentman-availability-calendar');
        echo '<input type="text" name="' . esc_attr(RAC_OPTION_KEY) . '[gf_msg_available]" value="' . esc_attr($msg) . '" class="regular-text" />';
    }

    public function render_gf_msg_limited_field() {
        $settings = get_option(RAC_OPTION_KEY, []);
        $msg = isset($settings['gf_msg_limited']) ? $settings['gf_msg_limited'] : __('Voor deze datum is nog beperkte beschikbaarheid.', 'rentman-availability-calendar');
        echo '<input type="text" name="' . esc_attr(RAC_OPTION_KEY) . '[gf_msg_limited]" value="' . esc_attr($msg) . '" class="regular-text" />';
    }

    public function render_gf_msg_unavailable_field() {
        $settings = get_option(RAC_OPTION_KEY, []);
        $msg = isset($settings['gf_msg_unavailable']) ? $settings['gf_msg_unavailable'] : __('Helaas is deze datum niet beschikbaar.', 'rentman-availability-calendar');
        echo '<input type="text" name="' . esc_attr(RAC_OPTION_KEY) . '[gf_msg_unavailable]" value="' . esc_attr($msg) . '" class="regular-text" />';
    }

    public function handle_test_connection() {
        if (!isset($_GET['rac_action']) || $_GET['rac_action'] !== 'test_connection') {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('rac_test_connection');

        $client = $this->get_api_client();
        $result = $client->test_connection();

        if (is_wp_error($result)) {
            add_settings_error('rac_messages', 'rac_test_failed', $result->get_error_message(), 'error');
        } else {
            add_settings_error('rac_messages', 'rac_test_success', __('Connection successful! The API token is valid.', 'rentman-availability-calendar'), 'success');
        }
    }

    public function handle_clear_cache() {
        if (!isset($_GET['rac_action']) || $_GET['rac_action'] !== 'clear_cache') {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('rac_clear_cache');

        $this->get_api_client()->clear_cache();
        add_settings_error('rac_messages', 'rac_cache_cleared', __('Cache cleared successfully.', 'rentman-availability-calendar'), 'success');
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $client = $this->get_api_client();
        $is_configured = $client->is_configured();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <?php settings_errors('rac_messages'); ?>

            <?php if (!$is_configured): ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('Please enter your Rentman API token below to get started.', 'rentman-availability-calendar'); ?></p>
                </div>
            <?php endif; ?>

            <div class="rac-admin-actions" style="margin: 15px 0;">
                <?php if ($is_configured): ?>
                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('rac_action', 'test_connection', admin_url('admin.php?page=rentman-availability-calendar')), 'rac_test_connection')); ?>" class="button button-secondary">
                        <?php esc_html_e('Test Connection', 'rentman-availability-calendar'); ?>
                    </a>
                    <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('rac_action', 'clear_cache', admin_url('admin.php?page=rentman-availability-calendar')), 'rac_clear_cache')); ?>" class="button button-secondary">
                        <?php esc_html_e('Clear Cache', 'rentman-availability-calendar'); ?>
                    </a>
                <?php endif; ?>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('rac_settings_group');
                do_settings_sections('rentman-availability-calendar');
                submit_button();
                ?>
            </form>

            <div class="rac-legend-preview" style="margin-top: 30px; padding: 20px; background: #0a0a0a; border: 1px solid #d4d4d4; border-radius: 8px; border-top: 3px solid #c9a227;">
                <h3 style="color: #c9a227; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 300; font-size: 1.1rem; margin-top: 0;"><?php esc_html_e('Color Legend', 'rentman-availability-calendar'); ?></h3>
                <p style="color: #8a8a8a; font-size: 0.85rem;"><?php esc_html_e('The calendar uses the following color scheme:', 'rentman-availability-calendar'); ?></p>
                <div style="display: flex; gap: 24px; flex-wrap: wrap; margin-top: 14px;">
                    <div style="display: flex; align-items: center; gap: 8px; color: #fff; font-size: 0.85rem;">
                        <span style="display:inline-block; width:22px; height:22px; border-radius:50%; background:#22c55e; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></span>
                        <?php esc_html_e('0 appointments (Available)', 'rentman-availability-calendar'); ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; color: #fff; font-size: 0.85rem;">
                        <span style="display:inline-block; width:22px; height:22px; border-radius:50%; background:#f59e0b; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></span>
                        <?php esc_html_e('1-2 appointments (Busy)', 'rentman-availability-calendar'); ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px; color: #fff; font-size: 0.85rem;">
                        <span style="display:inline-block; width:22px; height:22px; border-radius:50%; background:#ef4444; box-shadow: 0 1px 3px rgba(0,0,0,0.3);"></span>
                        <?php esc_html_e('3+ appointments (Full)', 'rentman-availability-calendar'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
