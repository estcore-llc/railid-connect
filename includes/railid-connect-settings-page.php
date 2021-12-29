<?php
/**
 * Класс управления настройками на странице администрирования.
 *
 * @package   Estcore_RailID_Connect
 * @category  Settings
 * @author    Mikhail Jurcenoks <support@estcore.ru>
 * @copyright 2021 Estcore LLC. All rights reserved.
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * Класс RailID_Connect_Settings_Page.
 *
 * Страница администрирования плагина.
 *
 * @package Estcore_RailID_Connect
 * @category Settings
 */
class RailID_Connect_Settings_Page {

	private $settings;
	private $logger;
	private $settings_fields = array();
	private $options_page_name = 'railid-connect-settings';
	private $settings_field_group;

	/**
	 * Settings page class constructor.
	 *
	 * @param RailID_Connect_Option_Settings $settings Локальная копия настроек базового плагина.
	 * @param RailID_Connect_Option_Logger   $logger   Экземпляр класса журналирования.
	 */
	public function __construct( RailID_Connect_Option_Settings $settings, RailID_Connect_Option_Logger $logger ) {

		$this->settings             = $settings;
		$this->logger               = $logger;
		$this->settings_field_group = $this->settings->get_option_name() . '-group';
		$fields = $this->get_settings_fields();
		foreach ( $fields as $key => &$field ) {	$field['key']  = $key; $field['name'] = $this->settings->get_option_name() . '[' . $key . ']'; }
		$this->settings_fields = $fields;
	}

	/**
	 * Включение страницы администрирования.
	 *
	 * @param RailID_Connect_Option_Settings $settings Локальная копия настроек базового плагина.
	 * @param RailID_Connect_Option_Logger   $logger   Экземпляр класса журналирования.
	 *
	 * @return void
	 */
	public static function register( RailID_Connect_Option_Settings $settings, RailID_Connect_Option_Logger $logger ) {
		$settings_page = new self( $settings, $logger );
		add_action( 'admin_menu', array( $settings_page, 'admin_menu' ) );
		add_action( 'admin_init', array( $settings_page, 'admin_init' ) );
	}
	public function admin_menu() {
		add_options_page(__( 'RailID Connect Client', 'estcore-railid-connect' ),	__( 'RailID Connect Client', 'estcore-railid-connect' ), 'manage_options', $this->options_page_name, array( $this, 'settings_page' ));
	}
	public function admin_init() {
		register_setting($this->settings_field_group,	$this->settings->get_option_name(),	array($this, 'sanitize_settings',	));

		add_settings_section(
			'client_settings',
			__( 'Client Settings', 'estcore-railid-connect' ),
			array( $this, 'client_settings_description' ),
			$this->options_page_name
		);

		add_settings_section(
			'user_settings',
			__( 'WordPress User Settings', 'estcore-railid-connect' ),
			array( $this, 'user_settings_description' ),
			$this->options_page_name
		);

		add_settings_section(
			'authorization_settings',
			__( 'Authorization Settings', 'estcore-railid-connect' ),
			array( $this, 'authorization_settings_description' ),
			$this->options_page_name
		);

		add_settings_section(
			'log_settings',
			__( 'Log Settings', 'estcore-railid-connect' ),
			array( $this, 'log_settings_description' ),
			$this->options_page_name
		);

		// Preprocess fields and add them to the page.
		foreach ( $this->settings_fields as $key => $field ) {
			// Make sure each key exists in the settings array.
			if ( ! isset( $this->settings->{ $key } ) ) {
				$this->settings->{ $key } = null;
			}

			// Determine appropriate output callback.
			switch ( $field['type'] ) {
				case 'checkbox':
					$callback = 'do_checkbox';
					break;

				case 'select':
					$callback = 'do_select';
					break;

				case 'text':
				default:
					$callback = 'do_text_field';
					break;
			}

			// Add the field.
			add_settings_field(
				$key,
				$field['title'],
				array( $this, $callback ),
				$this->options_page_name,
				$field['section'],
				$field
			);
		}
	}

