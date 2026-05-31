<?php

namespace App\Livewire\Servers;

use App\Models\Server;
use App\Shell\ServerContext;
use Livewire\Component;

class ServerSelector extends Component
{
    public string $currentLabel = '';
    public bool $isRemote = false;

    public function mount(): void
    {
        ServerContext::resolve();
        $this->currentLabel = ServerContext::label();
        $this->isRemote = ServerContext::isRemote();
    }

    public function selectServer(int $id): void
    {
        ServerContext::switchTo($id);
        
        // Redirect to dashboard to refresh all statistics
        $this->redirectRoute('dashboard');
    }

    public function selectLocal(): void
    {
        ServerContext::switchToLocal();
        $this->redirectRoute('dashboard');
    }

    public function render()
    {
        $servers = Server::where('user_id', auth()->id())
            ->where('is_active', true)
            ->orderBy('is_local', 'desc')
            ->orderBy('name')
            ->get();

        return view('livewire.servers.server-selector', [
            'servers' => $servers,
        ]);
    }
}
