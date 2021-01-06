<?php

use AMO\AmoApi;
use AMO\Database;
use AMO\LeadsCollection;

require __DIR__ . '/../vendor/autoload.php';
require_once 'google_functions.php';
require_once 'calendar_functions.php';
require_once 'amo_functions.php';

$requestType = $_GET['type'];

function saveConfig($filename, $config) {
    file_put_contents($filename, "<?php\nreturn " . var_export($config, true) . ";");
}

switch ($requestType) {
    case 'instructors':
        $instructors = AmoApi::getInstance()->getInstructorIds();

        header("Content-type: application/json; charset=utf-8");
        echo json_encode($instructors);
    break;
    case 'getAdminData':
        $activeLeads = Database::getInstance()->loadActiveLeads();
        $completeLeads = Database::getInstance()->loadCompleteLeads();

        $docs = Database::getInstance()->loadAllDocs();
        $activeLeads->setDocs($docs);
        $completeLeads->setDocs($docs);

        header("Content-type: application/json; charset=utf-8");
        echo json_encode([
            "instructors"    => $activeLeads->getInstructors(),
            "groups"         => $activeLeads->getGroups(),
            "completeGroups" => $completeLeads->getGroups(),
        ]);
    break;
    case 'syncInstructors':
        $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
        authAmoInterface($cookieFileName);
        $client = getClient('../token.json', '../credentials.json');
        $service = new Google_Service_Calendar($client);

        $instructors = loadInstructorIdsFromFieldEnum($cookieFileName);
        $calendarConfig = include('calendar_config.php');

        $allInstructorIds = array_keys($instructors);
        $instructorsWithCalendars = array_keys($calendarConfig);

        $instructorsToAddCalendar = array_diff( $allInstructorIds, $instructorsWithCalendars );
        $instructorsToRemoveCalendar = array_diff( $instructorsWithCalendars, $allInstructorIds );

        $result = [
            "changed" => false,
            "added" => [],
            "removed" => [],
        ];

        foreach ($instructorsToAddCalendar as $instructorId) {
            $instructorName = $instructors[$instructorId];
            $calendarId = addCalendar($service, $instructorName);
            $calendarConfig[ $instructorId ] = $calendarId;

            $result['changed'] = true;
            $result['added'][] = [
                'id' => $instructorId,
                'name' => $instructorName,
                'calendarId' => $calendarId,
            ];
        }

        foreach ($instructorsToRemoveCalendar as $instructorId) {
            $instructorName = $instructors[$instructorId];
            $calendarId = $calendarConfig[ $instructorId ];
            removeCalendar($service, $calendarId);
            unset($calendarConfig[$instructorId]);

            $result['changed'] = true;
            $result['removed'][] = [
                'id' => $instructorId,
                'name' => $instructorName,
                'calendarId' => $calendarId,
            ];
        }

        saveConfig('calendar_config.php', $calendarConfig);
        header("Content-type: application/json; charset=utf-8");
        echo json_encode($result);
    break;
    case 'getAllInstructorsData':
        $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
        authAmoInterface($cookieFileName);

        $instructors = loadInstructorIds($cookieFileName);
        $instructorsData = [];
        foreach ($instructors as $id => $name) {
            $leadsResponse = loadInstructorLeadsWithExtraData($cookieFileName, $id);
            $leads = $leadsResponse['response']['items'];
            $instructorsData[] = [
                'id' => $id,
                'name' => $name,
                'groups' => getGroupsInfo($leads),
                'students' => getStudents($leads),
            ];
        }

        header("Content-type: application/json; charset=utf-8");
        echo json_encode([
            "instructors" => $instructorsData,
        ]);
    break;
    case 'getHours':
        $instructorId = $_GET['instructorId'];

        if ($instructorId) {
            $instructors = AmoApi::getInstance()->getInstructorIds();
            $instructor = $instructors[$instructorId];
            $leads = Database::getInstance()->loadActiveInstructorLeads($instructor);

            $client = getClient('../token.json');
            $service = new Google_Service_Calendar($client);
            $calendarId = getInstructorCalendarId($instructorId);
            $timestamp = (new DateTime())->getTimestamp();
            $events = getAllEvents($service, $calendarId, $timestamp);

            $leads->setEvents($events);
            $contactsAndHours = $leads->asStudentArrays();
            $groups = $leads->getGroups(true);

            header("Content-type: application/json; charset=utf-8");
            echo json_encode([
                "instructor" => $instructor,
                "leads"      => $contactsAndHours,
                "groups"     => $groups,
            ]);
        }
    break;
    case 'getPhone':
        $contactId = $_GET['contactId'];
        $contact = AmoApi::getInstance()->getSingleContact($contactId);

        header("Content-type: application/json; charset=utf-8");
        echo json_encode($contact->asArray());
    break;
    case 'updateHours':
        $leadId = $_GET['leadId'];
        $hours = $_GET['hours'];

        if ($leadId) {
            $lead = AmoApi::getInstance()->setLeadHours($leadId, $hours);
            Database::getInstance()->updateLead($lead);
        }
    break;
    case 'addNote':
        $leadId = $_GET['leadId'];
        $text = $_GET['text'];

        if ($leadId) {
            $notes = AmoApi::getInstance()->addLeadNote($leadId, $text);
            header("Content-type: application/json; charset=utf-8");
            echo json_encode($notes);
        }
    break;
    case 'getLead':
        $leadId = $_GET['leadId'];

        if ($leadId) {
            $lead = AmoApi::getInstance()->getSingleLead($leadId, true);
            $leadData = $lead->asUserArray();

            header("Content-type: application/json; charset=utf-8");
            echo json_encode($leadData);
        }
    break;
    case 'test':
        //$activeLeads = AmoApi::getInstance()->getActiveLeads(null, true);
        $instructors = AmoApi::getInstance()->getInstructorIds();

        header("Content-type: application/json; charset=utf-8");
        echo json_encode([
            "groups"         => '',//$activeLeads->getGroups(),
        ]);
    break;
    case 'getVideo':
        header("Content-type: application/json; charset=utf-8");
        echo json_encode(["video" => getVideoLinks()]);
    break;
    case 'getTickets':
        header("Content-type: application/json; charset=utf-8");
        echo json_encode(getGibddTickets());
    break;
}