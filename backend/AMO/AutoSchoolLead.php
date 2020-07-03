<?php

namespace AMO;


class AutoSchoolLead
{
    protected $rawData;

    protected $paymentFields = [413511, 413515, 413517, 413519, 571769];
    protected $invoiceFields = [539217, 539221, 539223, 539225, 571771];

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

    public function isEverythingPayed() {
        return $this->getCustomFieldValue(583197) === '1';
    }

    public function totalDebt() {
        return intval( $this->getCustomFieldValue(552815) );
    }

    public function phone() {
        $phone = $this->getCustomFieldValue(389479);
        if ( is_array($phone) ) {
            $phone = $phone[0];
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

    public function group() {
        return $this->getCustomFieldValue(580073);
    }

    public function sidePayments() {
        return [
            "Остаток"                => $this->getCustomFieldValue(552815),
            "Вступительный взнос"    => $this->getCustomFieldValue(587231),
            "Членский взнос"         => $this->getCustomFieldValue(587233),
            "ГСМ"                    => $this->getCustomFieldValue(561445),
            "Медcправка"             => $this->getCustomFieldValue(561693),
        ];
    }

    private function getDateFromValue($valueWithDate) {
        if (!$valueWithDate) {
            return false;
        }

        preg_match('#\d{2}.\d{2}.\d{4}#', $valueWithDate, $matches);
        if ($matches[0]) {
            $ddmmYY = $matches[0];
            $parsedDate = \DateTime::createFromFormat('d.m.Y', $ddmmYY);
            return $parsedDate;
        }

        return false;
    }

    function getPaymentOverdueDays() {
        if ($this->isEverythingPayed()) {
            return 0;
        }

        if ($this->totalDebt() === 0) {
            return 0;
        }

        $sidePayments = array_values( $this->sidePayments() );
        $lastSidePaymentDate = false;
        foreach ($sidePayments as $paymentValue) {
            $paymentDate = $this->getDateFromValue($paymentValue);
            if ($paymentDate) {
                if (!$lastSidePaymentDate) {
                    $lastSidePaymentDate = $paymentDate;
                }
                else {
                    $isNewDateGreater = $paymentDate->diff($lastSidePaymentDate) > 0;
                    if ($isNewDateGreater) {
                        $lastSidePaymentDate = $paymentDate;
                    }
                }
            }
        }

        $allInvoicesPayed = true;
        $unpayedInvoiceDate = false;

        foreach ($this->paymentFields as $index => $paymentFieldId) {
            $paymentValue = $this->getCustomFieldValue($paymentFieldId);

            $invoiceFieldId = $this->invoiceFields[$index];
            $invoiceValue = $this->getCustomFieldValue($invoiceFieldId);
            $invoiceDate = $this->getDateFromValue($invoiceValue);

            if ($invoiceValue) {
                $isInvoicePayed = $paymentValue > 0;
                $allInvoicesPayed = $allInvoicesPayed && $isInvoicePayed;

                if (!$isInvoicePayed && !$unpayedInvoiceDate) {
                    $unpayedInvoiceDate = $invoiceDate;
                }
            }
        }

        $lastPaymentDate = $lastSidePaymentDate;
        if ($unpayedInvoiceDate) {
            if ($lastPaymentDate) {
                $isInvoceDateGreater = $unpayedInvoiceDate->diff($lastPaymentDate) > 0;
                if ($isInvoceDateGreater) {
                    $lastPaymentDate = $unpayedInvoiceDate;
                }
            }
            else {
                $lastPaymentDate = $unpayedInvoiceDate;
            }
        }

        if (!$lastPaymentDate) {
            return 0;
        }

        $today = new \DateTime();
        $daysFromLastPayment = $today->diff($lastPaymentDate)->days;
        return $daysFromLastPayment;
    }
}