<?php

namespace App\Livewire\Settings;

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ProfilePage extends Component
{
    /** @var array<string, mixed> */
    public array $profile = [
        'name'   => '',
        'email'  => '',
        'locale' => 'en',
        'theme'  => 'light',
    ];

    /** @var array<string, mixed> */
    public array $password = [
        'current'      => '',
        'new'          => '',
        'confirmation' => '',
    ];

    public function mount(): void
    {
        $user = $this->user();
        $this->profile = [
            'name'   => $user->name,
            'email'  => $user->email,
            'locale' => $user->locale ?? 'en',
            'theme'  => $user->ui_theme ?? 'light',
        ];
    }

    public function saveProfile(): void
    {
        $user = $this->user();

        $data = $this->validate([
            'profile.name'   => ['required', 'string', 'max:120'],
            'profile.email'  => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'profile.locale' => ['required', Rule::in(SetLocale::SUPPORTED)],
            'profile.theme'  => ['required', Rule::in(['light', 'dark'])],
        ])['profile'];

        $before = ['name' => $user->name, 'email' => $user->email, 'locale' => $user->locale, 'ui_theme' => $user->ui_theme];

        $user->update([
            'name'     => trim($data['name']),
            'email'    => mb_strtolower(trim($data['email'])),
            'locale'   => $data['locale'],
            'ui_theme' => $data['theme'],
        ]);

        // Reflect the locale change immediately for this request, so any
        // subsequent flash message in the topbar uses the new language.
        app()->setLocale($data['locale']);
        session(['locale' => $data['locale']]);

        Log::info('lodgely.profile.updated', [
            'id'     => $user->id,
            'before' => $before,
            'after'  => ['name' => $user->name, 'email' => $user->email, 'locale' => $user->locale, 'ui_theme' => $user->ui_theme],
        ]);

        $this->dispatch('toast', message: __('Profile saved.'));
    }

    public function changePassword(): void
    {
        $user = $this->user();

        $this->validate([
            'password.current'      => ['required', 'string'],
            'password.new'          => ['required', 'string', 'min:12', 'different:password.current'],
            'password.confirmation' => ['required', 'same:password.new'],
        ]);

        if (! Hash::check($this->password['current'], $user->password)) {
            $this->addError('password.current', __('Current password is incorrect.'));
            return;
        }

        $user->forceFill(['password' => $this->password['new']])->save();

        event(new PasswordReset($user));

        $this->password = ['current' => '', 'new' => '', 'confirmation' => ''];

        Log::info('lodgely.profile.password_changed', ['id' => $user->id]);

        $this->dispatch('toast', message: __('Password updated.'));
    }

    public function render(): View
    {
        return view('livewire.settings.profile-page', [
            'user' => $this->user(),
        ]);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        return $user;
    }
}
