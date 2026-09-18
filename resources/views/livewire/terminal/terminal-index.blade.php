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
            <select wire:model.live="selectedServerId" class="form-input" style="min-width:210px;">
                <option value="">Servidor local</option>
                @foreach($servers as $server)
                    <option value="{{ $server->id }}">{{ $server->is_local ? 'Local' : $server->name }} · {{ $server->hostname }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if($errorMessage)
        <div style="margin-bottom:14px;padding:11px 14px;border:1px solid rgba(239,68,68,.35);border-radius:var(--radius-sm);color:var(--danger);background:rgba(239,68,68,.08);font-size:12px;">
            <i class="fa-solid fa-triangle-exclamation"></i> {{ $errorMessage }}
        </div>
    @endif
    @if($notice)
        <div style="margin-bottom:14px;padding:11px 14px;border:1px solid rgba(245,158,11,.35);border-radius:var(--radius-sm);color:var(--warning);background:rgba(245,158,11,.08);font-size:12px;display:flex;justify-content:space-between;gap:12px;align-items:center;">
            <span><i class="fa-solid fa-shield-halved"></i> {{ $notice }}</span>
            @if($pendingCommand)
                <button wire:click="confirmCommand" class="btn btn-secondary btn-sm">Confirmar</button>
            @endif
        </div>
    @endif

    <section class="glass lp-panel" style="padding:0;overflow:hidden;border-color:rgba(79,70,229,.3);">
            <div style="background:var(--bg-base);padding:10px 14px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--glass-border);gap:12px;flex-wrap:wrap;">
                <div style="display:flex;gap:6px;align-items:center;">
                    <span style="font-family:monospace;font-size:11px;color:var(--text-muted);">{{ $cwd }}</span>
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
            <div wire:ignore style="height:calc(100vh - 300px);min-height:460px;max-height:900px;padding:12px 12px 24px;box-sizing:border-box;background:#090b10;">
                <div id="terminal-container" style="height:100%;width:100%;"></div>
            </div>
            <div style="padding:8px 14px;display:flex;gap:14px;font-size:11px;color:var(--text-muted);">
                <span><kbd>Tab</kbd> autocompletar</span><span><kbd>↑ ↓</kbd> historial</span><span><kbd>Ctrl+L</kbd> limpiar</span><span><kbd>Ctrl+C</kbd> cancelar línea</span>
                @if($exitCode !== null)<span style="margin-left:auto;color:{{ $exitCode === 0 ? '#a6e3a1' : '#f38ba8' }};">Salida: {{ $exitCode }} · {{ $durationMs ?? 0 }} ms</span>@endif
                <button onclick="copyTerminalOutput(this)" data-output="{{ base64_encode($output) }}" class="btn btn-ghost btn-sm" title="Copiar salida"><i class="fa-solid fa-copy"></i></button>
                <button onclick="downloadTerminalOutput(this)" data-output="{{ base64_encode($output) }}" class="btn btn-ghost btn-sm" title="Descargar salida"><i class="fa-solid fa-download"></i></button>
            </div>
        </section>
    </div>

    @assets
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css" />
    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js"></script>
    <style>
        #terminal-container { width:100%; overflow:hidden; position:relative; box-sizing:border-box; }
        #terminal-container .xterm { width:100%; height:100%; max-height:100%; }
        #terminal-container .xterm-viewport { height:100% !important; max-height:100%; overflow-y:auto !important; padding-bottom:0; box-sizing:border-box; }
        #terminal-container .xterm-screen { padding-bottom:0; }
        kbd { background:rgba(255,255,255,.1);padding:2px 5px;border-radius:3px; }
        @media (max-width:900px) { .page-header { flex-direction:column; } .page-header > div:last-child { justify-content:flex-start !important; } }
    </style>
    @endassets

    @script
    <script>
        window.copyTerminalOutput = (button) => navigator.clipboard?.writeText(atob(button.dataset.output || ''));
        window.downloadTerminalOutput = (button) => { const blob = new Blob([atob(button.dataset.output || '')], {type: 'text/plain'}); const link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = 'terminal-output.txt'; link.click(); URL.revokeObjectURL(link.href); };
        (() => {
            const container = document.getElementById('terminal-container');
            if (!container || !window.Terminal || !window.FitAddon) return;
            const term = new Terminal({ cursorBlink: true, cursorStyle: 'block', scrollback: 3000, fontFamily: 'Fira Code, Menlo, Monaco, monospace', fontSize: 13, theme: { background: '#090b10', foreground: '#cdd6f4', cursor: '#6366f1', selectionBackground: 'rgba(99,102,241,.3)' } });
            const fit = new FitAddon.FitAddon();
            term.loadAddon(fit); term.open(container); setTimeout(() => { fit.fit(); term.scrollToBottom(); }, 80);
            const resizeTerminal = () => { if (container.offsetParent !== null) { fit.fit(); term.scrollToBottom(); } };
            window.addEventListener('resize', resizeTerminal);
            if (window.ResizeObserver) new ResizeObserver(resizeTerminal).observe(container);
            const pasteClipboard = () => {
                const apply = (text) => { if (text) { insert(text); term.focus(); } };
                const legacyPaste = () => {
                    const ta = document.createElement('textarea');
                    ta.style.cssText = 'position:fixed;left:-9999px;top:0;width:1px;height:1px;opacity:0;pointer-events:none;';
                    ta.setAttribute('tabindex', '-1');
                    document.body.appendChild(ta);
                    ta.focus();
                    let ok = false;
                    try { ok = document.execCommand('paste'); } catch (e) { ok = false; }
                    if (ok && ta.value) apply(ta.value);
                    ta.remove();
                    term.focus();
                };
                if (navigator.clipboard && navigator.clipboard.readText) navigator.clipboard.readText().then(apply).catch(legacyPaste);
                else legacyPaste();
            };
            container.addEventListener('contextmenu', (e) => { e.preventDefault(); pasteClipboard(); });
            let line = ''; let position = 0; let history = @js($history); let historyIndex = -1; let draft = ''; let currentCwd = @js($cwd);
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
            $wire.on('terminal-output', event => { const data = event[0] || event; if (data.cwd) currentCwd = data.cwd; if (data.output) data.output.split('\n').forEach(row => term.writeln(row.replace(/\r/g, ''))); term.write(prompt()); term.scrollToBottom(); });
            $wire.on('terminal-clear', () => { clearTerminal(); });
        })();
    </script>
    @endscript
</div>
