<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) exit;

function respond($status, $data) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST');
    header("Access-Control-Allow-Headers: X-Requested-With");

    header('Content-Type: application/json');
    http_response_code($status);

    exit(json_encode(
        $status >= 400 ? [
            'success' => false,
            'message' => $data
        ] : [
            'success' => true,
            'data' => $data
        ]
    ));
}

function check_method($method) {
    if($_SERVER['REQUEST_METHOD'] != $method) respond(405, "Этот эндпоинт принимает $method-запросы.");
}

function get_body() {
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

function check_keys($object, $keys) {
    foreach($keys as $key) if(!key_exists($key, $object)) respond(422, "Не обнаружено поле $key.");
    return true;
}

function validate_keys($object, $keys, $callback) {
    foreach($keys as $key) if(!call_user_func($callback, $key, $object[$key])) respond(422, "Поле $key не прошло валидацию.");
    return true;
}

function validate_values($array, $keys, $callback) {
    foreach($array as $value) if(!validate_keys($value, $keys, $callback)) respond(422, "Одно из значений в списке не прошло валидацию.");
    return true;
}

function get_user() {
    global $USER;
    $user_id = $USER->GetID();
    if($user_id <= 0) respond(403, 'Не авторизован');
    return $user_id;
}