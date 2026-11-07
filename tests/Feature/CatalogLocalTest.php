<?php

namespace Tests\Feature;

use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** The catalog without the old WooCommerce store (catalog:detach): last pull, then nothing reads the old site. */
class CatalogLocalTest extends TestCase
{
    use DatabaseTransactions;

    private const OLD = 'https://universaldriveshafts.com/wp-content/uploads/';

    public function test_once_local_nothing_reads_the_old_store(): void
    {
        Http::fake();
        Settings::set('catalog.detached_at', '2026-11-06 12:00:00');
        $this->artisan('catalog:sync-live')->assertSuccessful();
        $this->artisan('catalog:localize-images')->assertSuccessful();
        // a picture that was never copied here is a 404, not a fetch from the old site
        $key = rtrim(strtr(base64_encode(self::OLD . 'zz-never-mirrored.jpg'), '+/', '-_'), '=');
        $this->get("/media/320/$key.webp")->assertNotFound();
        Http::assertNothingSent();
        $this->artisan('catalog:detach')->expectsOutputToContain('already local')->assertSuccessful();
    }

    public function test_the_cutover_takes_the_old_store_counts_and_points_photos_at_the_local_copy(): void
    {
        Settings::set('catalog.detached_at', null);
        $inv = DB::table('inventory')->where('source', 'manual')->whereIn('wp_product_id', DB::table('catalog_live')->select('wp_product_id'))->first();
        $this->assertNotNull($inv);
        $id = (int) $inv->wp_product_id;
        $rel = $this->mirroredFile();
        DB::table('catalog_live')->where('wp_product_id', $id)->update(['image' => self::OLD . $rel, 'thumb' => self::OLD . $rel, 'gallery' => json_encode([self::OLD . $rel]), 'locked_at' => null]);
        $this->fakeOldStore($id, 7);

        $this->artisan('catalog:detach', ['--no-backup' => true])->assertSuccessful();

        $this->assertSame(7, (int) DB::table('inventory')->where('id', $inv->id)->value('stock'));
        $this->assertSame('Old store count at the cutover', DB::table('inventory_adjustments')->where('inventory_id', $inv->id)->latest('id')->value('reason'));
        $this->assertSame('7', DB::connection('wp')->table('wp_postmeta')->where('post_id', $id)->where('meta_key', '_stock')->value('meta_value'));
        $live = DB::table('catalog_live')->where('wp_product_id', $id)->first();
        $this->assertSame('/uploads/wp/' . $rel, $live->image);
        $this->assertSame(['/uploads/wp/' . $rel], json_decode($live->gallery, true));
        $this->assertTrue((bool) $live->in_stock);
        $this->assertTrue(\App\Support\LiveOrigin::retired());

        // launch day: the old store sold 4 more, but a count typed here after the cutover wins
        $this->fakeOldStore($id, 3);
        $this->artisan('catalog:detach', ['--refresh-stock' => true, '--no-backup' => true])->assertSuccessful();
        $this->assertSame(3, (int) DB::table('inventory')->where('id', $inv->id)->value('stock'));
        DB::table('inventory_adjustments')->insert(['inventory_id' => $inv->id, 'delta' => 2, 'before' => 3, 'after' => 5, 'reason' => 'Inline edit', 'user_id' => null, 'created_at' => now()->addMinute()]);
        DB::table('inventory')->where('id', $inv->id)->update(['stock' => 5]);
        $this->fakeOldStore($id, 1);
        $this->artisan('catalog:detach', ['--refresh-stock' => true, '--no-backup' => true])->assertSuccessful();
        $this->assertSame(5, (int) DB::table('inventory')->where('id', $inv->id)->value('stock'));
    }

    private ?int $oldQty = null;

    /** The old store answers: no catalog changes, one product with this stock count (Http::fake stubs add up, so it is faked once). */
    private function fakeOldStore(int $id, int $qty): void
    {
        $first = $this->oldQty === null;
        $this->oldQty = $qty;
        if (!$first) return;
        Http::fake([
            'cloudflare-dns.com/*' => Http::response(['Answer' => []]),
            'dns.google/*' => Http::response(['Answer' => []]),
            '*/wp-json/wc/store/v1/products*' => Http::response([]),
            '*/wp-json/wc/v3/products*' => fn () => Http::response([['id' => $id, 'sku' => 'ZZ-TEST', 'manage_stock' => true, 'stock_quantity' => $this->oldQty,
                'stock_status' => $this->oldQty > 0 ? 'instock' : 'outofstock']], 200, ['X-WP-TotalPages' => '1']),
        ]);
    }

    /** "yyyy/mm/name.jpg" of a picture that is in the local mirror. */
    private function mirroredFile(): string
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(public_path('uploads/wp'), \FilesystemIterator::SKIP_DOTS)) as $f) {
            if (preg_match('/^[\w-]+\.(jpe?g|png)$/i', $f->getFilename())) return substr($f->getPathname(), strlen(public_path('uploads/wp/')));
        }
        $this->fail('No mirrored picture found under public/uploads/wp');
    }
}
