<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga agar hanya user berstatus aktif yang dapat melanjutkan request —
 * termasuk request update Livewire setelah halaman terbuka. Didaftarkan sebagai
 * alias route DAN persistent middleware Livewire (bootstrap/app.php).
 */
class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && ! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda dinonaktifkan. Hubungi administrator.',
            ]);
        }

        return $next($request);
    }
}
