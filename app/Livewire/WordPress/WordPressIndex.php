<?php

namespace App\Livewire\WordPress;

use App\Services\WordPressService;
use Illuminate\Support\Str;
use Livewire\Component;
use Illuminate\Support\Facades\File;

class WordPressIndex extends Component
{
    // Array of domains loaded from DB
    public array $domains = [];

    public ?string $selectedDomain = null;
    public string $siteTitle = 'Mi Blog WordPress';
    public string $adminUser = 'admin';
    public string $adminEmail = 'admin@example.com';
    public string $adminPass = '';
    
    public bool $isInstalling = false;
    public string $installOutput = '';
    public bool $installSuccess = false;

    public function mount(WordPressService $wpService)
    {
        $this->adminPass = Str::password(16, true, true, false, false);
        $this->loadDomains($wpService);
    }

    public function loadDomains(WordPressService $wpService)
    {
        $dbDomains = \App\Models\Domain::where('status', 'active')->get();
        $this->domains = [];
        foreach ($dbDomains as $dom) {
            $this->domains[] = [
                'id' => $dom->id,
                'name' => $dom->name,
                'path' => $dom->document_root,
                'has_wp' => $wpService->isInstalled($dom->document_root),
            ];
        }
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
