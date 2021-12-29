<?php
/**
 * Класс клиента OIDC/oAuth.
 *
 * @package   Estcore_RailID_Connect
 * @category  Authentication
 * @author    Mikhail Jurcenoks <support@estcore.ru>
 * @copyright 2021 Estcore LLC. All rights reserved.
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * Класс RailID_Connect_Client.
 *
 * Плагин клиента OIDC/oAuth, реализация класса.
 *
 * @package  Estcore_RailID_Connect
 * @category Authentication
 */
class RailID_Connect_Client {

	private $client_id;
	private $client_secret;
	private $scope;
	private $endpoint_login;
	private $endpoint_userinfo;
	private $endpoint_token;
	private $redirect_uri;
	private $state_time_limit = 180;
	private $logger;

	/**
	 * Конструктор клиента.
	 * @param string                               $client_id         @see RailID_Connect_Option_Settings::client_id для описания.
	 * @param string                               $client_secret     @see RailID_Connect_Option_Settings::client_secret для описания.
	 * @param string                               $scope             @see RailID_Connect_Option_Settings::scope для описания.
	 * @param string                               $endpoint_login    @see RailID_Connect_Option_Settings::endpoint_login для описания.
	 * @param string                               $endpoint_userinfo @see RailID_Connect_Option_Settings::endpoint_userinfo для описания.
	 * @param string                               $endpoint_token    @see RailID_Connect_Option_Settings::endpoint_token для описания.
	 * @param string                               $redirect_uri      @see RailID_Connect_Option_Settings::redirect_uri для описания.
	 * @param int                                  $state_time_limit  @see RailID_Connect_Option_Settings::state_time_limit для описания.
	 * @param RailID_Connect_Option_Logger         $logger            Экземпляр класса журналирования событий.
	 */
  
	public function __construct( $client_id, $client_secret, $scope, $endpoint_login, $endpoint_userinfo, $endpoint_token, $redirect_uri, $state_time_limit, $logger ) {
		$this->client_id = $client_id;
		$this->client_secret = $client_secret;
		$this->scope = $scope;
		$this->endpoint_login = $endpoint_login;
		$this->endpoint_userinfo = $endpoint_userinfo;
		$this->endpoint_token = $endpoint_token;
		$this->redirect_uri = $redirect_uri;
		$this->state_time_limit = $state_time_limit;
		$this->logger = $logger;
	}

	/**
	 * Возвращает настроенный URI перенаправления, предоставленный IDP.
	 * @return string
	 */
	public function get_redirect_uri() {
		return $this->redirect_uri;
	}
	/**
	 * Возвращает настроенный URL-адрес входа в конечную точку IDP.
	 * @return string
	 */
	public function get_endpoint_login_url() {
		return $this->endpoint_login;
	}

	/**
	 * Проверяет запрос на аутентификацию при входе.
	 * @param array<string> $request Результат запроса аутентификации.
	 * @return array<string>|WP_Error
	 */
	public function validate_authentication_request( $request ) {
		if ( isset( $request['error'] ) ) {	return new WP_Error( 'unknown-error',  __( 'An unknown error occurred.', 'estcore-railid-connect' ), $request );	}
		if ( ! isset( $request['code'] ) ) { return new WP_Error( 'no-code', __('No authentication code present in the request.', 'estcore-railid-connect' ), $request );	}
		if ( ! isset( $request['state'] ) ) {
      do_action( 'railid-connect-no-state-provided' );
			return new WP_Error( 'missing-state', __( 'Missing state.', 'estcore-railid-connect' ), $request );
		}
		if ( ! $this->check_state( $request['state'] ) ) {
			return new WP_Error( 'invalid-state', __( 'Invalid state.', 'estcore-railid-connect' ), $request );
		}
		return $request;
	}

	/**
	 * Возвращает код авторизации из запроса
	 * @param array<string>|WP_Error $request Результат запроса аутентификации.
	 * @return string|WP_Error
	 */
	public function get_authentication_code( $request ) {
		if ( ! isset( $request['code'] ) ) {
			return new WP_Error( 'missing-authentication-code', __( 'Missing authentication code.', 'estcore-railid-connect' ), $request );
		}

		return $request['code'];
	}

	/**
	 * Используя authorization_code, запрашивает у IDP токен аутентификации.
	 * @param string|WP_Error $code Код авторизации.
	 * @return array<mixed>|WP_Error
	 */
	public function request_authentication_token( $code ) {
		$parsed_url = parse_url( $this->endpoint_token );
		$host = $parsed_url['host'];

		$request = array(
			'body' => array(
				'code'          => $code,
				'client_id'     => $this->client_id,
				'client_secret' => $this->client_secret,
				'redirect_uri'  => $this->redirect_uri,
				'grant_type'    => 'authorization_code',
				'scope'         => $this->scope,
			),
			'headers' => array( 'Host' => $host ),
		);
		$request = apply_filters( 'railid-connect-alter-request', $request, 'get-authentication-token' );
		$this->logger->log( $this->endpoint_token, 'request_authentication_token' );
		$response = wp_remote_post( $this->endpoint_token, $request );

		if ( is_wp_error( $response ) ) {
			$response->add( 'request_authentication_token', __( 'Request for authentication token failed.', 'estcore-railid-connect' ) );
		}
		return $response;
	}

