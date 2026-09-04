<?php
if (!defined('ABSPATH')) {
    exit;
}

class RAC_API_Client {

    private $base_url = 'https://api.rentman.net';
    private $token = '';
    private $cache_minutes = RAC_DEFAULT_CACHE_MINUTES;

    public function __construct() {
        $settings = get_option(RAC_OPTION_KEY, []);
        $this->token = isset($settings['api_token']) ? trim($settings['api_token']) : '';
        $this->cache_minutes = isset($settings['cache_minutes']) ? max(1, (int) $settings['cache_minutes']) : RAC_DEFAULT_CACHE_MINUTES;
    }

    public function is_configured() {
        return !empty($this->token);
    }

    public function get_token() {
        return $this->token;
    }

    public function get_cache_minutes() {
        return $this->cache_minutes;
    }

    public function clear_cache() {
        global $wpdb;
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '" . RAC_TRANSIENT_PREFIX . "%'"
        );
        RAC_Logger::instance()->log_cache('clear_all', 'all', ['deleted' => (int) $deleted]);
    }

    public function clear_month_cache($year, $month) {
        $key = RAC_TRANSIENT_PREFIX . "appointments_{$year}_{$month}";
        delete_transient($key);
        RAC_Logger::instance()->log_cache('clear_month', $key);
    }

    public function test_connection() {
        if (!$this->is_configured()) {
            return new WP_Error('rac_no_token', __('No API token configured.', 'rentman-availability-calendar'));
        }

        $response = $this->request('/appointments', [
            'limit' => 1,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        return true;
    }

    public function get_appointments_for_month($year, $month) {
        return $this->get_projects_for_month($year, $month);
    }

    public function get_projects_for_month($year, $month) {
        $cache_key = RAC_TRANSIENT_PREFIX . "projects_{$year}_{$month}";
        $cached = get_transient($cache_key);

        if ($cached !== false && is_array($cached)) {
            RAC_Logger::instance()->log_cache('hit', $cache_key, ['count' => count($cached)]);
            return $cached;
        }

        RAC_Logger::instance()->log_cache('miss', $cache_key);

        $first_day = sprintf('%04d-%02d-01', $year, $month);
        $last_day  = gmdate('Y-m-t', gmmktime(0, 0, 0, $month, 1, $year));

        $query_filter = [
            'planperiod_start' => [
                'gte' => $first_day . ' 00:00:00',
                'lte' => $last_day . ' 23:59:59',
            ],
        ];

        $all_projects = [];
        $offset = 0;
        $limit = 100;
        $max_pages = 50;

        for ($page = 0; $page < $max_pages; $page++) {
            $response = $this->request('/projects', [
                'limit'  => $limit,
                'offset' => $offset,
                'query'  => wp_json_encode($query_filter),
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            $data = isset($response['data']) ? $response['data'] : [];

            if (!is_array($data) || empty($data)) {
                break;
            }

            $all_projects = array_merge($all_projects, $data);

            if (count($data) < $limit) {
                break;
            }

            $offset += $limit;
        }

        RAC_Logger::instance()->log('Projects fetched', [
            'year'   => $year,
            'month'  => $month,
            'total'  => count($all_projects),
            'pages'  => $page + 1,
        ]);

        set_transient($cache_key, $all_projects, $this->cache_minutes * MINUTE_IN_SECONDS);

        return $all_projects;
    }

    private function request($endpoint, $params = []) {
        if (!$this->is_configured()) {
            return new WP_Error('rac_no_token', __('No API token configured.', 'rentman-availability-calendar'));
        }

        $url = $this->base_url . $endpoint;

        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }

        $args = [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Accept'        => 'application/json',
            ],
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            RAC_Logger::instance()->log_request($endpoint, $params, null, $response->get_error_message());
            return new WP_Error(
                'rac_request_failed',
                sprintf(__('Request failed: %s', 'rentman-availability-calendar'), $response->get_error_message())
            );
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code === 401 || $code === 403) {
            RAC_Logger::instance()->log_request($endpoint, $params, $code, 'Auth failed');
            return new WP_Error('rac_auth_failed', __('Authentication failed. Check your API token.', 'rentman-availability-calendar'));
        }

        if ($code >= 400) {
            $error_msg = $this->extract_error_message($body);
            RAC_Logger::instance()->log_request($endpoint, $params, $code, $error_msg);
            return new WP_Error(
                'rac_api_error',
                sprintf(__('API error (HTTP %d): %s', 'rentman-availability-calendar'), $code, $error_msg)
            );
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            RAC_Logger::instance()->log_request($endpoint, $params, $code, 'JSON parse failed');
            return new WP_Error('rac_parse_error', __('Failed to parse API response.', 'rentman-availability-calendar'));
        }

        RAC_Logger::instance()->log_request($endpoint, $params, $code);
        return $decoded;
    }

    private function extract_error_message($body) {
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            if (isset($decoded['message'])) {
                return sanitize_text_field($decoded['message']);
            }
            if (isset($decoded['error'])) {
                return sanitize_text_field($decoded['error']);
            }
        }
        return __('Unknown error', 'rentman-availability-calendar');
    }
}
