<?php


namespace AMO;


class Group
{
    protected $lead;

    public static function createFromLead($lead) {
        return new self($lead);
    }

    public function __construct($lead) {
        $this->lead = $lead;
    }

    public function name() {
        return $this->lead->getCustomFieldValue(580073);
    }

    public function startDate() {
        return $this->lead->getDateValue(541467);
    }

    public function endDate() {
        return $this->lead->getDateValue(541469);
    }

    public function examDate() {
        return $this->lead->getDateValue(540659);
    }

    public function examAddress() {
        return $this->lead->getCustomFieldValue(540873);
    }

    public function category() {
        return $this->lead->getCustomFieldValue(405003);
    }

    public function asArray() {
        return [
            "name"         => $this->name(),
            "start"        => $this->startDate(),
            "end"          => $this->endDate(),
            "exam"         => $this->examDate(),
            "exam_address" => $this->examAddress(),
            "category"     => $this->category(),
        ];
    }
}