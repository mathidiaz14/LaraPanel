<?php

namespace App\Livewire\Logs;

use App\Services\LogAggregationService;
use Livewire\Component;

class LogAggregate extends Component
{
    /** Empty = todas las fuentes seleccionadas. */
    public array $selectedSources = [];

    public ?string $level = null;

    public string $query = '';

    public int $perSource = 120;

    public array $sourceDefs = [];

    public function mount(LogAggregationService $service)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

        $this->sourceDefs = array_values($service->sources());
    }

    public function toggleSource(string $key)
    {
        if (in_array($key, $this->selectedSources, true)) {
            $this->selectedSources = array_values(array_filter(
                $this->selectedSources,
                fn ($k) => $k !== $key
            ));
        } else {
            $this->selectedSources[] = $key;
        }
    }

    public function setLevel(?string $level)
    {
        $this->level = in_array($level, ['error', 'warn', 'info', 'debug'], true) ? $level : null;
    }

    public function loadMore()
    {
        $this->perSource = min(2000, $this->perSource + 200);
    }

    public function resetFilters()
    {
        $this->selectedSources = [];
        $this->level = null;
        $this->query = '';
        $this->perSource = 120;
    }

    public function render(LogAggregationService $service)
    {
        $result = $service->aggregate($this->selectedSources, [
            'level' => $this->level,
            'query' => $this->query,
        ], $this->perSource);

        return view('livewire.logs.log-aggregate', [
            'result' => $result,
        ])->layout('layouts.app', [
            'title'      => 'Logs Agregados',
            'breadcrumb' => '<span>Sistema</span> / <strong>Logs Agregados</strong>',
        ]);
    }
}