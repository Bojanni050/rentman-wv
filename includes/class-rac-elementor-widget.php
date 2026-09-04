<?php
if (!defined('ABSPATH')) {
    exit;
}

class RAC_Elementor_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'rentman_availability_calendar';
    }

    public function get_title() {
        return __('Rentman Calendar', 'rentman-availability-calendar');
    }

    public function get_icon() {
        return 'eicon-calendar';
    }

    public function get_categories() {
        return ['general'];
    }

    public function get_keywords() {
        return ['rentman', 'calendar', 'availability', 'booking'];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Calendar Settings', 'rentman-availability-calendar'),
                'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'year',
            [
                'label'   => __('Year', 'rentman-availability-calendar'),
                'type'    => \Elementor\Controls_Manager::NUMBER,
                'default' => (int) gmdate('Y'),
                'min'     => 2000,
                'max'     => 2100,
            ]
        );

        $this->add_control(
            'month',
            [
                'label'   => __('Month', 'rentman-availability-calendar'),
                'type'    => \Elementor\Controls_Manager::SELECT,
                'default' => (int) gmdate('n'),
                'options' => [
                    1  => __('January', 'rentman-availability-calendar'),
                    2  => __('February', 'rentman-availability-calendar'),
                    3  => __('March', 'rentman-availability-calendar'),
                    4  => __('April', 'rentman-availability-calendar'),
                    5  => __('May', 'rentman-availability-calendar'),
                    6  => __('June', 'rentman-availability-calendar'),
                    7  => __('July', 'rentman-availability-calendar'),
                    8  => __('August', 'rentman-availability-calendar'),
                    9  => __('September', 'rentman-availability-calendar'),
                    10 => __('October', 'rentman-availability-calendar'),
                    11 => __('November', 'rentman-availability-calendar'),
                    12 => __('December', 'rentman-availability-calendar'),
                ],
            ]
        );

        $this->add_control(
            'show_legend',
            [
                'label'        => __('Show Legend', 'rentman-availability-calendar'),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => __('Yes', 'rentman-availability-calendar'),
                'label_off'    => __('No', 'rentman-availability-calendar'),
                'return_value' => 'yes',
                'default'      => 'yes',
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Calendar Style', 'rentman-availability-calendar'),
                'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'max_width',
            [
                'label'      => __('Max Width', 'rentman-availability-calendar'),
                'type'       => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range'      => [
                    'px' => ['min' => 300, 'max' => 1200],
                    '%'  => ['min' => 50, 'max' => 100],
                ],
                'default'    => [
                    'unit' => 'px',
                    'size' => 760,
                ],
                'selectors'  => [
                    '{{WRAPPER}} .rac-calendar-wrap' => 'max-width: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'header_bg',
            [
                'label'     => __('Header Background', 'rentman-availability-calendar'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#0a0a0a',
                'selectors' => [
                    '{{WRAPPER}} .rac-calendar-header' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'header_text',
            [
                'label'     => __('Header Text Color', 'rentman-availability-calendar'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .rac-month-label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'accent_color',
            [
                'label'     => __('Accent Color', 'rentman-availability-calendar'),
                'type'      => \Elementor\Controls_Manager::COLOR,
                'default'   => '#c9a227',
                'selectors' => [
                    '{{WRAPPER}} .rac-calendar-header' => 'border-bottom-color: {{VALUE}};',
                    '{{WRAPPER}} .rac-nav-btn' => 'border-color: {{VALUE}}; color: {{VALUE}};',
                    '{{WRAPPER}} td.rac-today' => 'box-shadow: 0 0 0 3px {{VALUE}};',
                    '{{WRAPPER}} td.rac-today .rac-day-number::after' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        if (!class_exists('RAC_Calendar')) {
            echo '<p>' . esc_html__('Rentman Availability Calendar plugin is not active.', 'rentman-availability-calendar') . '</p>';
            return;
        }

        $settings = $this->get_settings_for_display();

        $year  = isset($settings['year']) ? absint($settings['year']) : (int) gmdate('Y');
        $month = isset($settings['month']) ? absint($settings['month']) : (int) gmdate('n');

        if ($month < 1 || $month > 12) {
            $month = (int) gmdate('n');
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) gmdate('Y');
        }

        wp_enqueue_style('rac-calendar-style');
        wp_enqueue_script('rac-calendar-script');

        $calendar = RAC_Calendar::instance();
        echo $calendar->render_shortcode([
            'year'  => $year,
            'month' => $month,
        ]);
    }

    protected function content_template() {
        ?>
        <div class="rac-calendar-wrap" style="max-width: 760px; margin: 20px auto;">
            <div class="rac-calendar-header" style="background: #0a0a0a; border-bottom: 3px solid #c9a227; display: flex; justify-content: space-between; align-items: center; padding: 18px 24px;">
                <span style="color: #c9a227; font-size: 1.3rem;">&laquo;</span>
                <h2 style="color: #fff; text-transform: uppercase; letter-spacing: 0.12em; font-weight: 300; margin: 0;">Calendar Preview</h2>
                <span style="color: #c9a227; font-size: 1.3rem;">&raquo;</span>
            </div>
            <div style="padding: 40px; text-align: center; color: #8a8a8a; font-size: 0.9rem;">
                Rentman Availability Calendar widget — preview appears on the live page.
            </div>
        </div>
        <?php
    }
}
