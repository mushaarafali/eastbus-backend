<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;use Illuminate\Http\Request;use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Hash;use Illuminate\Support\Str;
class StaffAuthController extends Controller {
 public function login(Request $r){$v=$r->validate(['login'=>'required|string','password'=>'required|string']);$login=trim($v['login']);$staff=DB::table('staff')->where('login_id',$login)->orWhere('phone',$login)->orWhere('email',$login)->first();if(!$staff||!$staff->is_active||!Hash::check($v['password'],$staff->password))return response()->json(['success'=>false,'message'=>'Invalid staff login credentials.'],401);$token=Str::random(80);DB::table('staff_api_tokens')->insert(['staff_id'=>$staff->id,'token_hash'=>hash('sha256',$token),'last_used_at'=>now(),'expires_at'=>now()->addDays(30),'created_at'=>now(),'updated_at'=>now()]);unset($staff->password);return ['success'=>true,'token'=>$token,'staff'=>$staff];}
 public function me(Request $r){return ['success'=>true,'staff'=>$r->attributes->get('staff')];}
 public function logout(Request $r){DB::table('staff_api_tokens')->where('id',$r->attributes->get('staff_token_id'))->delete();return ['success'=>true,'message'=>'Logged out.'];}
}
