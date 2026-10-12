<?php

namespace App\Http\Controllers\Auth\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auth\AdminLoginService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AdminLoginService $adminLoginService) {}

    public function login(Request $request)
    {
        return $this->adminLoginService->login(
            (string) $request->input('email', ''),
            (string) $request->input('password', ''),
        );
    }
}