	/**
	 * Используя токен обновления, запросите новые токены у IDP.
	 * @param string $refresh_token Маркер обновления, ранее полученный из ответа на маркер.
	 * @return array|WP_Error
	 */
	public function request_new_tokens( $refresh_token ) {
		$request = array(
			'body' => array(
				'refresh_token' => $refresh_token,
				'client_id'     => $this->client_id,
				'client_secret' => $this->client_secret,
				'grant_type'    => 'refresh_token',
			),
		);
		$request = apply_filters( 'railid-connect-alter-request', $request, 'refresh-token' );
		$this->logger->log( $this->endpoint_token, 'request_new_tokens' );
		$response = wp_remote_post( $this->endpoint_token, $request );
		if ( is_wp_error( $response ) ) {
			$response->add( 'refresh_token', __( 'Refresh token failed.', 'estcore-railid-connect' ) );
		}
		return $response;
	}

	/**
	 * Извлечь и расшифровать тело токена ответа на токен
	 * @param array<mixed>|WP_Error $token_result Символический ответ.
	 * @return array<mixed>|WP_Error|null
	 */
	public function get_token_response( $token_result ) {
		if ( ! isset( $token_result['body'] ) ) {
			return new WP_Error( 'missing-token-body', __( 'Missing token body.', 'estcore-railid-connect' ), $token_result );
		}
		$token_response = json_decode( $token_result['body'], true );
		if ( is_null( $token_response ) ) {
			return new WP_Error( 'invalid-token', __( 'Invalid token.', 'estcore-railid-connect' ), $token_result );
		}

		if ( isset( $token_response['error'] ) ) {
			$error = $token_response['error'];
			$error_description = $error;
			if ( isset( $token_response['error_description'] ) ) {
				$error_description = $token_response['error_description'];
			}
			return new WP_Error( $error, $error_description, $token_result );
		}
		return $token_response;
	}

	/**
	 * Обменять access_token на user_claim из конечной точки userinfo.
	 * @param string $access_token Маркер доступа, полученный из утверждения пользователя аутентификации.
	 * @return array|WP_Error
	 */
	public function request_userinfo( $access_token ) {
	
		$request = apply_filters( 'railid-connect-alter-request', array(), 'get-userinfo' );
		if ( ! array_key_exists( 'headers', $request ) || ! is_array( $request['headers'] ) ) {
			$request['headers'] = array();
		}
		$request['headers']['Authorization'] = 'Bearer ' . $access_token;
		$parsed_url = parse_url( $this->endpoint_userinfo );
		$host = $parsed_url['host'];

		if ( ! empty( $parsed_url['port'] ) ) {
			$host .= ":{$parsed_url['port']}";
		}

		$request['headers']['Host'] = $host;
		$this->logger->log( $this->endpoint_userinfo, 'request_userinfo' );
		$response = wp_remote_post( $this->endpoint_userinfo, $request );

		if ( is_wp_error( $response ) ) {
			$response->add( 'request_userinfo', __( 'Request for userinfo failed.', 'estcore-railid-connect' ) );
		}
		return $response;
	}

	/**
	 * Сгенерируйте новое состояние, сохраните его как временное и верните хэш состояния.
	 * @param string $redirect_to URL-адрес перенаправления, который будет использоваться после аутентификации IDP.
	 * @return string
	 */
	public function new_state( $redirect_to ) {
		$state = md5( mt_rand() . microtime( true ) );
		$state_value = array(
			$state => array(
				'redirect_to' => $redirect_to,
			),
		);
		set_transient( 'railid-connect-state--' . $state, $state_value, $this->state_time_limit );

		return $state;
	}

	/**
	 * Проверяет наличие данного переходного состояния.
	 * @param string $state The state hash to validate.
	 * @return bool
	 */
	public function check_state( $state ) {

		$state_found = true;

		if ( ! get_option( '_transient_railid-connect-state--' . $state ) ) {
			do_action( 'railid-connect-state-not-found', $state );
			$state_found = false;
		}

		$valid = get_transient( 'railid-connect-state--' . $state );

		if ( ! $valid && $state_found ) {
			do_action( 'railid-connect-state-expired', $state );
		}

		return boolval( $valid );
	}

