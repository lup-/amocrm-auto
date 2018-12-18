<?php
const LINK_FIELD_ID = "559905";

function authAmoApi($cookieFileName) {
    $userName = 'mailjob@icloud.com';
    $userHash = '142a2eebe3051c6b30a9d2cbe3c4cbdb';
    $authUrl = 'https://mailjob.amocrm.ru/private/api/auth.php';

    $requestHandle = curl_init();
    curl_setopt($requestHandle, CURLOPT_COOKIEJAR, $cookieFileName);
    curl_setopt($requestHandle, CURLOPT_URL, $authUrl);
    curl_setopt($requestHandle, CURLOPT_POST, 1);
    curl_setopt($requestHandle, CURLOPT_POSTFIELDS, "USER_LOGIN={$userName}&USER_HASH={$userHash}");
    curl_setopt($requestHandle, CURLOPT_RETURNTRANSFER, 1);

    curl_exec($requestHandle);
    curl_close($requestHandle);
}

function setLeadLink($leadId, $cookieFileName) {
    $url = "http://amo-auto.humanistic.tech/user.html?id=".$leadId;

    $updateData = [
        "update" => [
            [
                "id"            => $leadId,
                "updated_at"    => time(),
                "custom_fields" => [
                    [
                        "id"     => LINK_FIELD_ID,
                        "values" => [
                            [
                                "value" => $url,
                            ],
                        ],
                    ],
                ],
            ],
        ]
    ];

    $leadsUrl = 'https://mailjob.amocrm.ru/api/v2/leads';

    $requestHandle = curl_init();
    curl_setopt($requestHandle,CURLOPT_RETURNTRANSFER,1);
    curl_setopt($requestHandle,CURLOPT_URL, $leadsUrl);
    curl_setopt($requestHandle,CURLOPT_CUSTOMREQUEST,'POST');
    curl_setopt($requestHandle,CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($requestHandle,CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($requestHandle,CURLOPT_HEADER,false);
    curl_setopt($requestHandle,CURLOPT_COOKIEFILE, $cookieFileName);
    $response = curl_exec($requestHandle);

    return $response;
}

$leadId = $_REQUEST['leads']['status'][0]['id'];

if ($leadId) {
    $cookieFileName = tempnam(sys_get_temp_dir(), "WEBHOOK");
    authAmoApi($cookieFileName);

    setLeadLink($leadId, $cookieFileName);
}