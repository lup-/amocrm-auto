<?php

namespace AMO;

use \League\OAuth2\Client\Token\AccessToken;

class AmoApi
{
    private static $instance;

    private $cookieFileName;
    private $userName = 'mailjob@icloud.com';
    private $userHash = '142a2eebe3051c6b30a9d2cbe3c4cbdb';
    private $token;

    private $authUrl = 'https://mailjob.amocrm.ru/private/api/auth.php';
    private $contactUrl = 'https://mailjob.amocrm.ru/api/v2/contacts';
    private $notesUrl = 'https://mailjob.amocrm.ru/api/v2/notes';
    private $leadsUrl = 'https://mailjob.amocrm.ru/api/v2/leads';

    const ELEMENT_TYPE_LEAD = 2; //https://www.amocrm.com/developers/content/api/notes/#element_types
    const NOTE_TYPE_COMMON = 4;  //https://www.amocrm.com/developers/content/api/notes/#note_types
    const NOTE_TYPE_SYSTEM = 25;

    public function __construct() {
        $this->cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
    }

    public function __destruct() {
        unlink($this->cookieFileName);
    }

    public function auth() {
        $this->token = $this->loadToken();

        return $this;
    }

    private function loadToken() {
        $tokenPath = __DIR__ . "/../../" . $_ENV['AMO_TOKEN_FILE'];
        $accessToken = json_decode(file_get_contents($tokenPath), true);

        return new AccessToken([
            'access_token'  => $accessToken['accessToken'],
            'refresh_token' => $accessToken['refreshToken'],
            'expires'       => $accessToken['expires'],
            'baseDomain'    => $accessToken['baseDomain'],
        ]);
    }

    public function addNoteToLead($leadId, $text) {
        $addData = [
            "add" => [
                [
                    "element_id"   => $leadId,
                    "element_type" => self::ELEMENT_TYPE_LEAD,
                    "text"         => $text,
                    "note_type"    => self::NOTE_TYPE_COMMON,
                ],
            ]
        ];

        $requestHandle = curl_init();
        curl_setopt($requestHandle,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($requestHandle,CURLOPT_URL, $this->notesUrl);
        curl_setopt($requestHandle,CURLOPT_CUSTOMREQUEST,'POST');
        curl_setopt($requestHandle,CURLOPT_POSTFIELDS, json_encode($addData));
        curl_setopt($requestHandle,CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($requestHandle,CURLOPT_HEADER,false);
        curl_setopt($requestHandle,CURLOPT_COOKIEFILE, $this->cookieFileName);
        $response = curl_exec($requestHandle);

        return $response;
    }

    public function sendFileToLead(Document $document) {
        $noteText = "{$document->getFilename()}: {$document->getEditUrl()}";
        $this->addNoteToLead($document->getUserId(), $noteText);
    }

    public function getContact($contactId) {
        $requestUrl = $this->contactUrl . '?id=' . $contactId;

        $requestHandle = curl_init();
        curl_setopt($requestHandle,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($requestHandle,CURLOPT_URL, $requestUrl);
        curl_setopt($requestHandle,CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($requestHandle,CURLOPT_HEADER,false);
        curl_setopt($requestHandle,CURLOPT_COOKIEFILE, $this->cookieFileName);
        $response = curl_exec($requestHandle);

        $asArray = true;
        $parsedResponse = json_decode($response, $asArray);

        return $parsedResponse
            ? AmoContact::createFromArray($parsedResponse['_embedded']['items'][0])
            : false;
    }

    private function getLeadsPage($filter = [], $page = 1, $limit = 500) {
        $limitOffset = ($page-1) * $limit;

        $requestParams = [
            'filter' => $filter,
            'limit_rows' => $limit,
            'limit_offset' => $limitOffset,
        ];

        $requestUrl = $this->leadsUrl."?".http_build_query($requestParams);

        $requestHandle = curl_init();
        curl_setopt($requestHandle, CURLOPT_COOKIEFILE, $cookieFileName);
        curl_setopt($requestHandle, CURLOPT_URL, $requestUrl);
        curl_setopt($requestHandle, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($requestHandle);
        curl_close($requestHandle);

        $asArray = true;
        $parsedResponse = json_decode($response, $asArray);

        return $parsedResponse;
    }

    public function getLeads($filter = []) {
        $apiResponse = $this->getLeadsPage($filter, 1);
        return $apiResponse;
    }

    public function getActiveLeads() {
        return $this->getLeads(['active' => 1]);
    }

    /**
     * @return mixed
     */
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new AmoApi();
            self::$instance->auth();
        }

        return self::$instance;
    }
}