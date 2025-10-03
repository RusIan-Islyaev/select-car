# select-car

<?php
$APPLICATION->IncludeComponent(
    'custom:select_car',
    '',
    [
        'HL_CARS_ID' => 9, // ID HL-блока Cars
        'HL_BOOKINGS_ID' => 10, // ID HL-блока CarBookings
        'HL_DRIVERS_ID' => 6, // ID HL-блока Drivers
        'HL_COMFORT_ID' => 5, // ID HL-блока ComfortCategories
    ]
);
?>
