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

        // Clear command?
        if ($cmd === 'clear') {
            $this->dispatch('terminal-clear');
            $this->command = '';
            return;
        }

        // Add to history
        array_unshift($this->history, $cmd);
        $this->history = array_slice($this->history, 0, 50); // Keep last 50
        $this->historyIndex = -1;

        // Execute via service
        $result = $term->execute($cmd, $this->cwd);
        
        // Update state
        $this->cwd = $result['cwd'];
        
        // Dispatch to frontend (Xterm.js)
        $this->dispatch('terminal-output', [
            'command' => $cmd,
            'output'  => $result->output(),
            'cwd'     => $this->cwd,
            'code'    => $result->exitCode
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
