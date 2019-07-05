<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

class CustomStringProcessor extends TemplateProcessor {
    public static function ensureMacroCompleted($macro) {
        if (substr($macro, 0, 1) !== '%' && substr($macro, -1) !== '%') {
            $macro = '%' . $macro . '%';
        }

        return $macro;
    }
}

function listFiles($service) {
    $files = [];
    $optParams = [
        'pageSize' => 10,
        'fields' => 'nextPageToken, files(id, name)'
    ];
    $results = $service->files->listFiles($optParams);

    foreach ($results->getFiles() as $file) {
        $files[$file->getId()] = $file->getName();
    }

    return $files;
}

function downloadTemplate($templateId, $service) {
    $response = $service->files->get($templateId, ['alt' => 'media']);
    $content = $response->getBody()->getContents();

    $tempFileName = tempnam(sys_get_temp_dir(), 'gdrive_download_');
    file_put_contents($tempFileName, $content);

    return $tempFileName;
}

function replaceInDocxTemplate($filePath, $replacePairs) {
    $phpword = new CustomStringProcessor($filePath);

    foreach ($replacePairs as $from => $to) {
        $phpword->setValue($from,$to);
    }

    $tempResultFileName = tempnam(sys_get_temp_dir(), 'phpword_template_');
    $phpword->saveAs($tempResultFileName);

    return $tempResultFileName;
}