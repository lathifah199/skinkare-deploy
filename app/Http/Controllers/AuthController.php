<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Orangtua;
use App\Models\Nakes;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // =================== LOGIN VIEW ====================
    public function showLoginForm()
    {
        if (Auth::guard('orangtua')->check()) {
            return redirect()->route('halaman_orangtua');
        }
        if (Auth::guard('nakes')->check()) {
            return redirect()->route('halaman_nakes');
        }
        return view('pages.login');
    }

    // =================== LOGIN PROCESS ====================
  public function login(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'kata_sandi' => 'required|string',
        ]);

        // Login Orangtua
        $orangtua = Orangtua::where('email', $request->email)->first();
        if ($orangtua && Hash::check($request->kata_sandi, $orangtua->kata_sandi)) {
            session([
                'user_id' => $orangtua->id_orangtua,
                'user_nama' => $orangtua->nama,
                'user_tipe' => 'orangtua',
            ]);
            Auth::guard('orangtua')->login($orangtua);
            $request->session()->regenerate();
            return redirect()->route('halaman_orangtua')
                ->with('success', 'Login anda berhasil');
        }

        // Login Nakes
        $nakes = Nakes::where('email', $request->email)->first();
        if ($nakes && Hash::check($request->kata_sandi, $nakes->kata_sandi)) {
            session([
                'user_id' => $nakes->id_nakes,
                'user_nama' => $nakes->nama_lengkap,
                'user_tipe' => 'nakes',
            ]);
            Auth::guard('nakes')->login($nakes);
            $request->session()->regenerate();
            return redirect()->route('halaman_nakes')->with('success', 'Login berhasil!');
        }

        // ⚠ Jika gagal login, kirim error
        return back()->withErrors([
            'email' => 'Email yang dimasukkan salah',
            'kata_sandi' => 'Kata sandi anda salah',
        ])->withInput();

    }

    // =================== LUPA SANDI VIEW ====================
    public function showLupaSandiForm()
    {
        return view('pages.lupa_sandi');
    }

    // =================== PROSES LUPA SANDI ====================
    public function showLupaSandi(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'kata_sandi' => 'required|string|min:6|confirmed',
        ]);

        $orangtua = Orangtua::where('email', $request->email)->first();

        if (!$orangtua) {
            return back()->withErrors(['email' => 'Email pengguna tidak ditemukan.']);
        }

        $orangtua->kata_sandi = bcrypt($request->kata_sandi);
        $orangtua->save();

        // ✅ Ubah session key agar bisa dibedakan
        return redirect()->route('login')->with('success_reset', 'Reset kata sandi berhasil! Silakan login.');
    }

    // =================== REGISTRASI VIEW ====================
    public function showRegisterForm()
    {
        return view('pages.registrasi');
    }

    // =================== PROSES REGISTRASI ====================
    public function registrasi(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:100',
            'email' => 'required|string|email|max:255|unique:orangtua',
            'domisili' => 'required|string|max:255',
            'no_hp' => 'required|string|max:12',
            'kata_sandi' => 'required|string|min:6|confirmed',
        ]);

        Orangtua::create([
            'nama' => $validated['nama'],
            'email' => $validated['email'],
            'domisili' => $validated['domisili'],
            'no_hp' => $validated['no_hp'],
            'kata_sandi' => bcrypt($validated['kata_sandi']),
        ]);

        return redirect()->route('login')->with('success', 'Registrasi berhasil! Silakan login.');
    }

    // =================== LOGOUT ====================
    public function logout(Request $request)
    {
        Auth()->logout(); // Logout semua guard
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('halamanbf');
    }
}