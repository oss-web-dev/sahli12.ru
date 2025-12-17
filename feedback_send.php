<?php

define('BOT_TOKEN', '8082172238:AAEhmdaRTSXLHZ6b4QrUPUfOLSUeEGwStGc'); // Получите у @BotFather в Telegram
//define('CHAT_ID', '@genkaok');     // ID чата (или пользователя), куда бот будет слать сообщения
define('CHAT_ID', '6664148987');     // ID чата (или пользователя), куда бот будет слать сообщения
define('CHAT_ID_2', '2810053');     // ID чата (или пользователя), куда бот будет слать сообщения

file_put_contents('/var/log/sahli_feedback.log', date('Y-m-d H:i:s') . ': ' . print_r([
        'IP' => $_SERVER['REMOTE_ADDR'],
        'POST' => $_POST
    ], true) . PHP_EOL . PHP_EOL, FILE_APPEND);

// --- ПРОВЕРКА МЕТОДА ЗАПРОСА ---
// Скрипт должен вызываться только через POST запрос
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//    http_response_code(301); // Method Not Allowed
//    header('Location: /');
    exit(json_encode(['status' => false]));
}

$uploadedFile = null;
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $uploadedFile = $_FILES['file']['tmp_name'];
    $uploadedFileName = $_FILES['file']['name'];
    // Можно добавить проверки на тип файла и размер, если необходимо
}

$name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name'])) : 'Не указано';
$phone = isset($_POST['phone']) ? htmlspecialchars(trim($_POST['phone'])) : 'Не указан'; // Используем 'phone'
$message_text = isset($_POST['message']) ? htmlspecialchars(trim($_POST['message'])) : 'Нет сообщения';
// Проверяем наличие флага 'need_answer' и его значение 'on'
$need_answer_flag = isset($_POST['need_answer']) && $_POST['need_answer'] === 'on';

$telegramMessage = "*🔔 Новый отзыв\\!*\n\n"; // Жирный шрифт для заголовка
$telegramMessage .= "*Имя:* " . escapeMarkdownV2($name) . "\n";
if ($phone) {
    $telegramMessage .= "*Телефон:* " . escapeMarkdownV2($phone) . "\n";
}
$telegramMessage .= "*Сообщение:*\n```\n" . escapeMarkdownV2($message_text) . "```\n\n"; // Используем <pre> для сохранения форматирования и переносов строк

if ($need_answer_flag) {
    $telegramMessage .= "❗️ *Требуется ответ\\!*";
} else {
    $telegramMessage .= "_Ответ не требуется_"; // Курсив
}

function escapeMarkdownV2($text)
{
    $reserved = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
    foreach ($reserved as $char) {
        $text = str_replace($char, '\\' . $char, $text);
    }
    return $text;
}

function sendTelegramMessage($token, $chatId, $text, $parseMode = 'MarkdownV2')
{
    // URL для запроса к Telegram API методу sendMessage
    $apiUrl = "https://api.telegram.org/bot{$token}/sendMessage";

    // Данные для отправки
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => $parseMode // Указываем режим разметки
    ];

    // Используем cURL для отправки запроса
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    // Отправляем данные как application/x-www-form-urlencoded
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // Получить ответ сервера в виде строки
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Таймаут соединения 5 секунд
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);      // Общий таймаут запроса 10 секунд

    $responseJson = curl_exec($ch); // Выполняем запрос
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Получаем HTTP код ответа
    $curlError = curl_error($ch); // Получаем ошибку cURL, если она была
    curl_close($ch); // Закрываем сеанс cURL

    // Обработка ошибок cURL
    if ($curlError) {
        return ['ok' => false, 'error' => 'Ошибка cURL: ' . $curlError];
    }

    // Декодируем JSON ответ от Telegram API
    $response = json_decode($responseJson, true);

    // Проверка HTTP кода и ответа от Telegram API
    if ($httpCode !== 200 || !$response || !isset($response['ok'])) {
        $errorDescription = $response['description'] ?? 'Неизвестная ошибка API';
        return [
            'ok' => false,
            'error' => "Ошибка API Telegram (HTTP: {$httpCode}): {$errorDescription}",
            'response_raw' => $responseJson // Возвращаем сырой ответ для отладки
        ];
    }

    // Если в ответе Telegram 'ok' === false
    if ($response['ok'] === false) {
        return [
            'ok' => false,
            'error' => "Ошибка от API Telegram: " . ($response['description'] ?? 'Нет описания'),
            'error_code' => $response['error_code'] ?? null,
            'response_raw' => $responseJson
        ];
    }

    // Успешная отправка
    return ['ok' => true, 'result' => $response['result']];
}

function sendTelegramMessageWithPhoto($token, $chatId, $text, $photoPath)
{
    $apiUrl = "https://api.telegram.org/bot{$token}/sendPhoto";

    $postFields = [
        'chat_id' => $chatId,
        'caption' => $text,
        'parse_mode' => 'MarkdownV2',
        'photo' => new CURLFile($photoPath)
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type:multipart/form-data"));
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $responseJson = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['ok' => false, 'error' => 'Ошибка cURL: ' . $curlError];
    }

    $response = json_decode($responseJson, true);

    if ($httpCode !== 200 || !$response || !isset($response['ok'])) {
        $errorDescription = $response['description'] ?? 'Неизвестная ошибка API';
        return [
            'ok' => false,
            'error' => "Ошибка API Telegram (HTTP: {$httpCode}): {$errorDescription}",
            'response_raw' => $responseJson
        ];
    }

    if ($response['ok'] === false) {
        return [
            'ok' => false,
            'error' => "Ошибка от API Telegram: " . ($response['description'] ?? 'Нет описания'),
            'error_code' => $response['error_code'] ?? null,
            'response_raw' => $responseJson
        ];
    }

    return ['ok' => true, 'result' => $response['result']];
}


if (BOT_TOKEN === 'YOUR_BOT_TOKEN_HERE' || CHAT_ID === 'YOUR_CHAT_ID_HERE') {
    http_response_code(301); // Method Not Allowed
    header('Location: /');
} else {
    // Вызов функции отправки
    if ($uploadedFile) {
        // Отправляем сообщение с фото
        $sendResult = sendTelegramMessageWithPhoto(BOT_TOKEN, CHAT_ID, $telegramMessage, $uploadedFile);
        $sendResult = sendTelegramMessageWithPhoto(BOT_TOKEN, CHAT_ID_2, $telegramMessage, $uploadedFile);
    } else {
        // Отправляем только текстовое сообщение
        $sendResult = sendTelegramMessage(BOT_TOKEN, CHAT_ID, $telegramMessage);
        $sendResult = sendTelegramMessage(BOT_TOKEN, CHAT_ID_2, $telegramMessage);
    }

    header('Content-Type: application/json'); // Указываем, что ответ будет в формате JSON

    if ($sendResult['ok']) {
        exit(json_encode(['status' => true, 'message' => 'Ваше сообщение отправлено']));
    } else {
        exit(json_encode(['status' => false, 'message' => 'Не удалось отправить сообщение', 'd' => $sendResult]));
    }
}

exit; // Завершаем выполнение скрипта

?>
