<?php
/**
 * Settings page.
 *
 * @package BrewLogForBrewfather
 */

namespace BrewLogForBrewfather;

defined( 'ABSPATH' ) || exit;

/**
 * The plugin's page under Settings, for Admins.
 */
final class Settings {

	/**
	 * Page slug, also the settings group name.
	 */
	const PAGE = 'brew-log-for-brewfather';

	/**
	 * Action name (for admin-post.php) of the connection test.
	 */
	const TEST_ACTION = 'blfb_test_connection';

	/**
	 * Query argument that carries the connection test result back to the page.
	 */
	const RESULT_ARG = 'blfb_connection';

	/**
	 * Action name (for admin-post.php) of the clear cache button.
	 */
	const CLEAR_ACTION = 'blfb_clear_cache';

	/**
	 * Query argument set after the cache was cleared.
	 */
	const CLEARED_ARG = 'blfb_cache_cleared';

	/**
	 * Registers the hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_' . self::TEST_ACTION, array( __CLASS__, 'handle_test_connection' ) );
		add_action( 'admin_post_' . self::CLEAR_ACTION, array( __CLASS__, 'handle_clear_cache' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BLFB_PLUGIN_FILE ), array( __CLASS__, 'add_settings_link' ) );

		// Other credentials may mean another Brewfather account.
		add_action( 'update_option_' . Options::USER_ID, array( __CLASS__, 'clear_cache' ) );
		add_action( 'update_option_' . Options::API_KEY, array( __CLASS__, 'clear_cache' ) );
	}

	/**
	 * Adds the page under Settings.
	 */
	public static function add_page() {
		add_options_page(
			__( 'Brew Log for Brewfather', 'brew-log-for-brewfather' ),
			__( 'Brew Log for Brewfather', 'brew-log-for-brewfather' ),
			Access::ADMIN_CAP,
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Registers the options, section and fields.
	 */
	public static function register() {
		register_setting(
			self::PAGE,
			Options::USER_ID,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_user_id' ),
				'default'           => '',
			)
		);
		register_setting(
			self::PAGE,
			Options::API_KEY,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_api_key' ),
				'default'           => '',
			)
		);

		register_setting(
			self::PAGE,
			Options::CACHE_TIMEOUT,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_cache_timeout' ),
				'default'           => Options::DEFAULT_CACHE_MINUTES,
			)
		);

		add_settings_section(
			'blfb_account',
			__( 'Brewfather account', 'brew-log-for-brewfather' ),
			array( __CLASS__, 'render_account_intro' ),
			self::PAGE
		);
		add_settings_field(
			Options::USER_ID,
			__( 'User ID', 'brew-log-for-brewfather' ),
			array( __CLASS__, 'render_user_id_field' ),
			self::PAGE,
			'blfb_account',
			array( 'label_for' => Options::USER_ID )
		);
		add_settings_field(
			Options::API_KEY,
			__( 'API key', 'brew-log-for-brewfather' ),
			array( __CLASS__, 'render_api_key_field' ),
			self::PAGE,
			'blfb_account',
			array( 'label_for' => Options::API_KEY )
		);

