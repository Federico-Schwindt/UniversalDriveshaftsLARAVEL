<?php
require '/var/www/vhosts/dev.udshafts.com/uds-app/vendor/autoload.php';
$app = require '/var/www/vhosts/dev.udshafts.com/uds-app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$u = App\Models\User::where('email','owner@example.com')->first() ?? App\Models\User::whereNotNull('admin_role')->first();
$svc = app(App\Services\Chatbot\ChatbotService::class);
$conv = $svc->conversation($u);
$t=microtime(true);
$r = $svc->turn($u, $conv, 'Do you have a 1350 series u-joint? What is the price?');
echo round(microtime(true)-$t,1),"s\n", json_encode(array_intersect_key($r, array_flip(['text','reply','answer','error'])) ?: $r, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) ,"\n";
echo 'provider=',DB::table('chat_conversations')->where('id',$conv->id)->value('provider'),' conv=',$conv->id,"\n";
