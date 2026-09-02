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

    public function get_month_availability($year, $month) {
        $client = new RAC_API_Client();

        if (!$client->is_configured()) {
            return new WP_Error('rac_not_configured', __('API token not configured.', 'rentman-availability-calendar'));
        }

        $appointments = $client->get_appointments_for_month($year, $month);

        if (is_wp_error($appointments)) {
            return $appointments;
        }

        $day_counts = [];
        $day_details = [];

        foreach ($appointments as $apt) {
            $start = isset($apt['start']) ? $apt['start'] : '';
            $day = $this->extract_day_from_date($start, $year, $month);

            if ($day === null) {
                continue;
            }

            if (!isset($day_counts[$day])) {
                $day_counts[$day] = 0;
            }
            $day_counts[$day]++;

            $name = isset($apt['name']) ? $apt['name'] : (isset($apt['displayname']) ? $apt['displayname'] : '');
            $end = isset($apt['end']) ? $apt['end'] : '';
            $location = isset($apt['location']) ? $apt['location'] : '';

            $day_details[$day][] = [
                'name'     => sanitize_text_field($name),
                'start'    => sanitize_text_field($start),
                'end'      => sanitize_text_field($end),
                'location' => sanitize_text_field($location),
            ];
        }

        $days = [];
        $days_in_month = (int) gmdate('t', gmmktime(0, 0, 0, $month, 1, $year));

        for ($d = 1; $d <= $days_in_month; $d++) {
            $count = isset($day_counts[$d]) ? $day_counts[$d] : 0;
            $days[] = [
                'day'     => $d,
                'count'   => $count,
                'level'   => $this->get_level($count),
                'details' => isset($day_details[$d]) ? $day_details[$d] : [],
            ];
        }

        return [
            'year'     => $year,
            'month'    => $month,
            'monthLabel' => gmdate('F Y', gmmktime(0, 0, 0, $month, 1, $year)),
            'days'     => $days,
        ];
    }

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

    public function get_level($count) {
        if ($count === 0) {
            return 'green';
        }
        if ($count <= 2) {
            return 'orange';
        }
        return 'red';
    }
}
