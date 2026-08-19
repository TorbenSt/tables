<?php

namespace App\Livewire;

use App\Models\PhotoSession;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class PhotoSessionIndex extends Component
{
    public function render()
    {
        return view('livewire.photo-session-index', [
            'sessions' => PhotoSession::query()->latest()->withCount(['photos', 'detectedTables'])->get(),
            'title' => 'Foto-Sessions',
        ]);
    }
}
