<?php
require __DIR__ . '/vendor/autoload.php';
require('public_html/amo_functions.php');
require('public_html/calendar_functions.php');
require('public_html/google_functions.php');

function saveConfig($filename, $config) {
    file_put_contents($filename, "<?php\nreturn " . var_export($config, true) . ";");
}

$cookieFileName = tempnam(sys_get_temp_dir(), "AMO");
authAmoInterface($cookieFileName);
$client = getClient('token.json', 'credentials.json');
$service = new Google_Service_Calendar($client);

$instructors = loadInstructorIdsFromFieldEnum($cookieFileName);
$calendarConfig = include('public_html/calendar_config.php');

$allInstructorIds = array_keys($instructors);
$instructorsWithCalendars = array_keys($calendarConfig);

$instructorsToAddCalendar = array_diff( $allInstructorIds, $instructorsWithCalendars );
$instructorsToRemoveCalendar = array_diff( $instructorsWithCalendars, $allInstructorIds );

foreach ($instructorsToAddCalendar as $instructorId) {
    $instructorName = $instructors[$instructorId];
    $calendarId = addCalendar($service, $instructorName);
    $calendarConfig[ $instructorId ] = $calendarId;
    echo "[Добавил] {$instructorId} {$instructorName} => {$calendarId}\n";
}

foreach ($instructorsToRemoveCalendar as $instructorId) {
    $calendarId = $calendarConfig[ $instructorId ];
    removeCalendar($service, $calendarId);
    unset($calendarConfig[$instructorId]);
    echo "[Удалил] {$instructorId} => {$calendarId}\n";
}

saveConfig('public_html/calendar_config.php', $calendarConfig);
echo "[Сохранил конфиг]\n";
