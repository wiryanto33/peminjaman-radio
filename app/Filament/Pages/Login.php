<?php

namespace App\Filament\Pages;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\View\View;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class Login extends BaseLogin
{
    protected static string $view = 'filament.pages.login';

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        // Determine which field is used for login
        $login = $data['login'] ?? $data['email'] ?? null;
        $field = $this->resolveLoginField($login);

        // Check if user exists and was created through social login (password is null)
        if ($login) {
            $user = User::where($field, $login)->first();
            if ($user && is_null($user->password)) {
                throw ValidationException::withMessages([
                    'data.login' => 'This account was created using social login. Please login with Google.',
                ]);
            }
        }

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();

            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function mount(): void
    {
        parent::mount();
    }
    /**
     * @return array<int | string, string | Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getRememberFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('Username/NRP')
            ->required()
            ->autofocus()
            ->autocomplete('username');
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        $login = $data['login'] ?? $data['email'] ?? '';
        $field = $this->resolveLoginField($login);

        return [
            $field => $login,
            'password' => $data['password'] ?? ''
        ];
    }

    private function resolveLoginField(?string $login): string
    {
        if (empty($login)) {
            return 'email';
        }

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }

        if (preg_match('/^\d+$/', $login)) {
            return 'nrp';
        }

        return 'name';
    }
}
