<?php
const INSTRUCTOR_FIELD_ID = "398075";
const HOURS_FIELD_ID = "552963";
const NEEDED_HOURS_FIELD_ID = "414085";
const GROUP_FIELD_ID = "580073";

const HOUR_PRICE = 275;

function initAmoApi() {
    $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
    authAmoApi($cookieFileName);

    return $cookieFileName;
}
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

    curl_exec($requestHandle);

    curl_close($requestHandle);
}

function loadApiLeadsPage($cookieFileName, $page) {
    $limit = 500;
    $limitOffset = ($page-1) * $limit;

    $leadsUrl = "https://mailjob.amocrm.ru/api/v2/leads?filter[active]=1&limit_rows={$limit}&limit_offset={$limitOffset}";
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

function loadApiLeads($cookieFileName) {
    $page_1 = loadApiLeadsPage($cookieFileName, 1);
    $page_2 = loadApiLeadsPage($cookieFileName, 2);
    $page_1['_embedded']['items'] = array_merge($page_1['_embedded']['items'], $page_2['_embedded']['items']);

    return $page_1;
}

function joinLeads($targetLeads, $allLeads) {
    $resultLeads = [];

    foreach ($targetLeads as $targetLead) {
        $extraLeadData = current(array_filter($allLeads, function ($extraLead) use ($targetLead) {
            return $extraLead['id'] === $targetLead['id'];
        }));

        $targetLead['_extra'] = $extraLeadData;
        $resultLeads[] = $targetLead;
    }

    return $resultLeads;
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

function loadCustomFieldsDataFromSettings($cookieFileName) {
    $fieldsUrl = "https://mailjob.amocrm.ru/ajax/settings/custom_fields/";
    $requestHandle = curl_init();
    curl_setopt($requestHandle, CURLOPT_COOKIEFILE, $cookieFileName);
    curl_setopt($requestHandle, CURLOPT_POST, 1);
    curl_setopt($requestHandle, CURLOPT_URL, $fieldsUrl);
    curl_setopt($requestHandle, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($requestHandle, CURLOPT_HTTPHEADER, [
        "X-Requested-With: XMLHttpRequest",
    ]);

    $response = curl_exec($requestHandle);
    curl_close($requestHandle);

    $asArray = true;
    $parsedResponse = json_decode($response, $asArray);

    return $parsedResponse['response']['params']['fields'];
}

function getCustomFieldDescription($allFieldsData, $searchFieldId) {
    foreach ($allFieldsData as $segmentCode => $segmentFields) {
        $foundFields = array_filter( $segmentFields, function ($field) use ($searchFieldId) {
            return $field['id'] == $searchFieldId;
        });

        if (count($foundFields) > 0) {
            return current($foundFields);
        }
    }

    return false;
}

function loadInstructorIdsFromFieldEnum($cookieFileName) {
    $fieldsData = loadCustomFieldsDataFromSettings($cookieFileName);
    $instructorFieldData = getCustomFieldDescription($fieldsData, INSTRUCTOR_FIELD_ID);

    if (!$instructorFieldData) {
        return false;
    }

    $instructors = [];

    foreach ($instructorFieldData['enums'] as $enumEntry) {
        $instructors[ $enumEntry['id'] ] = $enumEntry['value'];
    }

    return $instructors;
}

function loadLeadsWithExtraDataPage($cookieFileName, $leadsFilter, $page = 1) {
    $leadsUrl = "https://mailjob.amocrm.ru/ajax/leads/list/pipeline/";
    $leadsFilter["page"] = $page;

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

function loadLeadsWithExtraDataFilter($cookieFileName, $filter) {
    $result = loadLeadsWithExtraDataPage($cookieFileName, $filter);
    $totalPages = $result['response']['pagination']['total'];

    for ($page = 2; $page <= $totalPages; $page++) {
        $pageResult = loadLeadsWithExtraDataPage($cookieFileName, $filter, $page);
        $result['response']['items'] = array_merge($result['response']['items'], $pageResult['response']['items']);
    }

    return $result;
}

function loadAllLeadsWithExtraData($cookieFileName) {
    $filter = [
        "skip_filter" => "y",
        "json"        => 1,
    ];

    return loadLeadsWithExtraDataFilter($cookieFileName, $filter);
}

function loadCompletedLeadsWithExtraData($cookieFileName) {
    $filter = [
        "filter[pipe][1191751][]" => "142",
        "filter[tags_logic]"      => "or",
        "useFilter"               => "y",
        "sel"                     => "complited",
        "json"                    => 1,
    ];

    return loadLeadsWithExtraDataFilter($cookieFileName, $filter);
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

function loadGroups($cookieFileName) {
    $apiResponse = loadApiLeads($cookieFileName);
    $leads = $apiResponse['_embedded']['items'];
    $groups = getGroupsInfo($leads);

    return $groups;
}

function getCustomFieldValue($fieldId, $leadData) {
    if (isset($leadData['cf' . $fieldId])) {
        return $leadData['cf' . $fieldId];
    }

    foreach ($leadData['custom_fields'] as $fieldData) {
        if ($fieldData['id'] == $fieldId) {
            $fieldValue = $fieldData['values'][0]['value'];

            if ($fieldValue === false || $fieldValue === "false") {
                $fieldValue = "нет";
            }

            return $fieldValue;
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

    $schoolLead = AMO\AutoSchoolLead::createFromArray($apiLeadData);

    $leadData = [
        "ФИО"             => $contactData['name'],
        "Категория"       => getCustomFieldValue(405003, $apiLeadData),
        "Группа"          => $schoolLead->group() ? $schoolLead->group() : "",
        "Коробка"         => getCustomFieldValue(389859, $apiLeadData),
        "Откат по часам"  => getCustomFieldValue(552963, $apiLeadData),
        "Стоимость"       => $schoolLead->studyPrice() ? $schoolLead->studyPrice() : 'не указана',
        "Остаток"         => $schoolLead->paymentDetails(),
        "Медкомиссия"   => [
            "Серия, номер, лицензия" => getCustomFieldValue(413345, $apiLeadData),
            "Кем выдано"             => getCustomFieldValue(413347, $apiLeadData),
            "Когда выдано"           => getCustomFieldValue(542317, $apiLeadData),
        ],
        "Сертификат" => [
            "Серия, номер" => getCustomFieldValue(413337, $apiLeadData),
            "Кем выдано"   => getCustomFieldValue(413343, $apiLeadData),
            "Когда выдано" => getCustomFieldValue(542325, $apiLeadData),
        ],
        "Экзамен в ГИБДД" => getCustomFieldValue(540659, $apiLeadData),
    ];

    return $leadData;
}

function normalizeFieldName($fieldName) {
    $fieldName = preg_replace('#\W+#ui', '_', $fieldName);
    $fieldName = mb_strtolower($fieldName);
    $fieldName = trim($fieldName);
    return $fieldName;
}

function formatFullRussianDate($parsedDate) {
    $enDate = $parsedDate->format('d F Y');
    $monthNames = [
        'January' => 'Января',
        'February' => 'Февраля',
        'March' => 'Марта',
        'April' => 'Апреля',
        'May' => 'Мая',
        'June' => 'Июня',
        'July' => 'Июля',
        'August' => 'Августа',
        'September' => 'Сентября',
        'October' => 'Окрября',
        'November' => 'Ноября',
        'December' => 'Декабря',
    ];

    $ruDate = strtr($enDate, $monthNames);
    return $ruDate;
}

function makeReplacementPairs($apiLeadData, $contactData) {
    list($familyName, $name, $secondName) = explode(' ', $contactData['name']) + ['', '', ''];

    $replacementPairs = [
        'Сделка.ID'               => $apiLeadData['id'],
        'Имя'                     => $contactData['name'],
        'Имя.Фамилия'             => $familyName,
        'Имя.Имя'                 => $name,
        'Имя.Отчество'            => $secondName,
        'Телефон'                 => '',
        'Телефон.Рабочий'         => '',
        'Контакт.Имя'             => $contactData['name'],
        'Контакт.Имя.Фамилия'     => $familyName,
        'Контакт.Имя.Имя'         => $name,
        'Контакт.Имя.Отчество'    => $secondName,
        'Контакт.Телефон'         => '',
        'Контакт.Телефон.Рабочий' => '',
        'Сделка.Бюджет'           => $apiLeadData['sale'],
        'Сделка.Бюджет.Прописью'  => is_numeric($apiLeadData['sale']) ? numberToText($apiLeadData['sale']) : '',
        'Сделка.Ответственный'    => '',
    ];

    foreach ($apiLeadData['custom_fields'] as $field) {
        $name = $field['name'];
        $value = $field['values'][0]['value'];
        $replacementPairs[ $name ] = $value;

        if (is_numeric($value)) {
            $replacementPairs[ $name.'.Прописью' ] = numberToText($value);
        }
    }

    if ($contactData['custom_fields']) {
        foreach ($contactData['custom_fields'] as $field) {
            $replacementPairs[$field['name']] = $field['values'][0]['value'];
            $replacementPairs['Контакт.'.$field['name']] = $field['values'][0]['value'];

            if ($field['name'] == 'Телефон') {
                $replacementPairs['Контакт.Телефон'] = $field['values'][0] ? $field['values'][0]['value'] : '';
                $replacementPairs['Контакт.Телефон.Рабочий'] = $field['values'][1] ? $field['values'][1]['value'] : '';
            }
        }
    }

    $dateTimeFields = [
        'Дата заключения договора',
        'Медкомиссия, когда выдано',
        'Дата распределения инструктора',
        'Дата начала обучения',
        'Дата окончания обучения',
        'Дата окончания  обучения',
        'Дата выдачи свидетельства',
        'Дата Экзамена в Гибдд',
        'День рождения',
        'Контакт.День рождения',
        'Дата выдачи паспорта',
        'Контакт.Дата выдачи паспорта'
    ];

    foreach ($dateTimeFields as $fieldName) {
        try {
            $dateAsString = $replacementPairs[$fieldName];

            $parsedDate = DateTime::createFromFormat('Y-m-d H:i:s', $dateAsString);

            if (!$parsedDate) {
                $parsedDate = DateTime::createFromFormat('d.m.Y', $dateAsString);
            }

            if ($parsedDate) {
                $replacementPairs[$fieldName] = $parsedDate->format('d.m.Y');
                $replacementPairs[$fieldName . '.Полный'] = formatFullRussianDate($parsedDate);
            }
        }
        catch (Exception $e) {
        }
    }

    foreach ($replacementPairs as $field => $value) {
        $replacementPairs[ normalizeFieldName($field) ] = $value;
    }

    return $replacementPairs;
}

function makeGroupReplacementParis($groupData) {
    $replacementPairs = [
        'Группа'                   => $groupData['name'],
        'Группа.Колво'             => $groupData['people'],
        'Дата начала обучения'     => $groupData['start'],
        'Дата окончания  обучения' => $groupData['end'],
        'Дата Экзамена в Гибдд'    => $groupData['exam'],
        'Адрес сдачи'              => $groupData['exam_address'],
        'Категория'                => $groupData['category'],
    ];

    foreach ($replacementPairs as $field => $value) {
        $replacementPairs[ normalizeFieldName($field) ] = $value;
    }

    return $replacementPairs;
}

function loadLeadReplacementPairs($cookieFileName, $leadId) {
    $apiData = loadApiLead($cookieFileName, $leadId);
    $apiLeadData = $apiData['_embedded']['items'][0];

    $contactId = $apiLeadData['contacts']['id'][0];
    $apiData = loadApiContact($cookieFileName, $contactId);
    $contactData = $apiData['_embedded']['items'][0];

    return makeReplacementPairs($apiLeadData, $contactData);
}

function getContactsDataScheduleFromLeadsAndEvents($leadsData, $eventsData) {
    $contactsAndHours = [];
    foreach ($leadsData as $leadData) {
        $schoolLead = get_class($leadData) === AMO\AutoSchoolLead::class
            ? $leadData
            : AMO\AutoSchoolLead::createFromArray($leadData);

        $name = $schoolLead->name();
        /**
         * @var $foundEvent Google_Service_Calendar_Event
         */
        $foundEvent = false;
        foreach ($eventsData as $event) {
            if ($name && $event->summary === $name) {
                $foundEvent = $event;
            }
        }

        $contactsAndHours[ $leadData['id'] ] = $schoolLead->asStudentArray($foundEvent);
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
function getVideoLinks() {
    return [
        [ "id" => 1, "youtubeUrl" => "https://www.youtube.com/watch?v=GIkYf6oeIiM", "title" => "Урок 1 Общее положение" ],
        [ "id" => 2, "youtubeUrl" => "https://www.youtube.com/watch?v=tlC6A25bSfE", "title" => "Урок 2 Дорожные знаки. Предупреждающие знаки" ],
        [ "id" => 3, "youtubeUrl" => "https://www.youtube.com/watch?v=eppaeDdg8C0", "title" => "Урок 3 Знаки приоритета" ],
        [ "id" => 4, "youtubeUrl" => "https://www.youtube.com/watch?v=BtZlsr_WHRE", "title" => "Урок 4 Запрещающие знаки" ],
        [ "id" => 5, "youtubeUrl" => "https://www.youtube.com/watch?v=81_yaF5lY04", "title" => "Урок 5 Предписывающие знаки" ],
        [ "id" => 6, "youtubeUrl" => "https://www.youtube.com/watch?v=b5I4LvO6dn8", "title" => "Урок 6 Знаки особых предписаний" ],
        [ "id" => 7, "youtubeUrl" => "https://www.youtube.com/watch?v=ADykreDw4oQ", "title" => "Урок 7 Информационные знаки" ],
        [ "id" => 8, "youtubeUrl" => "https://www.youtube.com/watch?v=VqH7W3TvkSQ", "title" => "Урок 8 Знаки сервиса" ],
        [ "id" => 9, "youtubeUrl" => "https://www.youtube.com/watch?v=dSoP-12yoV4", "title" => "Урок 9 Знаки дополнительной информации(таблички)" ],
        [ "id" => 10, "youtubeUrl" => "https://www.youtube.com/watch?v=tEVviEICArE", "title" => "Урок 10 Дорожная разметка" ],
        [ "id" => 11, "youtubeUrl" => "https://www.youtube.com/watch?v=jWZ1cC_tulQ", "title" => "Урок 11 Обязанности участников дорожного движения" ],
        [ "id" => 12, "youtubeUrl" => "https://www.youtube.com/watch?v=dW3Qla-6q9g", "title" => "Урок 12 Начало движения, маневрирование" ],
        [ "id" => 13, "youtubeUrl" => "https://www.youtube.com/watch?v=QLiLDZdN8Q8", "title" => "Урок 13 Расположение транспортных средств на проезжей части" ],
        [ "id" => 14, "youtubeUrl" => "https://www.youtube.com/watch?v=hVs7qjukXGE", "title" => "Урок 14 Регулирование дорожного движения" ],
        [ "id" => 15, "youtubeUrl" => "https://www.youtube.com/watch?v=mFO9qI7NS1Y", "title" => "Урок 15 Проезд перекрёстков" ],
        [ "id" => 16, "youtubeUrl" => "https://www.youtube.com/watch?v=33MManf3pwQ", "title" => "Урок 16 Применение специальных сигналов" ],
        [ "id" => 17, "youtubeUrl" => "https://www.youtube.com/watch?v=4oYHO0tjdIs", "title" => "Урок 17 Проезд пешеходных переходов и мест остановок маршрутных транспортных средств" ],
        [ "id" => 18, "youtubeUrl" => "https://www.youtube.com/watch?v=y-CoTWg32ao", "title" => "Урок 18 Остановка и стоянка транспортных средств" ],
        [ "id" => 19, "youtubeUrl" => "https://www.youtube.com/watch?v=TmnumRArhvI", "title" => "Урок 19 Скорость движения" ],
        [ "id" => 20, "youtubeUrl" => "https://www.youtube.com/watch?v=FeeO1zDrxq4", "title" => "Урок 20 Обгон, опережение, встречный разъезд" ],
        [ "id" => 21, "youtubeUrl" => "https://www.youtube.com/watch?v=aUdUvyA-BJc", "title" => "Урок 21 Порядок использования внешних световых приборов и звуковых сигналов" ],
        [ "id" => 22, "youtubeUrl" => "https://www.youtube.com/watch?v=0eDdzNQlJI4", "title" => "Урок 22 Перевозка людей и грузов" ],
        [ "id" => 23, "youtubeUrl" => "https://www.youtube.com/watch?v=P70UNAaGW_s", "title" => "Урок 23 Движение по автомагистралям" ],
        [ "id" => 24, "youtubeUrl" => "https://www.youtube.com/watch?v=wqj_Imvr6og", "title" => "Урок 24 Проезд железнодорожных переездов" ],
        [ "id" => 25, "youtubeUrl" => "https://www.youtube.com/watch?v=laF192HaYZ0", "title" => "Урок 25 Применение аварийной сигнализации" ],
        [ "id" => 26, "youtubeUrl" => "https://www.youtube.com/watch?v=DI0LIxOcNMk", "title" => "Урок 26 Движение в жилых зонах" ],
        [ "id" => 27, "youtubeUrl" => "https://www.youtube.com/watch?v=mySnnVoThiA", "title" => "Урок 27 Учебная езда" ],
        [ "id" => 28, "youtubeUrl" => "https://www.youtube.com/watch?v=RJUROwb6xCA", "title" => "Урок 28 Буксировка транспортных средств" ],
        [ "id" => 29, "youtubeUrl" => "https://www.youtube.com/watch?v=WmKjIbXAUt0", "title" => "Урок 29 Ситуация на автомобильных дорогах. Дорожно-транспортные происшествия" ],
        [ "id" => 30, "youtubeUrl" => "https://www.youtube.com/watch?v=u0fSvFNdtJA", "title" => "Урок 30 Динамический габарит. Управление автомобилем в транспортном потоке" ],
        [ "id" => 31, "youtubeUrl" => "https://www.youtube.com/watch?v=yHglqDfszf0", "title" => "Урок 31 Обгон. Управление автомобилем в темное время суток" ],
        [ "id" => 32, "youtubeUrl" => "https://www.youtube.com/watch?v=D2lfzXn3uCg", "title" => "Урок 32 Движение зимой. Движение в дождь и туман" ],
        [ "id" => 33, "youtubeUrl" => "https://www.youtube.com/watch?v=Wp3CSacDlcQ", "title" => "Урок 33 Движение по горным дорогам. Управление автомобилем в опасных критических ситуациях" ],
        [ "id" => 34, "youtubeUrl" => "https://www.youtube.com/watch?v=TlxKsTHN3Xg", "title" => "Урок 34 Общее устройство и работа двигателя" ],
        [ "id" => 35, "youtubeUrl" => "https://www.youtube.com/watch?v=nLYzy0GQbOQ", "title" => "Урок 35 Кузов автомобиля, рабочее место водителя, системы пассивной безопасности" ],
        [ "id" => 36, "youtubeUrl" => "https://www.youtube.com/watch?v=iu1gfnvWOkg", "title" => "Урок 36 Общее устройство и принцип работы тормозных систем" ],
        [ "id" => 37, "youtubeUrl" => "https://www.youtube.com/watch?v=xcu8DW6sqPc", "title" => "Урок 37 Общее устройство и принцип работы системы рулевого управления" ],
        [ "id" => 38, "youtubeUrl" => "https://www.youtube.com/watch?v=8rgkReQYdm4", "title" => "Урок 38 Общее устройство трансмиссии" ],
        [ "id" => 39, "youtubeUrl" => "https://www.youtube.com/watch?v=9f_IsDFOB0o", "title" => "Урок 39 Источники и потребители электрической энергии" ],
        [ "id" => 40, "youtubeUrl" => "https://www.youtube.com/watch?v=LUxs2TrIrjI", "title" => "Урок 40 Назначение и состав ходовой части" ],
        [ "id" => 41, "youtubeUrl" => "https://www.youtube.com/watch?v=GRAyLj_MH-s", "title" => "Урок 41 Электронные системы помощи водителю" ],
        [ "id" => 42, "youtubeUrl" => "https://www.youtube.com/watch?v=OejDqNZCoLU", "title" => "Урок 42 Общее устройство прицепов и тягово-сцепных устройств" ],
        [ "id" => 43, "youtubeUrl" => "https://www.youtube.com/watch?v=M6K0NuuJWQc", "title" => "Урок 43 Требования к оборудованию и техническому состоянию транспортных средств" ],
        [ "id" => 44, "youtubeUrl" => "https://www.youtube.com/watch?v=OwZcmAZysPU", "title" => "Урок 44 Законодательство в сфере взаимодействия общества и природы" ],
        [ "id" => 45, "youtubeUrl" => "https://www.youtube.com/watch?v=kTSUiDd-soM", "title" => "Урок 45 Законодательство, устанавливающее ответственность за нарушения в сфере дорожного движения" ],
        [ "id" => 46, "youtubeUrl" => "https://www.youtube.com/watch?v=_Rqb1svq3pU", "title" => "Урок 46 Нормативные правовые акты, определяющие порядок перевозки грузов автомобильным транспортом" ],
        [ "id" => 47, "youtubeUrl" => "https://www.youtube.com/watch?v=KOcUindOWM8", "title" => "Урок 47 Основные показатели работы грузовых автомобилей" ],
        [ "id" => 48, "youtubeUrl" => "https://www.youtube.com/watch?v=1faFa-yJt8k", "title" => "Урок 48 Организация грузовых перевозок" ],
        [ "id" => 49, "youtubeUrl" => "https://www.youtube.com/watch?v=NuYWSRNbTJU", "title" => "Урок 49 Диспетчерское руководство работой подвижного состава" ],
        [ "id" => 50, "youtubeUrl" => "https://www.youtube.com/watch?v=R6DmbOsSINs", "title" => "Урок 50 Нормативно правовое обеспечение пассажирских перевозок автомобильным транспортом" ],
        [ "id" => 51, "youtubeUrl" => "https://www.youtube.com/watch?v=bI2_h2wEOos", "title" => "Урок 51 Технико эксплуатационные показатели пассажирского автотранспорта" ],
        [ "id" => 52, "youtubeUrl" => "https://www.youtube.com/watch?v=aucvztuwIXo", "title" => "Урок 52 Диспетчерское руководство работой такси на линии" ],
        [ "id" => 53, "youtubeUrl" => "https://www.youtube.com/watch?v=wOK7D-93Sfs", "title" => "Урок 53 Работа такси на линии" ],
        [ "id" => 54, "youtubeUrl" => "https://www.youtube.com/watch?v=oCqe35bWK08", "title" => "Урок 54 Дорожное движение" ],
        [ "id" => 55, "youtubeUrl" => "https://www.youtube.com/watch?v=_EBmHnQj9fQ", "title" => "Урок 55 Профессиональная надежность водителя" ],
        [ "id" => 56, "youtubeUrl" => "https://www.youtube.com/watch?v=SYSdjsJdjOw", "title" => "Урок 56 Влияние свойств транспортного средства на эффективность и безопасность управления" ],
        [ "id" => 57, "youtubeUrl" => "https://www.youtube.com/watch?v=Jrpg0bz_c48", "title" => "Урок 57 Дорожные условия и безопасность движения" ],
        [ "id" => 58, "youtubeUrl" => "https://www.youtube.com/watch?v=GLjKEX6y_Fk", "title" => "Урок 58 Управление транспортным средством в нештатных ситуациях" ],
        [ "id" => 59, "youtubeUrl" => "https://www.youtube.com/watch?v=hIjjZkzp8_c", "title" => "Урок 59 Управление транспортным средством в штатных ситуациях" ],
        [ "id" => 60, "youtubeUrl" => "https://www.youtube.com/watch?v=12fL09hkNGo", "title" => "Урок 60 Обеспечение безопасности наиболее уязвимых участников дорожного движения" ],
        [ "id" => 61, "youtubeUrl" => "https://www.youtube.com/watch?v=xbxk2T7sUVE", "title" => "Урок 61 Приемы управления транспортным средством" ],
        [ "id" => 62, "youtubeUrl" => "https://www.youtube.com/watch?v=5HuZmPvjW8E", "title" => "Урок 62 Принципы эффективного и безопасного управления транспортным средством" ],
    ];
}

function getGibddTickets() {
    return [
        "1"  => [
            "Билет №1 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/1_1-5_2018.pdf",
            "Билет №1 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/1_6-10_2018.pdf",
            "Билет №1 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/1_11-15_2018.pdf",
            "Билет №1 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/1_16-20_2018.pdf",
            "Комментарии к билету"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/1-komment-abm-2018.pdf",
        ],
        "2"  => [
            "Билет №2 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/2_1-5_2018.pdf",
            "Билет №2 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/2_6-10_2018.pdf",
            "Билет №2 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/2_11-15_2018.pdf",
            "Билет №2 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/2_16-20_2018.pdf",
            "Комментарии к билету"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/2-komment-abm-2018.pdf",
        ],
        "3"  => [
            "Билет №3 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/3_1-5_2018.pdf",
            "Билет №3 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/3_6-10_2018.pdf",
            "Билет №3 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/3_11-15_2018.pdf",
            "Билет №3 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/3_16-20_2018.pdf",
            "Комментарии к билету"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/3-komment-abm-2018.pdf",
        ],
        "4"  => [
            "Билет №4 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/4_1-5_2018.pdf",
            "Билет №4 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/4_6-10_2018.pdf",
            "Билет №4 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/4_11-15_2018.pdf",
            "Билет №4 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/4_16-20_2018.pdf",
            "Комментарии к билету"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/4-komment-abm-2018.pdf",
        ],
        "5"  => [
            "Билет №5 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/5_1-5_2018.pdf",
            "Билет №5 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/5_6-10_2018.pdf",
            "Билет №5 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/5_11-15_2018.pdf",
            "Билет №5 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/5_16-20_2018.pdf",
            "Комментарии к билету"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/5-komment-abm-2018.pdf",
        ],
        "6"  => [
            "Билет №6 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/6_1-5_2018.pdf",
            "Билет №6 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/6_6-10_2018.pdf",
            "Билет №6 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/6_11-15_2018.pdf",
            "Билет №6 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/6_16-20_2018.pdf",
            "Комментарии к билету"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/6-komment-abm-2018.pdf",
        ],
        "7"  => [
            "Билет №7 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/7_1-5_2018.pdf",
            "Билет №7 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/7_6-10_2018.pdf",
            "Билет №7 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/7_11-15_2018.pdf",
            "Билет №7 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/7_16-20_2018.pdf",
            "Комментарии к билету"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/7-komment-abm-2018.pdf",
        ],
        "8"  => [
            "Билет №8 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/8_1-5_2018.pdf",
            "Билет №8 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/8_6-10_2018.pdf",
            "Билет №8 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/8_11-15_2018.pdf",
            "Билет №8 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/8_16-20_2018.pdf",
            "Комментарии к билету"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/8-komment-abm-2018.pdf",
        ],
        "9"  => [
            "Билет №9 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/9_1-5_2018.pdf",
            "Билет №9 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/9_6-10_2018.pdf",
            "Билет №9 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/9_11-15_2018.pdf",
            "Билет №9 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/9_16-20_2018.pdf",
            "Комментарии к билету"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/9-komment-abm-2018.pdf",
        ],
        "10" => [
            "Билет №10 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/10_1-5_2018.pdf",
            "Билет №10 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/10_6-10_2018.pdf",
            "Билет №10 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/10_11-15_2018.pdf",
            "Билет №10 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/10_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/10-komment-abm-2018.pdf",
        ],
        "11" => [
            "Билет №11 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/11_1-5_2018.pdf",
            "Билет №11 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/11_6-10_2018.pdf",
            "Билет №11 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/11_11-15_2018.pdf",
            "Билет №11 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/11_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/11-komment-abm-2018.pdf",
        ],
        "12" => [
            "Билет №12 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/12_1-5_2018.pdf",
            "Билет №12 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/12_6-10_2018.pdf",
            "Билет №12 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/12_11-15_2018.pdf",
            "Билет №12 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/12_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/12-komment-abm-2018.pdf",
        ],
        "13" => [
            "Билет №13 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/13_1-5_2018.pdf",
            "Билет №13 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/13_6-10_2018.pdf",
            "Билет №13 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/13_11-15_2018.pdf",
            "Билет №13 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/13_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/13-komment-abm-2018.pdf",
        ],
        "14" => [
            "Билет №14 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/14_1-5_2018.pdf",
            "Билет №14 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/14_6-10_2018.pdf",
            "Билет №14 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/14_11-15_2018.pdf",
            "Билет №14 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/14_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/14-komment-abm-2018.pdf",
        ],
        "15" => [
            "Билет №15 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/15_1-5_2018.pdf",
            "Билет №15 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/15_6-10_2018.pdf",
            "Билет №15 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/15_11-15_2018.pdf",
            "Билет №15 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/15_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/15-komment-abm-2018.pdf",
        ],
        "16" => [
            "Билет №16 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/16_1-5_2018.pdf",
            "Билет №16 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/16_6-10_2018.pdf",
            "Билет №16 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/16_11-15_2018.pdf",
            "Билет №16 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/16_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/16-komment-abm-2018.pdf",
        ],
        "17" => [
            "Билет №17 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/17_1-5_2018.pdf",
            "Билет №17 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/17_6-10_2018.pdf",
            "Билет №17 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/17_11-15_2018.pdf",
            "Билет №17 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/17_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/17-komment-abm-2018.pdf",
        ],
        "18" => [
            "Билет №18 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/18_1-5_2018.pdf",
            "Билет №18 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/18_6-10_2018.pdf",
            "Билет №18 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/18_11-15_2018.pdf",
            "Билет №18 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/18_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/18-komment-abm-2018.pdf",
        ],
        "19" => [
            "Билет №19 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/19_1-5_2018.pdf",
            "Билет №19 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/19_6-10_2018.pdf",
            "Билет №19 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/19_11-15_2018.pdf",
            "Билет №19 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/19_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/19-komment-abm-2018.pdf",
        ],
        "20" => [
            "Билет №20 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/20_1-5_2018.pdf",
            "Билет №20 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/20_6-10_2018.pdf",
            "Билет №20 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/20_11-15_2018.pdf",
            "Билет №20 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/20_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/20-komment-abm-2018.pdf",
        ],
        "21" => [
            "Билет №21 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/21_1-5_2018.pdf",
            "Билет №21 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/21_6-10_2018.pdf",
            "Билет №21 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/21_11-15_2018.pdf",
            "Билет №21 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/21_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/21-komment-abm-2018.pdf",
        ],
        "22" => [
            "Билет №22 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/22_1-5_2018.pdf",
            "Билет №22 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/22_6-10_2018.pdf",
            "Билет №22 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/22_11-15_2018.pdf",
            "Билет №22 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/22_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/22-komment-abm-2018.pdf",
        ],
        "23" => [
            "Билет №23 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/23_1-5_2018.pdf",
            "Билет №23 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/23_6-10_2018.pdf",
            "Билет №23 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/23_11-15_2018.pdf",
            "Билет №23 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/23_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/23-komment-abm-2018.pdf",
        ],
        "24" => [
            "Билет №24 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/24_1-5_2018.pdf",
            "Билет №24 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/24_6-10_2018.pdf",
            "Билет №24 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/24_11-15_2018.pdf",
            "Билет №24 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/24_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/24-komment-abm-2018.pdf",
        ],
        "25" => [
            "Билет №25 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/25_1-5_2018.pdf",
            "Билет №25 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/25_6-10_2018.pdf",
            "Билет №25 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/25_11-15_2018.pdf",
            "Билет №25 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/25_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/25-komment-abm-2018.pdf",
        ],
        "26" => [
            "Билет №26 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/26_1-5_2018.pdf",
            "Билет №26 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/26_6-10_2018.pdf",
            "Билет №26 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/26_11-15_2018.pdf",
            "Билет №26 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/26_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/26-komment-abm-2018.pdf",
        ],
        "27" => [
            "Билет №27 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/27_1-5_2018.pdf",
            "Билет №27 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/27_6-10_2018.pdf",
            "Билет №27 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/27_11-15_2018.pdf",
            "Билет №27 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/27_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/27-komment-abm-2018.pdf",
        ],
        "28" => [
            "Билет №28 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/28_1-5_2018.pdf",
            "Билет №28 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/28_6-10_2018.pdf",
            "Билет №28 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/28_11-15_2018.pdf",
            "Билет №28 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/28_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/28-komment-abm-2018.pdf",
        ],
        "29" => [
            "Билет №29 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/29_1-5_2018.pdf",
            "Билет №29 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/29_6-10_2018.pdf",
            "Билет №29 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/29_11-15_2018.pdf",
            "Билет №29 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/29_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/29-komment-abm-2018.pdf",
        ],
        "30" => [
            "Билет №30 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/30_1-5_2018.pdf",
            "Билет №30 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/30_6-10_2018.pdf",
            "Билет №30 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/30_11-15_2018.pdf",
            "Билет №30 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/30_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/30-komment-abm-2018.pdf",
        ],
        "31" => [
            "Билет №31 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/31_1-5_2018.pdf",
            "Билет №31 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/31_6-10_2018.pdf",
            "Билет №31 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/31_11-15_2018.pdf",
            "Билет №31 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/31_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/31-komment-abm-2018.pdf",
        ],
        "32" => [
            "Билет №32 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/32_1-5_2018.pdf",
            "Билет №32 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/32_6-10_2018.pdf",
            "Билет №32 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/32_11-15_2018.pdf",
            "Билет №32 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/32_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/32-komment-abm-2018.pdf",
        ],
        "33" => [
            "Билет №33 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/33_1-5_2018.pdf",
            "Билет №33 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/33_6-10_2018.pdf",
            "Билет №33 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/33_11-15_2018.pdf",
            "Билет №33 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/33_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/33-komment-abm-2018.pdf",
        ],
        "34" => [
            "Билет №34 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/34_1-5_2018.pdf",
            "Билет №34 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/34_6-10_2018.pdf",
            "Билет №34 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/34_11-15_2018.pdf",
            "Билет №34 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/34_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/34-komment-abm-2018.pdf",
        ],
        "35" => [
            "Билет №35 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/35_1-5_2018.pdf",
            "Билет №35 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/35_6-10_2018.pdf",
            "Билет №35 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/35_11-15_2018.pdf",
            "Билет №35 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/35_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/35-komment-abm-2018.pdf",
        ],
        "36" => [
            "Билет №36 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/36_1-5_2018.pdf",
            "Билет №36 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/36_6-10_2018.pdf",
            "Билет №36 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/36_11-15_2018.pdf",
            "Билет №36 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/36_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/36-komment-abm-2018.pdf",
        ],
        "37" => [
            "Билет №37 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/37_1-5_2018.pdf",
            "Билет №37 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/37_6-10_2018.pdf",
            "Билет №37 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/37_11-15_2018.pdf",
            "Билет №37 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/37_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/37-komment-abm-2018.pdf",
        ],
        "38" => [
            "Билет №38 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/38_1-5_2018.pdf",
            "Билет №38 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/38_6-10_2018.pdf",
            "Билет №38 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/38_11-15_2018.pdf",
            "Билет №38 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/38_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/38-komment-abm-2018.pdf",
        ],
        "39" => [
            "Билет №39 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/39_1-5_2018.pdf",
            "Билет №39 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/39_6-10_2018.pdf",
            "Билет №39 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/39_11-15_2018.pdf",
            "Билет №39 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/39_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/39-komment-abm-2018.pdf",
        ],
        "40" => [
            "Билет №40 вопросы 1-5"   => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/40_1-5_2018.pdf",
            "Билет №40 вопросы 6-10"  => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/40_6-10_2018.pdf",
            "Билет №40 вопросы 11-15" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/40_11-15_2018.pdf",
            "Билет №40 вопросы 16-20" => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/40_16-20_2018.pdf",
            "Комментарии к билету"    => "http://xn--90adear.xn--p1ai/upload/site1000/folder/original/avtovladeltsam/fees/abm/40-komment-abm-2018.pdf",
        ],
    ];
}

function timestampToFormat($format, $timestamp) {
    $date = new DateTime();
    $date->setTimestamp( intval($timestamp) );
    $date->setTimezone(new DateTimeZone('Europe/Moscow'));
    return $date->format($format);
}

function getGroupsInfo($leads) {
    $groups = [];

    foreach ($leads as $leadData) {
        $groupName = getCustomFieldValue(GROUP_FIELD_ID, $leadData);
        $isCorrectGroupName = $groupName !== 'false';
        $isGroupAdded = isset($groups[$groupName]);

        if ($groupName && $isCorrectGroupName && !$isGroupAdded) {
            $groups[$groupName] = [
                "name"       => $groupName,
                "start"      => getCustomFieldValue(541467, $leadData) ? timestampToFormat('d.m.Y', getCustomFieldValue(541467, $leadData)) : false,
                "end"        => getCustomFieldValue(541469, $leadData) ? timestampToFormat('d.m.Y', getCustomFieldValue(541469, $leadData)) : false,
                "exam"       => getCustomFieldValue(540659, $leadData) ? timestampToFormat('d.m.Y', getCustomFieldValue(540659, $leadData)) : false,
                "exam_address" => getCustomFieldValue(540873, $leadData) ? getCustomFieldValue(540873, $leadData) : false,
                "category"   => getCustomFieldValue(405003, $leadData) ? getCustomFieldValue(405003, $leadData) : false,
                "people"     => 0,
                "totalHours" => 0,
                "salary"     => 0,
                "leads"      => [],
            ];
        }

        if ($groupName && $isCorrectGroupName) {
            $groups[$groupName]['people'] += 1;
            try {
                $hours = intval(getCustomFieldValue(HOURS_FIELD_ID, $leadData));
            }
            catch (Exception $e) {
                $hours = 0;
            }

            $groups[$groupName]['totalHours'] += $hours;
            $groups[$groupName]['salary'] += $hours * HOUR_PRICE;
            $groups[$groupName]['leads'][] = $leadData;
        }
    }

    return $groups;
}

function getStudents($leads) {
    $students = [];

    $studentData = getContactsDataScheduleFromLeadsAndEvents($leads, []);

    foreach ($leads as $leadData) {
        $groupName = getCustomFieldValue(GROUP_FIELD_ID, $leadData);

        if ($groupName) {
            $student = array_merge(
                $studentData[ $leadData['id'] ],
                [
                    'id' => $leadData['id'],
                    'name' => $leadData['main_contact']['name'],
                ]
            );

            $students[$groupName][$leadData['id']] = $student;
        }
    }

    foreach ($students as $groupName => $groupStudents) {
        $students[$groupName] = array_values($groupStudents);
    }

    return $students;
}

function declension($number, $types) {
    list($one, $four, $five) = $types;
    $lastTwoDigits = $number % 100;
    $lastDigit = $number % 10;

    if ($lastTwoDigits >= 11 && $lastTwoDigits <= 19) {
        return $five;
    }
    else {
        if ($lastDigit === 1) {
            return $one;
        }

        if ($lastDigit > 1 && $lastDigit <= 4) {
            return $four;
        }
    }

    return $five;
}
function numberToText($number) {
    $triplets = [
        1 => ['тысяча', 'тысячи', 'тысяч'],
        2 => ['миллион', 'миллиона', 'миллионов'],
    ];

    $digitNames = [
        0 => [
            1 => 'сто',
            2 => 'двести',
            3 => 'триста',
            4 => 'четыреста',
            5 => 'пятьсот',
            6 => 'шестьсот',
            7 => 'семьсот',
            8 => 'восемьсот',
            9 => 'девятьсот',
        ],
        1 => [
            1 => 'десять',
            2 => 'двадцать',
            3 => 'тридцать',
            4 => 'сорок',
            5 => 'пятьдесят',
            6 => 'шестьдесят',
            7 => 'семьдесят',
            8 => 'восемьдесят',
            9 => 'девяносто',
        ],
        2 => [
            1 => ['один', 'одна'],
            2 => ['два', 'две'],
            3 => 'три',
            4 => 'четыре',
            5 => 'пять',
            6 => 'шесть',
            7 => 'семь',
            8 => 'восемь',
            9 => 'девять',
        ],
    ];
    $tenOnes = [
        '10' => 'десять',
        '11' => 'одинадцать',
        '12' => 'двенадцать',
        '13' => 'тринадцать',
        '14' => 'четырнадцать',
        '15' => 'пятнадцать',
        '16' => 'шестнадцать',
        '17' => 'семнадцать',
        '18' => 'восемнадцать',
        '19' => 'девятнадцать',
    ];

    $padLength = ceil( strlen($number)/3 ) * 3;
    $paddedToFullTriplet = str_pad($number, $padLength, "0", STR_PAD_LEFT);
    $splitByTriplets = str_split($paddedToFullTriplet, 3);

    $text = "";
    foreach ( array_reverse($splitByTriplets) as $tripletIndex => $tripletDigits ) {
        $suffix = "";
        $isThousand = $tripletIndex === 1;
        $firstDigit = floor($tripletDigits / 100);
        $lastTwoDigits = $tripletDigits % 100;
        $lastTwoIsTenOnes = $lastTwoDigits >= 10 && $lastTwoDigits <= 19;
        if ($lastTwoIsTenOnes) {
            $splitDigits = [$firstDigit, $lastTwoDigits];
        }
        else {
            $splitDigits = str_split($tripletDigits, 1);
        }

        if ($tripletIndex > 0) {
            $suffix = declension($tripletDigits, $triplets[$tripletIndex]);
        }

        $thousandText = "";
        foreach ( $splitDigits as $digitIndex => $digit ) {
            $digitText = "";
            if ($lastTwoIsTenOnes && $digitIndex === 1) {
                $digitText = $tenOnes[$digit];
            }
            else {
                if ($digit > 0) {
                    $digitText = $digitNames[$digitIndex][$digit];
                    if (is_array($digitText)) {
                        $digitText = $isThousand ? $digitText[1] : $digitText[0];
                    }
                }
            }

            $thousandText .= $digitText ? $digitText." " : "";
        }

        $thousandText .= $suffix ? $suffix." " : "";
        $text = $thousandText.$text;
    }

    $text = trim($text);

    return $text != "" ? $text : 'ноль';
}
