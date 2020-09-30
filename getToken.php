<?php
require __DIR__ . '/vendor/autoload.php';

use AmoCRM\Client\AmoCRMApiClient;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load(__DIR__ . '/.env');

$clientId = $_ENV['AMO_CLIENT_ID'];
$clientSecret = $_ENV['AMO_CLIENT_SECRET'];
$redirectUri = $_ENV['AMO_CLIENT_REDIRECT_URI'];
$tokenFile = $_ENV['AMO_TOKEN_FILE'];

$apiClient = new AmoCRMApiClient($clientId, $clientSecret, $redirectUri);

$state = bin2hex(random_bytes(16));
$_SESSION['oauth2state'] = $state;

$authorizationUrl = $apiClient->getOAuthClient()->getAuthorizeUrl([
    'state' => $state,
    'mode'  => 'post_message',
]);

printf("URL: %s\n", $authorizationUrl);
printf("State: %s\n", $state);