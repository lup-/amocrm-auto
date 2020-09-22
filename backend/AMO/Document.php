<?php

namespace AMO;

use Google_Service;
use Google_Service_Drive_DriveFile;
use Google_Service_Drive_Permission;
use PercentedVariablesTemplateProcessor;

class Document
{
    private $dbId;
    private $googleId;
    private $userId;
    private $mimeType = "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
    private $filename;
    private $googleTemplateId;
    private $templateData;
    private $templateName;

    /**
     * @var Google_Service
     */
    private $googleService;
    private $preparedTemplate;
    private $docContent;

    /**
     * @var Google_Service_Drive_DriveFile
     */
    private $templateDriveFile;
    /**
     * @var Google_Service_Drive_DriveFile
     */
    private $docDriveFile;

    private $uploadToFolderId = "1a8tASgfjbA_COgCyRZNvs5-IcCWOXkAH";

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
     * @param mixed $templateData
     */
    public function setTemplateData($templateData) {
        $this->templateData = $templateData;
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
            'templateId'   => $this->getGoogleTemplateId(),
            'downloadUrl'  => $this->getDownloadUrl(),
            'editUrl'      => $this->getEditUrl(),
        ];
    }

    public function prepareTemplate() {
        $response = $this->googleService->files->get($this->googleTemplateId, ['alt' => 'media']);
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

        return $this->getTemplateDriveFile()->getName();
    }

    public function generateFileName() {
        $baseName = $this->getTemplateName();
        if (!$baseName) {
            $baseName = 'Документ.docx';
        }

        $fileNameSuffix = $this->templateData
            ? $this->templateData['Контакт.Имя'].'_'.$this->templateData['Группа']
            : '';

        $this->setFilename( str_replace('.', '_'.$fileNameSuffix.'.', $baseName) );
        return $this;
    }

    public function uploadToGoogleDrive() {
        $file = new Google_Service_Drive_DriveFile();
        $file->setName( $this->getFilename() );
        $file->setMimeType( $this->getMimeType() );
        $file->setParents([$this->uploadToFolderId]);

        $this->docDriveFile = $this->googleService->files->create($file, array(
            'data' => $this->docContent,
            'uploadType' => 'multipart'
        ));

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
        $doc->setUserId($userId);

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

        if (isset($props['mime'])) {
            $doc->setMimeType($props['mime']);
        }

        return $doc;
    }
}