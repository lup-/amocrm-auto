<?php
require __DIR__ . '/../vendor/autoload.php';

use AmoCRM\Client\AmoCRMApiClient;

$clientId = $_ENV['AMO_CLIENT_ID'];
$clientSecret = $_ENV['AMO_CLIENT_SECRET'];
$redirectUri = $_ENV['AMO_CLIENT_REDIRECT_URI'];
$tokenFile = $_ENV['AMO_TOKEN_FILE'];

$apiClient = new AmoCRMApiClient($clientId, $clientSecret, $redirectUri);

try {
    $accessToken = $apiClient->getOAuthClient()->getAccessTokenByCode($_GET['code']);
    if (!$accessToken->hasExpired()) {
        $data = [
            'accessToken'  => $accessToken->getToken(),
            'expires'      => $accessToken->getExpires(),
            'refreshToken' => $accessToken->getRefreshToken(),
            'baseDomain'   => $apiClient->getAccountBaseDomain(),
        ];

        file_put_contents($tokenFile, json_encode($data));

        echo "<pre>".json_encode($data, JSON_PRETTY_PRINT)."</pre>";
    }
}
catch (Exception $e) {
    die((string) $e);
}

