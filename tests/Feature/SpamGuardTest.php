<?php

namespace Tests\Feature;

use App\Support\SpamGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Owner, 2026-11-14: "/admin/leads a lot of spam, fix that". What the bots sent, and what customers write, scored. */
class SpamGuardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_what_the_bots_sent_scores_as_spam(): void
    {
        foreach ([
            ['zvusaymepmcaculGP zvusalm', ''], ['HenryWawQF HenryWawQF', 'How it works: you upload a photo of your neighbor, classmate, teacher, secretary, or any other girl, and AI generates'],
            ['ivan-VipOO ivan-VipOO', 'Перед запуском рекламы стоит проверить весь путь заявки. Начните с телефона клиента'],
            ['RogerzeshyPP RogerzeshyPP', 'Продаю PlayStation последнего поколения вместе с кучей игр — переезжаю'],
            ['Qtlnkera PXQRushafts.comP', 'Sevnbhxu PXQRushafts.comPXQR kptiwyc wnijvxf PFQR1867248PFQR www.google.com'],
            ['Tamara Rizzo', 'Hello Udshafts Com Owner, My name is Tamara and I’m betting you’d like your website Udshafts Com to generate more leads'],
            ['AustinRoxGM FrankRoxGM', 'Dia duit, theastaigh uaim do phraghas a fháil.'],
            ['Rhmemqvu Wrcduw', 'cnKntiTScFcCEWAvo'], ['', 'Hey! Do you have any use for a freelance writer? I have nearly a decade of experience'],
        ] as [$name, $msg]) $this->assertGreaterThanOrEqual(3, SpamGuard::score($name, $msg), "$name: $msg");
    }

    public function test_what_customers_write_passes(): void
    {
        foreach ([
            ['Ana Ruiz', 'Need a 1350 end yoke for a 2005 F-250, is 3-3-4281X right?'], ['Carlos McKay', 'Necesito un cardán para una Toyota Hilux 2012, ¿tienen?'],
            ['李伟', '我需要一根传动轴，长度 52 英寸'], ['Jean Dupont', 'Bonjour, quel est le prix du 5-153X ?'], ['Mike', 'PTI 2-2-489 yoke?'],
            ['Dave OBrien', "Driveshaft drawing: https://universaldriveshafts.com/driveshaft-designer?s=AB+CD\nMeasurements: A=52"], ['Tom', 'Call me back please'], ['', ''],
        ] as [$name, $msg]) $this->assertLessThan(3, SpamGuard::score($name, $msg), "$name: $msg");
    }

    private function token(): string
    {
        preg_match('/name="_ft" value="([^"]+)"/', SpamGuard::fields(), $m);
        return $m[1];
    }

    public function test_the_form_keeps_bots_as_spam_without_mailing_the_shop(): void
    {
        Mail::fake();
        $base = ['source' => 'contact-us', 'first_name' => 'Ana', 'last_name' => 'Ruiz', 'email' => 'ana-' . uniqid() . '@example.test', 'message' => 'Need a 1350 end yoke for a 2005 F-250'];
        $status = fn ($email) => DB::table('leads')->where('email', $email)->orderByDesc('id')->value('status');
        $send = function (array $x) use ($base, $status) {
            $d = $x + $base; $d['email'] = 'lead-' . uniqid() . '@example.test';
            $this->post('/fitment-inquiry', $d)->assertRedirect()->assertSessionHas('fitment_ok');
            return $status($d['email']);
        };

        $this->assertSame('spam', $send([]), 'posted without loading the form');
        $ft = $this->token();
        $this->assertSame('spam', $send(['_ft' => $ft]), 'submitted within 3 seconds');
        $this->travel(10)->seconds();
        $this->assertSame('spam', $send(['_ft' => $ft, SpamGuard::FIELD => 'http://spam.example']), 'honeypot');
        $this->assertSame('spam', $send(['_ft' => $ft, 'first_name' => 'HenryWawQF', 'last_name' => 'HenryWawQF']), 'XRumer name');
        $this->assertSame('spam', $send(['_ft' => '1700000000.0123456789abcdef']), 'forged time');
        $this->assertSame('new', $send(['_ft' => $ft]), 'a person');
        $lead = DB::table('leads')->where('status', 'new')->orderByDesc('id')->first();
        $this->assertArrayNotHasKey('_ft', json_decode($lead->data, true));

        $admin = \App\Models\User::query()->create(['name' => 'Owner T', 'email' => 'own-' . uniqid() . '@example.test', 'password' => bcrypt('x')]);
        DB::table('users')->where('id', $admin->id)->update(['is_admin' => 1, 'admin_role' => 'owner']);
        $this->actingAs($admin->fresh());
        $open = $this->get('/admin/leads')->assertOk()->getContent();
        $this->assertStringNotContainsString('HenryWawQF', $open);
        $this->assertStringContainsString('HenryWawQF', $this->get('/admin/leads?tab=spam')->assertOk()->getContent());
    }
}
