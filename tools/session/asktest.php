<?php
require '/var/www/vhosts/dev.udshafts.com/uds-app/vendor/autoload.php';
$a = require '/var/www/vhosts/dev.udshafts.com/uds-app/bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$svc = app(App\Services\Chatbot\ChatbotService::class); $u = App\Models\User::find(12);
echo 'provider: ', $svc->provider(), "\n";
$svc->close($u); $conv = $svc->conversation($u);
$r = $svc->turn($u, $conv, 'How does freight shipping work?');
echo json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
$svc->close($u);
