<?php

namespace Sitchco\Tests\Modules\TagManager;

use Sitchco\Modules\TagManager\ExtraParamsField;
use Sitchco\Tests\TestCase;

class ExtraParamsFieldTest extends TestCase
{
    public function test_validator_accepts_single_token(): void
    {
        $this->assertTrue(ExtraParamsField::validateExtraParams(true, 'tess'));
    }

    public function test_validator_accepts_csv(): void
    {
        $this->assertTrue(ExtraParamsField::validateExtraParams(true, 'tess,session_hash'));
    }

    public function test_validator_accepts_whitespace_around_tokens(): void
    {
        $this->assertTrue(ExtraParamsField::validateExtraParams(true, ' tess , session_hash '));
    }

    public function test_validator_accepts_empty_string(): void
    {
        $this->assertTrue(ExtraParamsField::validateExtraParams(true, ''));
    }

    public function test_validator_accepts_duplicate_tokens(): void
    {
        $this->assertTrue(ExtraParamsField::validateExtraParams(true, 'tess, tess'));
    }

    /**
     * @dataProvider invalidCharacterTokenProvider
     */
    public function test_validator_rejects_token_with_invalid_character(string $token): void
    {
        $result = ExtraParamsField::validateExtraParams(true, $token);
        $this->assertIsString($result);
        $this->assertStringContainsString($token, $result);
    }

    public static function invalidCharacterTokenProvider(): array
    {
        return [
            'space' => ['utm source'],
            'equals' => ['tess=hash'],
            'period' => ['tess.hash'],
        ];
    }

    public function test_validator_rejects_script_tag(): void
    {
        $result = ExtraParamsField::validateExtraParams(true, '<script>');
        $this->assertIsString($result);
        $this->assertStringContainsString('<script>', $result);
    }

    public function test_validator_rejects_when_any_token_invalid(): void
    {
        $result = ExtraParamsField::validateExtraParams(true, 'tess, bad token, session_hash');
        $this->assertIsString($result);
        $this->assertStringContainsString('bad token', $result);
    }

    public function test_validator_passes_through_prior_invalid(): void
    {
        $this->assertSame('prior error', ExtraParamsField::validateExtraParams('prior error', 'tess'));
    }

    public function test_parse_returns_empty_for_whitespace_only_csv(): void
    {
        $this->assertSame([], ExtraParamsField::parse(',  ,'));
    }

    public function test_parse_returns_empty_for_empty_string(): void
    {
        $this->assertSame([], ExtraParamsField::parse(''));
    }
}
