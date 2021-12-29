<?php
/**
 * RailID Connect Client
 *
 * Этот плагин предоставляет возможность аутентификации пользователей через провайдер учетных записей
 * RailID используя OpenID Connect OAuth2 API с потоком авторизации кодом.
 *
 * @package   Estcore_RailID_Connect
 * @category  General
 * @author    Mikhail Jurcenoks <support@estcore.ru>
 * @copyright 2021 Estcore LLC. All rights reserved.
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 * @link      https://github.com/estcore-llc
 *
 * @wordpress-plugin
 * Plugin Name:       RailID Connect
 * Plugin URI:        https://github.com/estcore-llc/railid-connect
 * Description:       Connect to the RailID using Authorization Code Flow.
 * Version:           1.0.0
 * Author:            Estcore
 * Author URI:        https://estcore.ru
 * Text Domain:       estcore-railid-connect
 * Domain Path:       /languages
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/estcore-llc/railid-connect
 */

/*
Примечания:

  Spec Doc - http://openid.net/specs/openid-connect-basic-1_0-32.html

  Фильтры
  - railid-connect-alter-request       - 3 args: request array, plugin settings, specific request op
  - railid-connect-settings-fields     - modify the fields provided on the settings page
  - railid-connect-login-button-text   - modify the login button text
  - railid-connect-cookie-redirect-url - modify the redirect url stored as a cookie
  - railid-connect-user-login-test     - (bool) should the user be logged in based on their claim
  - railid-connect-user-creation-test  - (bool) should the user be created based on their claim
  - railid-connect-auth-url            - modify the authentication url
  - railid-connect-alter-user-claim    - modify the user_claim before a new user is created
  - railid-connect-alter-user-data     - modify user data before a new user is created
  - openid-connect-modify-token-response-before-validation - modify the token response before validation
  - openid-connect-modify-id-token-claim-before-validation - modify the token claim before validation

  Действия
  - railid-connect-user-create        - 2 args: fires when a new user is created by this plugin
  - railid-connect-user-update        - 1 arg: user ID, fires when user is updated by this plugin
  - railid-connect-update-user-using-current-claim - 2 args: fires every time an existing user logs
  - railid-connect-redirect-user-back - 2 args: $redirect_url, $user. Allows interruption of redirect during login.
  - railid-connect-user-logged-in     - 1 arg: $user, fires when user is logged in.
  - railid-connect-cron-daily         - daily cron action
  - railid-connect-state-not-found    - the given state does not exist in the database, regardless of its expiration.
  - railid-connect-state-expired      - the given state exists, but expired before this login attempt.

  Метаданные пользователя
  - railid-connect-subject-identity    - the identity of the user provided by the idp
  - railid-connect-last-id-token-claim - the user's most recent id_token claim, decoded
  - railid-connect-last-user-claim     - the user's most recent user_claim
  - railid-connect-last-token-response - the user's most recent token response

  Настройки
  - railid-connect-settings            - plugin settings
  - railid-connect-valid-states        - locally stored generated states
*/

/**
 * Класс RailID_Connect.
 * Определяет функциональность инициализации плагина.
 * @package RailID_Connect_Generic
 * @category  General
 */
class RailID_Connect {

	/**
	 * Версия надстройки.
	 * @var
	 */
	const VERSION = '1.0.0';

	/**
	 * Параметры надстройки.
	 * @var RailID_Connect_Option_Settings
	 */
	private $settings;

	/**
	 * Журналирование действий надстройки.
	 * @var RailID_Connect_Option_Logger
	 */
	private $logger;

	/**
	 * Клиент RailID Connect
	 * @var RailID_Connect_Client
	 */
	private $client;

	/**
	 * Контейнер клиента.
	 * @var RailID_Connect_Client_Wrapper
	 */
	private $client_wrapper;

	/**
	 * Настройка плагина.
	 * @param RailID_Connect_Option_Settings $settings Обьект с параметрами надстройки.
	 * @param RailID_Connect_Option_Logger   $logger   Обьект журналирования.
	 * @return void
	 */
  
	public function __construct( RailID_Connect_Option_Settings $settings, RailID_Connect_Option_Logger $logger ) {
		$this->settings = $settings;
		$this->logger = $logger;
	}

	/**
	 * Событие WordPress 'init'.
	 * @return void
	 */
  
