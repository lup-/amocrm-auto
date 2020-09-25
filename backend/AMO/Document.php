<?php

namespace AMO;

use Exception;
use Google_Service;
use Google_Service_Drive_DriveFile;
use Google_Service_Drive_Permission;
use PercentedVariablesTemplateProcessor;

class Document
{
    private $dbId;
    private $googleId;
    private $userId;
    private $groupId;
    private $mimeType = "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
    private $filename;
    private $googleTemplateId;
    private $templateData;
    private $groupTemplateData;
    private $templateName;

    /**
     * @var Google_Service
     */
    private $googleService;
    private $preparedTemplate;
    private $docContent;

    private $groupFolderId;

    /**
     * @var Google_Service_Drive_DriveFile
     */
    private $templateDriveFile;
    /**
     * @var Google_Service_Drive_DriveFile
     */
    private $docDriveFile;

    private $uploadToFolderId = "1a8tASgfjbA_COgCyRZNvs5-IcCWOXkAH";
    private $allGroupsFolderId = "1joxmSh0d_47gZ0EZVPxTZR3Y8c7KUHaB";

    /**
     * @return mixed
     */
    public function getDbId() {
        return $this->dbId;
    }

    /**
     * @param mixed $dbId
     */
    public function setDbId($dbId) {
        $this->dbId = $dbId;
    }

    /**
     * @return mixed
     */
    public function getGoogleId() {
        return $this->googleId;
    }

    /**
     * @param mixed $googleId
     */
    public function setGoogleId($googleId) {
        $this->googleId = $googleId;
    }

    /**
     * @return mixed
     */
    public function getUserId() {
        return $this->userId;
    }

    /**
     * @param mixed $userId
     */
    public function setUserId($userId) {
        $this->userId = $userId;
    }

    /**
     * @return mixed
     */
    public function getGroupId() {
        return $this->groupId;
    }

    /**
     * @param mixed $groupId
     */
    public function setGroupId($groupId) {
        $this->groupId = $groupId;
    }

    /**
     * @param Google_Service $googleService
     */
    public function setGoogleService($googleService) {
        $this->googleService = $googleService;
    }

    /**
     * @return mixed
     */
    public function getGoogleTemplateId() {
        return $this->googleTemplateId;
    }

    /**
     * @param mixed $googleTemplateId
     */
    public function setGoogleTemplateId($googleTemplateId) {
        $this->googleTemplateId = $googleTemplateId;
    }

    /**
     * @return bool
     */
    public function isGroup() {
        return !empty($this->groupId);
    }

    /**
     * @param mixed $templateData
     */
    public function setTemplateData($templateData) {
        $this->templateData = $templateData;
    }

    /**
     * @param mixed $groupTemplateData
     */
    public function setGroupTemplateData($groupTemplateData) {
        $this->groupTemplateData = $groupTemplateData;
        $this->setGroupId( $groupTemplateData['name'] );
    }

    /**
     * @return mixed
     */
    public function getFilename() {
        $parts = explode('.', $this->filename);
        if (empty($parts[1])) {
            return $this->filename.'.docx';
        }

        return $this->filename;
    }

    /**
     * @param mixed $filename
     */
    public function setFilename($filename) {
        $this->filename = $filename;
    }

    /**
     * @return string
     */
    public function getMimeType() {
        return $this->mimeType;
    }

    /**
     * @param string $mimeType
     */
    public function setMimeType($mimeType) {
        $this->mimeType = $mimeType;
    }

    /**
     * @return Google_Service_Drive_DriveFile
     */
    public function getTemplateDriveFile() {
        if (!$this->templateDriveFile) {
            $this->loadDriveTemplate();
        }

        return $this->templateDriveFile;
    }

    /**
     * @return Google_Service_Drive_DriveFile
     */
    public function getDocDriveFile() {
        if (!$this->docDriveFile) {
            $this->loadDriveDoc();
        }

        return $this->docDriveFile;
    }

    public function getDownloadUrl() {
        if (!$this->getGoogleId()) {
            return false;
        }

        return "https://drive.google.com/uc?export=download&id={$this->getGoogleId()}";
    }

    public function getEditUrl() {
        if (!$this->getGoogleId()) {
            return false;
        }

        return "https://docs.google.com/document/d/{$this->getGoogleId()}/edit";
    }

