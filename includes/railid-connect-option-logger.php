<?php
/**
 * Класс внутреннего аудита.
 * @package   Estcore_RailID_Connect
 * @category  Logging
 * @author    Mikhail Jurcenoks <support@estcore.ru>
 * @copyright 2021 Estcore LLC. All rights reserved.
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * Класс RailID_Connect_Option_Logger.
 * Простой класс для записи сообщений в таблицу параметров.
 * @package  Estcore_RailID_Connect
 * @category Logging
 */

class RailID_Connect_Option_Logger {

	/**
	 * Название параметра WordPress для журнала.
	 * @var string
	 */
	private $option_name;

	/**
	 * Тип сообщений журналирования.
	 *
	 * @var string
	 */
	private $default_message_type;

	/**
	 * Количество записей в журнале.
	 *
	 * @var int
	 */
	private $log_limit;

	/**
	 * Флаг включения функции журналирования.
	 *
	 * @var bool
	 */
	private $logging_enabled;

	/**
	 * Внутренний кэш событий.
	 *
	 * @var array
	 */
	private $logs;

	/**
	 * Настройте регистратор в соответствии с потребностями экземпляра класса плагина.
	 *
	 * @param string    $option_name          Название параметра WordPress для журнала.
	 * @param string    $default_message_type Тип сообщений журналирования.
	 * @param bool|TRUE $logging_enabled      Флаг включения функции журналирования.
	 * @param int       $log_limit            Количество записей в журнале.
	 */
	public function __construct( $option_name, $default_message_type = 'none', $logging_enabled = true, $log_limit = 1000 ) {
		$this->option_name = $option_name;
		$this->default_message_type = $default_message_type;
		$this->logging_enabled = boolval( $logging_enabled );
		$this->log_limit = intval( $log_limit );
	}

	/**
	 * Назначить класс журналирования определенным фильтрам.
	 * @param array|string $filter_names Массив или строка имен фильтров, к которым подключается регистратор.
	 * @param int          $priority     Уровень приоритета фильтра WordPress.
	 * @return void
	 */
	public function log_filters( $filter_names, $priority = 10 ) {
		if ( ! is_array( $filter_names ) ) {
			$filter_names = array( $filter_names );
		}

		foreach ( $filter_names as $filter ) {
			add_filter( $filter, array( $this, 'log_hook' ), $priority );
		}
	}

	/**
	 * Назначить класс журналирования определенным действиям.
	 * @param array|string $action_names Массив или строка имен действий, к которым подключается регистратор.
	 * @param int          $priority     Уровень приоритета события WordPress.
	 * @return void
	 */
	public function log_actions( $action_names, $priority ) {
		if ( ! is_array( $action_names ) ) {
			$action_names = array( $action_names );
		}

		foreach ( $action_names as $action ) {
			add_filter( $action, array( $this, 'log_hook' ), $priority );
		}
	}

	/**
	 * Журналирование событий.
	 * @param mixed $arg1 Аргумент вызова.
	 * @return mixed
	 */
	public function log_hook( $arg1 = null ) {
		$this->log( func_get_args(), current_filter() );
		return $arg1;
	}

	/**
	 * Сохраняет массив сообщений, содержащий данные и другую информацию, в журнал.
	 * @param mixed $data Данные записи журнала событий.
	 * @param mixed $type Тип записи в журнале событий.
	 * @return bool
	 */
	public function log( $data, $type = null ) {
		if ( boolval( $this->logging_enabled ) ) {
			$logs = $this->get_logs();
			$logs[] = $this->make_message( $data, $type );
			$logs = $this->upkeep_logs( $logs );
			return $this->save_logs( $logs );
		}

		return false;
	}

	/**
	 * Возвращает все сообщения журнала.
	 * @return array
	 */
	public function get_logs() {
		if ( empty( $this->logs ) ) {
			$this->logs = get_option( $this->option_name, array() );
		}
		return $this->logs;
	}

