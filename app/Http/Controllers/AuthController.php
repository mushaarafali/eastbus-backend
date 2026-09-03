<?php
namespace App\Http\Controllers;
use App\Models\{User,Operator}; use App\Support\Audit; use Illuminate\Http\Request; use Illuminate\Support\Facades\Auth; use Illuminate\Support\Facades\Hash;
class AuthController extends Controller {
 public function loginForm(){return view('auth.login');}
 public function login(Request $r){$data=$r->validate(['email'=>'required|email','password'=>'required']); if(!Auth::attempt($data,$r->boolean('remember'))) return back()->withErrors(['email'=>'Invalid email or password.'])->onlyInput('email'); $r->session()->regenerate(); if(!auth()->user()->is_active){Auth::logout(); return back()->withErrors(['email'=>'Your account is inactive.']);} Audit::log('Login','Authentication'); return redirect()->route(auth()->user()->role==='admin'?'admin.dashboard':'operator.dashboard');}
 public function registerForm(){return view('auth.operator-register');}
 public function registerOperator(Request $r){$d=$r->validate(['company_name'=>'required|max:150','owner_name'=>'required|max:150','email'=>'required|email|unique:users,email','phone'=>'required|max:30','address'=>'nullable|max:255','password'=>'required|min:8|confirmed']); $u=User::create(['name'=>$d['owner_name'],'email'=>$d['email'],'phone'=>$d['phone'],'password'=>Hash::make($d['password']),'role'=>'operator','is_active'=>false]); Operator::create(['user_id'=>$u->id,'company_name'=>$d['company_name'],'owner_name'=>$d['owner_name'],'email'=>$d['email'],'phone'=>$d['phone'],'address'=>$d['address']??null,'status'=>'pending','is_published'=>false]); return redirect()->route('login')->with('success','Registration submitted. Admin approval is required before login.');}
 public function logout(Request $r){Audit::log('Logout','Authentication'); Auth::logout(); $r->session()->invalidate(); $r->session()->regenerateToken(); return redirect()->route('login');}
}
