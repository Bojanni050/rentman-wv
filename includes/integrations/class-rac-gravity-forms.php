<?php
/**
 * Gravity Forms integration.
 *
 * Only instantiated when Gravity Forms is active (see Rentman_Availability_Calendar::init()),
 * so nothing here runs or breaks on sites without Gravity Forms.
 */

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

    public static function is_plugin_active() {
        return class_exists('GFForms');
    }

    private function __construct() {
        add_action('gform_enqueue_scripts', [$this, 'maybe_enqueue_assets'], 10, 2);
        add_filter('gform_validation', [$this, 'maybe_block_submission']);
    }

    public function is_enabled() {
        $settings = get_option(RAC_OPTION_KEY, []);
        $enabled = isset($settings['gf_enabled']) ? (bool) $settings['gf_enabled'] : false;
        return $enabled && self::is_plugin_active();
    }

    public function get_config() {
        $settings = get_option(RAC_OPTION_KEY, []);
        return [
            'form_id'       => isset($settings['gf_form_id']) ? absint($settings['gf_form_id']) : 0,
            'date_field_id' => isset($settings['gf_date_field_id']) ? absint($settings['gf_date_field_id']) : 0,
        ];
    }

    public function maybe_enqueue_assets($form, $is_ajax) {
        if (!$this->is_enabled() || !is_array($form) || !isset($form['id'])) {
            return;
        }

        $config = $this->get_config();

        if ($config['form_id'] && (int) $form['id'] !== $config['form_id']) {
            return;
        }

        RAC_Form_Availability::instance()->add_form([
            'platform'  => 'gf',
            'formId'    => (int) $form['id'],
            'dateField' => $config['date_field_id'],
        ]);
    }

    /**
     * Fails validation on the date field when the date is fully booked
     * and "block unavailable dates" is switched on.
     *
     * @param array $validation_result ['is_valid' => bool, 'form' => array]
     * @return array
     */
    public function maybe_block_submission($validation_result) {
        if (!$this->is_enabled() || empty($validation_result['form']['fields'])) {
            return $validation_result;
        }

        $form = $validation_result['form'];
        $config = $this->get_config();

        if ($config['form_id'] && (int) $form['id'] !== $config['form_id']) {
            return $validation_result;
        }

        foreach ($form['fields'] as &$field) {
            $is_target = $config['date_field_id']
                ? ((int) $field->id === $config['date_field_id'])
                : ($field->type === 'date');

            if (!$is_target) {
                continue;
            }

            $date_value = rgpost('input_' . $field->id);
            if (is_array($date_value)) {
                // Dropdown/multi-input date layouts are not checked (the browser check only supports datepicker/text input).
                continue;
            }

            $error = RAC_Form_Availability::instance()->get_blocking_error((string) $date_value);

            if ($error !== '') {
                $field->failed_validation = true;
                $field->validation_message = $error;
                $validation_result['is_valid'] = false;
            }

            if ($config['date_field_id']) {
                break;
            }
        }
        unset($field);

        $validation_result['form'] = $form;
        return $validation_result;
    }
}
