<?php
/**
 * Fluent Forms integration.
 *
 * Only instantiated when Fluent Forms is active (see Rentman_Availability_Calendar::init()).
 *
 * - Browser: watches the date field (flatpickr datepicker or native date input)
 *   and shows the availability message under it.
 * - Server: adds a validation error on the date field via fluentform/validation_errors
 *   when the date is fully booked and blocking is switched on.
 */

if (!defined('ABSPATH')) {
    exit;
}

class RAC_Fluent_Forms {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function is_plugin_active() {
        return defined('FLUENTFORM') || function_exists('wpFluentForm');
    }

    private function __construct() {
        add_action('fluentform/before_form_render', [$this, 'maybe_enqueue_assets'], 10, 2);
        add_filter('fluentform/validation_errors', [$this, 'maybe_block_submission'], 10, 4);
    }

    public function is_enabled() {
        $settings = get_option(RAC_OPTION_KEY, []);
        $enabled = isset($settings['ff_enabled']) ? (bool) $settings['ff_enabled'] : false;
        return $enabled && self::is_plugin_active();
    }

    public function get_config() {
        $settings = get_option(RAC_OPTION_KEY, []);
        return [
            'form_id'    => isset($settings['ff_form_id']) ? absint($settings['ff_form_id']) : 0,
            'date_field' => isset($settings['ff_date_field']) ? preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $settings['ff_date_field']) : '',
        ];
    }

    /**
     * @param object $form Fluent Forms form model/object (has ->id).
     * @param array  $atts Shortcode attributes.
     */
    public function maybe_enqueue_assets($form, $atts = []) {
        if (!$this->is_enabled()) {
            return;
        }

        $form_id = $this->get_form_id($form);
        $config = $this->get_config();

        if ($config['form_id'] && $form_id !== $config['form_id']) {
            return;
        }

        RAC_Form_Availability::instance()->add_form([
            'platform'  => 'ff',
            'formId'    => $form_id,
            'dateField' => $config['date_field'],
        ]);
    }

    /**
     * @param array  $errors   Errors keyed by input name => string[].
     * @param array  $formData Submitted values keyed by input name.
     * @param object $form     Form object.
     * @param array  $fields   Parsed inputs keyed by input name.
     * @return array
     */
    public function maybe_block_submission($errors, $formData, $form, $fields) {
        if (!$this->is_enabled() || !is_array($formData)) {
            return $errors;
        }

        $config = $this->get_config();

        if ($config['form_id'] && $this->get_form_id($form) !== $config['form_id']) {
            return $errors;
        }

        $date_fields = $config['date_field'] !== ''
            ? [$config['date_field']]
            : $this->detect_date_fields($fields);

        foreach ($date_fields as $name) {
            if (!isset($formData[$name]) || !is_scalar($formData[$name])) {
                continue;
            }

            $error = RAC_Form_Availability::instance()->get_blocking_error((string) $formData[$name]);

            if ($error !== '') {
                if (!isset($errors[$name]) || !is_array($errors[$name])) {
                    $errors[$name] = [];
                }
                $errors[$name]['rac_unavailable'] = $error;
            }
        }

        return $errors;
    }

    /**
     * Finds the input names of all date fields in a form.
     *
     * @param array $fields Parsed inputs from Fluent Forms.
     * @return string[]
     */
    private function detect_date_fields($fields) {
        $names = [];
        if (!is_array($fields)) {
            return $names;
        }

        foreach ($fields as $name => $field) {
            $element = '';
            if (isset($field['element'])) {
                $element = $field['element'];
            } elseif (isset($field['raw']['element'])) {
                $element = $field['raw']['element'];
            }

            if ($element === 'input_date') {
                $names[] = (string) $name;
            }
        }

        return $names;
    }

    private function get_form_id($form) {
        if (is_object($form) && isset($form->id)) {
            return (int) $form->id;
        }
        if (is_array($form) && isset($form['id'])) {
            return (int) $form['id'];
        }
        return 0;
    }
}
