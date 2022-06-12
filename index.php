<?php

require_once './vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

define("TELEGRAM_API_URL", 'https://api.telegram.org/bot' . $_ENV['TELEGRAM_API_TOKEN'] . '/');

const TRANSPORT_TYPES = [
    'bus' => 'Автобус',
    'tram' => 'Трамвай',
    'trolleybus' => 'Тролейбус',
];

function request($method, $params = array()) {
    if (!empty($params)) {
        $url = TELEGRAM_API_URL . $method . "?" . http_build_query($params);
    } else {
        $url =TELEGRAM_API_URL . $method;
    }

    return json_decode(file_get_contents($url), JSON_OBJECT_AS_ARRAY);
}

$update = json_decode(file_get_contents("php://input"), TRUE);
$chatId = $update["message"]["chat"]["id"];
$message = $update["message"]["text"];

if (!$chatId) {
    $chatId = $update["callback_query"]["message"]["chat"]['id'];
}
$messageId = $update["callback_query"]["message"]["message_id"];
$data = json_decode($update["callback_query"]["data"], true);

$output = '';
$encodedKeyboard = '';

if ($data['action'] === 'getTransportNumbers') {
    exec("python ./scripts/" . $data['t'] . '_numbers.py', $transportNumbers);

    $resButtons = [];
    foreach ($transportNumbers as $transportNumber) {
        $resButtons[] = [[
            'text' => $transportNumber,
            'callback_data' => json_encode([
                'action' => 'getTransportDirections',
                't' => $data['t'],
                'n' => $transportNumber,
            ])
        ]];
    }
    $keyboard = [
        'inline_keyboard' =>
            $resButtons
    ];
    $encodedKeyboard = json_encode($keyboard);
    $output = 'Выберите №:';

    request('editMessageText', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $output,
        'reply_markup' => $encodedKeyboard,
        'parse_mode' => 'HTML',
    ]);
}

if ($data['action'] === 'getTransportDirections') {
    exec("python ./scripts/" . $data['t'] . '_directions.py ' . $data['n'], $transportDirections);

    $resButtons = [];
    foreach ($transportDirections as $key => $transportDirection) {

        $resButtons[] = [[
            'text' => $transportDirection,
            'callback_data' => json_encode([
                'action' => 'getTransportInfo',
                't' => $data['t'],
                'n' => $data['n'],
                'd' => $key + 1,
            ])
        ]];
    }
    $keyboard = [
        'inline_keyboard' =>
            $resButtons
    ];
    $encodedKeyboard = json_encode($keyboard);
    $output = 'Выберите Направление:';

    request('editMessageText', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $output,
        'reply_markup' => $encodedKeyboard,
        'parse_mode' => 'HTML',
    ]);
}

if ($data['action'] === 'getTransportInfo') {
    exec("python ./scripts/" . $data['t'] . '_info.py ' . $data['n'] . ' ' . $data['d'], $transportInfo);

    request('editMessageText', [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => implode("\n", $transportInfo),
        'reply_markup' => '',
        'parse_mode' => 'HTML',
    ]);
}

if (strpos($message, "/transport") === 0) {
    $resButtons = [];
    foreach (TRANSPORT_TYPES as $transportType => $transportName) {
        $text = $transportName;

        $resButtons[] = [[
            'text' => $transportName,
            'callback_data' => json_encode([
                'action' => 'getTransportNumbers',
                't' => $transportType,
            ])
        ]];
    }
    $keyboard = [
        'inline_keyboard' =>
            $resButtons
    ];
    $encodedKeyboard = json_encode($keyboard);
    $output = 'Выберите тип транспорта:';

    request('sendMessage', [
        'chat_id' => $chatId,
        'text' => $output,
        'reply_markup' => $encodedKeyboard,
        'parse_mode' => 'HTML',
    ]);
}