		self::register_cache_section();
		self::register_ingredients_section();
	}

	/**
	 * Adds the section with the Ingredient visibility switches.
	 */
	private static function register_ingredients_section() {
		foreach ( array( Options::HIDE_INGREDIENTS_VISITORS, Options::HIDE_INGREDIENTS_USERS ) as $option ) {
			register_setting(
				self::PAGE,
				$option,
				array(
					'type'              => 'boolean',
					'sanitize_callback' => array( __CLASS__, 'sanitize_checkbox' ),
					'default'           => false,
				)
			);
		}

		add_settings_section(
			'blfb_ingredients',
			__( 'Ingredients', 'brew-log-for-brewfather' ),
			array( __CLASS__, 'render_ingredients_intro' ),
			self::PAGE
		);
		add_settings_field(
			'blfb_hide_ingredients',
			__( 'Hide Ingredients', 'brew-log-for-brewfather' ),
			array( __CLASS__, 'render_hide_ingredients_fields' ),
			self::PAGE,
			'blfb_ingredients'
		);
	}

	/**
	 * Stores a checkbox as a boolean.
	 *
	 * @param mixed $value Submitted value; absent when unchecked.
	 * @return bool
	 */
	public static function sanitize_checkbox( $value ) {
		return ! empty( $value );
	}

	/**
	 * Adds the cache section. Called from register() so the sections appear in order.
	 */
	private static function register_cache_section() {
		add_settings_section(
			'blfb_cache',
			__( 'Cache', 'brew-log-for-brewfather' ),
			array( __CLASS__, 'render_cache_intro' ),
			self::PAGE
		);
		add_settings_field(
			Options::CACHE_TIMEOUT,
			__( 'Cache timeout', 'brew-log-for-brewfather' ),
			array( __CLASS__, 'render_cache_timeout_field' ),
			self::PAGE,
			'blfb_cache',
			array( 'label_for' => Options::CACHE_TIMEOUT )
		);
	}

	/**
	 * Cache timeout in whole minutes, at least 1.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public static function sanitize_cache_timeout( $value ) {
		if ( '' === trim( (string) $value ) ) {
			return Options::DEFAULT_CACHE_MINUTES;
		}

		return max( 1, absint( $value ) );
	}

	/**
	 * Trims the user ID.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_user_id( $value ) {
		return trim( sanitize_text_field( (string) $value ) );
	}

	/**
	 * Keeps the saved key when the field is left empty; the page never sends
	 * the saved key back to the browser.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_api_key( $value ) {
		$value = trim( sanitize_text_field( (string) $value ) );

		return '' === $value ? (string) get_option( Options::API_KEY, '' ) : $value;
	}

	/**
	 * Clears the API cache.
	 */
	public static function clear_cache() {
		( new Cache() )->clear();
	}

	/**
	 * Adds a "Settings" link to the plugin's row on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function add_settings_link( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'brew-log-for-brewfather' ) . '</a>' );

		return $links;
	}

	/**
	 * Runs the connection test and redirects back to the page with the result.
	 */
	public static function handle_test_connection() {
		if ( ! current_user_can( Access::ADMIN_CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'brew-log-for-brewfather' ), 403 );
		}
		check_admin_referer( self::TEST_ACTION );

		$result = Api_Client::from_settings()->test_connection();

		wp_safe_redirect(
			add_query_arg(
				self::RESULT_ARG,
				is_wp_error( $result ) ? $result->get_error_code() : 'ok',
				admin_url( 'options-general.php?page=' . self::PAGE )
			)
		);
		exit;
	}

	/**
	 * Clears the cache and redirects back to the page.
	 */
	public static function handle_clear_cache() {
		if ( ! current_user_can( Access::ADMIN_CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'brew-log-for-brewfather' ), 403 );
		}
		check_admin_referer( self::CLEAR_ACTION );

		self::clear_cache();

		wp_safe_redirect( add_query_arg( self::CLEARED_ARG, '1', admin_url( 'options-general.php?page=' . self::PAGE ) ) );
		exit;
	}

	/**
	 * Renders a button that posts one admin-post.php action.
	 *
	 * @param string $action Action name.
	 * @param string $label  Button label.
	 */
	private static function render_action_button( $action, $label ) {
		?>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" style="display:inline-block;margin-right:8px">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<?php
			wp_nonce_field( $action );
			submit_button( $label, 'secondary', 'submit', false );
			?>
		</form>
		<?php
	}

	/**
	 * Renders the page.
	 */
	public static function render_page() {
		if ( ! current_user_can( Access::ADMIN_CAP ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php self::render_test_result(); ?>

			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>

			<p>
				<?php
				self::render_action_button( self::TEST_ACTION, __( 'Test connection', 'brew-log-for-brewfather' ) );
				self::render_action_button( self::CLEAR_ACTION, __( 'Clear cache now', 'brew-log-for-brewfather' ) );
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Shows the result of the last connection test, if the page was reached
	 * from one.
	 */
	private static function render_test_result() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only; the code is checked against a fixed list.
		if ( isset( $_GET[ self::CLEARED_ARG ] ) ) {
			wp_admin_notice( __( 'The cache has been cleared.', 'brew-log-for-brewfather' ), array( 'type' => 'success' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only; the code is checked against a fixed list.
		$code = isset( $_GET[ self::RESULT_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::RESULT_ARG ] ) ) : '';
		if ( '' === $code ) {
			return;
		}

		if ( 'ok' === $code ) {
			wp_admin_notice( __( 'Connected to Brewfather.', 'brew-log-for-brewfather' ), array( 'type' => 'success' ) );
			return;
		}

		$message = Api_Client::error_message( $code );
		if ( '' !== $message ) {
			wp_admin_notice(
				/* translators: %s: reason, e.g. "Brewfather rejected the API credentials." */
				sprintf( __( 'Connection failed: %s', 'brew-log-for-brewfather' ), $message ),
				array( 'type' => 'error' )
			);
		}
	}

	/**
	 * Explains where to find the credentials.
	 */
	public static function render_account_intro() {
		echo '<p>' . esc_html__( 'Find your user ID and create an API key in Brewfather under Settings → API keys. The key needs the recipes.read, batches.read and inventory.read scopes.', 'brew-log-for-brewfather' ) . '</p>';
	}

	/**
	 * Explains who sees Ingredients.
	 */
	public static function render_ingredients_intro() {
		echo '<p>' . esc_html__( 'Brewers and administrators always see the Ingredients of a Recipe.', 'brew-log-for-brewfather' ) . '</p>';
	}

	/**
	 * Renders the two Ingredient visibility checkboxes.
	 */
	public static function render_hide_ingredients_fields() {
		$fields = array(
			Options::HIDE_INGREDIENTS_VISITORS => __( 'Hide Ingredients from visitors (not logged in)', 'brew-log-for-brewfather' ),
			Options::HIDE_INGREDIENTS_USERS    => __( 'Hide Ingredients from logged-in users who are not Brewers (also hides them from visitors)', 'brew-log-for-brewfather' ),
		);

		echo '<fieldset>';
		foreach ( $fields as $option => $label ) {
			printf(
				'<label><input type="checkbox" name="%1$s" value="1"%2$s> %3$s</label><br>',
				esc_attr( $option ),
				checked( (bool) get_option( $option, false ), true, false ),
				esc_html( $label )
			);
		}
		echo '</fieldset>';
	}

	/**
	 * Explains the cache.
	 */
	public static function render_cache_intro() {
		echo '<p>' . esc_html__( 'Brewfather data is stored for a while so the site stays fast and Brewfather is not asked too often. Changes in Brewfather show up on the site after the cache timeout.', 'brew-log-for-brewfather' ) . '</p>';
	}

	/**
	 * Renders the cache timeout field.
	 */
	public static function render_cache_timeout_field() {
		printf(
			'<input type="number" id="%1$s" name="%1$s" value="%2$d" min="1" step="1" class="small-text"> %3$s',
			esc_attr( Options::CACHE_TIMEOUT ),
			absint( get_option( Options::CACHE_TIMEOUT, Options::DEFAULT_CACHE_MINUTES ) ),
			esc_html__( 'minutes', 'brew-log-for-brewfather' )
		);
	}

	/**
	 * Renders the user ID field.
	 */
	public static function render_user_id_field() {
		printf(
			'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text" autocomplete="off">',
			esc_attr( Options::USER_ID ),
			esc_attr( get_option( Options::USER_ID, '' ) )
		);
	}

	/**
	 * Renders the API key field. The saved key is never printed.
	 */
	public static function render_api_key_field() {
		$saved = '' !== (string) get_option( Options::API_KEY, '' );

		printf(
			'<input type="password" id="%1$s" name="%1$s" value="" class="regular-text" autocomplete="new-password">',
			esc_attr( Options::API_KEY )
		);
		if ( $saved ) {
			echo '<p class="description">' . esc_html__( 'A key is saved. Leave empty to keep it.', 'brew-log-for-brewfather' ) . '</p>';
		}
	}
}