	/**
	 * Get the plugin settings fields definition.
	 *
	 * @return array
	 */
	private function get_settings_fields() {

		$fields = array(
			'login_type'        => array(
				'title'       => __( 'Login Type', 'estcore-railid-connect' ),
				'description' => __( 'Select how the client (login form) should provide login options.', 'estcore-railid-connect' ),
				'type'        => 'select',
				'options'     => array(
					'button' => __( 'OpenID Connect button on login form', 'estcore-railid-connect' ),
					'auto'   => __( 'Auto Login - SSO', 'estcore-railid-connect' ),
				),
				'section'     => 'client_settings',
			),
			'client_id'         => array(
				'title'       => __( 'Client ID', 'estcore-railid-connect' ),
				'description' => __( 'The ID this client will be recognized as when connecting the to Identity provider server.', 'estcore-railid-connect' ),
				'example'     => 'my-wordpress-client-id',
				'type'        => 'text',
				'disabled'    => defined( 'RIDC_CLIENT_ID' ),
				'section'     => 'client_settings',
			),
			'client_secret'     => array(
				'title'       => __( 'Client Secret Key', 'estcore-railid-connect' ),
				'description' => __( 'Arbitrary secret key the server expects from this client. Can be anything, but should be very unique.', 'estcore-railid-connect' ),
				'type'        => 'text',
				'disabled'    => defined( 'RIDC_CLIENT_SECRET' ),
				'section'     => 'client_settings',
			),
			'scope'             => array(
				'title'       => __( 'OpenID Scope', 'estcore-railid-connect' ),
				'description' => __( 'Space separated list of scopes this client should access.', 'estcore-railid-connect' ),
				'example'     => 'email profile openid offline_access',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'endpoint_login'    => array(
				'title'       => __( 'Login Endpoint URL', 'estcore-railid-connect' ),
				'description' => __( 'Identify provider authorization endpoint.', 'estcore-railid-connect' ),
				'example'     => 'https://example.com/oauth2/authorize',
				'type'        => 'text',
				'disabled'    => defined( 'RIDC_ENDPOINT_LOGIN_URL' ),
				'section'     => 'client_settings',
			),
			'endpoint_userinfo' => array(
				'title'       => __( 'Userinfo Endpoint URL', 'estcore-railid-connect' ),
				'description' => __( 'Identify provider User information endpoint.', 'estcore-railid-connect' ),
				'example'     => 'https://example.com/oauth2/UserInfo',
				'type'        => 'text',
				'disabled'    => defined( 'RIDC_ENDPOINT_USERINFO_URL' ),
				'section'     => 'client_settings',
			),
			'endpoint_token'    => array(
				'title'       => __( 'Token Validation Endpoint URL', 'estcore-railid-connect' ),
				'description' => __( 'Identify provider token endpoint.', 'estcore-railid-connect' ),
				'example'     => 'https://example.com/oauth2/token',
				'type'        => 'text',
				'disabled'    => defined( 'RIDC_ENDPOINT_TOKEN_URL' ),
				'section'     => 'client_settings',
			),
			'endpoint_end_session'    => array(
				'title'       => __( 'End Session Endpoint URL', 'estcore-railid-connect' ),
				'description' => __( 'Identify provider logout endpoint.', 'estcore-railid-connect' ),
				'example'     => 'https://example.com/oauth2/logout',
				'type'        => 'text',
				'disabled'    => defined( 'RIDC_ENDPOINT_LOGOUT_URL' ),
				'section'     => 'client_settings',
			),
			'identity_key'     => array(
				'title'       => __( 'Identity Key', 'estcore-railid-connect' ),
				'description' => __( 'Where in the user claim array to find the user\'s identification data. Possible standard values: preferred_username, name, or sub. If you\'re having trouble, use "sub".', 'estcore-railid-connect' ),
				'example'     => 'preferred_username',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'no_sslverify'      => array(
				'title'       => __( 'Disable SSL Verify', 'estcore-railid-connect' ),
				// translators: %1$s HTML tags for layout/styles, %2$s closing HTML tag for styles.
				'description' => sprintf( __( 'Do not require SSL verification during authorization. The OAuth extension uses curl to make the request. By default CURL will generally verify the SSL certificate to see if its valid an issued by an accepted CA. This setting disabled that verification.%1$sNot recommended for production sites.%2$s', 'estcore-railid-connect' ), '<br><strong>', '</strong>' ),
				'type'        => 'checkbox',
				'section'     => 'client_settings',
			),
			'http_request_timeout'      => array(
				'title'       => __( 'HTTP Request Timeout', 'estcore-railid-connect' ),
				'description' => __( 'Set the timeout for requests made to the IDP. Default value is 5.', 'estcore-railid-connect' ),
				'example'     => 30,
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'enforce_privacy'   => array(
				'title'       => __( 'Enforce Privacy', 'estcore-railid-connect' ),
				'description' => __( 'Require users be logged in to see the site.', 'estcore-railid-connect' ),
				'type'        => 'checkbox',
				'section'     => 'authorization_settings',
			),
			'alternate_redirect_uri'   => array(
				'title'       => __( 'Alternate Redirect URI', 'estcore-railid-connect' ),
				'description' => __( 'Provide an alternative redirect route. Useful if your server is causing issues with the default admin-ajax method. You must flush rewrite rules after changing this setting. This can be done by saving the Permalinks settings page.', 'estcore-railid-connect' ),
				'type'        => 'checkbox',
				'section'     => 'authorization_settings',
			),
			'nickname_key'     => array(
				'title'       => __( 'Nickname Key', 'estcore-railid-connect' ),
				'description' => __( 'Where in the user claim array to find the user\'s nickname. Possible standard values: preferred_username, name, or sub.', 'estcore-railid-connect' ),
				'example'     => 'preferred_username',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'email_format'     => array(
				'title'       => __( 'Email Formatting', 'estcore-railid-connect' ),
				'description' => __( 'String from which the user\'s email address is built. Specify "{email}" as long as the user claim contains an email claim.', 'estcore-railid-connect' ),
				'example'     => '{email}',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'displayname_format'     => array(
				'title'       => __( 'Display Name Formatting', 'estcore-railid-connect' ),
				'description' => __( 'String from which the user\'s display name is built.', 'estcore-railid-connect' ),
				'example'     => '{given_name} {family_name}',
				'type'        => 'text',
				'section'     => 'client_settings',
			),
			'identify_with_username'     => array(
				'title'       => __( 'Identify with User Name', 'estcore-railid-connect' ),
				'description' => __( 'If checked, the user\'s identity will be determined by the user name instead of the email address.', 'estcore-railid-connect' ),
				'type'        => 'checkbox',
				'section'     => 'client_settings',
			),
			'state_time_limit'     => array(
				'title'       => __( 'State time limit', 'estcore-railid-connect' ),
				'description' => __( 'State valid time in seconds. Defaults to 180', 'estcore-railid-connect' ),
				'type'        => 'number',
				'section'     => 'client_settings',
			),
			'token_refresh_enable'   => array(
				'title'       => __( 'Enable Refresh Token', 'estcore-railid-connect' ),
				'description' => __( 'If checked, support refresh tokens used to obtain access tokens from supported IDPs.', 'estcore-railid-connect' ),
				'type'        => 'checkbox',
				'section'     => 'client_settings',
			),
			'link_existing_users'   => array(
				'title'       => __( 'Link Existing Users', 'estcore-railid-connect' ),
				'description' => __( 'If a WordPress account already exists with the same identity as a newly-authenticated user over OpenID Connect, login as that user instead of generating an error.', 'estcore-railid-connect' ),
				'type'        => 'checkbox',
				'section'     => 'user_settings',
			),
			'create_if_does_not_exist'   => array(
				'title'       => __( 'Create user if does not exist', 'estcore-railid-connect' ),
				'description' => __( 'If the user identity is not link to an existing Wordpress user, it is created. If this setting is not enabled and if the user authenticates with an account which is not link to an existing Wordpress user then the authentication failed', 'estcore-railid-connect' ),
				'type'        => 'checkbox',
				'section'     => 'user_settings',
			),
			'redirect_user_back'   => array(
				'title'       => __( 'Redirect Back to Origin Page', 'estcore-railid-connect' ),
				'description' => __( 'After a successful OpenID Connect authentication, this will redirect the user back to the page on which they clicked the OpenID Connect login button. This will cause the login process to proceed in a traditional WordPress fashion. For example, users logging in through the default wp-login.php page would end up on the WordPress Dashboard and users logging in through the WooCommerce "My Account" page would end up on their account page.', 'estcore-railid-connect' ),
				'type'        => 'checkbox',
				'section'     => 'user_settings',
			),
			'redirect_on_logout'   => array(
				'title'       => __( 'Redirect to the login screen when session is expired', 'estcore-railid-connect' ),
				'description' => __( 'When enabled, this will automatically redirect the user back to the WordPress login page if their access token has expired.', 'estcore-railid-connect' ),
				'type'        => 'checkbox',
				'section'     => 'user_settings',
			),
			'enable_logging'    => array(
				'title'       => __( 'Enable Logging', 'estcore-railid-connect' ),
				'description' => __( 'Very simple log messages for debugging purposes.', 'estcore-railid-connect' ),
				'type'        => 'checkbox',
				'section'     => 'log_settings',
			),
			'log_limit'         => array(
				'title'       => __( 'Log Limit', 'estcore-railid-connect' ),
				'description' => __( 'Number of items to keep in the log. These logs are stored as an option in the database, so space is limited.', 'estcore-railid-connect' ),
				'type'        => 'number',
				'section'     => 'log_settings',
			),
		);

		return apply_filters( 'railid-connect-settings-fields', $fields );

	}

	public function sanitize_settings( $input ) {
		$options = array();
		foreach ( $this->settings_fields as $key => $field ) {
			if ( isset( $input[ $key ] ) ) {
				$options[ $key ] = sanitize_text_field( trim( $input[ $key ] ) );
			} else {
				$options[ $key ] = '';
			}
		}
		return $options;
	}

	/**
	 * Output the options/settings page.
	 *
	 * @return void
	 */
	public function settings_page() {
		$redirect_uri = admin_url( 'admin-ajax.php?action=railid-connect-authorize' );

		if ( $this->settings->alternate_redirect_uri ) {
			$redirect_uri = site_url( '/railid-connect-authorize' );
		}
		?>
		<div class="wrap">
			<h2><?php print esc_html( get_admin_page_title() ); ?></h2>

			<form method="post" action="options.php">
				<?php
				settings_fields( $this->settings_field_group );
				do_settings_sections( $this->options_page_name );
				submit_button();

				// Simple debug to view settings array.
				if ( isset( $_GET['debug'] ) ) {
					var_dump( $this->settings->get_values() );
				}
				?>
			</form>

			<h4><?php esc_html_e( 'Notes', 'estcore-railid-connect' ); ?></h4>

			<p class="description">
				<strong><?php esc_html_e( 'Redirect URI', 'estcore-railid-connect' ); ?></strong>
				<code><?php print esc_url( $redirect_uri ); ?></code>
			</p>
			<p class="description">
				<strong><?php esc_html_e( 'Login Button Shortcode', 'estcore-railid-connect' ); ?></strong>
				<code>[openid_connect_generic_login_button]</code>
			</p>
			<p class="description">
				<strong><?php esc_html_e( 'Authentication URL Shortcode', 'estcore-railid-connect' ); ?></strong>
				<code>[openid_connect_generic_auth_url]</code>
			</p>

			<?php if ( $this->settings->enable_logging ) { ?>
				<h2><?php esc_html_e( 'Logs', 'estcore-railid-connect' ); ?></h2>
				<div id="logger-table-wrapper">
					<?php print wp_kses_post( $this->logger->get_logs_table() ); ?>
				</div>

			<?php } ?>
		</div>
		<?php
	}
	public function do_text_field( $field ) {
		?>
		<input type="<?php print esc_attr( $field['type'] ); ?>"
				<?php echo ( ! empty( $field['disabled'] ) && boolval( $field['disabled'] ) ) ? ' disabled' : ''; ?>
			  id="<?php print esc_attr( $field['key'] ); ?>"
			  class="large-text<?php echo ( ! empty( $field['disabled'] ) && boolval( $field['disabled'] ) ) ? ' disabled' : ''; ?>"
			  name="<?php print esc_attr( $field['name'] ); ?>"
			  value="<?php print esc_attr( $this->settings->{ $field['key'] } ); ?>">
		<?php
		$this->do_field_description( $field );
	}
	public function do_checkbox( $field ) {
		?>
		<input type="hidden" name="<?php print esc_attr( $field['name'] ); ?>" value="0">
		<input type="checkbox"
			   id="<?php print esc_attr( $field['key'] ); ?>"
			   name="<?php print esc_attr( $field['name'] ); ?>"
			   value="1"
			<?php checked( $this->settings->{ $field['key'] }, 1 ); ?>>
		<?php
		$this->do_field_description( $field );
	}
	public function do_select( $field ) {
		$current_value = isset( $this->settings->{ $field['key'] } ) ? $this->settings->{ $field['key'] } : '';
		?>
		<select name="<?php print esc_attr( $field['name'] ); ?>">
			<?php foreach ( $field['options'] as $value => $text ) : ?>
				<option value="<?php print esc_attr( $value ); ?>" <?php selected( $value, $current_value ); ?>><?php print esc_html( $text ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
		$this->do_field_description( $field );
	}

	/**
	 * Выведите описание поля и пример, если он есть.
	 * @param array $field Массив определения поля настроек.
	 * @return void
	 */
	public function do_field_description( $field ) {
		?>
		<p class="description">
			<?php print esc_html( $field['description'] ); ?>
			<?php if ( isset( $field['example'] ) ) : ?>
				<br/><strong><?php esc_html_e( 'Example', 'estcore-railid-connect' ); ?>: </strong>
				<code><?php print esc_html( $field['example'] ); ?></code>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Выводит описание раздела настроек плагина "Настройки клиента".
	 * @return void
	 */
	public function client_settings_description() {	esc_html_e( 'Enter your OpenID Connect identity provider settings.', 'estcore-railid-connect' );	}

	/**
	 * Выводит описание раздела настроек плагина "Настройки пользователя".
	 * @return void
	 */
	public function user_settings_description() {
		esc_html_e( 'Modify the interaction between OpenID Connect and WordPress users.', 'estcore-railid-connect' );
	}

	/**
	 * Выводит описание раздела настроек плагина "Настройки авторизации".
	 * @return void
	 */
	public function authorization_settings_description() {
		esc_html_e( 'Control the authorization mechanics of the site.', 'estcore-railid-connect' );
	}

	/**
	 * Выводит описание раздела настроек плагина "Настройки журналирования".
	 * @return void
	 */
	public function log_settings_description() {
		esc_html_e( 'Log information about login attempts through RailID Connect.', 'estcore-railid-connect' );
	}
}
