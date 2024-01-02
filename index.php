<?php
# YOU HAVE TO SET A WEBHOOK.
# https://api.telegram.org/bot1234567890:xxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxx/setWebhook?url=https://urlToThisFilesPath.com/path/index.php

define('TOKEN', 'botxxxx');
# e.g. : define('TOKEN', 'bot1234567890:xxxxxxxxxx-xxxxxxxxxxxxxxxxxxxxxxxxxx');

# Name of the folder to save qr codes
define('FOLDER_TO_SAVE', 'imgs');

// Maximum number of QR codes to keep in the json file
define('MAX_TRY', 10);

// Making sure new file name doesn't exist
function generateUniqueFileName($folder, $extension = 'png', $maxAttempts = 10) {
    $attempts = 0;
    do {
        $name = rand(1, 999);
        $path = "$folder/$name.$extension";
        $attempts++;
    } while ($attempts < $maxAttempts && file_exists($path));
    
    return $path;
}

// Generating the QR code
function createQRCode($text, $filePath) {
    require_once("phpqrcode/qrlib.php");
    QRcode::png($text, $filePath, "H", 15, 4);
}

// Showing a welcome message to the user
function sendWelcomeMessage($chatId) {
    $welcomeMessage = "Hi! Just send me any text, and I will send you the QR Code.";
    sendMessage($chatId, $welcomeMessage);
}

// Sending the message to the user
function sendMessage($chatId, $text) {
    $text = urlencode($text);
    $url = "https://api.telegram.org/" . TOKEN . "/sendMessage?chat_id=$chatId&text=$text";
    file_get_contents($url);
}

// Sending Qr Code to the user
function sendQRCode($chatId, $filePath, $text) {
    $qrcodeUrl = dirname("https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]") . "/$filePath";
    $tlgCaption = "`$text`";
    
    $parameters = array(
        "chat_id" => $chatId,
        "photo" => $qrcodeUrl,
        "caption" => $tlgCaption,
        "parse_mode" => "MarkdownV2"
    );
    
    send('sendPhoto', $parameters);
}

// Send a request to the Telegram API
function send($method, $parameters) {
    $url = "https://api.telegram.org/" . TOKEN . "/$method";
    $curl = curl_init();
    
    if (!$curl) {
        exit("Error initializing cURL");
    }

    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $parameters);
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($curl);
    
    return $output;
}

// Saving the latest Qr Codes on a json file so the 'self cleaning' functionality could work
function saveOnFile($filePath) {
    $latestCodesFile = 'latestCodes.json';
    
    if (!file_exists($latestCodesFile)) {
        file_put_contents($latestCodesFile, "[]");
    }

    $oldCodes = json_decode(file_get_contents($latestCodesFile), true);

    // Cleaning up the mess
    if (count($oldCodes) > MAX_TRY) {
        $fileToDelete = basename($oldCodes[count($oldCodes) - 1]['url']);
        unlink(FOLDER_TO_SAVE . '/' . $fileToDelete);
        array_pop($oldCodes);
    }

    $newCode = new stdClass;
    $newCode-> url = $filePath;

    array_unshift($oldCodes, $newCode);

    file_put_contents($latestCodesFile, json_encode($oldCodes, JSON_PRETTY_PRINT));
}

// Geting the data from user
$update =  json_decode(file_get_contents("php://input"), true);

if (isset($update["message"]["text"])) {
    $chatId = $update["message"]["chat"]["id"];
    $message = $update["message"]["text"];

    // Create the folder to save QR codes if it doesn't exist
    if (!is_dir(FOLDER_TO_SAVE)) {
        mkdir(FOLDER_TO_SAVE);
    }

    // Generate a unique file name for the QR code
    $pathToFile = generateUniqueFileName(FOLDER_TO_SAVE);

    // Create the QR code
    createQRCode($message, $pathToFile);

    // Check if the message is a start command
    if ($message === "/start") {
        // Send a welcome message
        sendWelcomeMessage($chatId);
    } else {
        // Send the QR code to the user
        sendQRCode($chatId, $pathToFile, $message);

        // Save the QR code file information in the history file
        saveOnFile($pathToFile);
    }
}
