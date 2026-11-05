<?php
require '/var/www/vhosts/dev.udshafts.com/uds-app/vendor/autoload.php';
$app = require '/var/www/vhosts/dev.udshafts.com/uds-app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$key=config('services.anthropic.key'); $ws=config('services.anthropic.workspace');
foreach ([[], ['anthropic-workspace-id'=>$ws]] as $extra) {
  $r = Illuminate\Support\Facades\Http::withHeaders(array_merge(['x-api-key'=>$key,'anthropic-version'=>'2023-06-01'],$extra))
    ->post('https://api.anthropic.com/v1/messages',['model'=>config('services.anthropic.model'),'max_tokens'=>20,'messages'=>[['role'=>'user','content'=>'Say OK']]]);
  echo ($extra?'with ws':'no ws'),': ',$r->status(),' ',substr(preg_replace('/sk-ant-[\w-]+/','[key]',$r->body()),0,300),"\n";
}
