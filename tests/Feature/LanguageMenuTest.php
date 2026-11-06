<?php

namespace Tests\Feature;

use App\Support\PartGlossary;
use Tests\TestCase;

/** Storefront languages: Chinese and Japanese next to EN / ES / FR, in the dropdown language menu. */
class LanguageMenuTest extends TestCase
{
    public function test_chinese_and_japanese_set_the_page_language_and_the_menu_lists_every_language(): void
    {
        $this->get('/?lang=ja')->assertOk()
            ->assertSee('<html lang="ja">', false)
            ->assertSee('<details class="langsw', false)
            ->assertSee('hreflang="zh-Hans"', false)
            ->assertSeeInOrder(['English', 'Español', 'Français', '简体中文', '日本語'])
            ->assertCookie('uds_lang', 'ja', false);

        $this->get('/about-us?lang=zh')->assertOk()->assertSee('<html lang="zh-Hans">', false);
    }

    public function test_product_titles_keep_the_code_before_the_word_in_chinese_and_japanese(): void
    {
        app()->setLocale('ja');
        $this->assertStringContainsString('1310シリーズ', PartGlossary::t('1310 Series'));
        $this->assertStringContainsString('2ピース', PartGlossary::t('2pc'));
        app()->setLocale('zh');
        $this->assertStringContainsString('1310系列', PartGlossary::t('1310 Series'));
        app()->setLocale('es');
        $this->assertSame('serie 1310', PartGlossary::t('1310 Series'));
        app()->setLocale('en');
    }
}
