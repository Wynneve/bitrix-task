<?
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) exit;

use Bitrix\Main\Loader,
    Bitrix\Highloadblock\HighloadBlockTable;

/**
 * Получает класс highload-блока по его названию
 * @param mixed $hlblock_name название highload-блока
 * @throws Exception модуль highloadblock не подключён или highload-блок не найден
 */
function get_hlclass($hlblock_name) {
    if(!Loader::includeModule('highloadblock')) throw new Exception('Модуль highloadblock не подключён');

    // Получаем highload-блок по его названию
    $hlblock = HighloadBlockTable::getList([
        'filter' => ['=NAME' => $hlblock_name]
    ])->fetch();
    if(!$hlblock) throw new Exception("Highload-блок $hlblock_name не найден");

    $hlclass = HighloadBlockTable::compileEntity($hlblock)->getDataClass();
    return $hlclass;
}