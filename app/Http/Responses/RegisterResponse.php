<?php

namespace App\Http\Responses;

use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\RegistrationResponse as RegistrationResponseContract;
use Filament\Notifications\Notification;

class RegisterResponse implements RegistrationResponseContract
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function toResponse($request) // <--- HAPUS TYPE HINT DI SINI
    {
        // Logout user agar tidak otomatis masuk ke dashboard
        Filament::auth()->logout();

        // Tampilkan notifikasi
        Notification::make()
            ->title('Registrasi Berhasil!')
            ->body('Akun Anda telah berhasil didaftarkan. Silakan tunggu verifikasi dari admin sebelum dapat login.')
            ->success()
            ->persistent()
            ->send();

        // Gunakan redirect()->to() tanpa paksaan type hint pada method
        return redirect()->to(Filament::getLoginUrl());
    }
}
