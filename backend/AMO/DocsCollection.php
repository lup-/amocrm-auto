<?php


namespace AMO;


class DocsCollection
{
    private $docsArray;

    public function __construct(array $docsArray = []) {
        $this->docsArray = $docsArray;
    }

    public function getDocsForUser($userId) {
        return array_values( array_filter($this->docsArray, function ($doc) use ($userId) {
            return $doc['userId'] == $userId;
        }) );
    }

    public static function from(array $docsArray) {
        return new self($docsArray);
    }
}