    public function asArray() {
        return [
            'filename'     => $this->getFilename(),
            'templateName' => $this->getTemplateName(),
            'mime'         => $this->getMimeType(),
            'userId'       => $this->getUserId(),
            'googleId'     => $this->getGoogleId(),
            'groupId'      => $this->getGroupId(),
            'templateId'   => $this->getGoogleTemplateId(),
            'downloadUrl'  => $this->getDownloadUrl(),
            'editUrl'      => $this->getEditUrl(),
        ];
    }

    public function prepareTemplate() {
        /**
         * @var Google_Service_Drive_DriveFile $file
         */
        $file = $this->googleService->files->get($this->googleTemplateId);

        if ($file->getMimeType() === 'application/vnd.google-apps.document') {
            $response = $this->googleService->files->export(
                $this->googleTemplateId,
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ['alt' => 'media']
            );
        }
        else {
            $response = $this->googleService->files->get($this->googleTemplateId, ['alt' => 'media']);
        }

        $this->preparedTemplate = $response->getBody()->getContents();

        return $this;
    }

    public function fillTemplate($templateData) {
        if (!$templateData) {
            $templateData = $this->templateData;
        }

        $this->setTemplateData($templateData);
        $this->setMimeType( $this->getTemplateDriveFile()->getMimeType() );

        $tempFileName = tempnam(sys_get_temp_dir(), 'phpword_template_');
        file_put_contents($tempFileName, $this->preparedTemplate);

        $phpword = new PercentedVariablesTemplateProcessor($tempFileName);
        foreach ($templateData as $from => $to) {
            $phpword->setValue($from, $to);
        }

        $tempResultFileName = tempnam(sys_get_temp_dir(), 'phpword_result_');
        $phpword->saveAs($tempResultFileName);

        $this->docContent = file_get_contents($tempResultFileName);

        unlink($tempFileName);
        unlink($tempResultFileName);

        return $this;
    }

    private function makeGroupReplacementParis($groupData) {
        $replacementPairs = [
            'Группа'                   => $groupData['name'],
            'Группа.Колво'             => $groupData['people'],
            'Дата начала обучения'     => $groupData['start'],
            'Дата окончания  обучения' => $groupData['end'],
            'Дата Экзамена в Гибдд'    => $groupData['exam'],
            'Адрес сдачи'              => $groupData['exam_address'],
            'Категория'                => $groupData['category'],
        ];

        foreach ($replacementPairs as $field => $value) {
            $replacementPairs[ normalizeFieldName($field) ] = $value;
        }

        return $replacementPairs;
    }

    public function fillGroupTemplate($group, $date) {
        if (!$group) {
            $group = $this->groupTemplateData;
        }

        $this->setGroupTemplateData($group);
        $this->setMimeType( $this->getTemplateDriveFile()->getMimeType() );

        $tempFileName = tempnam(sys_get_temp_dir(), 'phpword_template_');
        file_put_contents($tempFileName, $this->preparedTemplate);

        $phpword = new PercentedVariablesTemplateProcessor($tempFileName);
        $groupPairs = $this->makeGroupReplacementParis($group);

        foreach ($groupPairs as $from => $to) {
            $phpword->setValue($from, $to);
        }

        $phpword->setValue('Дата', $date);
        $phpword->setValue('дата', $date);

        $replacePairs = [];
        foreach ($group['leads'] as $lead) {
            $leadPairs = $lead->asReplacementPairs();

            for ($i = 1; $i < 10; $i++) {
                $leadPairs["авторяд{$i}"] = '';
            }

            $replacePairs[] = $leadPairs;
        }

        $a = 1;

        usort($replacePairs, function ($pairsA, $pairsB) {
            return strcmp($pairsA["Имя"], $pairsB["Имя"]);
        });

        for ($i = 1; $i < 10; $i++) {
            try {
                $phpword->cloneRowAndSetValues("авторяд{$i}", $replacePairs);
            }
            catch (Exception $exception) {

            }
        }

        $tempResultFileName = tempnam(sys_get_temp_dir(), 'phpword_template_');
        $phpword->saveAs($tempResultFileName);

        $this->docContent = file_get_contents($tempResultFileName);

        unlink($tempFileName);
        unlink($tempResultFileName);

        return $this;
    }

