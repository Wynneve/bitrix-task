<?
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) exit;

use Bitrix\Main\Loader,
    Bitrix\Highloadblock\HighloadBlockTable;

class TaskCars extends CBitrixComponent {
    /**
     * Логика компонента при подключении
     */
    public function executeComponent() {
        try {
            // Получаем параметры компонента
            $user_id = $this->arParams['USER_ID'];
            $begin_time = $this->arParams['BEGIN_TIME'];
            $end_time = $this->arParams['END_TIME'];
            
            // Получаем классы комфорта для пользователя
            $classes = $this->getClassesForUser($this->arParams['USER_ID']);
            if(count($classes) == 0) {
                $this->returnResult(403, "Нет классов комфорта у пользователя, поездки невозможны");
                return;
            }

            // Получаем автомобили с этими классами комфорта
            $cars = $this->getCars($classes);
            // Фильтруем автомобили по доступности в указанный период
            $available_cars = $this->getAvailableCars($cars, $begin_time, $end_time);
            
            // Если нет доступных автомобилей, возвращаем 404
            if(count($available_cars) == 0) {
                $this->returnResult(409, "Нет доступных автомобилей в указанный период");
                return;
            }
            
            // Возвращаем доступные автомобили
            $this->returnResult(200, $available_cars);
        } catch(Exception $e) {
            $this->returnResult(500, $e->getMessage());
        }
    }

    /**
     * Получает классы комфорта для пользователя
     * @param mixed $user_id ID пользователя
     * @throws Exception не найден highload-блок "Positions" или "ComfortClasses"
     */
    protected function getClassesForUser($user_id) {
        // Получаем должность для пользователя из его UF_POSITION
        $user = CUser::GetByID($user_id)->Fetch();
        $position_id = $user['UF_POSITION'];
        
        // Получаем highload-блок "Positions"
        $positions_class = get_hlclass('Positions');
        // Получаем запись с ID = $position_id
        $position = $positions_class::getList([
            'filter' => ['ID' => $position_id]
        ])->fetch();
        // Получаем классы комфорта для должности из её UF_CLASSES
        $classes = $position['UF_CLASSES'];

        // Получаем highload-блок "ComfortClasses"
        $comfort_classes_class = get_hlclass('ComfortClasses');
        // Получаем записи с ID из UF_CLASSES
        $comfort_classes = $comfort_classes_class::getList([
            'filter' => ['ID' => $classes]
        ])->fetchAll();
        
        return $comfort_classes;
    }

    /**
     * Получает автомобили с указанными классами комфорта
     * @param mixed $classes классы комфорта
     * @throws Exception инфоблок автомобилей не найден
     */
    protected function getCars($classes = null) {
        // Получаем код инфоблока автомобилей
        $cars_code = CIBlock::GetList([], ['CODE' => 'cars'])->Fetch()['ID'];
        if(!$cars_code) {
            throw new Exception('Не найден инфоблок автомобилей');
        }

        // Получаем автомобили указанных классов комфорта
        $classes = array_column($classes, null, 'UF_XML_ID');
        $rsCars = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID'      => $cars_code,
                'ACTIVE'         => 'Y',
                'PROPERTY_CLASS' => array_keys($classes),
            ],
            false,
            false,
            ['ID', 'NAME', 'PROPERTY_CLASS', 'PROPERTY_DRIVER']
        );
        while($car = $rsCars->Fetch()) {
            $cars[$car['ID']]['NAME'] = $car['NAME'];

            $cars[$car['ID']]['CLASS'] = $classes[$car['PROPERTY_CLASS_VALUE']]['UF_NAME'];
        
            $user = CUser::GetByID($car['PROPERTY_DRIVER_VALUE'])->Fetch();
            $cars[$car['ID']]['DRIVER'] = trim($user['LAST_NAME'] . ' ' . $user['NAME']);
        }

        return $cars;
    }

    /**
     * Возвращает доступные автомобили в указанный период
     * @param mixed $cars       список автомобилей для фильтрации
     * @param mixed $begin_time время начала поездки
     * @param mixed $end_time   время окончания поездки
     * @throws Exception инфоблок поездок не найден
     */
    protected function getAvailableCars($cars, $begin_time, $end_time) {
        global $DB;

        // Получаем код инфоблока поездок
        $travels_code = CIBlock::GetList([], ['CODE' => 'travels'])->Fetch()['ID'];
        if(!$travels_code) {
            throw new Exception('Не найден инфоблок поездок');
        }

        // Приводим время к формату БД Битрикса для сравнения
        $begin_time = trim($DB->CharToDateFunction(ConvertTimeStamp($begin_time->getTimestamp(), 'FULL')), '\'');
        $end_time   = trim($DB->CharToDateFunction(ConvertTimeStamp($end_time->getTimestamp(), 'FULL')), '\'');

        // Получаем поездки, пересекающиеся с указанным периодом
        $rsTravels = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $travels_code,
                'ACTIVE'    => 'Y',
                // условие, что указанный период не пересекается с данной поездкой:
                // либо указанный период закончился до начала поездки: PROPERTY_TIME_BEGIN >= $end_time
                // либо указанный период начался после окончания поездки: PROPERTY_TIME_END <= $begin_time
                // всё это вместе: PROPERTY_TIME_BEGIN > $end_time || PROPERTY_TIME_END < $begin_time
                // а нам нужно наоборот, чтобы указанный период пересекался с поездкой, поэтому инвертируем:
                // PROPERTY_TIME_BEGIN <= $end_time && PROPERTY_TIME_END >= $begin_time
                '<=PROPERTY_TIME_BEGIN' => $end_time,
                '>=PROPERTY_TIME_END'   => $begin_time,
                'PROPERTY_CAR'          => array_keys($cars),
            ],
            false,
            false,
            ['ID', 'PROPERTY_CAR']
        );

        // Удаляем автомобили, которые заняты в указанный период
        while($travel = $rsTravels->Fetch()) {
            unset($cars[$travel['PROPERTY_CAR_VALUE']]);
        }

        // Возвращаем оставшиеся автомобили
        return array_values($cars);
    }

    /**
     * Возвращает результат выполнения компонента
     * @param mixed $status HTTP-статус
     * @param mixed $data   данные для ответа
     * @return void
     */
    protected function returnResult($status, $data) {
        $this->arResult = [
            'STATUS' => $status,
            'DATA'   => $data
        ];
    }
}