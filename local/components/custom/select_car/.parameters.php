<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

$arComponentParameters = [
    'PARAMETERS' => [
        'HL_CARS_ID' => [
            'PARENT' => 'BASE',
            'NAME' => 'ID Highload-блока автомобилей',
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ],
        'HL_BOOKINGS_ID' => [
            'PARENT' => 'BASE',
            'NAME' => 'ID Highload-блока бронирований',
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ],
        'HL_DRIVERS_ID' => [
            'PARENT' => 'BASE',
            'NAME' => 'ID Highload-блока водителей',
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ],
        'HL_COMFORT_ID' => [
            'PARENT' => 'BASE',
            'NAME' => 'ID Highload-блока категорий комфорта',
            'TYPE' => 'STRING',
            'DEFAULT' => '',
        ],
        'CACHE_TIME' => ['DEFAULT' => 3600],
    ]
];
?>