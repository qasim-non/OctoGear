<?php

namespace Tests\Unit;

use App\Support\MobileNumber;
use PHPUnit\Framework\TestCase;

class MobileNumberTest extends TestCase
{
    public function test_normalizes_local_format(): void
    {
        $this->assertSame('+966555555555', MobileNumber::normalize('0555555555'));
    }

    public function test_normalizes_national_format(): void
    {
        $this->assertSame('+966555555555', MobileNumber::normalize('555555555'));
    }

    public function test_normalizes_international_format_without_plus(): void
    {
        $this->assertSame('+966555555555', MobileNumber::normalize('966555555555'));
    }

    public function test_keeps_e164_format(): void
    {
        $this->assertSame('+966555555555', MobileNumber::normalize('+966555555555'));
    }

    public function test_strips_separators(): void
    {
        $this->assertSame('+966555555555', MobileNumber::normalize('05 5555 5555'));
        $this->assertSame('+966555555555', MobileNumber::normalize('+966-555555555'));
    }

    public function test_rejects_invalid_numbers(): void
    {
        $this->assertNull(MobileNumber::normalize('0111111111'));
        $this->assertNull(MobileNumber::normalize('12345'));
        $this->assertNull(MobileNumber::normalize('ab'));
        $this->assertNull(MobileNumber::normalize(''));
        $this->assertNull(MobileNumber::normalize(null));
    }
}