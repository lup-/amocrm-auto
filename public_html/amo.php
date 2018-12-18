<?php
const INSTRUCTOR_FIELD_ID = "398075";
const HOURS_FIELD_ID = "552963";

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

function authAmoInterface($cookieFileName) {
    $crmUrl = "https://mailjob.amocrm.ru/";
    $authUrl = "https://mailjob.amocrm.ru/oauth2/authorize";

    $requestHandle = curl_init();
    curl_setopt($requestHandle, CURLOPT_COOKIEJAR, $cookieFileName);
    curl_setopt($requestHandle, CURLOPT_URL, $crmUrl);
    curl_setopt($requestHandle, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec($requestHandle);
    preg_match('#name="csrf_token" value="(.*?)"#', $response, $matches);
    $csrfToken = $matches[1];

    curl_close($requestHandle);

    $authData = json_encode([
        "password"   => "KeamqdSH",
        "username"   => "mailjob@icloud.com",
        "csrf_token" => $csrfToken,
    ]);

    $requestHandle = curl_init();
    curl_setopt($requestHandle, CURLOPT_COOKIEJAR, $cookieFileName);
    curl_setopt($requestHandle, CURLOPT_COOKIEFILE, $cookieFileName);
    curl_setopt($requestHandle, CURLOPT_URL, $authUrl);
    curl_setopt($requestHandle, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($requestHandle, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
    ]);
    curl_setopt($requestHandle, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($requestHandle, CURLOPT_POSTFIELDS, $authData);
    curl_setopt($requestHandle, CURLOPT_VERBOSE, 1);

    curl_exec($requestHandle);

    curl_close($requestHandle);
}

function loadApiLeads($cookieFileName) {
    $leadsUrl = "https://mailjob.amocrm.ru/api/v2/leads?filter[active]=1";
    $requestHandle = curl_init();
    curl_setopt($requestHandle, CURLOPT_COOKIEFILE, $cookieFileName);
    curl_setopt($requestHandle, CURLOPT_URL, $leadsUrl);
    curl_setopt($requestHandle, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec($requestHandle);
    curl_close($requestHandle);

    $asArray = true;
    $parsedResponse = json_decode($response, $asArray);

    return $parsedResponse;
}

function loadInstructorIds($cookieFileName) {
    $leadsUrl = "https://mailjob.amocrm.ru/ajax/leads/list/pipeline/";

    $requestHandle = curl_init();
    curl_setopt($requestHandle, CURLOPT_COOKIEFILE, $cookieFileName);
    curl_setopt($requestHandle, CURLOPT_URL, $leadsUrl);
    curl_setopt($requestHandle, CURLOPT_POST, 1);
    curl_setopt($requestHandle, CURLOPT_POSTFIELDS, "useFilter=y");
    curl_setopt($requestHandle, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($requestHandle, CURLOPT_HTTPHEADER, [
        "X-Requested-With: XMLHttpRequest",
    ]);

    $response = curl_exec($requestHandle);
    curl_close($requestHandle);

    $asArray = true;
    $parsedResponse = json_decode($response, $asArray);
    $customFields = $parsedResponse['response']['fields'];

    $instructors = [];

    foreach ($customFields as $customFieldData) {
        if ($customFieldData['id'] == INSTRUCTOR_FIELD_ID) {
            foreach ($customFieldData['enums'] as $enumEntry) {
                $instructors[ $enumEntry['id'] ] = $enumEntry['value'];
            }
        }
    }

    return $instructors;
}

function loadInstructorLeadsWithExtraData($cookieFileName, $instructorId) {
    $leadsUrl = "https://mailjob.amocrm.ru/ajax/leads/list/pipeline/";

    $leadsFilter = [
        "filter[cf][".INSTRUCTOR_FIELD_ID."]" => $instructorId,
        "useFilter"          => "y",
    ];

    $requestHandle = curl_init();
    curl_setopt($requestHandle, CURLOPT_COOKIEFILE, $cookieFileName);
    curl_setopt($requestHandle, CURLOPT_URL, $leadsUrl);
    curl_setopt($requestHandle, CURLOPT_POST, 1);
    curl_setopt($requestHandle, CURLOPT_POSTFIELDS, http_build_query($leadsFilter));
    curl_setopt($requestHandle, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($requestHandle, CURLOPT_HTTPHEADER, [
        "X-Requested-With: XMLHttpRequest",
    ]);

    $response = curl_exec($requestHandle);
    curl_close($requestHandle);

    $asArray = true;
    $parsedResponse = json_decode($response, $asArray);

    return $parsedResponse;
}

function getContactsAndHoursFromLeads($leadsData) {
    $contactsAndHours = [];
    foreach ($leadsData as $leadData) {
        $contactsAndHours[ $leadData['id'] ] = [
            'contact' => $leadData['main_contact']['name'],
            'hours' => $leadData['cf'.HOURS_FIELD_ID],
        ];
    }

    return $contactsAndHours;
}

function setLeadHours($leadId, $hours, $cookieFileName) {
    $updateData = [
        "update" => [
            [
                "id"            => $leadId,
                "updated_at"    => time(),
                "custom_fields" => [
                    [
                        "id"     => HOURS_FIELD_ID,
                        "values" => [
                            [
                                "value" => $hours,
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

function loadApiLead($cookieFileName, $leadId) {
    $leadsUrl = "https://mailjob.amocrm.ru/api/v2/leads?id=".$leadId;
    $requestHandle = curl_init();
    curl_setopt($requestHandle, CURLOPT_COOKIEFILE, $cookieFileName);
    curl_setopt($requestHandle, CURLOPT_URL, $leadsUrl);
    curl_setopt($requestHandle, CURLOPT_RETURNTRANSFER, 1);

    $response = curl_exec($requestHandle);
    curl_close($requestHandle);

    $asArray = true;
    $parsedResponse = json_decode($response, $asArray);

    return $parsedResponse;
}


$requestType = $_GET['type'];

switch ($requestType) {
    case 'instructors':
        $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
        authAmoInterface($cookieFileName);

        $instructors = loadInstructorIds($cookieFileName);

        header("Content-type: application/json; charset=utf-8");
        echo json_encode($instructors);
    break;
    case 'getHours':
        $instructorId = $_GET['instructorId'];

        if ($instructorId) {
            $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
            authAmoInterface($cookieFileName);

            $leadsResponse = loadInstructorLeadsWithExtraData($cookieFileName, $instructorId);
            $leads = $leadsResponse['response']['items'];
            $contactsAndHours = getContactsAndHoursFromLeads($leads);

            header("Content-type: application/json; charset=utf-8");
            echo json_encode($contactsAndHours);
        }
    break;
    case 'updateHours':
        $leadId = $_GET['leadId'];
        $hours = $_GET['hours'];

        if ($leadId) {
            $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
            authAmoApi($cookieFileName);

            setLeadHours($leadId, $hours, $cookieFileName);
        }
    break;
    case 'getLead':
        $leadId = $_GET['leadId'];
        $excludeFields = [542327, 559905];

        if ($leadId) {
            $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
            authAmoApi($cookieFileName);

            $apiData = loadApiLead($cookieFileName, $leadId);
            $apiLeadData = $apiData['_embedded']['items'][0];
            $leadData = [
                "Название" => $apiLeadData['name'],
            ];

            foreach ($apiLeadData['custom_fields'] as $fieldData) {
                if (!in_array($fieldData['id'], $excludeFields)) {
                    $leadData[$fieldData['name']] = $fieldData['values'][0]['value'];
                }
            }

            header("Content-type: application/json; charset=utf-8");
            echo json_encode($leadData);
        }
    break;
}