<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * A German client reported they couldn't find the outreach toggles — because
 * "Outreach"/"Qualified"/"Called"/"Mailed" (and the section's helper caption)
 * had no lang/de.json entry and silently fell back to raw English. Pins the
 * fix so a future edit to either file can't quietly drop these again.
 */
class OutreachTranslationTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function outreachStrings(): array
    {
        return [
            'Outreach' => ['Outreach'],
            'Qualified' => ['Qualified'],
            'Called' => ['Called'],
            'Mailed' => ['Mailed'],
            'Not contacted' => ['Not contacted'],
            'Click a pill to toggle.' => ['Click a pill to toggle.'],
            'Mark as :label' => ['Mark as :label'],
            'Filter options' => ['Filter options'],
            'Visible filters' => ['Visible filters'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('outreachStrings')]
    public function test_string_has_a_german_translation_distinct_from_the_english_fallback(string $key): void
    {
        app()->setLocale('de');

        $translated = __($key);

        $this->assertNotSame(
            $key,
            $translated,
            "'{$key}' has no lang/de.json entry — it renders as raw English for German users.",
        );
    }

    public function test_en_and_de_lang_files_carry_the_exact_same_key_set(): void
    {
        $en = json_decode(file_get_contents(lang_path('en.json')), true);
        $de = json_decode(file_get_contents(lang_path('de.json')), true);

        $this->assertSame(
            [],
            array_diff(array_keys($en), array_keys($de)),
            'lang/en.json has keys missing from lang/de.json.',
        );
        $this->assertSame(
            [],
            array_diff(array_keys($de), array_keys($en)),
            'lang/de.json has keys missing from lang/en.json.',
        );
    }
}
