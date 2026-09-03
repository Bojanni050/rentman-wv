<?php
/**
 * Plugin Name: Rentman Availability Calendar
 * Description: Displays a color-coded availability calendar based on appointment counts from the Rentman API. 0 appointments = green, 1-2 = orange, 3+ = red.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL-2.0-or-later
 * Text Domain: rentman-availability-calendar
 */

if (!defined('ABSPATH')) {
    exit;
}

define('RAC_VERSION', '1.0.0');
define('RAC_PLUGIN_FILE', __FILE__);
define('RAC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RAC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RAC_OPTION_KEY', 'rac_settings');
define('RAC_TRANSIENT_PREFIX', 'rac_cache_');
define('RAC_DEFAULT_CACHE_MINUTES', 15);

require_once RAC_PLUGIN_DIR . 'includes/class-rac-api-client.php';
require_once RAC_PLUGIN_DIR . 'includes/class-rac-settings.php';
require_once RAC_PLUGIN_DIR . 'includes/class-rac-calendar.php';
require_once RAC_PLUGIN_DIR . 'includes/class-rac-elementor-widget.php';

class Rentman_Availability_Calendar {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('wp_ajax_rac_get_month_data', [$this, 'ajax_get_month_data']);
        add_action('wp_ajax_nopriv_rac_get_month_data', [$this, 'ajax_get_month_data']);
        add_action('elementor/widgets/register', [$this, 'register_elementor_widget']);
    }

    public function init() {
        RAC_Settings::instance();
        RAC_Calendar::instance();
    }

    public function register_elementor_widget($widgets_manager) {
        if (did_action('elementor/loaded')) {
            $widgets_manager->register(new RAC_Elementor_Widget());
        }
    }

    public function register_assets() {
        wp_register_style(
            'rac-calendar-style',
            RAC_PLUGIN_URL . 'css/calendar.css',
            [],
            RAC_VERSION
        );
        wp_register_script(
            'rac-calendar-script',
            RAC_PLUGIN_URL . 'js/calendar.js',
            ['jquery'],
            RAC_VERSION,
            true
        );
        wp_localize_script('rac-calendar-script', 'racData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('rac_calendar_nonce'),
        ]);
    }

    public function ajax_get_month_data() {
        check_ajax_referer('rac_calendar_nonce', 'nonce');

        $year  = isset($_POST['year']) ? absint($_POST['year']) : (int) gmdate('Y');
        $month = isset($_POST['month']) ? absint($_POST['month']) : (int) gmdate('n');

        if ($month < 1 || $month > 12) {
            wp_send_json_error(['message' => __('Invalid month.', 'rentman-availability-calendar')]);
        }

        $calendar = RAC_Calendar::instance();
        $data = $calendar->get_month_availability($year, $month);

        if (is_wp_error($data)) {
            wp_send_json_error(['message' => $data->get_error_message()]);
        }

        wp_send_json_success($data);
    }
}

Rentman_Availability_Calendar::instance();
