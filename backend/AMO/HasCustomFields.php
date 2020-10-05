<?php


namespace AMO;


trait HasCustomFields
{
    protected $parsedCustomFields = null;
    protected $cachedFields = [];

    public function findCustomField($fieldId) {
        $customFields = $this->rawData['custom_fields'];
        if (empty($customFields)) {
            $customFields = $this->rawData['custom_fields_values'];
        }
        if (empty($customFields) && $this->rawData['_extra']) {
            $customFields = $this->rawData['_extra']['custom_fields'];
        }

        if (empty($customFields)) {
            return null;
        }

        foreach ($customFields as $fieldData) {
            $iterateFieldId = $fieldData['id'];
            if (empty($iterateFieldId)) {
                $iterateFieldId = $fieldData['field_id'];
            }

            if ($iterateFieldId == $fieldId) {
                return $fieldData;
            }
        }

        return null;
    }

    public function customFields() {
        $customFields = $this->rawData['custom_fields'];
        if (empty($customFields)) {
            $customFields = $this->rawData['custom_fields_values'];
        }
        if (empty($customFields) && $this->rawData['_extra']) {
            $customFields = $this->rawData['_extra']['custom_fields'];
        }

        if (empty($customFields)) {
            return [];
        }

        return $customFields;
    }

    private function parseCustomFields() {
        $this->parsedCustomFields = [];
        foreach ($this->customFields() as $fieldData) {
            $fieldId = $fieldData['id'];
            if (empty($fieldId)) {
                $fieldId = $fieldData['field_id'];
            }

            $fieldValue = $fieldData['values'][0]['value'];
            if ($fieldValue === "false") {
                $fieldValue = false;
            }

            $this->parsedCustomFields[$fieldId] = $fieldValue;
        }
    }

    public function getCustomFieldValue($fieldId) {
        if (is_null($this->parsedCustomFields)) {
            $this->parseCustomFields();
        }

        return $this->parsedCustomFields[$fieldId];
    }

    public function getCustomFieldName($fieldId) {
        $field = $this->findCustomField($fieldId);

        if (!$field) {
            return null;
        }

        return $field['name'];
    }

    private function formatTimestamp($timestamp) {
        $date = new \DateTime();
        $date->setTimestamp($timestamp);
        $date->setTimezone(new \DateTimeZone('Europe/Moscow'));
        return $date->format($this->dateFormat);
    }
    public function getPaymentValue($fieldId) {
        if (isset($this->cachedFields['payment'][$fieldId])) {
            return $this->cachedFields['payment'][$fieldId];
        }

        $fieldValue = $this->getCustomFieldValue($fieldId);

        if (is_numeric($fieldValue)) {
            $this->cachedFields['payment'][$fieldId] = intval($fieldValue);
            return intval($fieldValue);
        }

        preg_match('#^[\d \.,]+#', $fieldValue, $matches);
        if ($matches[0]) {
            $preparedValue = preg_replace('#\W#', '', $matches[0]);

            $this->cachedFields['payment'][$fieldId] = intval($preparedValue);

            return intval($preparedValue);
        }

        $this->cachedFields['payment'][$fieldId] = false;
        return false;
    }
    public function getPaymentDate($fieldId) {
        if (isset($this->cachedFields['date'][$fieldId])) {
            return $this->cachedFields['date'][$fieldId];
        }

        $this->cachedFields['date'][$fieldId] = $this->getDateFromValue( $this->getCustomFieldValue($fieldId) );
        return $this->cachedFields['date'][$fieldId];
    }
    public function getDateValue($fieldId) {
        if (isset($this->cachedFields['date'][$fieldId])) {
            return $this->cachedFields['date'][$fieldId];
        }

        $timestamp = $this->getIntValue($fieldId);

        if (!$timestamp) {
            return false;
        }

        $this->cachedFields['date'][$fieldId] = $this->formatTimestamp($timestamp);
        return $this->cachedFields['date'][$fieldId];
    }
    public function getIntValue($fieldId) {
        if (isset($this->cachedFields['int'][$fieldId])) {
            return $this->cachedFields['int'][$fieldId];
        }

        try {
            $value = intval($this->getCustomFieldValue($fieldId));
        }
        catch (Exception $e) {
            $value = 0;
        }

         $this->cachedFields['int'][$fieldId] = $value;
        return $this->cachedFields['int'][$fieldId];
    }
    public function getPhoneField($fieldId) {
        if (isset($this->cachedFields['phone'][$fieldId])) {
            return $this->cachedFields['phone'][$fieldId];
        }

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

        $this->cachedFields['phone'][$fieldId] = '+'.$phone;
        return $this->cachedFields['phone'][$fieldId];
    }
}