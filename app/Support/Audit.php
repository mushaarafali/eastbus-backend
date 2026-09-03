<?php
namespace App\Support; use App\Models\SystemLog;
class Audit { public static function log(string $action,string $module,string $description=''): void { SystemLog::create(['user_id'=>auth()->id(),'action'=>$action,'module'=>$module,'description'=>$description,'ip_address'=>request()->ip(),'created_at'=>now()]); } }
