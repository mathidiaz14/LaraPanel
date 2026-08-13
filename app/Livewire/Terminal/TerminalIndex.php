<?php

namespace App\Livewire\Terminal;

use App\Services\TerminalService;
use Livewire\Component;

class TerminalIndex extends Component
{
    public string $command = '';
    public string $cwd = '/var/www';
    public array $history = [];
    public int $historyIndex = -1;

    public function runCommand(TerminalService $term): void
    {
        $cmd = trim($this->command);
        if (empty($cmd)) return;

        if ($cmd === 'clear') {
            $this->dispatch('terminal-clear');
            $this->command = '';
            return;
        }

        array_unshift($this->history, $cmd);
        $this->history = array_slice($this->history, 0, 50);
        $this->historyIndex = -1;

        $result = $term->execute($cmd, $this->cwd);
        $this->cwd = $result['cwd'];

        $this->dispatch('terminal-output', [
            'command' => $cmd,
            'output'  => $result['output'],
            'cwd'     => $this->cwd,
            'code'    => $result['code'],
        ]);

        $this->command = '';
    }

    public function render()
    {
        return view('livewire.terminal.terminal-index')->layout('layouts.app', [
            'title'      => 'Terminal Web',
            'breadcrumb' => '<span>Avanzado</span> / <strong>Terminal</strong>',
        ]);
    }
}
