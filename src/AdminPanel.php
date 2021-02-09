<?php //phpcs:disable WordPress.Files.FileName.InvalidClassFileName

namespace Dottxado\ModifyWooOrder;

/**
 * Class AdminPanel
 *
 * @package Dottxado\ModifyWooOrder
 */
class AdminPanel {

	const OPTION_NAME = 'dottxado-modify-woo-order';

	const STATUS = 'processing';

	const TIME_TO_EDIT = 900;

	/**
	 * Singleton instance
	 *
	 * @var AdminPanel $instance This instance.
	 */
	private static $instance = null;

	/**
	 * Admin2 constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'page_init' ) );
		add_action( 'init', array( $this, 'load_translations' ) );
	}

	/**
	 * Get the singleton instance
	 *
	 * @return AdminPanel
	 */
	public static function get_instance(): AdminPanel {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add plugin subpage
	 */
	public function add_plugin_page() {
		add_submenu_page(
			'woocommerce',
			'Modify Order Conditions',
			'Modify Order Conditions',
			'manage_options',
			'modify-woocommerce-order',
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Display the options page
	 */
	public function create_admin_page(): void {
		?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Modify Order Conditions', 'modify-woocommerce-order' ); ?></h1>
            <form action="options.php" method="post">
				<?php
				settings_fields( self::OPTION_NAME . '-group' );
				do_settings_sections( self::OPTION_NAME . '-admin' );
				submit_button();
				?>
            </form>
        </div>
		<?php
	}

	/**
	 * Register and add settings
	 */
	public function page_init() {
		register_setting(
			self::OPTION_NAME . '-group',
			self::OPTION_NAME,
			array( $this, 'sanitize' )
		);

		add_settings_section(
			'setting_section_id',
			'', // Title.
			array( $this, 'print_section_info' ),
			self::OPTION_NAME . '-admin'
		);

		add_settings_field(
			'subject', // ID.
			__( 'Conditions', 'modify-woocommerce-order' ),
			array( $this, 'conditions_callback' ),
			self::OPTION_NAME . '-admin',
			'setting_section_id'
		);
	}

	/**
	 * Sanitize each setting field as needed
	 *
	 * @param string $input Contains all settings fields as array keys.
	 *
	 * @return string
	 */
	public function sanitize( string $input ): string {
		return sanitize_text_field( $input );
	}

	/**
	 * Print the Section text
	 */
	public function print_section_info() {
		esc_html_e( 'Insert the condition for the modify order functionality. These conditions will be displayed under the "Modify order" link in the WooCommerce Thank You page.', 'modify-woocommerce-order' );
		echo '<br>';
		esc_html_e( 'The order can be modified only if it is in "process" status, has been placed in up to 15 minutes and has an amount bigger than 0. An order already modified cannot be modified again.', 'modify-woocommerce-order' );
	}

	/**
	 * Get the settings option array and print one of its values
	 */
	public function conditions_callback() {
		$option = get_option( self::OPTION_NAME, '' );
		wp_kses(
			printf(
				'<textarea id="modify-woocommerce-order-conditions" cols="50" rows="6" name="%s" required >%s</textarea>',
				self::OPTION_NAME, //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_attr( $option )
			),
			array(
				'textarea' => array(),
			)
		);
	}

	/**
	 * Load plugin translations
	 */
	public function load_translations() {
		load_plugin_textdomain( 'modify-woocommerce-order', false, dirname( __FILE__ ) . '/languages' );
	}

	/**
	 * Get the status in which the order can be modified by the user
	 *
	 * @return string
	 */
	public static function get_status(): string {
		return self::STATUS;
	}

	/**
	 * Get the time frame in which the order can be modified by the user
	 *
	 * @return int
	 */
	public static function get_time_to_edit(): int {
		return self::TIME_TO_EDIT;
	}

	/**
	 * Get the order conditions configured in the administration panel
	 *
	 * @return string
	 */
	public static function get_conditions(): string {
		return get_option( self::OPTION_NAME, '' );
	}
}
