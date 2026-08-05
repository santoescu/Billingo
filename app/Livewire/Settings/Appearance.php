<?php

namespace App\Livewire\Settings;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Appearance extends Component
{
    public string $appearance = 'light';

    public function mount(): void
    {
        $this->appearance = session('appearance', 'light');
    }

    public function updatedAppearance(string $value): void
    {
        session(['appearance' => $value]);
        auth()->user()->update([
            'appearance' => $value,
        ]);
        $this->dispatch('appearance-updated');
    }

    public function render()
    {
        return view('livewire.settings.appearance');
    }
}
