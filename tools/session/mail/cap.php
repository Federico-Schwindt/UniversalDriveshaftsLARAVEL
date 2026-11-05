<?php
// usage: php cap.php <outdir> <locale-to-store-on-test-order: en|es>
$root = '/var/www/vhosts/dev.udshafts.com/uds-app';
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$out = $argv[1]; @mkdir($out, 0777, true); $loc = $argv[2] ?? 'en';
config(['mail.default' => 'array']);
$hasLoc = Illuminate\Support\Facades\Schema::hasColumn('orders', 'locale');
$order = DB::table('orders')->where('order_number', 'UDS-260910-ROLP')->first();
$orig = $hasLoc ? $order->locale : null;
if ($hasLoc) DB::table('orders')->where('id', $order->id)->update(['locale' => $loc === 'en' ? null : $loc]);
$order = DB::table('orders')->find($order->id);
$items = DB::table('order_items')->where('order_id', $order->id)->get();
$m = app(App\Services\OrderMailer::class);
try {
  $m->send($order, $items);
  $m->sendShipped($order, 'UPS', '1Z999AA10123456784', 'Ground');
  $d = clone $order; $d->delivered_at = '2026-10-20 15:30:00'; $m->sendDelivered($d);
  App\Support\Mailer::send($order->email, "Payment link for order {$order->order_number} — \$2,704.08", 'emails.order.pay-link', [
    'order' => $order, 'items' => App\Services\OrderMailer::items($items), 'balance' => 2704.08, 'payUrl' => 'https://dev.udshafts.com/pay/X/Y', 'note' => null,
    'hero' => ['kicker' => 'Payment request', 'title' => 'Hi Federico, your order is ready to be paid.', 'text' => 'Pay in under a minute with any major card — your parts start moving as soon as it clears.'], 'preheader' => "\$2,704.08 due on order {$order->order_number}",
  ], ['replyTo' => [config('uds.email')]]);
  $rm = app(App\Services\RecoveryMailer::class);
  $row = (object) ['items' => json_encode([['type' => 'product', 'wp_product_id' => 63835, 'name' => 'Flange Yoke 1310', 'sku' => '3102-80', 'qty' => 2, 'price' => 52.77]]), 'subtotal' => 105.54, 'token' => 'TOKEN', 'email' => $order->email, 'user_id' => null];
  $rm->cartReminder($row, 1); $rm->cartReminder($row, 2);
  $rm->backInStock((object) ['label' => 'Flange Yoke 1310', 'vehicle' => null, 'email' => $order->email], ['name' => 'Flange Yoke 1310', 'sku' => '3102-80', 'price' => 52.77, 'url' => 'https://dev.udshafts.com/parts/3102-80', 'stock' => 11]);
  $rm->quote(['sku' => 'U3591-5550', 'email' => $order->email, 'phone' => '+1 813 555 0100', 'vehicle' => 'Ford F150 2019', 'description' => 'Ford F150 1pc 18-20', 'note' => 'Need it fast', 'url' => 'https://dev.udshafts.com/parts/U3591-5550', 'name' => 'Federico']);
  // a few Mailer::simple messages exactly as their callers build them
  App\Support\Mailer::simple($order->email, "Order {$order->order_number} is ready for pickup", ['kicker' => 'Ready for pickup', 'title' => 'Your order is ready, Federico.', 'intro' => 'Order ' . $order->order_number . ' is packed and waiting at the counter in Odessa, FL.']);
} finally {
  if ($hasLoc) DB::table('orders')->where('id', $order->id)->update(['locale' => $orig]);
}
$i = 0;
foreach (app('mail.manager')->mailer('array')->getSymfonyTransport()->messages() as $sm) {
  $msg = $sm->getOriginalMessage();
  $to = implode(',', array_map(fn ($a) => $a->getAddress(), $msg->getTo()));
  $i++;
  file_put_contents(sprintf('%s/%02d.html', $out, $i), "<!-- TO: $to -->\n<!-- SUBJECT: " . $msg->getSubject() . " -->\n" . $msg->getHtmlBody());
  file_put_contents(sprintf('%s/%02d.txt', $out, $i), "SUBJECT: " . $msg->getSubject() . "\n" . $msg->getTextBody());
  echo sprintf("%02d  %-34s %s\n", $i, $to, $msg->getSubject());
}