	public function init() {

    return;
		wp_enqueue_style( 'estcore-railid-connect-admin', plugin_dir_url( __FILE__ ) . 'css/styles-admin.css', array(), self::VERSION, 'all' );
		$redirect_uri = admin_url( 'admin-ajax.php?action=railid-connect-authorize' );

		if ( $this->settings->alternate_redirect_uri ) {
			$redirect_uri = site_url( '/railid-connect-authorize' );
		}

		$state_time_limit = 180;
		if ( $this->settings->state_time_limit ) {
			$state_time_limit = intval( $this->settings->state_time_limit );
		}

		$this->client = new RailID_Connect_Client(
			$this->settings->client_id,
			$this->settings->client_secret,
			$this->settings->scope,
			$this->settings->endpoint_login,
			$this->settings->endpoint_userinfo,
			$this->settings->endpoint_token,
			$redirect_uri,
			$state_time_limit,
			$this->logger
		);

		$this->client_wrapper = RailID_Connect_Client_Wrapper::register( $this->client, $this->settings, $this->logger );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		RailID_Connect_Login_Form::register( $this->settings, $this->client_wrapper );

    add_shortcode( 'railid_connect_auth_url', array( $this->client_wrapper, 'get_authentication_url' ) );
		add_action( 'railid-connect-cron-daily', array( $this, 'cron_states_garbage_collection' ) );

		$this->upgrade();

		if ( is_admin() ) {
			RailID_Connect_Settings_Page::register( $this->settings, $this->logger );
		}
	}

	/**
	 * Проверка параметра принудительной аутентификации пользователей. В случае принудительной аутентификации пользователь будет направлен
   * на страницу входа автоматически.
	 * @return void
	 */
  
