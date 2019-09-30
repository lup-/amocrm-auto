<?php
function getCalendarList() {
    return [
        "788903"  => "dbhe2bkg2e4c5p9tgqjk9qdlps@group.calendar.google.com", //Мельников Александр Геннадьевич
        "920525"  => "423eu1ams26911lcje1tv7c994@group.calendar.google.com", //Кокорин Дмитрий Владимирович
        "920527"  => "jc9dusa5n43hsqv6kn8shu8fjc@group.calendar.google.com", //Шматко Дмитрий Николаевич
        "920531"  => "vovpks0oodl64pscluhc613hek@group.calendar.google.com", //Кузнецов Иван Николаевич
        "920533"  => "juot3gdq89rber7q55kd7m34lo@group.calendar.google.com", //Кузнецов Геннадий Григорьевич
        "920537"  => "thph1brp7lh38881f16t8u16jk@group.calendar.google.com", //Коняхин Алексей Петрович
        "920539"  => "6bt34i3qg228jj3prro1iokres@group.calendar.google.com", //Прокопов Антон Викторович
        "920563"  => "idtrsqrnk20l233doidl8p83d8@group.calendar.google.com", //Юдин Александр Николаевич
        "1068181" => "59oc9t2attulefbm1tncf620e0@group.calendar.google.com", //Беликов Вячеслав Дмитриевич
        "1074817" => "kqofepg4leucaco7ma4514h574@group.calendar.google.com", //Монахов Алексей Валерьевич
        "1074819" => "cb915lf43oahv74m4iabi2k7qo@group.calendar.google.com", //Кисилев Сергей Сергеевич
        "1090033" => "d3ca8ghfr0avddfmrtjn0b2bmk@group.calendar.google.com", //Мельников Владимир Юрьевич
        "1098473" => "2qkr3ue25lu8vh1c35i8t1plg8@group.calendar.google.com", //Мельников Дмитрий Геннадьевич
        "920541"  => "ovlnarn2t1v7q6b7fpln0s0jak@group.calendar.google.com", //ПРИКАЗ
        "1113415" => "8q4lvlg4k9ul8igulkbj4g5hbc@group.calendar.google.com", //Панкратов Леонид Александрович
        "1113417" => "0j3cure22pujltndrqce02gnc0@group.calendar.google.com", //Мельников Геннадий Валентинович
    ];
}
function getInstructorCalendarId($instructorId) {
    $list = getCalendarList();
    return $list[$instructorId];
}
function getTimeRangeEvents($service, $calendarId, $startTimestamp, $endTimestamp) {
    $optParams = [
        'maxResults'   => 10,
        'orderBy'      => 'startTime',
        'singleEvents' => true,
        'timeMin'      => date('c', $startTimestamp),
        'timeMax'      => date('c', $endTimestamp),
    ];
    $results = $service->events->listEvents($calendarId, $optParams);
    $events = $results->getItems();
    return empty($events)
        ? false
        : $events;
}
function getEvents($service, $calendarId, $timestamp) {
    $startOfDay = $timestamp;
    $dayLengthSeconds = 86400;
    $endOfDay = $startOfDay + $dayLengthSeconds;
    return getTimeRangeEvents($service, $calendarId, $startOfDay, $endOfDay);
}
/**
 * @param $service
 * @param $calendarId
 * @param $timestamp
 * @return Google_Service_Calendar_Event[] | bool
 */
function getAllEvents($service, $calendarId, $timestamp) {
    $startOfDay = $timestamp;
    $optParams = [
        'maxResults'   => 10,
        'orderBy'      => 'startTime',
        'singleEvents' => true,
        'timeMin'      => date('c', $startOfDay),
    ];
    $results = $service->events->listEvents($calendarId, $optParams);
    $events = $results->getItems();
    return empty($events)
        ? false
        : $events;
}
function getTimeframes($service, $calendarId, $timestamp) {
    $timeframes = [
        "06:00" => [],
        "07:30" => [],
        "09:00" => [],
        "10:30" => [],
        "12:00" => [],
        "13:30" => [],
        "15:00" => [],
        "16:30" => [],
        "18:00" => [],
        "19:30" => [],
        "21:00" => [],
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
function getFullCalendarEvent($service, $calendarId, $startTimestamp, $endTimestamp = false) {
    if ($endTimestamp) {
        $events = getTimeRangeEvents($service, $calendarId, $startTimestamp, $endTimestamp);
    }
    else {
        $events = getAllEvents($service, $calendarId, $startTimestamp);
    }
    $fullCalendarEvents = [];
    foreach ($events as $event) {
        $start = new DateTime($event->start->dateTime);
        $end = new DateTime($event->end->dateTime);
        $fullCalendarEvents[] = [
            'id'    => $event->getId(),
            'title' => $event->getSummary(),
            'url'   => $event->getHtmlLink(),
            'start' => $start->format('Y-m-d H:i:s'),
            'end'   => $end->format('Y-m-d H:i:s'),
        ];
    }
    return $fullCalendarEvents;
}
function addEvent($service, $calendarId, $studentName, $date, $startTimeInput) {
    $startTime = DateTime::createFromFormat('Y-m-d H:i:s', $date." ".$startTimeInput, new DateTimeZone('Europe/Moscow'));
    if (!$startTime) {
        $startTime = DateTime::createFromFormat('Y-m-d H:i', $date." ".$startTimeInput, new DateTimeZone('Europe/Moscow'));
    }
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
function updateEvent($service, $calendarId, $eventId, $newStart, $newEnd) {
    /**
     * @var $event Google_Service_Calendar_Event
     */
    $event = $service->events->get($calendarId, $eventId);
    $startTime = DateTime::createFromFormat('Y-m-d H:i:s', $newStart, new DateTimeZone('Europe/Moscow'));
    $endTime = DateTime::createFromFormat('Y-m-d H:i:s', $newEnd, new DateTimeZone('Europe/Moscow'));
    $startTimeEvent = new Google_Service_Calendar_EventDateTime([
        'dateTime' => $startTime->format(DateTime::ISO8601),
        'timeZone' => 'Europe/Moscow',
    ]);
    $endTimeEvent = new Google_Service_Calendar_EventDateTime([
        'dateTime' => $endTime->format(DateTime::ISO8601),
        'timeZone' => 'Europe/Moscow',
    ]);
    $event->setStart($startTimeEvent);
    $event->setEnd($endTimeEvent);
    $updatedEvent = $service->events->update($calendarId, $eventId, $event);
    return $updatedEvent ? $updatedEvent : false;
}