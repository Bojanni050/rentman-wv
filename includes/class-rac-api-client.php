<?php
if (!defined('ABSPATH')) {
    exit;
}

class RAC_API_Client {

    private const BASE_URL = 'https://api.rentman.net';
    private const DEFAULT_CACHE_MINUTES = 15;
    private const MAX_PAGES = 50;
    private const ITEMS_PER_PAGE = 100;
    private const REQUEST_DELAY = 1; // Seconds between requests to prevent rate limiting

    private $token = '';
    private $cache_minutes = self::DEFAULT_CACHE_MINUTES;
    private $last_request_time = 0;

    public function __construct() {
        $settings = get_option(RAC_OPTION_KEY, []);
        $this->token = isset($settings['api_token']) ? wp_unslash(trim($settings['api_token'])) : '';
        $this->cache_minutes = isset($settings['cache_minutes']) ? max(1, (int) $settings['cache_minutes']) : self::DEFAULT_CACHE_MINUTES;
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
        $prefix = RAC_TRANSIENT_PREFIX;
        $options = $wpdb->get_results(
            $wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $prefix . '%')
        );
        
        $deleted = 0;
        foreach ($options as $option) {
            if (delete_option($option->option_name)) {
                $deleted++;
            }
        }
        
        RAC_Logger::instance()->log_cache('clear_all', 'all', ['deleted' => $deleted]);
    }

    public function clear_month_cache($year, $month) {
        $year = absint($year);
        $month = absint($month);
        $key = sanitize_key(RAC_TRANSIENT_PREFIX . "appointments_{$year}_{$month}");
        delete_transient($key);
        RAC_Logger::instance()->log_cache('clear_month', $key);
    }

    /**
     * Tests the API connection
     * 
     * @return true|WP_Error True on success, WP_Error on failure
     */
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

    /**
     * Gets appointments for a specific month (alias for get_projects_for_month)
     * 
     * @param int $year The year
     * @param int $month The month (1-12)
     * @return array|WP_Error Array of projects or WP_Error on failure
     */
    public function get_appointments_for_month($year, $month) {
        return $this->get_projects_for_month($year, $month);
    }

    /**
     * Fetches projects from Rentman API for a specific month
     * Uses /projectequipment endpoint with cursor-based pagination
     * 
     * @param int $year The year to fetch projects for
     * @param int $month The month to fetch projects for (1-12)
     * @return array|WP_Error Array of projects or WP_Error on failure
     */
    public function get_projects_for_month($year, $month) {
        $year = absint($year);
        $month = absint($month);
        
        if ($month < 1 || $month > 12) {
            return new WP_Error('rac_invalid_month', __('Invalid month. Must be between 1 and 12.', 'rentman-availability-calendar'));
        }

        $cache_key = sanitize_key(RAC_TRANSIENT_PREFIX . "projects_{$year}_{$month}");
        $cached = get_transient($cache_key);

        if ($cached !== false && is_array($cached)) {
            RAC_Logger::instance()->log_cache('hit', $cache_key, ['count' => count($cached)]);
            return $cached;
        }

        RAC_Logger::instance()->log_cache('miss', $cache_key);

        $first_day = sprintf('%04d-%02d-01', $year, $month);
        $last_day  = gmdate('Y-m-t', gmmktime(0, 0, 0, $month, 1, $year));

        // Build initial URL with filters
        $url = self::BASE_URL . '/projectequipment?' . http_build_query([
            'limit'                  => self::ITEMS_PER_PAGE,
            'expand'                => 'equipment_group',
            'planperiod_start[gte]' => $first_day . 'T00:00:00Z',
            'planperiod_start[lte]' => $last_day . 'T23:59:59Z',
        ], '', '&', PHP_QUERY_RFC3986);

        // Initialize day counters
        $day_counts = [];
        $max_pages = self::MAX_PAGES;
        $pages_fetched = 0;

        do {
            if ($pages_fetched >= $max_pages) {
                break;
            }

            $pages_fetched++;

            // Use direct wp_remote_get for cursor-based pagination
            $args = [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Accept'        => 'application/json',
                ],
            ];

            // Rate limiting
            $now = time();
            if ($now - $this->last_request_time < self::REQUEST_DELAY) {
                $sleep_time = self::REQUEST_DELAY - ($now - $this->last_request_time);
                if (function_exists('usleep')) {
                    usleep($sleep_time * 1000000);
                } else {
                    sleep($sleep_time);
                }
            }
            $this->last_request_time = time();

            $response = wp_remote_get($url, $args);

            if (is_wp_error($response)) {
                RAC_Logger::instance()->log_request('/projectequipment', [], null, $response->get_error_message());
                return new WP_Error(
                    'rac_request_failed',
                    sprintf(__('Request failed: %s', 'rentman-availability-calendar'), $response->get_error_message())
                );
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($code === 401 || $code === 403) {
                RAC_Logger::instance()->log_request('/projectequipment', [], $code, 'Auth failed');
                return new WP_Error('rac_auth_failed', __('Authentication failed. Check your API token.', 'rentman-availability-calendar'));
            }

            if ($code >= 400) {
                $error_msg = $this->extract_error_message($body);
                RAC_Logger::instance()->log_request('/projectequipment', [], $code, $error_msg);
                return new WP_Error(
                    'rac_api_error',
                    sprintf(__('API error (HTTP %d): %s', 'rentman-availability-calendar'), $code, $error_msg)
                );
            }

            $decoded = json_decode($body, true);

            if (!is_array($decoded)) {
                RAC_Logger::instance()->log_request('/projectequipment', [], $code, 'JSON parse failed');
                return new WP_Error('rac_parse_error', __('Failed to parse API response.', 'rentman-availability-calendar'));
            }

            // Process equipment items
            $data = isset($decoded['data']) ? $decoded['data'] : [];

            foreach ($data as $item) {
                if (!isset($item['equipment_group']) || !isset($item['equipment_group']['project'])) {
                    continue;
                }

                $project_url = $item['equipment_group']['project'];
                $project_id = $this->extract_id_from_url($project_url);

                if (!$project_id) {
                    continue;
                }

                // Get planperiod from equipment_group
                $start_date = isset($item['equipment_group']['planperiod_start']) ? $item['equipment_group']['planperiod_start'] : null;
                $end_date = isset($item['equipment_group']['planperiod_end']) ? $item['equipment_group']['planperiod_end'] : null;

                if (!$start_date || !$end_date) {
                    continue;
                }

                // Convert to timestamps for easier comparison
                $start_ts = strtotime($start_date);
                $end_ts = strtotime($end_date);

                if ($start_ts === false || $end_ts === false) {
                    continue;
                }

                // Determine which days in the month this period overlaps with
                $month_start_ts = gmmktime(0, 0, 0, $month, 1, $year);
                $month_end_ts = gmmktime(23, 59, 59, $month, gmdate('t', $month_start_ts), $year);

                // Clamp the period to the month boundaries
                $period_start_ts = max($start_ts, $month_start_ts);
                $period_end_ts = min($end_ts, $month_end_ts);

                // Iterate through each day in the overlapping period
                $current_day_ts = $period_start_ts;
                while ($current_day_ts <= $period_end_ts) {
                    $day_key = gmdate('Y-m-d', $current_day_ts);
                    
                    if (!isset($day_counts[$day_key])) {
                        $day_counts[$day_key] = [];
                    }
                    
                    // Use project_id as key to ensure uniqueness
                    $day_counts[$day_key][$project_id] = true;
                    
                    // Move to next day
                    $current_day_ts = gmmktime(0, 0, 0, 
                        gmdate('m', $current_day_ts), 
                        gmdate('d', $current_day_ts) + 1, 
                        gmdate('Y', $current_day_ts)
                    );
                }
            }

            // Check for next page
            $next_page_url = isset($decoded['next_page_url']) ? $decoded['next_page_url'] : null;
            
            if (empty($next_page_url) || !is_string($next_page_url)) {
                break;
            }

            // Follow the next_page_url (which includes the cursor token)
            $url = $next_page_url;

        } while (true);

        // Convert day counts to simple arrays (we only need the count)
        $result = [];
        foreach ($day_counts as $day => $projects) {
            $result[$day] = count($projects);
        }

        RAC_Logger::instance()->log('Projects fetched via equipment', [
            'year'   => $year,
            'month'  => $month,
            'days'    => count($result),
            'pages'   => $pages_fetched,
        ]);

        set_transient($cache_key, $result, $this->cache_minutes * MINUTE_IN_SECONDS);

        return $result;
    }

    /**
     * Extracts ID from a Rentman API URL (e.g., /projects/2542 -> 2542)
     * 
     * @param string $url The API URL
     * @return int|null The extracted ID or null if not found
     */
    private function extract_id_from_url($url) {
        if (!is_string($url) || empty($url)) {
            return null;
        }

        // Remove leading slash if present
        $url = ltrim($url, '/');

        // Split by / and get the last numeric part
        $parts = explode('/', $url);
        
        foreach ($parts as $part) {
            if (is_numeric($part)) {
                return (int) $part;
            }
        }

        return null;
    }

    /**
     * Makes a request to the Rentman API with rate limiting
     * 
     * @param string $endpoint The API endpoint
     * @param array $params Query parameters
     * @return array|WP_Error API response or WP_Error on failure
     */
    private function request($endpoint, $params = []) {
        if (!$this->is_configured()) {
            return new WP_Error('rac_no_token', __('No API token configured.', 'rentman-availability-calendar'));
        }

        // Rate limiting
        $now = time();
        if ($now - $this->last_request_time < self::REQUEST_DELAY) {
            $sleep_time = self::REQUEST_DELAY - ($now - $this->last_request_time);
            if (function_exists('usleep')) {
                usleep($sleep_time * 1000000); // Convert seconds to microseconds
            } else {
                sleep($sleep_time);
            }
        }
        $this->last_request_time = time();

        $url = self::BASE_URL . $endpoint;

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

    /**
     * Extracts error message from API response body
     * 
     * @param string $body The response body
     * @return string The error message
     */
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
