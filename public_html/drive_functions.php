<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

class PercentedVariablesTemplateProcessor extends TemplateProcessor {
    public static function ensureMacroCompleted($macro) {
        if (substr($macro, 0, 1) !== '%' && substr($macro, -1) !== '%') {
            $macro = '%' . $macro . '%';
        }

        return $macro;
    }

    protected function indexClonedVariables($count, $xmlBlock) {
        $results = array();
        for ($i = 1; $i <= $count; $i++) {
            $results[] = preg_replace('/%(.*?)%/', '%\\1#' . $i . '%', $xmlBlock);
        }

        return $results;
    }

    public function cloneRowAndSetValues($rowSearch, $replacePairs) {
        $this->cloneRow($rowSearch, count($replacePairs));

        foreach ($replacePairs as $index => $replaceRow) {
            $rowIndex = $index+1;
            $this->setValue("пп#{$rowIndex}", $rowIndex);
            foreach ($replaceRow as $varName => $varValue) {
                $this->setValue("${varName}#{$rowIndex}", $varValue);
            }
        }
    }
}

function listFolderFiles($service, $folderId) {
    $files = [];
    $optParams = [
        'pageSize' => 100,
        'fields' => 'nextPageToken, files(id, name, fileExtension)',
        'q' => "'${folderId}' in parents and trashed = false",
    ];
    $results = $service->files->listFiles($optParams);

    foreach ($results->getFiles() as $file) {
        $fileName = $file->getName();
        list($title, $extension) = explode('.', $fileName);

        $files[] = [
            'id' => $file->getId(),
            'fileName' => $fileName,
            'title' => $title,
            'extension' => $extension,
        ];
    }

    return $files;
}

function getFilename($templateId, $service) {
    /**
     * @var Google_Service_Drive_DriveFile $file
     */
    $file = $service->files->get($templateId);
    return $file->getName();
}

function downloadTemplate($templateId, $service) {
    $response = $service->files->get($templateId, ['alt' => 'media']);
    $content = $response->getBody()->getContents();

    $tempFileName = tempnam(sys_get_temp_dir(), 'gdrive_download_');
    file_put_contents($tempFileName, $content);

    return $tempFileName;
}

function replaceInDocxTemplate($filePath, $replacePairs) {
    $phpword = new PercentedVariablesTemplateProcessor($filePath);

    foreach ($replacePairs as $from => $to) {
        $phpword->setValue($from, $to);
    }

    $tempResultFileName = tempnam(sys_get_temp_dir(), 'phpword_template_');
    $phpword->saveAs($tempResultFileName);

    return $tempResultFileName;
}

function groupReplaceInDocxTemplate($filePath, $group, $cookieFileName) {
    $phpword = new PercentedVariablesTemplateProcessor($filePath);

    $phpword->setValue('Группа', $group['name']);

    $replacePairs = [];
    foreach ($group['leads'] as $apiLeadData) {
        $contactId = $apiLeadData['contacts']['id'][0];
        $apiData = loadApiContact($cookieFileName, $contactId);
        $contactData = $apiData['_embedded']['items'][0];

        $leadPairs = makeReplacementPairs($apiLeadData, $contactData);
        for ($i = 1; $i < 10; $i++) {
            $leadPairs["авторяд{$i}"] = '';
        }

        $replacePairs[] = $leadPairs;
    }

    for ($i = 1; $i < 10; $i++) {
        try {
            $phpword->cloneRowAndSetValues("авторяд{$i}", $replacePairs);
        }
        catch (Exception $exception) {

        }
    }

    $tempResultFileName = tempnam(sys_get_temp_dir(), 'phpword_template_');
    $phpword->saveAs($tempResultFileName);

    return $tempResultFileName;
}