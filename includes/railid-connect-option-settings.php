<?php
/**
 * Класс обработки параметров WordPress.
 *
 * @package   Estcore_RailID_Connect
 * @category  Settings
 * @author    Mikhail Jurcenoks <support@estcore.ru>
 * @copyright 2021 Estcore LLC. All rights reserved.
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * RailID_Connect_Option_Settings class.
 * Перехват параметров WordPress.
 * @package Estcore_RailID_Connect
 * @category  Settings
 *
 * Настройки клиента OAuth:
 *
 * @property string $login_type             Каким образом клиент (форма входа) должен предоставлять параметры входа в систему.
 * @property string $client_id              Идентификатор клиента подключения к серверу поставщика удостоверений.
 * @property string $client_secret          Секретный ключ, ожидаемый сервером IDP от клиента.
 * @property string $scope                  Список областей, к которым должен получить доступ этот клиент.
 * @property string $endpoint_login         URL-адрес конечной точки авторизации IDP (https://railid.ru/oauth/authorize).
 * @property string $endpoint_userinfo      URL-адрес конечной точки информации о пользователе IDP (https://railid.ru/oauth/me).
 * @property string $endpoint_token         URL-адрес конечной точки проверки токена IDP (https://railid.ru/oauth/token).
 * @property string $endpoint_end_session		URL-адрес конечной точки выхода IDP (https://railid.ru/oauth/destroy).
 *
 * Нестандартные параметры:
 *
 * @property bool   $no_sslverify           Флаг для включения/отключения проверки SSL во время авторизации.
 * @property int    $http_request_timeout   Тайм-аут для запросов к IDP. Значение по умолчанию - 5.
 * @property string $identity_key           Ключ в массиве требований пользователя для поиска его идентификационных данных.
 * @property string $nickname_key           Ключ в массиве утверждений пользователя для поиска его псевдонима.
 * @property string $email_format           Ключ(и) в массиве утверждений пользователя для формулировки адреса электронной почты пользователя.
 * @property string $displayname_format     Ключ(и) в массиве утверждений пользователя для формулирования отображаемого имени пользователя.
 * @property bool   $identify_with_username Флаг, указывающий, как будет определяться личность пользователя.
 * @property int    $state_time_limit       Действительный лимит времени состояния в секундах. По умолчанию 180 секунд.
 *
 * Настройки плагина:
 *
 * @property bool $enforce_privacy          Флаг указывает, требуется ли пользователю пройти аутентификацию для доступа к сайту.
 * @property bool $alternate_redirect_uri   Флаг, указывающий, следует ли использовать альтернативный URI перенаправления.
 * @property bool $token_refresh_enable     Флаг, поддерживать ли токены обновления IDP.
 * @property bool $link_existing_users      Флаг, указывающий, следует ли ссылаться на существующие учетные записи WordPress или возвращать ошибку.
 * @property bool $create_if_does_not_exist Флаг, указывающий, создавать ли новых пользователей или нет.
 * @property bool $redirect_user_back       Флаг, указывающий, следует ли перенаправлять пользователя обратно на страницу, с которой он начал.
 * @property bool $redirect_on_logout       Флаг, указывающий, следует ли перенаправлять на экран входа в систему по истечении срока сеанса.
 * @property bool $enable_logging           Флаг для включения/отключения ведения журнала.
 * @property int  $log_limit                Максимальное количество сохраняемых записей журнала.
 */
class RailID_Connect_Option_Settings {

	/**
	 * Параметр WordPress: название/значение.
	 * @var string
	 */

  private $option_name;

	/**
	 * Массив сохраненных значений.
	 * @var array<mixed>
	 */
	private $values;

	/**
	 * Значение параметров настройки по-умолчанию.
	 * @var array<mixed>
	 */
	private $default_settings;

	/**
	 * List of settings that can be defined by environment variables.
	 *
	 * @var array<string,string>
	 */
	private $environment_settings = array(
		'client_id'            => 'RIDC_CLIENT_ID',
		'client_secret'        => 'RIDC_CLIENT_SECRET',
		'endpoint_login'       => 'RIDC_ENDPOINT_LOGIN_URL',
		'endpoint_userinfo'    => 'RIDC_ENDPOINT_USERINFO_URL',
		'endpoint_token'       => 'RIDC_ENDPOINT_TOKEN_URL',
		'endpoint_end_session' => 'RIDC_ENDPOINT_LOGOUT_URL',
	);

	/**
	 * Конструктор класса.
	 * @param string       $option_name       Название параметра/ключ.
	 * @param array<mixed> $default_settings  Настройки по умолчанию.
	 * @param bool         $granular_defaults Детализированные значения по умолчанию.
	 */
	public function __construct( $option_name, $default_settings = array(), $granular_defaults = true ) {
		$this->option_name = $option_name;
		$this->default_settings = $default_settings;
		$this->values = array();

		if ( ! empty( $this->option_name ) ) {
			$this->values = (array) get_option( $this->option_name, $this->default_settings );
		}

		// Убеждаемся в том, что для каждой заданной переменной/константы среды задан ключ настроек.
		foreach ( $this->environment_settings as $key => $constant ) {
			if ( defined( $constant ) ) {
				$this->__set( $key, constant( $constant ) );
			}
		}

		if ( $granular_defaults ) {
			$this->values = array_replace_recursive( $this->default_settings, $this->values );
		}
	}

	/**
	 * Собственный метод извлечения настроек.
	 * @param string $key Ключ/имя массива параметров.
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		if ( isset( $this->values[ $key ] ) ) {
			return $this->values[ $key ];
		}
	}

	/**
	 * Собственный метод сохранения настроек.
	 * @param string $key   Ключ/имя массива параметров.
	 * @param mixed  $value Значения параметров.
	 *
	 * @return void
	 */
	public function __set( $key, $value ) {
		$this->values[ $key ] = $value;
	}

	/**
	 * Собственный метод проверки наличия настроек.
	 * @param string $key Ключ/имя массива параметров.
	 * @return bool
	 */
	public function __isset( $key ) {
		return isset( $this->values[ $key ] );
	}

	/**
	 * Собственный метод очистки настроек.
	 * @param string $key Ключ/имя массива параметров.
	 * @return void
	 */
	public function __unset( $key ) {
		unset( $this->values[ $key ] );
	}

	/**
	 * Возвращает массив настроек плагина.
	 * @return array
	 */
	public function get_values() {
		return $this->values;
	}

	/**
	 * Возвращает ключ/имя массива параметров.
	 * @return string
	 */
	public function get_option_name() {
		return $this->option_name;
	}

	/**
	 * Сохраняет параметры плагина в таблице параметров WordPress.
	 * @return void
	 */
	public function save() {

		// Для каждой определенной переменной/константы среды убеждаемся, что она не сохранена в базе данных.
		foreach ( $this->environment_settings as $key => $constant ) {
			if ( defined( $constant ) ) {
				$this->__unset( $key );
			}
		}
		update_option( $this->option_name, $this->values );

	}
}
