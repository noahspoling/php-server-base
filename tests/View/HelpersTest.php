<?php

declare(strict_types=1);

namespace App\Tests\View;

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function test_it_escapes_html_tags(): void
    {
        self::assertSame('&lt;script&gt;alert(1)&lt;/script&gt;', e('<script>alert(1)</script>'));
    }

    public function test_it_escapes_ampersands(): void
    {
        self::assertSame('Tom &amp; Jerry', e('Tom & Jerry'));
    }

    public function test_it_escapes_both_quote_styles_so_it_is_safe_inside_an_attribute(): void
    {
        self::assertSame('&quot;a&quot; &#039;b&#039;', e('"a" \'b\''));
    }

    public function test_it_renders_null_as_an_empty_string(): void
    {
        self::assertSame('', e(null));
    }

    public function test_it_stringifies_numbers(): void
    {
        self::assertSame('42', e(42));
        self::assertSame('1.5', e(1.5));
    }

    public function test_invalid_utf8_is_substituted_rather_than_blanking_the_whole_value(): void
    {
        $result = e("safe\xB1\x31text");

        self::assertStringContainsString('safe', $result);
        self::assertStringContainsString('text', $result);
        self::assertNotSame('', $result);
    }
}
