=== RailID Connect ===
Contributors: Mikhail Jurcenoks
Donate link: https://estcore.ru
Tags: security, login, oauth2, openidconnect, apps, authentication, autologin, sso, railid
Requires at least: 4.9
Tested up to: 5.8.2
Stable tag: 1.0.0
Requires PHP: 7.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Простой клиент, который реализует систему единого входа (SSO) или аутентификацию opt-in authentication посредством реализации OAuth2 сервера.

== Описание ==

Этот плагин позволяет аутентифицировать пользователей с помощью OpenID Connect OAuth2 API с потоком кода авторизации.
После установки его можно настроить для автоматической аутентификации пользователей (SSO) или предоставления «Вход с помощью RailID Connect»
в форме входа в систему. После получения согласия существующий пользователь автоматически входит в WordPress, а новые пользователи создаются в базе данных WordPress.

Большую часть документации можно найти на странице «Настройки» > «Универсальная панель управления RailID Connect».

Отправляйте вопросы в репозиторий Github: https://github.com/estcore-llc/railid-connect

== Установка ==

1. Загрузите содержимое плагина в папку `/wp-content/plugins/`;
2. Активируйте плагин;
3. На странице Настройки > RailID Connect укажите сведения, полученные от администрации системы единого входа RailID.

== Журнал изменений ==

Начальная реализация надстройки.