    public function loadDriveTemplate() {
        if (!$this->googleTemplateId || !$this->googleService) {
            return false;
        }

        $this->templateDriveFile = $this->googleService->files->get($this->googleTemplateId);
        return $this;
    }

    public function loadDriveDoc() {
        if (!$this->getGoogleId()) {
            return false;
        }

        $this->docDriveFile = $this->googleService->files->get($this->getGoogleId());
        return $this;
    }

    public function setTemplateName($templateName) {
        $this->templateName = $templateName;
    }

    public function getTemplateName() {
        if ($this->templateName) {
            return $this->templateName;
        }

        if (!$this->googleTemplateId || !$this->googleService) {
            return false;
        }

        $filename =  $this->getTemplateDriveFile()->getName();

        if (strpos($filename, '.') === false) {
            return $filename.".docx";
        }

        return $filename;
    }

    public function generateFileName() {
        $baseName = $this->getTemplateName();
        if (!$baseName) {
            $baseName = 'Документ.docx';
        }

        $fileNameSuffix = $this->templateData
            ? $this->templateData['Контакт.Имя'].'_'.$this->templateData['Группа']
            : '';

        if ($this->isGroup()) {
            $fileNameSuffix = $this->groupId;
        }

        $this->setFilename( str_replace('.', '_'.$fileNameSuffix.'.', $baseName) );
        return $this;
    }

    private function getGroupFolderId() {
        if ($this->groupFolderId) {
            return $this->groupFolderId;
        }

        $driveQuery = "name = '{$this->getGroupId()}' and mimeType = 'application/vnd.google-apps.folder' and '{$this->allGroupsFolderId}' in parents";
        $folders = $this->googleService->files->listFiles(['q' => $driveQuery]);
        if ($folders->count() > 0) {
            $folder = $folders[0];
            $this->groupFolderId = $folder->getId();

            return $this->groupFolderId;
        }

        $file = new \Google_Service_Drive_DriveFile();
        $file->setName($this->getGroupId());
        $file->setMimeType('application/vnd.google-apps.folder');
        $file->setParents([$this->allGroupsFolderId]);

        $folder = $this->googleService->files->create($file);
        $this->groupFolderId = $folder->getId();

        return $this->groupFolderId;
    }
    
    public function uploadToGoogleDrive() {
        $file = new Google_Service_Drive_DriveFile();
        $file->setName( $this->getFilename() );
        $file->setMimeType( $this->getMimeType() );

        if ($this->isGroup()) {
            $file->setParents([$this->getGroupFolderId()]);
        }
        else {
            $file->setParents([$this->uploadToFolderId]);
        }

        $this->docDriveFile = $this->googleService->files->create($file, [
            'data' => $this->docContent,
            'uploadType' => 'multipart',
        ]);

        $this->setGoogleId( $this->docDriveFile->getId() );

        $newPermission = new Google_Service_Drive_Permission();
        $newPermission->setType('anyone');
        $newPermission->setRole('reader');
        $this->googleService->permissions->create($this->getGoogleId(), $newPermission);

        return $this;
    }

    public function sendDownload() {
        header("Content-disposition: attachment; filename=" . $this->getFilename());
        header("Content-type: " . $this->getMimeType());
        echo $this->docContent;
    }

    public static function makeFromTemplate($service, $googleTemplateId, $userId = null) {
        $doc = new Document();
        $doc->setGoogleService($service);
        $doc->setGoogleTemplateId($googleTemplateId);

        if ($userId) {
            $doc->setUserId($userId);
            $doc->setGroupId(null);
        }

        return $doc;
    }

    public static function makeFromArray($props) {
        $doc = new Document();

        if (isset($props['filename'])) {
            $doc->setFilename($props['filename']);
        }

        if (isset($props['templateName'])) {
            $doc->setTemplateName($props['templateName']);
        }

        if (isset($props['templateId'])) {
            $doc->setGoogleTemplateId($props['templateId']);
        }

        if (isset($props['googleId'])) {
            $doc->setGoogleId($props['googleId']);
        }

        if (isset($props['userId'])) {
            $doc->setUserId($props['userId']);
        }

        if (isset($props['groupId'])) {
            $doc->setGroupId($props['groupId']);
        }

        if (isset($props['mime'])) {
            $doc->setMimeType($props['mime']);
        }

        return $doc;
    }
}