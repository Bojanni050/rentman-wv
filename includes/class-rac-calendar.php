<?php
if (!defined('ABSPATH')) {
    exit;
}

class RAC_Calendar {

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('rentman_calendar', [$this, 'render_shortcode']);
    }

    /**
     * Renders the calendar shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'year'  => (int) gmdate('Y'),
            'month' => (int) gmdate('n'),
        ], $atts, 'rentman_calendar');

        $year = absint($atts['year']);
        $month = absint($atts['month']);

        if ($month < 1 || $month > 12) {
            $month = (int) gmdate('n');
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) gmdate('Y');
        }

        wp_enqueue_style('rac-calendar-style');
        wp_enqueue_script('rac-calendar-script');

        $client = new RAC_API_Client();

        if (!$client->is_configured()) {
            return '<div class="rac-calendar-wrap"><p class="rac-error">'
                . esc_html__('The Rentman API token is not configured. Please contact an administrator.', 'rentman-availability-calendar')
                . '</p></div>';
        }

        $initial_data = $this->get_month_availability($year, $month);

        $initial_data_json = $initial_data && !is_wp_error($initial_data)
            ? wp_json_encode($initial_data)
            : wp_json_encode(['error' => true]);

        ob_start();
        ?>
        <div class="rac-calendar-wrap" id="rac-calendar-root">
            <div class="rac-calendar-header">
                <button type="button" class="rac-nav-btn rac-prev-month" aria-label="<?php esc_attr_e('Previous month', 'rentman-availability-calendar'); ?>">
                    <span aria-hidden="true">&laquo;</span>
                </button>
                <h2 class="rac-month-label" data-year="<?php echo esc_attr($year); ?>" data-month="<?php echo esc_attr($month); ?>">
                    <?php echo esc_html(gmdate('F Y', gmmktime(0, 0, 0, $month, 1, $year))); ?>
                </h2>
                <button type="button" class="rac-nav-btn rac-next-month" aria-label="<?php esc_attr_e('Next month', 'rentman-availability-calendar'); ?>">
                    <span aria-hidden="true">&raquo;</span>
                </button>
            </div>

            <div class="rac-legend">
                <div class="rac-legend-item">
                    <span class="rac-dot rac-green"></span>
                    <?php esc_html_e('Available (0)', 'rentman-availability-calendar'); ?>
                </div>
                <div class="rac-legend-item">
                    <span class="rac-dot rac-orange"></span>
                    <?php esc_html_e('Busy (1-2)', 'rentman-availability-calendar'); ?>
                </div>
                <div class="rac-legend-item">
                    <span class="rac-dot rac-red"></span>
                    <?php esc_html_e('Full (3+)', 'rentman-availability-calendar'); ?>
                </div>
            </div>

            <div class="rac-calendar-loading" style="display:none;">
                <span class="rac-spinner"></span>
                <?php esc_html_e('Loading...', 'rentman-availability-calendar'); ?>
            </div>

            <div class="rac-calendar-error" style="display:none;">
                <p></p>
            </div>

            <div class="rac-calendar-table-wrap">
                <?php echo $this->render_calendar_grid($year, $month, $initial_data); ?>
            </div>

            <script type="application/json" id="rac-initial-data">
                <?php echo $initial_data_json; ?>
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders the calendar grid HTML
     * 
     * @param int $year The year
     * @param int $month The month (1-12)
     * @param array|WP_Error $data Availability data
     * @return string HTML for the calendar grid
     */
    private function render_calendar_grid($year, $month, $data) {
        $days_in_month = (int) gmdate('t', gmmktime(0, 0, 0, $month, 1, $year));
        $first_weekday = (int) gmdate('w', gmmktime(0, 0, 0, $month, 1, $year));

        $day_counts = [];
        if ($data && !is_wp_error($data) && isset($data['days'])) {
            foreach ($data['days'] as $d) {
                if (isset($d['day'], $d['count'])) {
                    $day_counts[(int) $d['day']] = (int) $d['count'];
                }
            }
        }

        $weekdays = [
            __('Sun', 'rentman-availability-calendar'),
            __('Mon', 'rentman-availability-calendar'),
            __('Tue', 'rentman-availability-calendar'),
            __('Wed', 'rentman-availability-calendar'),
            __('Thu', 'rentman-availability-calendar'),
            __('Fri', 'rentman-availability-calendar'),
            __('Sat', 'rentman-availability-calendar'),
        ];

        $today = [
            (int) gmdate('Y'),
            (int) gmdate('n'),
            (int) gmdate('j'),
        ];

        ob_start();
        ?>
        <table class="rac-calendar-table">
            <thead>
                <tr>
                    <?php foreach ($weekdays as $wd): ?>
                        <th><?php echo esc_html($wd); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php for ($i = 0; $i < $first_weekday; $i++): ?>
                        <td class="rac-empty"></td>
                    <?php endfor; ?>

                    <?php for ($day = 1; $day <= $days_in_month; $day++):
                        $count = isset($day_counts[$day]) ? $day_counts[$day] : 0;
                        $level = $this->get_level($count);
                        $is_today = ($today[0] === $year && $today[1] === $month && $today[2] === $day);
                    ?>
                        <td class="rac-day rac-<?php echo esc_attr($level); ?><?php echo $is_today ? ' rac-today' : ''; ?>"
                            data-day="<?php echo esc_attr($day); ?>"
                            data-count="<?php echo esc_attr($count); ?>">
                            <span class="rac-day-number"><?php echo esc_html($day); ?></span>
                            <span class="rac-day-count"><?php echo esc_html($count); ?></span>
                        </td>
                    <?php
                        $weekday = (int) gmdate('w', gmmktime(0, 0, 0, $month, $day, $year));
                        if ($weekday === 6 && $day < $days_in_month) {
                            echo '</tr><tr>';
                        }
                    endfor;

                    $last_weekday = (int) gmdate('w', gmmktime(0, 0, 0, $month, $days_in_month, $year));
                    for ($i = $last_weekday + 1; $i <= 6; $i++):
                    ?>
                        <td class="rac-empty"></td>
                    <?php endfor; ?>
                    ?>
                </tr>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Gets availability data for a specific month
     * 
     * @param int $year The year
     * @param int $month The month (1-12)
     * @return array|WP_Error Availability data or WP_Error on failure
     */
    public function get_month_availability($year, $month) {
        $year = absint($year);
        $month = absint($month);
        
        $logger = RAC_Logger::instance();
        $logger->log('get_month_availability', ['year' => $year, 'month' => $month]);

        $client = new RAC_API_Client();

        if (!$client->is_configured()) {
            $logger->log('API token not configured', ['year' => $year, 'month' => $month]);
            return new WP_Error('rac_not_configured', __('API token not configured.', 'rentman-availability-calendar'));
        }

        // get_projects_for_month now returns an array of day => count
        // where day is in YYYY-MM-DD format and count is the number of unique projects
        $day_counts = $client->get_projects_for_month($year, $month);

        if (is_wp_error($day_counts)) {
            $logger->log('Failed to fetch projects', ['error' => $day_counts->get_error_message()]);
            return $day_counts;
        }

        $logger->log('Day counts received from API', [
            'days_with_appointments' => count($day_counts),
            'total_appointments'     => array_sum($day_counts),
        ]);

        $days = [];
        $days_in_month = (int) gmdate('t', gmmktime(0, 0, 0, $month, 1, $year));

        for ($d = 1; $d <= $days_in_month; $d++) {
            $day_key = sprintf('%04d-%02d-%02d', $year, $month, $d);
            $count = isset($day_counts[$day_key]) ? $day_counts[$day_key] : 0;
            $days[] = [
                'day'     => $d,
                'count'   => $count,
                'level'   => $this->get_level($count),
                'details' => [], // Details are not available with the new approach
            ];
        }

        return [
            'year'     => $year,
            'month'    => $month,
            'monthLabel' => gmdate('F Y', gmmktime(0, 0, 0, $month, 1, $year)),
            'days'     => $days,
        ];
    }

    /**
     * Checks if a project is relevant based on type and status
     * 
     * @param array $project The project data
     * @return bool True if project is relevant, false otherwise
     */
    private function is_relevant_project($project) {
        if (!is_array($project)) {
            return false;
        }
        
        $type = $this->get_project_type($project);
        $status = $this->get_project_status($project);

        $relevant_types = ['Huwelijksfeest', 'Zakelijk-project'];
        $relevant_statuses = ['Bevestigd', 'Confirmed'];

        if (!in_array($type, $relevant_types, true)) {
            return false;
        }

        if (!in_array($status, $relevant_statuses, true)) {
            return false;
        }

        return true;
    }

    /**
     * Gets the project type from various possible fields
     * 
     * @param array $project The project data
     * @return string The project type or empty string
     */
    private function get_project_type($project) {
        if (!is_array($project)) {
            return '';
        }
        if (isset($project['type'])) {
            return (string) $project['type'];
        }
        if (isset($project['project_type'])) {
            return (string) $project['project_type'];
        }
        if (isset($project['category'])) {
            return (string) $project['category'];
        }
        return '';
    }

    /**
     * Gets the project status from various possible fields
     * 
     * @param array $project The project data
     * @return string The project status or empty string
     */
    private function get_project_status($project) {
        if (!is_array($project)) {
            return '';
        }
        if (isset($project['status'])) {
            return (string) $project['status'];
        }
        if (isset($project['project_status'])) {
            return (string) $project['project_status'];
        }
        return '';
    }

    /**
     * Extracts the day from a date string if it belongs to the specified month and year
     * 
     * @param string $date_string The date string to parse
     * @param int $year The year to match
     * @param int $month The month to match (1-12)
     * @return int|null The day number or null if date doesn't match
     */
    private function extract_day_from_date($date_string, $year, $month) {
        if (empty($date_string)) {
            return null;
        }

        $timestamp = strtotime($date_string);
        if ($timestamp === false) {
            return null;
        }

        $apt_year = (int) gmdate('Y', $timestamp);
        $apt_month = (int) gmdate('n', $timestamp);
        $apt_day = (int) gmdate('j', $timestamp);

        if ($apt_year !== $year || $apt_month !== $month) {
            return null;
        }

        return $apt_day;
    }

    /**
     * Determines the availability level based on appointment count
     * 
     * @param int $count Number of appointments
     * @return string The level ('green', 'orange', or 'red')
     */
    public function get_level($count) {
        $count = absint($count);
        if ($count === 0) {
            return 'green';
        }
        if ($count <= 2) {
            return 'orange';
        }
        return 'red';
    }

    /**
     * Gets availability for a specific date
     * 
     * @param string $date_string The date string to check
     * @return array Availability data with success status, date, status, count, message, and details
     */
    public function get_date_availability($date_string) {
        $normalized = $this->normalize_date($date_string);

        if (is_wp_error($normalized)) {
            return [
                'success' => false,
                'message' => $normalized->get_error_message(),
            ];
        }

        $parts = explode('-', $normalized);
        $year  = (int) $parts[0];
        $month = (int) $parts[1];
        $day   = (int) $parts[2];

        $cache_key = RAC_TRANSIENT_PREFIX . "date_{$normalized}";
        $cached = get_transient($cache_key);

        if ($cached !== false && is_array($cached)) {
            RAC_Logger::instance()->log_cache('hit', $cache_key, ['status' => isset($cached['status']) ? $cached['status'] : '']);
            return $cached;
        }

        RAC_Logger::instance()->log_cache('miss', $cache_key);

        $month_data = $this->get_month_availability($year, $month);

        if (is_wp_error($month_data)) {
            return [
                'success' => false,
                'message' => $month_data->get_error_message(),
            ];
        }

        $count = 0;
        $level = 'green';
        $details = [];

        if (isset($month_data['days'])) {
            foreach ($month_data['days'] as $d) {
                if ((int) $d['day'] === $day) {
                    $count = (int) $d['count'];
                    $level = $d['level'];
                    $details = isset($d['details']) ? $d['details'] : [];
                    break;
                }
            }
        }

        $status = 'available';
        if ($level === 'orange') {
            $status = 'limited';
        } elseif ($level === 'red') {
            $status = 'unavailable';
        }

        $result = [
            'success' => true,
            'date'    => $normalized,
            'status'  => $status,
            'count'   => $count,
            'message' => '',
            'details' => $details,
        ];

        RAC_Logger::instance()->log_availability($normalized, $status, $count, ['details_count' => count($details)]);

        $cache_ttl = max(60, RAC_DEFAULT_CACHE_MINUTES * MINUTE_IN_SECONDS);
        set_transient($cache_key, $result, $cache_ttl);

        return $result;
    }

    /**
     * Normalizes a date string to Y-m-d format
     * 
     * @param string $date_string The date string to normalize
     * @return string|WP_Error Normalized date string (Y-m-d) or WP_Error on failure
     */
    public function normalize_date($date_string) {
        if (empty($date_string)) {
            return new WP_Error('rac_empty_date', __('No date provided.', 'rentman-availability-calendar'));
        }

        $date_string = trim($date_string);

        $settings = get_option(RAC_OPTION_KEY, []);
        $configured_format = isset($settings['gf_date_format']) ? $settings['gf_date_format'] : 'd/m/Y';

        // Try configured format first
        if ($configured_format !== 'auto') {
            $dt = DateTime::createFromFormat($configured_format, $date_string);
            if ($dt !== false) {
                $dt->setTime(0, 0, 0);
                return $dt->format('Y-m-d');
            }
        }

        // Try common date formats
        $formats = [
            'd/m/Y',  // European: 22/09/2026
            'm/d/Y',  // US: 09/22/2026
            'Y-m-d',  // ISO: 2026-09-22
            'd-m-Y',  // European with dashes: 22-09-2026
            'm-d-Y',  // US with dashes: 09-22-2026
            'Y/m/d',  // ISO with slashes: 2026/09/22
        ];

        foreach ($formats as $format) {
            $dt = DateTime::createFromFormat($format, $date_string);
            if ($dt !== false) {
                $dt->setTime(0, 0, 0);
                return $dt->format('Y-m-d');
            }
        }

        // Fallback to strtotime
        $timestamp = strtotime($date_string);
        if ($timestamp !== false) {
            return gmdate('Y-m-d', $timestamp);
        }

        return new WP_Error('rac_invalid_date', sprintf(__('Invalid date format. Expected format: %s', 'rentman-availability-calendar'), $configured_format));
    }
}
