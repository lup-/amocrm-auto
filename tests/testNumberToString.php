<?php
require_once "../public_html/amo_functions.php";

use PHPUnit\Framework\TestCase;

class TestNumberToString extends TestCase
{
    public function testDeclension() {
        $this->assertEquals("тысяч",  declension(0,   ['тысяча', 'тысячи', 'тысяч']));
        $this->assertEquals("тысячи", declension(2,   ['тысяча', 'тысячи', 'тысяч']));
        $this->assertEquals("тысячи", declension(4,   ['тысяча', 'тысячи', 'тысяч']));
        $this->assertEquals("тысяч",  declension(5,   ['тысяча', 'тысячи', 'тысяч']));
        $this->assertEquals("тысяч",  declension(12,  ['тысяча', 'тысячи', 'тысяч']));
        $this->assertEquals("тысяч",  declension(138, ['тысяча', 'тысячи', 'тысяч']));
    }

    public function testNumbers() {
        $testCases = [
            "0"        => "ноль",
            "1"        => "один",
            "14"       => "четырнадцать",
            "179"      => "сто семьдесят девять",
            "100"      => "сто",
            "101"      => "сто один",
            "110"      => "сто десять",
            "2361"     => "две тысячи триста шестьдесят один",
            "10742"    => "десять тысяч семьсот сорок два",
            "21139"    => "двадцать одна тысяча сто тридцать девять",
            "22139"    => "двадцать две тысячи сто тридцать девять",
            "90159"    => "девяносто тысяч сто пятьдесят девять",
            "137567"   => "сто тридцать семь тысяч пятьсот шестьдесят семь",
            "1890001"  => "один миллион восемьсот девяносто тысяч один",
            "3001002"  => "три миллиона одна тысяча два",
            "12500333" => "двенадцать миллионов пятьсот тысяч триста тридцать три",
        ];

        foreach ($testCases as $test => $expectedResult) {
            $result = numberToText($test);
            $this->assertEquals($expectedResult, $result);
        }
    }
}