	/**
	 * Возвращает название опции, в которой хранится журнал событий.
	 * @return string
	 */
	public function get_option_name() {
		return $this->option_name;
	}

	/**
	 * Создаёт массив сообщений, содержащий данные и другую информацию.
	 * @param mixed $data Данные записи журнала событий.
	 * @param mixed $type Тип записи в журнале событий.
	 * @return array
	 */
	private function make_message( $data, $type ) {
		// Тип записи в журнале событий.
		if ( empty( $type ) ) {
			$type = $this->default_message_type;

			if ( is_array( $data ) && isset( $data['type'] ) ) {
				$type = $data['type'];
			} else if ( is_wp_error( $data ) ) {
				$type = $data->get_error_code();
			}
		}

		$request_uri = ( ! empty( $_SERVER['REQUEST_URI'] ) ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : 'Unknown';

		// Конструктор записи в журнале.
		$message = array(
			'type'    => $type,
			'time'    => time(),
			'user_ID' => get_current_user_id(),
			'uri'     => preg_replace( '/code=([^&]+)/i', 'code=', $request_uri ),
			'data'    => $data,
		);

		return $message;
	}

	/**
	 * Контролирует количество сообщений в журнале.
	 * @param array $logs Журнал сообщений плагина.
	 * @return array
	 */
	private function upkeep_logs( $logs ) {
		$items_to_remove = count( $logs ) - $this->log_limit;

		if ( $items_to_remove > 0 ) {
			// Сохранять только последние $log_limit сообщения с конца.
			$logs = array_slice( $logs, ( $items_to_remove * -1 ) );
		}

		return $logs;
	}

	/**
	 * Сохраняет сообщение в журнал.
	 * @param array $logs Массив сообщений журнала.
	 *
	 * @return bool
	 */
	private function save_logs( $logs ) {
		// Save the logs.
		$this->logs = $logs;
		return update_option( $this->option_name, $logs, false );
	}

	/**
	 * Очищает журнал сообщений.
	 * @return void
	 */
	public function clear_logs() {
		$this->save_logs( array() );
	}

	/**
	 * Возвращает простую таблицу журнала для всех событий.
	 * @param array $logs Массив сообщений журнала.
	 * @return string
	 */
	public function get_logs_table( $logs = array() ) {
		if ( empty( $logs ) ) {
			$logs = $this->get_logs();
		}
		$logs = array_reverse( $logs );

		ini_set( 'xdebug.var_display_max_depth', '-1' );

		ob_start();
		?>
		<table id="logger-table" class="wp-list-table widefat fixed striped posts">
			<thead>
			<th class="col-details">Details</th>
			<th class="col-data">Data</th>
			</thead>
			<tbody>
			<?php foreach ( $logs as $log ) { ?>
				<tr>
					<td class="col-details">
						<div>
							<label><?php esc_html_e( 'Type', 'estcore-railid-connect' ); ?>: </label>
							<?php print esc_html( $log['type'] ); ?>
						</div>
						<div>
							<label><?php esc_html_e( 'Date', 'estcore-railid-connect' ); ?>: </label>
							<?php print esc_html( gmdate( 'Y-m-d H:i:s', $log['time'] ) ); ?>
						</div>
						<div>
							<label><?php esc_html_e( 'User', 'estcore-railid-connect' ); ?>: </label>
							<?php print esc_html( ( get_userdata( $log['user_ID'] ) ) ? get_userdata( $log['user_ID'] )->user_login : '0' ); ?>
						</div>
						<div>
							<label><?php esc_html_e( 'URI ', 'estcore-railid-connect' ); ?>: </label>
							<?php print esc_url( $log['uri'] ); ?>
						</div>
					</td>

					<td class="col-data"><pre><?php var_dump( $log['data'] ); ?></pre></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
		<?php
		$output = ob_get_clean();

		return $output;
	}
}
