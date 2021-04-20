<?php
function getCalendarList() {
    return include('calendar_config.php');
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

    if (!$events) {
        $events = [];
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

function addCalendar($service, $calendarName) {
    $calendar = new Google_Service_Calendar_Calendar();
    $calendar->setSummary($calendarName);

    $createdCalendar = $service->calendars->insert($calendar);
    return $createdCalendar->getId();
}
function removeCalendar($service, $calendarId) {
    return $service->calendars->delete($calendarId);
}