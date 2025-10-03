<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Type\DateTime;
use Bitrix\Highloadblock as HL;
use Bitrix\Main\Entity;

class CarAvailableListComponent extends CBitrixComponent
{
    public function onPrepareComponentParams($arParams)
    {
        // Получение id хайлоад блоков из параметров компонента
        $ids = ['HL_CARS_ID', 'HL_BOOKINGS_ID', 'HL_DRIVERS_ID', 'HL_COMFORT_ID'];
        foreach ($ids as $id) {
            $arParams[$id] = isset($arParams[$id]) ? (int)$arParams[$id] : 0;
        }
        return $arParams;
    }

    private function findAvailableCars($categories, DateTime $start, DateTime $end)
    {
        $entities = $this->getEntities();
        
        // Находим занятые автомобили
        $busyIds = $this->getBusyCarIds($entities['bookings'], $start, $end);
        
        // Получаем доступные автомобили
        $availableCars = $this->getAvailableCarsList($entities, $categories, $busyIds);
        
        return [
            'cars' => $availableCars,
            'booking_info' => [
                'period' => $start->toString() . ' - ' . $end->toString(),
                'busy_ids' => $busyIds,
                'categories' => $categories,
                'found_count' => count($availableCars)
            ]
        ];
    }

    private function getBusyCarIds($bookingsEntity, DateTime $start, DateTime $end)
    {
        $busyIds = [];

        $bookings = $bookingsEntity::query()
            ->setSelect(['UF_CAR', 'UF_DATE_START', 'UF_DATE_END'])
            ->exec();

        while ($booking = $bookings->fetch()) {
            try {
                $bookingStart = new DateTime($booking['UF_DATE_START']);
                $bookingEnd = new DateTime($booking['UF_DATE_END']);
                
                // проверяем перекрытие дат
                if ($this->hasDateOverlap($start, $end, $bookingStart, $bookingEnd)) {
                    $busyIds[] = (int)$booking['UF_CAR'];
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return array_unique($busyIds);
    }

    private function getAvailableCarsList($entities, $categories, $busyIds)
    {
        $query = $entities['cars']::query()
            ->setSelect([
                'ID', 'UF_CAR_MODEL', 'UF_COMFORT_CATEGORY',
                'COMFORT_NAME' => 'COMFORT.UF_NAME',
                'UF_DRIVER', 'DRIVER_NAME' => 'DRIVER.UF_NAME', 
                'DRIVER_PHONE' => 'DRIVER.UF_PHONE'
            ])
            ->registerRuntimeField('COMFORT', $this->createReference('COMFORT', $entities['comfort'], 'UF_COMFORT_CATEGORY'))
            ->registerRuntimeField('DRIVER', $this->createReference('DRIVER', $entities['drivers'], 'UF_DRIVER'))
            ->where('UF_ACTIVE', true)
            ->where('UF_COMFORT_CATEGORY', 'in', $categories);

        if (!empty($busyIds)) {
            $query->whereNotIn('ID', $busyIds);
        }

        $cars = [];
        $result = $query->exec();
        
        while ($car = $result->fetch()) {
            $cars[] = [
                'ID' => $car['ID'],
                'MODEL' => $car['UF_CAR_MODEL'],
                'COMFORT_CATEGORY_ID' => $car['UF_COMFORT_CATEGORY'],
                'COMFORT_CATEGORY_NAME' => $car['COMFORT_NAME'],
                'DRIVER_ID' => $car['UF_DRIVER'],
                'DRIVER_NAME' => $car['DRIVER_NAME'],
                'DRIVER_PHONE' => $car['DRIVER_PHONE'],
            ];
        }

        return $cars;
    }

    private function getEntities()
    {
        $blocks = [
            'cars' => ['Cars', 'HL_CARS_ID'],
            'bookings' => ['CarBookings', 'HL_BOOKINGS_ID'],
            'drivers' => ['Drivers', 'HL_DRIVERS_ID'],
            'comfort' => ['ComfortCategories', 'HL_COMFORT_ID']
        ];

        $entities = [];
        foreach ($blocks as $key => [$name, $param]) {
            $entities[$key] = $this->getEntityDataClass($name, $this->arParams[$param]);
        }
        return $entities;
    }

    private function getEntityDataClass($name, $paramId = 0)
    {
        if ($paramId > 0) {
            $hlBlock = HL\HighloadBlockTable::getById($paramId)->fetch();
            if ($hlBlock) {
                return HL\HighloadBlockTable::compileEntity($hlBlock)->getDataClass();
            }
        }

        $hlBlock = HL\HighloadBlockTable::getList([
            'filter' => ['NAME' => $name],
            'limit' => 1
        ])->fetch();

        if (!$hlBlock) {
            throw new \Exception("HL-блок '{$name}' не найден");
        }

        return HL\HighloadBlockTable::compileEntity($hlBlock)->getDataClass();
    }

    private function createReference($name, $entity, $field)
    {
        return new Entity\ReferenceField(
            $name, $entity, ["=this.{$field}" => 'ref.ID'], ['join_type' => 'LEFT']
        );
    }

    private function hasDateOverlap($start1, $end1, $start2, $end2)
    {
        return ($start1 >= $start2 && $start1 < $end2) ||
               ($end1 > $start2 && $end1 <= $end2) ||
               ($start1 <= $start2 && $end1 >= $end2);
    }

    private function convertDate($htmlDate)
    {
        $htmlDate = str_replace('T', ' ', $htmlDate) . (strlen($htmlDate) === 16 ? ':00' : '');
        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $htmlDate);
        
        if ($date === false) {
            throw new \Exception('Неверный формат даты: ' . $htmlDate);
        }
        
        return new DateTime($date->format('d.m.Y H:i:s'));
    }

    private function getCurrentUserId()
    {
        global $USER;
        return $USER->GetID();
    }

    private function getUserCategories($userId)
    {
        $user = \CUser::GetByID($userId)->Fetch();
        // Получение доступных категорий комфорта из свойства пользователя
        return !empty($user['UF_ACCESSIBLE_CATEGORIES']) ? $user['UF_ACCESSIBLE_CATEGORIES'] : [];
    }

    private function checkModules()
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \Exception('Модуль Highload Block не установлен');
        }
    }

