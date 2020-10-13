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
        //$loadFromAMO = $_GET['loadFromAMO'] === '1';
        $loadFromAMO = true;

        $instructors = AmoApi::getInstance()->getInstructorIds();
        if ($loadFromAMO) {
            $contactsHash = AmoApi::getInstance()->getContactsHash();

            $activeLeads = AmoApi::getInstance()->getActiveLeads();
            $activeLeads->setContactsHash($contactsHash);
            $activeLeads->setInstructors($instructors);

            $completeLeads = AmoApi::getInstance()->getCompletedLeads();
            $completeLeads->setContactsHash($contactsHash);
            $completeLeads->setInstructors($instructors);

            //Database::getInstance()->updateLeads( $activeLeads );
            //Database::getInstance()->updateLeads( $completeLeads, true );
        }
        else {
            $activeLeads = Database::getInstance()->loadActiveLeads();
            $completeLeads = Database::getInstance()->loadCompleteLeads();
        }

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
            $cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
            authAmoInterface($cookieFileName);
            authAmoApi($cookieFileName);

            $allApiLeadsResponse = loadApiLeads($cookieFileName);
            $allApiLeads = $allApiLeadsResponse['_embedded']['items'];

            $instructors = loadInstructorIds($cookieFileName);
            $leadsResponse = loadInstructorLeadsWithExtraData($cookieFileName, $instructorId);
            $leads = joinLeads($leadsResponse['response']['items'], $allApiLeads);

            $client = getClient('../token.json');
            $service = new Google_Service_Calendar($client);
            $calendarId = getInstructorCalendarId($instructorId);
            $timestamp = (new DateTime())->getTimestamp();
            $events = getAllEvents($service, $calendarId, $timestamp);

            $contactsAndHours = getContactsDataScheduleFromLeadsAndEvents($leads, $events);
            $groups = getGroupsInfo($leads);

            header("Content-type: application/json; charset=utf-8");
            echo json_encode([
                "instructor" => $instructors[$instructorId],
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
        echo json_encode(getVideoLinks());
    break;
    case 'getTickets':
        header("Content-type: application/json; charset=utf-8");
        echo json_encode(getGibddTickets());
    break;
}