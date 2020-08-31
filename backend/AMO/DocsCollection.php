<?php


namespace AMO;


class DocsCollection
{
    private $docsArray;
    private $docsModels;

    public function __construct(array $docsArray = []) {
        $this->docsArray = $docsArray;
        $this->docsModels = array_map(function ($docArray) {
            return Document::makeFromArray($docArray);
        }, $docsArray);
    }

    public function getDocsForUser($userId) {
        $docsOfUser = array_filter($this->docsModels, function ($docModel) use ($userId) {
            return $docModel->getUserId() == $userId;
        });

        $docsAsArray = array_map(function ($docModel) {
            return $docModel->asArray();
        }, $docsOfUser);

        return array_values($docsAsArray);
    }

    public static function from(array $docsArray) {
        return new self($docsArray);
    }
}