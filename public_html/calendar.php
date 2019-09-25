<?php
require __DIR__ . '/../vendor/autoload.php';
require_once  'google_functions.php';
require_once  'calendar_functions.php';

const EVENT_DESCRIPTION = 'Занятие с учеником в автошколе';

$client = getClient('../token.json');
$service = new Google_Service_Calendar($client);

$response = [];
$calendarId = getInstructorCalendarId($_REQUEST['instructorId']);

switch ($_REQUEST['action']) {
    case 'list':
        $timestamp = DateTime::createFromFormat('Y-m-d H:i:s', $_REQUEST['date'].' 00:00:00')->getTimestamp();
        $response = getTimeframes($service, $calendarId, $timestamp);
    break;
    case 'listEvents':
        $timestamp = (new DateTime('today'))->getTimestamp();
        $response = getFullCalendarEvent($service, $calendarId, $timestamp);
    break;
    case 'add':
        $studentName = $_REQUEST['studentName'];
        $date = $_REQUEST['date'];
        $startTime = $_REQUEST['time'];

        $eventLink = addEvent($service, $calendarId, $studentName, $date, $startTime);
        $response = ["success" => boolval($eventLink), "link" => $eventLink];
    break;
    case 'update':
        $newStart = $_REQUEST['start'];
        $newEnd = $_REQUEST['end'];
        $eventId = $_REQUEST['id'];

        $updatedEvent = updateEvent($service, $calendarId, $eventId, $newStart, $newEnd);

        $response = ["success" => boolval($updatedEvent), "event" => $updatedEvent];
    break;
}

header("Content-type: application/json; charset=utf-8");
echo json_encode($response);