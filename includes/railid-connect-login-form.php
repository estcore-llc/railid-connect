<?php
/**
 * Форма входа и класс обработки кнопки входа.
 *
 * @package   Estcore_RailID_Connect
 * @category  Login
 * @author    Mikhail Jurcenoks <support@estcore.ru>
 * @copyright 2021 Estcore LLC. All rights reserved.
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * Класс RailID_Connect_Login_Form.
 * Форма входа и класс обработки кнопки входа.
 * @package Estcore_RailID_Connect
 * @category Login
 */
class RailID_Connect_Login_Form {

	private $settings;
	private $client_wrapper;

	/**
	 * Конструктор класса.
	 *
	 * @param RailID_Connect_Option_Settings $settings       Экземпляр объекта настроек плагина.
	 * @param RailID_Connect_Client_Wrapper  $client_wrapper Экземпляр объекта-оболочки клиента плагина.
	 */
	public function __construct( $settings, $client_wrapper ) {
		$this->settings = $settings;
		$this->client_wrapper = $client_wrapper;
	}

	/**
	 * Регистрирует экземпляр класса RailID_Connect_Login_Form.
	 *
	 * @param RailID_Connect_Option_Settings $settings       Экземпляр объекта настроек плагина.
	 * @param RailID_Connect_Client_Wrapper  $client_wrapper Экземпляр объекта-оболочки клиента плагина.
	 *
	 * @return void
	 */
	public static function register( $settings, $client_wrapper ) {
		$login_form = new self( $settings, $client_wrapper );
		add_filter( 'login_message', array( $login_form, 'handle_login_page' ), 99 );
		add_shortcode( 'railid_connect_login_button', array( $login_form, 'make_login_button' ) );
		$login_form->handle_redirect_login_type_auto();
	}

	/**
	 * Перенаправление автоматического входа.
	 * @return void
	 */
	public function handle_redirect_login_type_auto() {

		if ( 'wp-login.php' == $GLOBALS['pagenow']
			&& ( 'auto' == $this->settings->login_type || ! empty( $_GET['force_redirect'] ) )
			&& ( ! isset( $_GET['action'] ) || ! in_array( $_GET['action'], array( 'logout', 'postpass' ) ) )
			&& ! isset( $_POST['wp-submit'] ) ) {
			if ( ! isset( $_GET['login-error'] ) ) {
				wp_redirect( $this->client_wrapper->get_authentication_url() );
				exit;
			} else {
				add_action( 'login_footer', array( $this, 'remove_login_form' ), 99 );
			}
		}

	}

	/**
	 * Реализует фильтр login_message.
	 * @param string $message Текстовое сообщение для отображения на странице входа.
	 * @return string
	 */
	public function handle_login_page( $message ) {

		if ( isset( $_GET['login-error'] ) ) {
			$error_message = ! empty( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : 'Unknown error.';
			$message .= $this->make_error_output( sanitize_text_field( wp_unslash( $_GET['login-error'] ) ), $error_message );
		}
		$message .= $this->make_login_button();
		return $message;
	}

	/**
	 * Вывести пользователю сообщение об ошибке.
	 * @param string $error_code    Код ошибки.
	 * @param string $error_message Текст сообщения об ошибке.
	 * @return string
	 */
	public function make_error_output( $error_code, $error_message ) {

		ob_start();
		?>
		<div id="login_error"><?php // translators: %1$s is the error code from the IDP. ?>
			<strong><?php printf( esc_html__( 'ERROR (%1$s)', 'estcore-railid-connect' ), esc_html( $error_code ) ); ?>: </strong>
			<?php print esc_html( $error_message ); ?>
		</div>
		<?php
		return wp_kses_post( ob_get_clean() );
	}

	/**
	 * Create a login button (link).
	 *
	 * @param array $atts Array of optional attributes to override login buton
	 * functionality when used by shortcode.
	 *
	 * @return string
	 */
	public function make_login_button( $atts = array() ) {

		$atts = shortcode_atts(
			array(
				'button_text' => __( 'Login with RailID', 'estcore-railid-connect' ),
			),
			$atts,
			'railid_connect_login_button'
		);

		$text = apply_filters( 'railid-connect-login-button-text', $atts['button_text'] );
		$text = esc_html( $text );

		$href = $this->client_wrapper->get_authentication_url( $atts );
		$href = esc_url_raw( $href );

		$login_button = <<<HTML
<div class="railid-connect-login-button" style="margin: 1em 0; text-align: center;">
	<a class="button button-large" href="{$href}">{$text}</a>
</div>
HTML;

		return $login_button;

	}

	/**
	 * Removes the login form from the HTML DOM
	 *
	 * @return void
	 */
	public function remove_login_form() {
		?>
		<script type="text/javascript">
			(function() {
				var loginForm = document.getElementById("user_login").form;
				var parent = loginForm.parentNode;
				parent.removeChild(loginForm);
			})();
		</script>
		<?php
	}
}
