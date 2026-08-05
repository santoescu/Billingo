<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Language extends Component
{
    public string $locale;

    public function mount()
    {
        // Cargar el idioma actual del usuario o el idioma por defecto
        $this->locale = Auth::user()->locale ?? config('app.locale');
    }

    public function updateLocale()
    {
        $user = Auth::user();
        $user->locale = $this->locale;
        $user->save();

        app()->setLocale($this->locale);
        $this->dispatch('toast', type: 'success', message: __('Saved.'));
        $this->dispatch('update-locale');
    }

    public function render()
    {
        return view('livewire.settings.language');
    }
}
