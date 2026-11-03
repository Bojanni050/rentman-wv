<?php
/**
 * Shared realtime availability check for form plugins.
 *
 * Holds everything that does not depend on a specific form plugin:
 * the AJAX endpoint, the message/blocking settings, the front-end assets
 * and the per-form configuration that integrations hand to the browser.
 *
 * Integrations (Gravity Forms, Fluent Forms) only decide *which* form and
 * date field to watch and how to block a submission server-side.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAC_Form_Availability {

    private static $instance = null;

    /** @var array[] Form configs queued for the browser on this request. */
    private $form_configs = [];

    /** @var bool Whether the inline config has been attached already. */
    private $configs_printed = false;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('wp_ajax_rac_check_date_availability', [$this, 'ajax_check_date_availability']);
        add_action('wp_ajax_nopriv_rac_check_date_availability', [$this, 'ajax_check_date_availability']);
        add_action('wp_print_footer_scripts', [$this, 'print_form_configs'], 1);
    }

    /**
     * Settings shared by all form integrations.
     *
     * The option keys keep their historical gf_ prefix so existing
     * installs keep their messages and choices after the update.
     *
     * @return array
     */
    public function get_shared_config() {
        $settings = get_option(RAC_OPTION_KEY, []);
        return [
            'block_unavailable' => isset($settings['gf_block_unavailable']) ? (bool) $settings['gf_block_unavailable'] : false,
            'msg_available'     => isset($settings['gf_msg_available']) ? $settings['gf_msg_available'] : __('Deze datum is beschikbaar.', 'rentman-availability-calendar'),
            'msg_limited'       => isset($settings['gf_msg_limited']) ? $settings['gf_msg_limited'] : __('Voor deze datum is nog beperkte beschikbaarheid.', 'rentman-availability-calendar'),
            'msg_unavailable'   => isset($settings['gf_msg_unavailable']) ? $settings['gf_msg_unavailable'] : __('Helaas is deze datum niet beschikbaar.', 'rentman-availability-calendar'),
            'date_format'       => isset($settings['gf_date_format']) ? $settings['gf_date_format'] : 'd/m/Y',
            'msg_position'      => isset($settings['gf_msg_position']) ? $settings['gf_msg_position'] : 'below',
            'msg_style'         => isset($settings['gf_msg_style']) ? $settings['gf_msg_style'] : 'full',
        ];
    }

    public function register_assets() {
        wp_register_style(
            'rac-forms-style',
            RAC_PLUGIN_URL . 'css/form-availability.css',
            [],
            RAC_VERSION
        );
        wp_register_script(
            'rac-forms-script',
            RAC_PLUGIN_URL . 'js/form-availability.js',
            ['jquery'],
            RAC_VERSION,
            true
        );
        wp_localize_script('rac-forms-script', 'racFormData', [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce(RAC_GF_NONCE_ACTION),
            'msgChecking' => __('Beschikbaarheid controleren...', 'rentman-availability-calendar'),
            'msgError'    => __('Er is een fout opgetreden.', 'rentman-availability-calendar'),
        ]);
    }

    /**
     * Called by an integration when one of its forms is rendered.
     *
     * @param array $form_config {
     *     @type string     $platform  'gf' or 'ff'.
     *     @type int        $formId    Form ID, 0 = any form of that platform.
     *     @type int|string $dateField GF field ID or FF input name; empty = auto-detect.
     * }
     */
    public function add_form($form_config) {
        if (!wp_script_is('rac-forms-script', 'registered')) {
            $this->register_assets();
        }

        wp_enqueue_style('rac-forms-style');
        wp_enqueue_script('rac-forms-script');

        $shared = $this->get_shared_config();
        $config = array_merge([
            'platform'         => '',
            'formId'           => 0,
            'dateField'        => '',
            'blockUnavailable' => $shared['block_unavailable'],
            'msgPosition'      => $shared['msg_position'],
            'msgStyle'         => $shared['msg_style'],
        ], $form_config);

        $key = $config['platform'] . ':' . $config['formId'];
        $this->form_configs[$key] = $config;
    }

    /**
     * Attaches the queued form configs right before footer scripts print,
     * so forms rendered anywhere in the page body are included.
     */
    public function print_form_configs() {
        if ($this->configs_printed || empty($this->form_configs)) {
            return;
        }
        $this->configs_printed = true;

        wp_add_inline_script(
            'rac-forms-script',
            sprintf(
                'window.racFormConfigs = (window.racFormConfigs || []).concat(%s);',
                wp_json_encode(array_values($this->form_configs))
            ),
            'before'
        );
    }

    /**
     * Checks a date and adds the configured message.
     *
     * @param string $date_string Raw date value from the form.
     * @return array Result from RAC_Calendar::get_date_availability() plus message/block_submission.
     */
    public function check_date($date_string) {
        $calendar = RAC_Calendar::instance();
        $result = $calendar->get_date_availability($date_string);

        if (empty($result['success'])) {
            return $result;
        }

        $config = $this->get_shared_config();
        $message = '';
        if ($result['status'] === 'available') {
            $message = $config['msg_available'];
        } elseif ($result['status'] === 'limited') {
            $message = $config['msg_limited'];
        } elseif ($result['status'] === 'unavailable') {
            $message = $config['msg_unavailable'];
        }

        $result['message'] = $message;
        $result['block_submission'] = ($result['status'] === 'unavailable' && $config['block_unavailable']);

        return $result;
    }

    /**
     * Server-side guard used by integrations on submit.
     *
     * @param string $date_string Submitted date value.
     * @return string Error message when the submission must be blocked, otherwise ''.
     */
    public function get_blocking_error($date_string) {
        $config = $this->get_shared_config();

        if (!$config['block_unavailable'] || $date_string === '' || $date_string === null) {
            return '';
        }

        $result = RAC_Calendar::instance()->get_date_availability($date_string);

        if (!empty($result['success']) && $result['status'] === 'unavailable') {
            RAC_Logger::instance()->log('Blocking submission', ['date' => $date_string, 'status' => $result['status']]);
            return $config['msg_unavailable'];
        }

        RAC_Logger::instance()->log('Allowing submission', [
            'date'   => $date_string,
            'status' => isset($result['status']) ? $result['status'] : '',
        ]);

        return '';
    }

    public function ajax_check_date_availability() {
        $logger = RAC_Logger::instance();

        check_ajax_referer(RAC_GF_NONCE_ACTION, 'nonce');

        $date_string = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';

        $logger->log('ajax_check_date_availability', ['date' => $date_string]);

        if ($date_string === '') {
            wp_send_json_error([
                'success' => false,
                'message' => __('Geen datum opgegeven.', 'rentman-availability-calendar'),
            ]);
        }

        $result = $this->check_date($date_string);

        if (empty($result['success'])) {
            wp_send_json_error($result);
        }

        $logger->log('Availability result', [
            'date'             => $date_string,
            'status'           => isset($result['status']) ? $result['status'] : '',
            'count'            => isset($result['count']) ? $result['count'] : 0,
            'block_submission' => $result['block_submission'],
        ]);

        wp_send_json_success($result);
    }
}
