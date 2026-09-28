<?php

namespace App\Livewire\SSL;

use App\Services\CertificateMonitorService;
use Livewire\Component;

class SslMonitor extends Component
{
    public int $scanDays = 14;

    public function render()
    {
        $groups = app(CertificateMonitorService::class)->scanExpiring($this->scanDays);

        return view('livewire.ssl.ssl-monitor', [
            'groups'   => $groups,
            'scanDays' => $this->scanDays,
        ])->layout('layouts.app', [
            'title'      => 'Monitor SSL',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Monitor SSL</strong>',
        ]);
    }
}