<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class AuthController extends Controller
{
    public function loginPage()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $data = $request->validated();
        if (Auth::attempt($data)) {
            Log::info('User logged in');
            if (auth()->user()->role == 'admin') {
                Log::info('Admin logged in');
                return redirect(route('category.index'))->with('login', 'You are logged in as admin!');
            }else{
                return redirect(route('client.index'))->with('login', 'You are logged in as client!');
            }
        } else {
            return redirect(route('loginPage'))->with('error', 'Invalid credentials!');
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        return redirect()->route('login')->with('logout', 'You are logged out!');
    }
}
