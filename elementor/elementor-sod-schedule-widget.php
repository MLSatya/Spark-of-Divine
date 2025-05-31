<?php
namespace Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class SOD_Schedule_Widget extends Widget_Base {

	/**
	 * Get widget name.
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'sod_schedule_widget';
	}

	/**
	 * Get widget title.
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
		return __( 'SOD Schedule', 'spark-of-divine-scheduler' );
	}

	/**
	 * Get widget icon.
	 *
	 * @return string Widget icon.
	 */
	public function get_icon() {
		return 'eicon-calendar';
	}

	/**
	 * Get widget categories.
	 *
	 * @return array Widget categories.
	 */
	public function get_categories() {
		return [ 'general' ];
	}

	/**
	 * Register widget controls.
	 */
	protected function register_controls() {
		$this->start_controls_section(
			'section_content',
			[
				'label' => __( 'Content', 'spark-of-divine-scheduler' ),
			]
		);

		// Optional: Provide a control for selecting the default view (Day, Week, Month, Year)
		$this->add_control(
			'default_view',
			[
				'label'   => __( 'Default View', 'spark-of-divine-scheduler' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'week',
				'options' => [
					'day'   => __( 'Day', 'spark-of-divine-scheduler' ),
					'week'  => __( 'Week', 'spark-of-divine-scheduler' ),
					'month' => __( 'Month', 'spark-of-divine-scheduler' ),
					'year'  => __( 'Year', 'spark-of-divine-scheduler' ),
				],
			]
		);

		$this->end_controls_section();
	}

	/**
     * Updated render method for SOD_Schedule_Widget to fix conflicts
     */
    protected function render() {
        // Don't render on shop-manager or staff-schedule pages
        global $post;
        if ($post && in_array($post->post_name, ['shop-manager', 'staff-schedule'])) {
            return;
        }

        $settings = $this->get_settings_for_display();

        if (!\Elementor\Plugin::$instance->editor->is_edit_mode()) {
            if ($post && in_array($post->post_name, ['shop-manager', 'staff-schedule'])) {
                return;
            }
        }

        // Clear any existing globals and set only what this template needs
        unset($GLOBALS['sod_staff_view']);
        unset($GLOBALS['sod_shop_manager_view']);

        // Set necessary globals
        $GLOBALS['sod_schedule_view'] = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'week';
        $GLOBALS['sod_schedule_date'] = isset($_GET['date']) ? sanitize_text_field($_GET['date']) : date('Y-m-d');
        $GLOBALS['sod_customer_view'] = true;

        // Include template
        $template = get_stylesheet_directory() . '/schedule-template.php';
        if (file_exists($template)) {
            include $template;
        } else {
                echo '<p>' . __('Schedule template not found. Expected at: ' . esc_html($template_path), 'spark-of-divine-scheduler') . '</p>';
            }
        }
	/**
	 * (Optional) Provide a live preview template in the editor.
	 */
	protected function content_template() {
		?>
		<# 
			print( 'SOD Schedule will be rendered on the frontend.' );
		#>
		<?php
	}
}