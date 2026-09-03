<?php
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request; use Symfony\Component\HttpFoundation\Response;
class RoleMiddleware { public function handle(Request $request, Closure $next, ...$roles): Response { if(!auth()->check()) return redirect()->route('login'); if(!auth()->user()->is_active) { auth()->logout(); return redirect()->route('login')->withErrors(['email'=>'Account is inactive.']); } if(!in_array(auth()->user()->role,$roles,true)) abort(403); return $next($request);} }