	/**
	 * Возвращает состояние авторизации из запроса.
	 * @param array<string>|WP_Error $request Результат запроса аутентификации.
	 * @return string|WP_Error
	 */
	public function get_authentication_state( $request ) {
		if ( ! isset( $request['state'] ) ) {
			return new WP_Error( 'missing-authentication-state', __( 'Missing authentication state.', 'estcore-railid-connect' ), $request );
		}

		return $request['state'];
	}

	/**
	 * Убедитесь, что токен соответствует основным требованиям.
	 * @param array $token_response Знаковый ответ.
	 * @return bool|WP_Error
	 */
	public function validate_token_response( $token_response ) {
		if ( ! isset( $token_response['id_token'] ) ||
			 ! isset( $token_response['token_type'] ) || strcasecmp( $token_response['token_type'], 'Bearer' )
		) {
			return new WP_Error( 'invalid-token-response', 'Invalid token response', $token_response );
		}
		return true;
	}

	/**
	 * Извлекает id_token_claim из token_response.
	 * @param array $token_response Знаковый ответ.
	 * @return array|WP_Error
	 */
	public function get_id_token_claim( $token_response ) {
		if ( ! isset( $token_response['id_token'] ) ) {
			return new WP_Error( 'no-identity-token', __( 'No identity token.', 'estcore-railid-connect' ), $token_response );
		}
		$tmp = explode( '.', $token_response['id_token'] );
		if ( ! isset( $tmp[1] ) ) {
			return new WP_Error( 'missing-identity-token', __( 'Missing identity token.', 'estcore-railid-connect' ), $token_response );
		}
		$id_token_claim = json_decode(
			base64_decode(
				str_replace( // Because token is encoded in base64 URL (and not just base64).
					array( '-', '_' ),
					array( '+', '/' ),
					$tmp[1]
				)
			),
			true
		);

		return $id_token_claim;
	}

	/**
	 * Убедитесь, что id_token_claim содержит требуемые значения.
	 * @param array $id_token_claim Утверждение идентификатора токена.
	 * @return bool|WP_Error
	 */
	public function validate_id_token_claim( $id_token_claim ) {
		if ( ! is_array( $id_token_claim ) ) {
			return new WP_Error( 'bad-id-token-claim', __( 'Bad ID token claim.', 'estcore-railid-connect' ), $id_token_claim );
		}
		if ( ! isset( $id_token_claim['sub'] ) || empty( $id_token_claim['sub'] ) ) {
			return new WP_Error( 'no-subject-identity', __( 'No subject identity.', 'estcore-railid-connect' ), $id_token_claim );
		}
		return true;
	}

	/**
	 * Попытка обменять access_token на user_claim.
	 * @param array $token_response Знаковый ответ.
	 * @return array|WP_Error|null
	 */
	public function get_user_claim( $token_response ) {
		$user_claim_result = $this->request_userinfo( $token_response['access_token'] );
		if ( is_wp_error( $user_claim_result ) || ! isset( $user_claim_result['body'] ) ) {
			return new WP_Error( 'bad-claim', __( 'Bad user claim.', 'estcore-railid-connect' ), $user_claim_result );
		}
		$user_claim = json_decode( $user_claim_result['body'], true );
		return $user_claim;
	}

	/**
	 * Проверяет, что user_claim имеет все требуемые значения и что идентичность субъекта совпадает с id_token и user_claim.
	 * @param array $user_claim     Утверждение аутентифицированного пользователя.
	 * @param array $id_token_claim Утверждение идентификатора токена.
	 * @return bool|WP_Error
	 */
	public function validate_user_claim( $user_claim, $id_token_claim ) {
		if ( ! is_array( $user_claim ) ) {
			return new WP_Error( 'invalid-user-claim', __( 'Invalid user claim.', 'estcore-railid-connect' ), $user_claim );
		}
		if ( isset( $user_claim['error'] ) ) {
			$message = __( 'Error from the IDP.', 'estcore-railid-connect' );
			if ( ! empty( $user_claim['error_description'] ) ) {
				$message = $user_claim['error_description'];
			}
			return new WP_Error( 'invalid-user-claim-' . $user_claim['error'], $message, $user_claim );
		}
		if ( $id_token_claim['sub'] !== $user_claim['sub'] ) {
			return new WP_Error( 'incorrect-user-claim', __( 'Incorrect user claim.', 'estcore-railid-connect' ), func_get_args() );
		}
		$login_user = apply_filters( 'railid-connect-user-login-test', true, $user_claim );
		if ( ! $login_user ) {
			return new WP_Error( 'unauthorized', __( 'Unauthorized access.', 'estcore-railid-connect' ), $login_user );
		}

		return true;
	}

	/**
	 * Получите идентичность субъекта из id_token.
	 * @param array $id_token_claim Утверждение идентификатора токена.
	 * @return mixed
	 */
	 public function get_subject_identity( $id_token_claim ) {
      return $id_token_claim['sub'];
	}

}
