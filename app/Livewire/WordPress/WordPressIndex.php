<?php

namespace App\Livewire\WordPress;

use App\Services\WordPressService;
use Illuminate\Support\Str;
use Livewire\Component;
use Illuminate\Support\Facades\File;

class WordPressIndex extends Component
{
    // domains mock - in a real scenario we pull from Domain model
    public array $domains = [
        ['id' => 1, 'name' => 'miproyecto.com', 'path' => '/var/www/miproyecto.com/public_html', 'has_wp' => false],
        ['id' => 2, 'name' => 'tiendaonline.net', 'path' => '/var/www/tiendaonline.net/public_html', 'has_wp' => true],
    ];

    public ?string $selectedDomain = null;
    public string $siteTitle = 'Mi Blog WordPress';
    public string $adminUser = 'admin';
    public string $adminEmail = 'admin@example.com';
    public string $adminPass = '';
    
    public bool $isInstalling = false;
    public string $installOutput = '';
    public bool $installSuccess = false;

    public function mount()
    {
        $this->adminPass = Str::password(16, true, true, false, false);
    }

    public function selectDomain(string $domainName)
    {
        $this->selectedDomain = $domainName;
        $this->installOutput = '';
        $this->installSuccess = false;
        $this->adminPass = Str::password(16, true, true, false, false);
    }

    public function installWP(WordPressService $wpService)
    {
        $this->validate([
            'selectedDomain' => 'required',
            'siteTitle'      => 'required|string|max:100',
            'adminUser'      => 'required|alpha_dash|max:50',
            'adminEmail'     => 'required|email',
            'adminPass'      => 'required|min:8',
        ]);

        $domain = collect($this->domains)->firstWhere('name', $this->selectedDomain);
        if (!$domain) return;

        $this->isInstalling = true;
        
        // Mock DB credentials based on domain name
        $dbName = str_replace('.', '_', $domain['name']) . '_wp';
        $dbUser = substr($dbName, 0, 16);
        $dbPass = Str::random(16);

        $result = $wpService->install(
            $domain['path'],
            'https://' . $domain['name'],
            $this->siteTitle,
            $this->adminUser,
            $this->adminPass,
            $this->adminEmail,
            $dbName,
            $dbUser,
            $dbPass
        );

        $this->installOutput = $result['output'];
        $this->installSuccess = $result['success'];
        $this->isInstalling = false;

        if ($result['success']) {
            // Update local mock array to show it's installed
            foreach ($this->domains as &$d) {
                if ($d['name'] === $this->selectedDomain) {
                    $d['has_wp'] = true;
                }
            }
        }
    }

    public function render()
    {
        return view('livewire.wordpress.wordpress-index')->layout('layouts.app', [
            'title'      => 'WordPress Manager',
            'breadcrumb' => '<span>Avanzado</span> / <strong>WordPress</strong>',
        ]);
    }
}
