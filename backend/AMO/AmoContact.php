<?php


namespace AMO;


class AmoContact
{
    protected $rawData;

    public static function createFromArray($rawData) {
        return new self($rawData);
    }

    public function __construct($rawData) {
        $this->rawData = $rawData;
    }

    public function findCustomField($fieldId) {
        foreach ($this->rawData['custom_fields'] as $fieldData) {
            if ($fieldData['id'] == $fieldId) {
                return $fieldData;
            }
        }

        if ($this->rawData['_extra']) {
            foreach ($this->rawData['_extra']['custom_fields'] as $fieldData) {
                if ($fieldData['id'] == $fieldId) {
                    return $fieldData;
                }
            }
        }

        return null;
    }
    public function getCustomFieldValue($fieldId) {
        if (isset($this->rawData['cf' . $fieldId])) {
            return $this->rawData['cf' . $fieldId];
        }

        $customField = $this->findCustomField($fieldId);
        if (!$customField) {
            return null;
        }

        $fieldValue = $customField['values'][0]['value'];

        if ($fieldValue === "false") {
            $fieldValue = false;
        }

        return $fieldValue;
    }

    public function getPhoneField($fieldId) {
        $phone = $this->getCustomFieldValue($fieldId);

        if ( is_array($phone) ) {
            $phone = $phone[0];
        }

        if ( empty($phone) ) {
            return false;
        }

        $phone = preg_replace('#\W#', '', $phone);
        if ($phone[0] === '8') {
            $phone[0] = '7';
        }

        if ($phone[0] !== '7') {
            $phone = '7'.$phone;
        }

        return '+'.$phone;
    }

    public function id() {
        return $this->rawData['id'];
    }

    public function name() {
        return $this->rawData['name'];
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