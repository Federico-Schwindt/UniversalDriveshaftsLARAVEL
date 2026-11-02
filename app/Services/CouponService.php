<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CouponService
{
    /** Validate a code against the cart. Returns [ok, coupon|null, error|null]. $items = cart lines (for category / SKU / sale rules). */
    public function validate(string $code, float $subtotal, ?array $items = null, ?string $email = null): array
    {
        $code = trim($code);
        if ($code === '') return [false, null, __('Enter a coupon code.')];
        $c = DB::table('coupons')->whereRaw('LOWER(code) = ?', [strtolower($code)])->first();
        if (!$c || !$c->active) return [false, null, __('This coupon code is not valid.')];
        if ($c->starts_at && Carbon::parse($c->starts_at)->isFuture()) return [false, null, __('This coupon is not active yet.')];
        if ($c->expires_at && Carbon::parse($c->expires_at)->isPast()) return [false, null, __('This coupon has expired.')];
        if ($c->usage_limit !== null && $c->used_count >= $c->usage_limit) return [false, null, __('This coupon has reached its usage limit.')];
        if ($c->minimum_amount !== null && $subtotal < (float) $c->minimum_amount) {
            return [false, null, __('Minimum order of :amount required for this coupon.', ['amount' => '$' . number_format($c->minimum_amount, 2)])];
        }
        $rx = $this->restrictions($c);
        if (!empty($rx['wholesale_only']) && (auth()->user()?->wholesale_status ?? 'none') !== 'approved') {
            return [false, null, __('This coupon is for approved wholesale accounts only.')];
        }
        if ($c->per_customer_limit !== null) {
            $uid = auth()->id(); $mail = $email ?: auth()->user()?->email;
            if ($uid || $mail) {
                // a card order that was never paid (declined, abandoned) has not used the coupon; invoice and
                // pay-at-pickup orders have, since those are committed when placed
                $used = DB::table('orders')->whereRaw('LOWER(coupon_code) = ?', [strtolower($c->code)])->whereNotIn('status', ['cancelled', 'failed'])
                    ->where(fn ($w) => $w->where('payment_status', 'paid')->orWhere('payment_method', '!=', 'stripe'))
                    ->where(fn ($w) => $w->when($uid, fn ($q) => $q->orWhere('user_id', $uid))->when($mail, fn ($q) => $q->orWhere('email', $mail)))->count();
                if ($used >= (int) $c->per_customer_limit) return [false, null, __('You have already used this coupon the maximum number of times.')];
            }
        }
        if ($items !== null && $this->scoped($rx) && $this->eligibleSubtotal($c, $items) <= 0) {
            return [false, null, __('This coupon does not apply to the items in your cart.')];
        }
        return [true, $c, null];
    }

    /** Discount for the cart; percent/fixed amounts apply only to the lines the coupon covers. */
    public function discountFor(object $c, float $subtotal, ?array $items = null): float
    {
        $base = $items !== null && $this->scoped($this->restrictions($c)) ? $this->eligibleSubtotal($c, $items) : $subtotal;
        $d = $c->type === 'percent' ? round($base * ((float) $c->amount) / 100, 2) : min($base, round((float) $c->amount, 2));
        if ($c->maximum_amount !== null && (float) $c->maximum_amount > 0) $d = min($d, round((float) $c->maximum_amount, 2));
        return max(0.0, min($d, $subtotal));
    }

    private function restrictions(object $c): array
    {
        return json_decode($c->restrictions ?: '{}', true) ?: [];
    }

    private function scoped(array $rx): bool
    {
        return !empty($rx['categories']) || !empty($rx['skus']) || !empty($rx['exclude_sale']);
    }

    private function eligibleSubtotal(object $c, array $items): float
    {
        $rx = $this->restrictions($c);
        $cats = array_map('strtolower', (array) ($rx['categories'] ?? []));
        $skus = array_map('strtolower', (array) ($rx['skus'] ?? []));
        $sum = 0.0;
        foreach ($items as $i) {
            $pid = (int) ($i['wp_product_id'] ?? 0);
            $isProduct = ($i['type'] ?? 'product') === 'product' && $pid > 0;
            if (($cats || $skus) && !$isProduct) continue;
            if ($cats || $skus) {
                $okSku = $skus && in_array(strtolower((string) ($i['sku'] ?? '')), $skus, true);
                $okCat = $cats && array_intersect($cats, $this->productCategories($pid));
                if (!$okSku && !$okCat) continue;
            }
            if (!empty($rx['exclude_sale']) && $isProduct && $this->onSale($pid)) continue;
            $sum += (float) $i['price'] * (int) $i['qty'];
        }
        return round($sum, 2);
    }

    private function productCategories(int $pid): array
    {
        return \Illuminate\Support\Facades\Cache::remember("cpn_cats_$pid", 300, fn () => DB::connection('wp')->table('wp_term_relationships as r')
            ->join('wp_term_taxonomy as x', 'x.term_taxonomy_id', '=', 'r.term_taxonomy_id')->join('wp_terms as t', 't.term_id', '=', 'x.term_id')
            ->where('r.object_id', $pid)->where('x.taxonomy', 'product_cat')->pluck('t.slug')->map(fn ($v) => strtolower($v))->all());
    }

    private function onSale(int $pid): bool
    {
        $m = DB::connection('wp')->table('wp_postmeta')->where('post_id', $pid)->whereIn('meta_key', ['_regular_price', '_sale_price'])->pluck('meta_value', 'meta_key');
        return ($m['_sale_price'] ?? '') !== '' && (float) ($m['_sale_price'] ?? 0) < (float) ($m['_regular_price'] ?? 0);
    }

    public function markUsed(string $code): void
    {
        DB::table('coupons')->whereRaw('LOWER(code) = ?', [strtolower($code)])->increment('used_count');
    }
}