    private function checkHLBlockParameters()
    {
        $requiredParams = [
            'HL_CARS_ID' => 'Автомобили',
            'HL_BOOKINGS_ID' => 'Бронирования', 
            'HL_DRIVERS_ID' => 'Водители',
            'HL_COMFORT_ID' => 'Категории комфорта'
        ];

        $missingParams = [];
        foreach ($requiredParams as $param => $name) {
            if (empty($this->arParams[$param])) {
                $missingParams[] = $name;
            }
        }

        if (!empty($missingParams)) {
            throw new \Exception(
                'В параметрах компонента не заполнены ID хайлоад-блоков : ' . 
                implode(', ', $missingParams));
        }
    }

    public function executeComponent()
    {
        $this->checkModules();
        $this->checkHLBlockParameters();

        // Как вариант, вывод данных при отправке формы
        $request = \Bitrix\Main\Context::getCurrent()->getRequest(); 
        if ($request->isPost() && $request->get('car_search') === '1') {
            $this->FormSend();
        }
        
        $this->includeComponentTemplate();
    }

    private function FormSend()
    {
        try {
            $request = \Bitrix\Main\Context::getCurrent()->getRequest();
            $dateStart = $request->get('date_start') ?? '';
            $dateEnd = $request->get('date_end') ?? '';
            
            if (empty($dateStart) || empty($dateEnd)) {
                $this->arResult['ERROR'] = 'Заполните оба поля даты';
                return;
            }

            $startDate = $this->convertDate($dateStart);
            $endDate = $this->convertDate($dateEnd);

            if ($endDate <= $startDate) {
                $this->arResult['ERROR'] = 'Время окончания должно быть больше времени начала';
                return;
            }

            $userId = $this->getCurrentUserId();
            if (!$userId) {
                $this->arResult['ERROR'] = 'Пользователь не авторизован';
                return;
            }

            $userCategories = $this->getUserCategories($userId);
            if (empty($userCategories)) {
                $this->arResult['MESSAGE'] = 'Нет доступных категорий для вашей должности';
                $this->arResult['CARS'] = [];
                return;
            }

            $result = $this->findAvailableCars($userCategories, $startDate, $endDate);
            $this->arResult['CARS'] = $result['cars'];
            $this->arResult['BOOKING_INFO'] = $result['booking_info'];
            $this->arResult['SEARCH_PARAMS'] = ['date_start' => $dateStart, 'date_end' => $dateEnd];

        } catch (\Exception $e) {
            $this->arResult['ERROR'] = $e->getMessage();
        }
    }
}
?>