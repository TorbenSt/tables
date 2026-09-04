<?php

namespace App\Livewire;

use App\Models\MapSite;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class MapSiteIndex extends Component
{
    public function render()
    {
        return view('livewire.map-site-index', [
            'sites' => MapSite::query()->latest()->withCount('tables')->get(),
            'title' => 'Kartenstandorte',
        ]);
    }
}
