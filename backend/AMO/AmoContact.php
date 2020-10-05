<?php


namespace AMO;


class AmoContact
{
    use HasCustomFields;

    protected $rawData;

    public static function createFromArray($rawData) {
        return new self($rawData);
    }

    public function __construct($rawData) {
        $this->rawData = $rawData;
    }

    public function id() {
        return $this->rawData['id'];
    }

    private function splitName() {
        list($familyName, $name, $secondName) = explode(' ', $this->name()) + ['', '', ''];
        return [
            "familyName" => $familyName,
            "firstName"  => $name,
            "secondName" => $secondName,
        ];
    }

    public function name() {
        return $this->rawData['name'];
    }

    public function firstName() {
        return $this->splitName()['firstName'];
    }

    public function secondName() {
        return $this->splitName()['secondName'];
    }

    public function familyName() {
        return $this->splitName()['familyName'];
    }

    public function phone() {
        return $this->getPhoneField(389479);
    }

    public function asArray() {
        return [
            'id'             => $this->id(),
            'name'           => $this->name(),
            'phone'          => $this->phone(),
        ];
    }
}