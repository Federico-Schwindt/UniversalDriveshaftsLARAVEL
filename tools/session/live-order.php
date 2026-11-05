<?php
require '/var/www/vhosts/dev.udshafts.com/uds-app/vendor/autoload.php';
$app = require '/var/www/vhosts/dev.udshafts.com/uds-app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
config(['mail.default' => 'array']);
app('session')->setId(Illuminate\Support\Str::random(40)); app('session')->start();
$u = App\Models\User::query()->create(['name' => 'Alex Morgan', 'email' => 'qa-bot-temp@example.com', 'password' => bcrypt(Illuminate\Support\Str::random(20))]);
DB::table('users')->where('id', $u->id)->update(['wholesale_status' => 'approved', 'phone' => '(813) 555-0142', 'created_at' => now()->subDay()]);
App\Services\B2bAccounts::evaluate($u->id, false);
$u = $u->fresh(); auth()->login($u);
file_put_contents($argv[1] . '/qa-user-id', $u->id);
$svc = app(App\Services\Chatbot\ChatbotService::class);
$conv = $svc->conversation($u);
foreach (["What's my business tier and how much more do I need for the next one?",
          "Add 2 of 5-1310XU to my cart and place the order for local pickup, pay on terms, PO 4471.",
          "yes, place it"] as $msg) {
    $t = microtime(true);
    $r = $svc->turn($u, $conv, $msg);
    echo "\n> $msg\n[" . round(microtime(true) - $t, 1) . "s] " . $r['text'] . "\n";
}
echo "\norders: ", json_encode(DB::table('orders')->where('user_id', $u->id)->get(['order_number','payment_method','status','total','po_number','source'])), "\n";
echo "tools: ", json_encode(DB::table('chat_messages')->where('conversation_id', $conv->id)->where('role','assistant')->pluck('content')->map(fn($c)=>collect(json_decode($c,true))->where('type','tool_use')->pluck('name')->all())->flatten()->all()), "\n";
