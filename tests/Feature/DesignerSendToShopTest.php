<?php

namespace Tests\Feature;

use App\Support\SpamGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Owner, 2026-11-14: "when driveshaft designer send to shop send the email to inquiry@universaldriveshafts.com". */
class DesignerSendToShopTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_designer_drawing_is_mailed_to_the_inquiry_inbox_with_the_drawing(): void
    {
        $sections = collect(glob(storage_path('app/designer/*.svg')))->map(fn ($f) => basename($f, '.svg'))->first(fn ($s) => preg_match('/^[A-Z0-9]+\+[A-Z0-9]+$/', $s));
        if (!$sections) $this->markTestSkipped('no cached designer drawing');
        $this->get('/contact?designer=' . urlencode($sections))->assertOk()->assertSee('name="designer_sections"', false);
        preg_match('/name="_ft" value="([^"]+)"/', SpamGuard::fields(), $m);
        $this->travel(10)->seconds();
        $email = 'dsg-' . uniqid() . '@example.test';
        $this->post('/fitment-inquiry', ['_ft' => $m[1], 'source' => 'contact-us', 'first_name' => 'Rick', 'last_name' => 'Stone', 'email' => $email,
            'message' => "Driveshaft drawing: " . url("/driveshaft-designer/1/$sections") . "\nMeasurements: A=52", 'designer_sections' => $sections, 'designer_dims' => 'A=52',
            'designer_url' => url("/driveshaft-designer/1/$sections")])->assertRedirect()->assertSessionHas('fitment_ok');
        $this->assertSame('new', DB::table('leads')->where('email', $email)->value('status'), 'not taken for spam');
        $sent = collect(app('mailer')->getSymfonyTransport()->messages())->map->getOriginalMessage()->last();
        $this->assertNotNull($sent);
        $this->assertSame(['inquiry@universaldriveshafts.com'], array_map(fn ($a) => $a->getAddress(), $sent->getTo()));
        $this->assertSame($email, $sent->getReplyTo()[0]->getAddress());
        $this->assertStringContainsString('with driveshaft drawing', $sent->getSubject());
        $this->assertCount(1, $sent->getAttachments());
    }
}