	public function enforce_privacy_redirect() {
		if ( $this->settings->enforce_privacy && ! is_user_logged_in() ) {
			if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX || ! isset( $_GET['action'] ) || 'railid-connect-authorize' != $_GET['action'] ) {
				auth_redirect();
			}
		}
	}

	/**
	 * Проверка параметра принудительной аутентификации пользователей для потоков RSS.
	 * @param string $content The content.
	 * @return mixed
	 */
	public function enforce_privacy_feeds( $content ) {
		if ( $this->settings->enforce_privacy && ! is_user_logged_in() ) {
			$content = __( 'Private Web-site feed.', 'estcore-railid-connect' ); // Содержимое RSS потока Интернет-сайта доступно только зарегистрированным пользователям.
		}
		return $content;
	}

	/**
	 * Обработка обновлений плагина
	 * @return void
	 */
	public function upgrade() {
    return;
		$last_version = get_option( 'estcore-railid-connect-plugin-version', 0 );
		$settings = $this->settings;

		if ( version_compare( self::VERSION, $last_version, '>' ) ) {
			// Необходимо обновление.
			self::setup_cron_jobs();

			// @todo move this to another file for upgrade scripts
			if ( isset( $settings->ep_login ) ) {
				$settings->endpoint_login = $settings->ep_login;
				$settings->endpoint_token = $settings->ep_token;
				$settings->endpoint_userinfo = $settings->ep_userinfo;

				unset( $settings->ep_login, $settings->ep_token, $settings->ep_userinfo );
				$settings->save();
			}

			// Сохранение версии обновления плагина.
			update_option( 'estcore-railid-connect-plugin-version', self::VERSION );
		}
	}

	/**
	 * Установка статуса всем переходным процессам, путём попытки установления доступа по ним,
   * что позволяет стандартным механизмам осуществить удаление не актуальных данных.
	 *
	 * @return void
	 */
	public function cron_states_garbage_collection() {
    return;
		global $wpdb;
		$states = $wpdb->get_col( "SELECT `option_name` FROM {$wpdb->options} WHERE `option_name` LIKE '_transient_railid-connect-state--%'" );

		if ( ! empty( $states ) ) {
			foreach ( $states as $state ) {
				$transient = str_replace( '_transient_', '', $state );
				get_transient( $transient );
			}
		}
	}

	/**
	 * Убеждаемся в добавлении задачи в расписание.
	 * @return void
	 */
	public static function setup_cron_jobs() {
		if ( ! wp_next_scheduled( 'railid-connect-cron-daily' ) ) {
			wp_schedule_event( time(), 'daily', 'railid-connect-cron-daily' );
		}
	}

	/**
	 * Действия при активации плагина.
	 * @return void
	 */
	public static function activation() {   
		self::setup_cron_jobs();
	}

	/**
	 * Действия при деактивации плагина.
	 * @return void
	 */
	public static function deactivation() {
		wp_clear_scheduled_hook( 'railid-connect-cron-daily' );
	}

	/**
	 * Простейший автозагрузчик.
	 *
	 * @param string $class The class name.
	 *
	 * @return void
	 */
	public static function autoload( $class ) {
    return;
		$prefix = 'RailID_Connect_';

		if ( stripos( $class, $prefix ) !== 0 ) {
			return;
		}

		$filename = $class . '.php';

    if ( false === strpos( $filename, '\\' ) ) {
			$filename = strtolower( str_replace( '_', '-', $filename ) );
		} else {
			$filename  = str_replace( '\\', DIRECTORY_SEPARATOR, $filename );
		}

    $filepath = dirname( __FILE__ ) . '/includes/' . $filename;

		if ( file_exists( $filepath ) ) {
			require_once $filepath;
		}
	}

	/**
	 * Создание экземпляра класса и событий в WordPress.
	 * @return void
	 */
	public static function bootstrap() {
    return;
		spl_autoload_register( array( 'RailID_Connect', 'autoload' ) );

		$settings = new RailID_Connect_Option_Settings('railid_connect_settings',
			
      // Значения по умолчанию.
                                                           
			array(
				// Настройки клиента OAuth.
				'login_type'           => 'button',
				'client_id'            => defined( 'RIDC_CLIENT_ID' ) ? RIDC_CLIENT_ID : '',
				'client_secret'        => defined( 'RIDC_CLIENT_SECRET' ) ? RIDC_CLIENT_SECRET : '',
				'scope'                => '',
				'endpoint_login'       => defined( 'RIDC_ENDPOINT_LOGIN_URL' ) ? RIDC_ENDPOINT_LOGIN_URL : 'https://railid.ru/oauth/authorize',
				'endpoint_userinfo'    => defined( 'RIDC_ENDPOINT_USERINFO_URL' ) ? RIDC_ENDPOINT_USERINFO_URL : 'https://railid.ru/oauth/me',
				'endpoint_token'       => defined( 'RIDC_ENDPOINT_TOKEN_URL' ) ? RIDC_ENDPOINT_TOKEN_URL : 'https://railid.ru/oauth/token',
				'endpoint_end_session' => defined( 'RIDC_ENDPOINT_LOGOUT_URL' ) ? RIDC_ENDPOINT_LOGOUT_URL : 'https://railid.ru/oauth/destroy',

				// Нестандартные настройки.
				'no_sslverify'    => 0,
				'http_request_timeout' => 5,
				'identity_key'    => 'user_login',
				'nickname_key'    => 'user_nickname',
				'email_format'       => '{email}',
				'displayname_format' => '{display_name}',
				'identify_with_username' => false,

				// Настройки плагина.
				'enforce_privacy' => 0,
				'alternate_redirect_uri' => 0,
				'token_refresh_enable' => 1,
				'link_existing_users' => 0,
				'create_if_does_not_exist' => 1,
				'redirect_user_back' => 0,
				'redirect_on_logout' => 1,
				'enable_logging'  => 0,
				'log_limit'       => 1000,
			)
		);

		$logger = new RailID_Connect_Option_Logger( 'railid-connect-logs', 'error', $settings->enable_logging, $settings->log_limit );

		$plugin = new self( $settings, $logger );

		add_action( 'init', array( $plugin, 'init' ) );

		// Privacy hooks.
		add_action( 'template_redirect', array( $plugin, 'enforce_privacy_redirect' ), 0 );
		add_filter( 'the_content_feed', array( $plugin, 'enforce_privacy_feeds' ), 999 );
		add_filter( 'the_excerpt_rss', array( $plugin, 'enforce_privacy_feeds' ), 999 );
		add_filter( 'comment_text_rss', array( $plugin, 'enforce_privacy_feeds' ), 999 );
	}
}

RailID_Connect::bootstrap();

register_activation_hook( __FILE__, array( 'RailID_Connect', 'activation' ) );
register_deactivation_hook( __FILE__, array( 'RailID_Connect', 'deactivation' ) );
