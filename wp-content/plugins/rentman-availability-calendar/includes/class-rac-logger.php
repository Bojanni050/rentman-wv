<?php
if (!defined('ABSPATH')) {
    exit;
}

class RAC_Logger {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
    }

    public function is_enabled() {
        $settings = get_option(RAC_OPTION_KEY, []);
        return isset($settings['debug_logging']) ? (bool) $settings['debug_logging'] : false;
    }

    public function log($message, $context = []) {
        if (!$this->is_enabled()) {
            return;
        }

        $timestamp = current_time('Y-m-d H:i:s');
        $entry = "[{$timestamp}] {$message}";

        if (!empty($context)) {
            $entry .= ' ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->append_to_file($entry);
    }

    public function log_request($endpoint, $params, $response_code = null, $error = null) {
        if (!$this->is_enabled()) {
            return;
        }

        $context = [
            'endpoint' => $endpoint,
            'params'   => $params,
        ];

        if ($response_code !== null) {
            $context['http_code'] = $response_code;
        }

        if ($error !== null) {
            $context['error'] = $error;
        }

        $this->log('API Request', $context);
    }

    public function log_cache($action, $key, $extra = []) {
        if (!$this->is_enabled()) {
            return;
        }

        $context = array_merge(['action' => $action, 'cache_key' => $key], $extra);
        $this->log('Cache', $context);
    }

    public function log_availability($date, $status, $count, $extra = []) {
        if (!$this->is_enabled()) {
            return;
        }

        $context = array_merge([
            'date'   => $date,
            'status' => $status,
            'count'  => $count,
        ], $extra);

        $this->log('Availability', $context);
    }

    public function get_log_file_path() {
        $upload_dir = wp_upload_dir();
        $log_dir = trailingslashit($upload_dir['basedir']) . 'rac-logs/';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        return $log_dir . 'debug.log';
    }

    public function get_log_file_url() {
        $upload_dir = wp_upload_dir();
        return trailingslashit($upload_dir['baseurl']) . 'rac-logs/debug.log';
    }

    private function append_to_file($entry) {
        $path = $this->get_log_file_path();
        $entry = $entry . "\n";
        @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
    }

    public function clear_log() {
        $path = $this->get_log_file_path();
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    public function get_log_contents($max_lines = 500) {
        $path = $this->get_log_file_path();
        if (!file_exists($path)) {
            return '';
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return '';
        }

        $lines = explode("\n", $contents);
        $lines = array_slice($lines, max(0, count($lines) - $max_lines));
        return implode("\n", $lines);
    }
}
