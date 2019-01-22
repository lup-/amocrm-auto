<?php
const INSTRUCTOR_FIELD_ID = "398075";
const HOURS_FIELD_ID = "552963";
const DEBT_FIELD_ID = "552815";
const NEEDED_HOURS_FIELD_ID = "414085";
const PHONE_FIELD_ID = "389479";
const MAILJOB_USER_ID = "2475916";

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

function getCustomFieldValue($fieldId, $leadData) {
    foreach ($leadData['custom_fields'] as $fieldData) {
        if ($fieldData['id'] == $fieldId) {
            $fieldValue = $fieldData['values'][0]['value'];

            if ($fieldValue === false || $fieldValue === "false") {
                $fieldValue = "нет";
            }

            return$fieldValue;
        }
    }

    return "не задано";
}

function loadLeadWithExtraDataAndFilterFields($cookieFileName, $leadId) {
    $apiData = loadApiLead($cookieFileName, $leadId);
    $apiLeadData = $apiData['_embedded']['items'][0];

    $contactId = $apiLeadData['contacts']['id'][0];
    $apiData = loadApiContact($cookieFileName, $contactId);
    $contactData = $apiData['_embedded']['items'][0];

    $leadData = [
        "ФИО"             => $contactData['name'],
        "Бюджет"          => $apiLeadData['sale'],
        "Категория"       => getCustomFieldValue(405003, $apiLeadData),
        "Группа"          => getCustomFieldValue(399063, $apiLeadData),
        "Коробка"         => getCustomFieldValue(389859, $apiLeadData),
        "Откат по часам"  => getCustomFieldValue(552963, $apiLeadData),
        "Остаток"         => getCustomFieldValue(552815, $apiLeadData),
        "Медкомиссия"     => getCustomFieldValue(413345, $apiLeadData),
        "Свидетельство"   => getCustomFieldValue(413337, $apiLeadData),
        "Экзамен в ГИБДД" => getCustomFieldValue(540659, $apiLeadData),
        "Примечание"      => getCustomFieldValue(540357, $apiLeadData),
    ];

    return $leadData;
}

function getContactsAndDataFromLeads($leadsData) {
    $contactsAndHours = [];
    foreach ($leadsData as $leadData) {
        $contactsAndHours[ $leadData['id'] ] = [
            'contact'     => $leadData['main_contact']['name'],
            'hours'       => $leadData['cf' . HOURS_FIELD_ID],
            'neededHours' => $leadData['cf' . NEEDED_HOURS_FIELD_ID],
            'debt'        => $leadData['cf' . DEBT_FIELD_ID],
            'phone'       => $leadData['cf' . PHONE_FIELD_ID],
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

/**
 * @param $leadId
 * @param $text
 * @param $cookieFileName
 * @return bool|string
 *
 * element_type
 * 1    Контакт
 * 2    Сделка
 * 3    Компания
 * 4    Задача. Для задачи доступен только тип события TASK_RESULT
 * 12   Покупатель
 *
 * note_type
 * 1    DEAL_CREATED        Сделка создана
 * 2    CONTACT_CREATED     Контакт создан
 * 3    DEAL_STATUS_CHANGED Статус сделки изменен
 * 4    COMMON              Обычное примечание
 * 12   COMPANY_CREATED     Компания создана
 * 13   TASK_RESULT         Результат по задаче
 * 25   SYSTEM              Системное сообщение
 * 102  SMS_IN              Входящее смс
 * 103  SMS_OUT             Исходящее смс
 */

function addNoteToLead($leadId, $text, $cookieFileName) {

    $addData = [
        "add" => [
            [
                "element_id"   => $leadId,
                "element_type" => 2,
                "text"         => $text,
                "note_type"    => 4,
            ],
        ]
    ];

    $notesUrl = 'https://mailjob.amocrm.ru/api/v2/notes';

    $requestHandle = curl_init();
    curl_setopt($requestHandle,CURLOPT_RETURNTRANSFER,1);
    curl_setopt($requestHandle,CURLOPT_URL, $notesUrl);
    curl_setopt($requestHandle,CURLOPT_CUSTOMREQUEST,'POST');
    curl_setopt($requestHandle,CURLOPT_POSTFIELDS, json_encode($addData));
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

function loadApiContact($cookieFileName, $contactId) {
    $contactsUrl = "https://mailjob.amocrm.ru/api/v2/contacts?id=".$contactId;
    $requestHandle = curl_init();
    curl_setopt($requestHandle, CURLOPT_COOKIEFILE, $cookieFileName);
    curl_setopt($requestHandle, CURLOPT_URL, $contactsUrl);
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
            $contactsAndHours = getContactsAndDataFromLeads($leads);

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
    case 'addNote':
        $leadId = $_GET['leadId'];
        $text = $_GET['text'];

        if ($leadId) {
            $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
            authAmoApi($cookieFileName);

            addNoteToLead($leadId, $text, $cookieFileName);
        }
    break;
    case 'getLead':
        $leadId = $_GET['leadId'];

        if ($leadId) {
            $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
            authAmoApi($cookieFileName);

            $leadData = loadLeadWithExtraDataAndFilterFields($cookieFileName, $leadId);

            header("Content-type: application/json; charset=utf-8");
            echo json_encode($leadData);
        }
    break;
}