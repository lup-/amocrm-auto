<?php
/**
 * Возвращает авторизованный клиент API Google
 * @return Google_Client
 */
function getClient($tokenPath) {
    $client = new Google_Client();
    $client->setApplicationName('Интерфейс инструктора автошколы ВОА');
    $client->setScopes([Google_Service_Calendar::CALENDAR_EVENTS, Google_Service_Drive::DRIVE_READONLY]);
    $client->setAuthConfig('../credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    if (file_exists($tokenPath)) {
        $accessToken = json_decode(file_get_contents($tokenPath), true);
        $client->setAccessToken($accessToken);
    }

    if ($client->isAccessTokenExpired()) {
        if ($client->getRefreshToken()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());

            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }

            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }
        else {
            throw new Exception("Ошибка токена: ".join(', ', $accessToken));
        }
    }
    return $client;
}