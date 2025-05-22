<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) exit;

global $APPLICATION, $USER;

const KEYS = [
    'login',
    'password'
];

// Проверка метода и получение тела запроса
check_method('POST');
$body = get_body();

// Проверка наличия параметров
check_keys($body, KEYS);

// Валидация параметров
$login    = $body['login'];
$password = $body['password'];
validate_keys($body, KEYS, fn($key, $value) =>
    match($key) {
        // логин и пароль должны быть непустыми строками
        'login'    => is_string($value) && strlen($value) > 0,
        'password' => is_string($value) && strlen($value) > 0,

        default      => true
    }
);

// Вход
$result = $USER->Login($login, $password, 'Y', 'Y');
if($result === true) {
    respond(200, [
        'user_id' => $USER->GetID(),
    ]);
} else {
    respond(401, "Авторизация не удалась");
}