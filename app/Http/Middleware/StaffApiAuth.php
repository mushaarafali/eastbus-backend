<?php
namespace App\Http\Middleware;
use Closure;use Illuminate\Http\Request;use Illuminate\Support\Facades\DB;use Symfony\Component\HttpFoundation\Response;
class StaffApiAuth {
 public function handle(Request $request, Closure $next): Response {
  $plain=$request->bearerToken(); if(!$plain)return response()->json(['success'=>false,'message'=>'Unauthenticated staff session.'],401);
  $token=DB::table('staff_api_tokens')->where('token_hash',hash('sha256',$plain))->first();
  if(!$token || ($token->expires_at && now()->greaterThan($token->expires_at)))return response()->json(['success'=>false,'message'=>'Staff session expired.'],401);
  $staff=DB::table('staff')->where('id',$token->staff_id)->where('is_active',1)->first(); if(!$staff)return response()->json(['success'=>false,'message'=>'Staff account is inactive.'],403);
  DB::table('staff_api_tokens')->where('id',$token->id)->update(['last_used_at'=>now()]); $request->attributes->set('staff',$staff); $request->attributes->set('staff_token_id',$token->id); return $next($request);
 }
}
