<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) exit;

global $APPLICATION, $USER;

const KEYS = [
    'begin_time',
    'end_time'
];

// Проверка метода и получение тела запроса
check_method('GET');
$body = $_GET;

// Проверка наличия параметров
check_keys($body, KEYS);

// Валидация параметров
$current_time = time();
// ожидаются время начала и окончания поездки в формате YYYY-MM-DDTHH:MM:SS+00:00
$begin_time = DateTime::createFromFormat(DateTime::ATOM, $_GET['begin_time']) ?: null;
$end_time   = DateTime::createFromFormat(DateTime::ATOM, $_GET['end_time'])   ?: null;
validate_keys($body, KEYS, fn($key, $value) =>
    match($key) {
        // время начала поездки должно быть больше текущего времени
        'begin_time' => $begin_time?->getTimestamp() > $current_time,
        // время окончания поездки должно быть больше времени начала поездки и больше текущего времени 
        'end_time'   => $end_time?->getTimestamp() > $current_time && $end_time > $begin_time,

        default      => true
    }
);

// Получение текущего пользователя
$user_id = get_user();

// Вызов компонента для получения доступных автомобилей
$result = $APPLICATION->IncludeComponent(
    'task:cars',
    '',
    [
        'USER_ID'    => $user_id,
        'BEGIN_TIME' => $begin_time,
        'END_TIME'   => $end_time,
    ],
    null,
    null,
    true
);

respond($result['STATUS'], $result['DATA']);