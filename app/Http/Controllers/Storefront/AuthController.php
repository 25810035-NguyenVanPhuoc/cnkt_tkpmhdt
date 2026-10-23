<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\LoginCustomerRequest;
use App\Http\Requests\Storefront\RegisterCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('storefront.auth.login');
    }

    public function login(LoginCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::where('phone', $request->validated('phone'))->first();

        // Khach vang lai (tao qua checkout/CRM, chua tung dang ky) co
        // password = NULL - khong the dang nhap, phai bao loi ro rang thay
        // vi de Hash::check() vo tinh nhan NULL.
        if (! $customer || ! $customer->password || ! Hash::check($request->validated('password'), $customer->password)) {
            return back()->withErrors(['phone' => 'Số điện thoại hoặc mật khẩu không đúng.'])->onlyInput('phone');
        }

        Auth::guard('customer')->login($customer, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('storefront.home');
    }

    public function showRegister(): View
    {
        return view('storefront.auth.register');
    }

    public function register(RegisterCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create([
            'name' => $request->validated('name'),
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
            'address' => $request->validated('address'),
            'password' => Hash::make($request->validated('password')),
        ]);

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return redirect()->route('storefront.home')->with('status', 'Đăng ký thành công! Chào mừng bạn.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront.home');
    }
}
