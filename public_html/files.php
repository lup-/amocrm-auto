<?php
require __DIR__ . '/../vendor/autoload.php';
require_once 'google_functions.php';
require_once 'drive_functions.php';
require_once 'amo_functions.php';

$client = getClient('../token.json');
$service = new Google_Service_Drive($client);

$response = [];

switch ($_REQUEST['action']) {
    case 'list':
        header("Content-type: application/json; charset=utf-8");
        echo json_encode($response);
    break;
    case 'makedoc':
        //$templateId = '1uDmyRhOUtjvl9194cI6antqCAE5AcpqA';
        $templateId = '1LMmz7ujo5oWrSOGV9ft205tDYZ-YNKGW';
        $leadId = '17973869';
        $cookieFileName = initAmoApi();
        $leadPairs = [
            'Сделка.ID'               => '',
            'Контакт.Имя'             => 'Павлов Александр Сергеевич',
            'Контакт.Телефон'         => '',
            'Контакт.Телефон.Рабочий' => '',
            'Сделка.Бюджет'           => '',
            'Сделка.Бюджет.Прописью'  => '',
            'Скидка'                  => '',
            'Вид документа'           => '',
            'Серия/номер паспорта'    => '',
            'Кем выдан паспорт'       => '',
            'Адрес по прописке'       => '',
            'Номер членского билета'  => '',
            'Сделка.Ответственный'    => '',
            'Дата начала обучения'    => '',
            'Дата окончания обучения' => '',
            'Инструктор'              => '',
            'Группа'                  => '',
            'Коробка'                 => '',
            'Дата'                    => '',
            'Дата.Формат.Расширенный' => '',
            'Год'                     => '',
        ];
        $leadPairs = loadLeadReplacementPairs($cookieFileName, $leadId);

        $templateFile = downloadTemplate($templateId, $service);
        $replacedFile = replaceInDocxTemplate($templateFile, $leadPairs);
        header("Content-disposition: attachment; filename=" . $replacedFile);
        header("Content-type: application/vnd.openxmlformats-officedocument.wordprocessingml.document");
        readfile($replacedFile);
    break;
}