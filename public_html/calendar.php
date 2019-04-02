<?php
require __DIR__ . '/../vendor/autoload.php';

const EVENT_DESCRIPTION = 'Занятие с учеником в автошколе';

/**
 * Возвращает авторизованный клиент API Google
 * @return Google_Client
 */
function getClient() {
    $client = new Google_Client();
    $client->setApplicationName('Интерфейс инструктора автошколы ВОА');
    $client->setScopes([Google_Service_Calendar::CALENDAR_EVENTS]);
    $client->setAuthConfig('../credentials.json');
    $client->setAccessType('offline');
    $client->setPrompt('select_account consent');

    $tokenPath = '../token.json';
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
function getCalendarList() {
    return [
        "788903"  => "tsys.pw_b9q9bu04kctk4978glu3r2dcjk@group.calendar.google.com", //Мельников Александр Геннадьевич
        "920525"  => "tsys.pw_hctjppuit2ciqbfpp316v2475g@group.calendar.google.com", //Кокорин Дмитрий Владимирович
        "920527"  => "tsys.pw_igmcgoeh5gjfgujlghmm84ccdk@group.calendar.google.com", //Шматко Дмитрий Николаевич
        "920531"  => "tsys.pw_lhcov9s26rqfbb1gm9hdndhb10@group.calendar.google.com", //Кузнецов Иван Николаевич
        "920533"  => "tsys.pw_tb6830h5j0g79llokn2v8a077c@group.calendar.google.com", //Кузнецов Геннадий Григорьевич
        "920537"  => "tsys.pw_864e4t0hsascind6dlecrs4qn8@group.calendar.google.com", //Коняхин Алексей Петрович
        "920539"  => "tsys.pw_2tvaaojpsj2t5qp0nbmhekkto8@group.calendar.google.com", //Прокопов Антон Викторович
        "920563"  => "tsys.pw_1iifrauqcvsicq2shp63rs60ts@group.calendar.google.com", //Юдин Александр Николаевич
        "1068181" => "tsys.pw_knim62g1s4tgfr1erg8o4boq4c@group.calendar.google.com", //Беликов Вячеслав Дмитриевич
        "1074817" => "tsys.pw_kb113aeq94acu573fru6dkaao4@group.calendar.google.com", //Монахов Алексей Валерьевич
        "1074819" => "tsys.pw_pdnbkb0cav09c9ark89js7dof0@group.calendar.google.com", //Кисилев Сергей Сергеевич
        "1090033" => "tsys.pw_e3reeunbge53hpbognl6lsrlac@group.calendar.google.com", //Мельников Владимир Юрьевич
        "1098473" => "tsys.pw_7usm0ckuuqj8uamaclh3cbkm74@group.calendar.google.com", //Мельников Дмитрий Геннадьевич
    ];
}
function getInstructorCalendarId($instructorId) {
    $list = getCalendarList();
    return $list[$instructorId];
}
function getEvents($service, $calendarId, $timestamp) {
    $startOfDay = $timestamp;
    $dayLengthSeconds = 86400;
    $endOfDay = $startOfDay + $dayLengthSeconds;

    $optParams = [
        'maxResults'   => 10,
        'orderBy'      => 'startTime',
        'singleEvents' => true,
        'timeMin'      => date('c', $startOfDay),
        'timeMax'      => date('c', $endOfDay),
    ];
    $results = $service->events->listEvents($calendarId, $optParams);
    $events = $results->getItems();

    return empty($events)
        ? false
        : $events;
}
function getTimeframes($service, $calendarId, $timestamp) {
    $timeframes = [
        "09:00" => [],
        "10:30" => [],
        "12:00" => [],
        "13:30" => [],
        "14:00" => [],
        "15:30" => [],
        "17:00" => [],
        "18:30" => [],
        "20:00" => [],
    ];
    $events = getEvents($service, $calendarId, $timestamp);

    if (!$events) {
        return $timeframes;
    }

    foreach ($events as $event) {
        $start = new DateTime($event->start->dateTime);
        $end = new DateTime($event->end->dateTime);
        $timeframe = $start->format('H:i');

        $timeframes[$timeframe][] = [
            'start' => $start->format('H:i'),
            'end' => $end->format('H:i'),
            'text' => $event->getSummary(),
        ];
    }

    return $timeframes;
}

function addEvent($service, $calendarId, $studentName, $date, $startTime) {
    $startTime = DateTime::createFromFormat('Y-m-d H:i:s', $date." ".$startTime, new DateTimeZone('Europe/Moscow'));
    $oneAndHalfHourSpec = 'PT1H30M0S';
    $endTime = clone $startTime;
    $endTime->add(new DateInterval($oneAndHalfHourSpec));

    $event = new Google_Service_Calendar_Event([
        'summary'     => $studentName,
        'description' => EVENT_DESCRIPTION,
        'start'       => [
            'dateTime' => $startTime->format(DateTime::ISO8601),
            'timeZone' => 'Europe/Moscow',
        ],
        'end'         => [
            'dateTime' => $endTime->format(DateTime::ISO8601),
            'timeZone' => 'Europe/Moscow',
        ]
    ]);

    $event = $service->events->insert($calendarId, $event);
    return ($event->htmlLink)
        ? $event->htmlLink
        : false;
}

$client = getClient();
$service = new Google_Service_Calendar($client);

$response = [];
$calendarId = getInstructorCalendarId($_REQUEST['instructorId']);

switch ($_REQUEST['action']) {
    case 'list':
        $timestamp = DateTime::createFromFormat('Y-m-d H:i:s', $_REQUEST['date'].' 00:00:00')->getTimestamp();
        $response = getTimeframes($service, $calendarId, $timestamp);
    break;
    case 'add':
        $studentName = $_REQUEST['studentName'];
        $date = $_REQUEST['date'];
        $startTime = $_REQUEST['time'];

        $eventLink = addEvent($service, $calendarId, $studentName, $date, $startTime);
        $response = ["success" => boolval($eventLink), "link" => $eventLink];
    break;
}

header("Content-type: application/json; charset=utf-8");
echo json_encode($response);