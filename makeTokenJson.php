<?php
require __DIR__ . '/vendor/autoload.php';

$client = new Google_Client();
$client->setApplicationName('Интерфейс инструктора автошколы ВОА');
$client->setScopes([Google_Service_Calendar::CALENDAR_EVENTS]);
$client->setAuthConfig('credentials.json');
$client->setAccessType('offline');
$client->setPrompt('select_account consent');

$tokenPath = 'token.json';
if (file_exists($tokenPath)) {
    $accessToken = json_decode(file_get_contents($tokenPath), true);
    $client->setAccessToken($accessToken);
}

if ($client->isAccessTokenExpired()) {
    if ($client->getRefreshToken()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
    }
    else {
        $authUrl = $client->createAuthUrl();
        printf("Открой ссылку в браузере:\n%s\n", $authUrl);
        print 'Введи код проверки: ';
        $authCode = trim(fgets(STDIN));

        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
        $client->setAccessToken($accessToken);

        if (array_key_exists('error', $accessToken)) {
            throw new Exception(join(', ', $accessToken));
        }
    }

    if (!file_exists(dirname($tokenPath))) {
        mkdir(dirname($tokenPath), 0700, true);
    }

    file_put_contents($tokenPath, json_encode($client->getAccessToken()));
}