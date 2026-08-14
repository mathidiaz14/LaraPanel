<div wire:poll.1s="refreshJob">
    <div class="page-header" style="gap:16px;align-items:flex-start;">
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-terminal" style="color:var(--accent-light);margin-right:10px;"></i>
                Terminal Web
            </h1>
            <p class="page-subtitle">Ejecución segura de comandos sin WebSocket, con historial, tareas y herramientas rápidas.</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end;">
            <select wire:model.live="selectedServerId" class="form-control form-control-sm" style="min-width:210px;">
                <option value="">Servidor local</option>
                @foreach($servers as $server)
                    <option value="{{ $server->id }}">{{ $server->is_local ? 'Local' : $server->name }} · {{ $server->hostname }}</option>
                @endforeach
            </select>
            <span class="badge badge-warning" style="font-size:11px;">SIN WEBSOCKET</span>
        </div>
    </div>

    @if($errorMessage)
        <div style="margin-bottom:14px;padding:11px 14px;border:1px solid rgba(243,139,168,.35);border-radius:8px;color:#f38ba8;background:rgba(243,139,168,.08);font-size:12px;">
            <i class="fa-solid fa-triangle-exclamation"></i> {{ $errorMessage }}
        </div>
    @endif
    @if($notice)
        <div style="margin-bottom:14px;padding:11px 14px;border:1px solid rgba(249,226,175,.35);border-radius:8px;color:#f9e2af;background:rgba(249,226,175,.08);font-size:12px;display:flex;justify-content:space-between;gap:12px;align-items:center;">
            <span><i class="fa-solid fa-shield-halved"></i> {{ $notice }}</span>
            @if($pendingCommand)
                <button wire:click="confirmCommand" class="btn btn-warning btn-sm">Confirmar</button>
            @endif
        </div>
    @endif

    <div style="display:grid;grid-template-columns:minmax(0,1fr) 290px;gap:16px;align-items:start;">
        <section class="glass lp-panel" style="padding:0;overflow:hidden;border-color:rgba(99,102,241,.3);">
            <div style="background:rgba(0,0,0,.45);padding:10px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--glass-border);gap:12px;flex-wrap:wrap;">
                <div style="display:flex;gap:6px;align-items:center;">
                    <span style="width:11px;height:11px;border-radius:50%;background:#ff5f56;"></span>
                    <span style="width:11px;height:11px;border-radius:50%;background:#ffbd2e;"></span>
                    <span style="width:11px;height:11px;border-radius:50%;background:#27c93f;"></span>
                    <span style="margin-left:8px;font-family:monospace;font-size:11px;color:var(--text-muted);">{{ $cwd }}</span>
                </div>
                <div style="display:flex;gap:10px;align-items:center;font-size:11px;color:var(--text-muted);">
                    <label style="display:flex;gap:5px;align-items:center;cursor:pointer;">
                        <input type="checkbox" wire:model="background"> Ejecutar en segundo plano
                    </label>
                    @if($activeJobId)
                        <span class="badge badge-warning">{{ strtoupper($jobStatus) }} #{{ $activeJobId }}</span>
                        <button wire:click="cancelJob" class="btn btn-danger btn-sm">Cancelar</button>
                    @endif
                </div>
            </div>
            <div id="terminal-container" wire:ignore style="height:360px;box-sizing:border-box;padding:12px;background:#090b10;"></div>
            <div style="padding:8px 14px;display:flex;gap:14px;font-size:11px;color:var(--text-muted);">
                <span><kbd>Tab</kbd> autocompletar</span><span><kbd>↑ ↓</kbd> historial</span><span><kbd>Ctrl+L</kbd> limpiar</span><span><kbd>Ctrl+C</kbd> cancelar línea</span>
                @if($exitCode !== null)<span style="margin-left:auto;color:{{ $exitCode === 0 ? '#a6e3a1' : '#f38ba8' }};">Salida: {{ $exitCode }} · {{ $durationMs ?? 0 }} ms</span>@endif
            </div>
        </section>

        <aside style="display:flex;flex-direction:column;gap:16px;">
            <section class="glass" style="padding:15px;">
                <h3 style="font-size:13px;margin:0 0 12px;color:var(--text-primary);"><i class="fa-solid fa-bolt" style="color:#f9e2af;"></i> Comandos rápidos</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:7px;">
                    @foreach($quickCommands as $quick)
                        <button wire:click="runQuickCommand('{{ addslashes($quick['command']) }}')" class="btn btn-ghost btn-sm" style="font-size:10px;text-align:left;padding:8px;">
                            <i class="fa-solid {{ $quick['icon'] }}" style="width:15px;color:#89b4fa;"></i> {{ $quick['label'] }}
                        </button>
                    @endforeach
                </div>
            </section>

            <section class="glass" style="padding:15px;">
                <h3 style="font-size:13px;margin:0 0 12px;color:var(--text-primary);"><i class="fa-solid fa-screwdriver-wrench" style="color:#a6e3a1;"></i> Mantenimiento</h3>
                <div style="display:flex;flex-direction:column;gap:7px;">
                    <button wire:click="runMaintenance('optimize')" class="btn btn-ghost btn-sm" style="text-align:left;">Optimizar Laravel</button>
                    <button wire:click="runMaintenance('clear-cache')" class="btn btn-ghost btn-sm" style="text-align:left;">Limpiar cachés</button>
                    <button wire:click="runMaintenance('git-status')" class="btn btn-ghost btn-sm" style="text-align:left;">Estado del proyecto</button>
                    <button wire:click="runMaintenance('git-pull')" class="btn btn-ghost btn-sm" style="text-align:left;color:#f9e2af;">Actualizar proyecto</button>
                </div>
            </section>

            <section class="glass" style="padding:15px;">
                <h3 style="font-size:13px;margin:0 0 10px;color:var(--text-primary);"><i class="fa-solid fa-folder-tree" style="color:#cba6f7;"></i> Archivos en {{ $cwd }}</h3>
                @if(!$selectedServerId || $servers->firstWhere('id', $selectedServerId)?->is_local)
                    <label class="btn btn-ghost btn-sm" style="display:block;text-align:center;margin-bottom:8px;cursor:pointer;">
                        <i class="fa-solid fa-upload"></i> Subir archivos
                        <input type="file" wire:model="uploads" multiple hidden>
                    </label>
                @endif
                @if($files)
                    <div style="max-height:190px;overflow:auto;display:flex;flex-direction:column;gap:3px;">
                        @foreach($files as $file)
                            <div style="display:flex;align-items:center;gap:3px;">
                                <button wire:click="useFile('{{ addslashes($file['name']) }}', {{ $file['is_dir'] ? 'true' : 'false' }})" class="btn btn-ghost btn-sm" style="font-family:monospace;font-size:10px;text-align:left;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;">
                                    <i class="fa-solid {{ $file['is_dir'] ? 'fa-folder' : 'fa-file' }}" style="color:{{ $file['is_dir'] ? '#f9e2af' : '#bac2de' }};width:16px;"></i>{{ $file['name'] }}
                                </button>
                                @if(!$file['is_dir'])
                                    <button wire:click="downloadFile('{{ addslashes($file['name']) }}')" class="btn btn-ghost btn-sm" title="Descargar"><i class="fa-solid fa-download"></i></button>
                                @endif
                                <button onclick="renameTerminalFile({{ Js::from($file['name']) }})" class="btn btn-ghost btn-sm" title="Renombrar"><i class="fa-solid fa-pen"></i></button>
                                <button wire:click="deleteFile('{{ addslashes($file['name']) }}')" wire:confirm="¿Eliminar {{ addslashes($file['name']) }}?" class="btn btn-ghost btn-sm" style="color:#f38ba8;" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p style="font-size:11px;color:var(--text-muted);margin:0;">No hay archivos locales disponibles para este servidor.</p>
                @endif
            </section>
        </aside>
    </div>

    <section class="glass" style="padding:15px;margin-top:16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;gap:10px;">
            <h3 style="font-size:13px;margin:0;color:var(--text-primary);"><i class="fa-solid fa-clock-rotate-left" style="color:#89b4fa;"></i> Historial persistente</h3>
            <span style="font-size:10px;color:var(--text-muted);">Últimos 30 comandos · privado por usuario</span>
        </div>
        <div style="overflow:auto;max-height:230px;">
            <table style="width:100%;font-size:11px;border-collapse:collapse;">
                <thead><tr style="color:var(--text-muted);text-align:left;"><th style="padding:6px;">Comando</th><th style="padding:6px;">Servidor</th><th style="padding:6px;">Estado</th><th style="padding:6px;">Fecha</th><th style="padding:6px;"></th></tr></thead>
                <tbody>
                    @forelse($history as $item)
                        <tr style="border-top:1px solid rgba(255,255,255,.05);">
                            <td style="padding:7px;font-family:monospace;color:#cdd6f4;max-width:430px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $item['command'] }}</td>
                            <td style="padding:7px;color:var(--text-muted);">{{ $item['server'] }}</td>
                            <td style="padding:7px;color:{{ $item['status'] === 'success' ? '#a6e3a1' : ($item['status'] === 'failed' ? '#f38ba8' : '#f9e2af') }};">{{ $item['status'] }}@if($item['exit_code'] !== null) ({{ $item['exit_code'] }}) @endif</td>
                            <td style="padding:7px;color:var(--text-muted);">{{ $item['created_at'] }}</td>
                            <td style="padding:7px;text-align:right;"><button wire:click="$set('command', '{{ addslashes($item['command']) }}')" class="btn btn-ghost btn-sm" title="Repetir"><i class="fa-solid fa-rotate-right"></i></button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="padding:18px;text-align:center;color:var(--text-muted);">Todavía no hay comandos ejecutados.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="display:flex;gap:7px;margin-top:10px;">
            <button onclick="copyTerminalOutput(this)" data-output="{{ base64_encode($output) }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-copy"></i> Copiar salida</button>
            <button onclick="downloadTerminalOutput(this)" data-output="{{ base64_encode($output) }}" class="btn btn-ghost btn-sm"><i class="fa-solid fa-download"></i> Descargar salida</button>
        </div>
    </section>

    @assets
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css" />
    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js"></script>
    <style>
        #terminal-container { width:100%; overflow:hidden; position:relative; box-sizing:border-box; }
        #terminal-container .xterm { width:100%; height:100%; }
        #terminal-container .xterm-viewport { overflow-y:auto !important; padding-bottom:14px; box-sizing:border-box; }
        kbd { background:rgba(255,255,255,.1);padding:2px 5px;border-radius:3px; }
        @media (max-width:900px) { .page-header { flex-direction:column; } .page-header > div:last-child { justify-content:flex-start !important; } }
        @media (max-width:760px) { .glass.lp-panel + aside, aside { display:block; } [style*="grid-template-columns:minmax(0,1fr) 290px"] { display:flex !important; flex-direction:column; } }
    </style>
    @endassets

    @script
    <script>
        window.copyTerminalOutput = (button) => navigator.clipboard?.writeText(atob(button.dataset.output || ''));
        window.downloadTerminalOutput = (button) => { const blob = new Blob([atob(button.dataset.output || '')], {type: 'text/plain'}); const link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = 'terminal-output.txt'; link.click(); URL.revokeObjectURL(link.href); };
        window.renameTerminalFile = (name) => { const next = window.prompt('Nuevo nombre', name); if (next) Livewire.dispatch('terminal-rename-file', {name, next}); };
        (() => {
            const container = document.getElementById('terminal-container');
            if (!container || !window.Terminal || !window.FitAddon) return;
            const term = new Terminal({ cursorBlink: true, cursorStyle: 'block', scrollback: 3000, fontFamily: 'Fira Code, Menlo, Monaco, monospace', fontSize: 13, theme: { background: '#090b10', foreground: '#cdd6f4', cursor: '#6366f1', selectionBackground: 'rgba(99,102,241,.3)' } });
            const fit = new FitAddon.FitAddon();
            term.loadAddon(fit); term.open(container); setTimeout(() => fit.fit(), 80);
            const resizeTerminal = () => { if (container.offsetParent !== null) fit.fit(); };
            window.addEventListener('resize', resizeTerminal);
            if (window.ResizeObserver) new ResizeObserver(resizeTerminal).observe(container);
            let line = ''; let position = 0; let history = []; let historyIndex = -1; let draft = ''; let currentCwd = @js($cwd);
            const suggestions = @json($suggestions);
            const prompt = () => '\x1b[1;32mroot@larapanel\x1b[0m:\x1b[1;34m' + currentCwd + '\x1b[0m# ';
            const redraw = (value = line) => { term.write('\r\x1b[K' + prompt() + value); line = value; position = value.length; };
            const clearTerminal = () => { term.reset(); line = ''; position = 0; historyIndex = -1; draft = ''; term.write(prompt()); };
            term.writeln('\x1b[1;36mLaraPanel Web Terminal · HTTP + Livewire\x1b[0m');
            term.writeln('No se usa WebSocket. Tab completa comandos y las tareas largas usan polling.\r\n');
            term.write(prompt());
            const insert = (text) => { const clean = text.replace(/[\r\n]+/g, ' ').replace(/[\x00-\x1f\x7f]/g, ''); line = line.slice(0, position) + clean + line.slice(position); term.write(clean + line.slice(position + clean.length)); const back = line.length - position - clean.length; if (back > 0) term.write(`\x1b[${back}D`); position += clean.length; };
            term.onData(data => {
                if (data === '\r' || data === '\n') { const command = line.trim(); term.write('\r\n'); if (!command) { term.write(prompt()); return; } if (command === 'clear') { clearTerminal(); return; } history = [command, ...history.filter(item => item !== command)].slice(0, 100); historyIndex = -1; draft = ''; $wire.set('command', command); $wire.call('runCommand'); line = ''; position = 0; return; }
                if (data === '\x03') { term.write('^C\r\n' + prompt()); line = ''; position = 0; return; }
                if (data === '\x0c') { const currentLine = line; const currentPosition = position; clearTerminal(); line = currentLine; position = currentPosition; term.write(line); if (line.length - position) term.write(`\x1b[${line.length - position}D`); return; }
                if (data === '\t') { const match = suggestions.find(item => item.startsWith(line)); if (match) redraw(match); return; }
                if (data === '\x7f' || data === '\x08') { if (position > 0) { line = line.slice(0, position - 1) + line.slice(position); position--; term.write('\b' + line.slice(position) + ' \x1b[' + (line.length - position + 1) + 'D'); } return; }
                if (data === '\x1b[A' || data === '\x1bOA') { if (history.length) { if (historyIndex < 0) draft = line; historyIndex = Math.min(historyIndex + 1, history.length - 1); redraw(history[historyIndex]); } return; }
                if (data === '\x1b[B' || data === '\x1bOB') { if (historyIndex >= 0) { historyIndex--; redraw(historyIndex < 0 ? draft : history[historyIndex]); } return; }
                if (data === '\x1b[D' || data === '\x1bOD') { if (position > 0) { position--; term.write('\x1b[D'); } return; }
                if (data === '\x1b[C' || data === '\x1bOC') { if (position < line.length) { position++; term.write('\x1b[C'); } return; }
                insert(data);
            });
            $wire.on('terminal-output', event => { const data = event[0] || event; if (data.cwd) currentCwd = data.cwd; if (data.output) data.output.split('\n').forEach(row => term.writeln(row.replace(/\r/g, ''))); term.write(prompt()); });
            $wire.on('terminal-clear', () => { clearTerminal(); });
            $wire.on('terminal-rename-file', event => { const data = event[0] || event; $wire.call('renameFile', data.name, data.next); });
        })();
    </script>
    @endscript
</div>
