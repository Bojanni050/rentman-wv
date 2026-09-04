<?php
if (!defined('ABSPATH')) {
    exit;
}

class RAC_Gravity_Forms {

    private static $instance = null;

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
        add_action('gform_enqueue_scripts', [$this, 'maybe_enqueue_assets'], 10, 2);
        add_filter('gform_form_validation_errors', [$this, 'maybe_block_submission'], 10, 2);
    }

    public function is_enabled() {
        $settings = get_option(RAC_OPTION_KEY, []);
        $enabled = isset($settings['gf_enabled']) ? (bool) $settings['gf_enabled'] : false;
        return $enabled && $this->is_gravity_forms_active();
    }

    public function is_gravity_forms_active() {
        return class_exists('GFForms');
    }

    public function get_config() {
        $settings = get_option(RAC_OPTION_KEY, []);
        return [
            'enabled'           => isset($settings['gf_enabled']) ? (bool) $settings['gf_enabled'] : false,
            'form_id'           => isset($settings['gf_form_id']) ? absint($settings['gf_form_id']) : 0,
            'date_field_id'     => isset($settings['gf_date_field_id']) ? absint($settings['gf_date_field_id']) : 0,
            'block_unavailable' => isset($settings['gf_block_unavailable']) ? (bool) $settings['gf_block_unavailable'] : false,
            'msg_available'     => isset($settings['gf_msg_available']) ? $settings['gf_msg_available'] : __('Deze datum is beschikbaar.', 'rentman-availability-calendar'),
            'msg_limited'       => isset($settings['gf_msg_limited']) ? $settings['gf_msg_limited'] : __('Voor deze datum is nog beperkte beschikbaarheid.', 'rentman-availability-calendar'),
            'msg_unavailable'   => isset($settings['gf_msg_unavailable']) ? $settings['gf_msg_unavailable'] : __('Helaas is deze datum niet beschikbaar.', 'rentman-availability-calendar'),
        ];
    }

    public function register_assets() {
        wp_register_style(
            'rac-gf-style',
            RAC_PLUGIN_URL . 'css/gravity-forms.css',
            [],
            RAC_VERSION
        );
        wp_register_script(
            'rac-gf-script',
            RAC_PLUGIN_URL . 'js/gravity-forms.js',
            ['jquery'],
            RAC_VERSION,
            true
        );
        wp_localize_script('rac-gf-script', 'racGfData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('rac_gf_nonce'),
        ]);
    }

    public function maybe_enqueue_assets($form, $is_ajax) {
        if (!$this->is_enabled()) {
            return;
        }

        $config = $this->get_config();

        if ($config['form_id'] && (int) $form['id'] !== $config['form_id']) {
            return;
        }

        wp_enqueue_style('rac-gf-style');
        wp_enqueue_script('rac-gf-script');

        wp_add_inline_script('rac-gf-script', sprintf(
            'window.racGfConfig = %s;',
            wp_json_encode([
                'formId'       => $config['form_id'],
                'dateFieldId'  => $config['date_field_id'],
                'blockUnavailable' => $config['block_unavailable'],
                'messages'     => [
                    'available'   => $config['msg_available'],
                    'limited'     => $config['msg_limited'],
                    'unavailable' => $config['msg_unavailable'],
                ],
            ])
        ), 'before');
    }

    public function ajax_check_date_availability() {
        check_ajax_referer('rac_gf_nonce', 'nonce');

        $date_string = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';

        if (empty($date_string)) {
            wp_send_json_error([
                'success' => false,
                'message' => __('Geen datum opgegeven.', 'rentman-availability-calendar'),
            ]);
        }

        if (!class_exists('RAC_Calendar')) {
            wp_send_json_error([
                'success' => false,
                'message' => __('Kalender-plugin niet actief.', 'rentman-availability-calendar'),
            ]);
        }

        $calendar = RAC_Calendar::instance();
        $result = $calendar->get_date_availability($date_string);

        if (empty($result['success'])) {
            wp_send_json_error($result);
        }

        $config = $this->get_config();
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

        wp_send_json_success($result);
    }

    public function maybe_block_submission($validation_errors, $form) {
        if (!$this->is_enabled()) {
            return $validation_errors;
        }

        $config = $this->get_config();

        if (!$config['block_unavailable'] || !$config['form_id'] || !$config['date_field_id']) {
            return $validation_errors;
        }

        if ((int) $form['id'] !== $config['form_id']) {
            return $validation_errors;
        }

        $date_value = '';
        foreach ($form['fields'] as $field) {
            if ((int) $field->id === $config['date_field_id']) {
                $date_value = rgpost('input_' . $field->id);
                break;
            }
        }

        if (empty($date_value)) {
            return $validation_errors;
        }

        $calendar = RAC_Calendar::instance();
        $result = $calendar->get_date_availability($date_value);

        if (!empty($result['success']) && $result['status'] === 'unavailable') {
            $validation_errors[] = $config['msg_unavailable'];
        }

        return $validation_errors;
    }
}
