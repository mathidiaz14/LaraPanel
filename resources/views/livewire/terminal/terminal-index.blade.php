<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-terminal" style="color:var(--accent-light);margin-right:10px;"></i>
                Terminal Web
            </h1>
            <p class="page-subtitle">Acceso CLI directo al servidor (Modo Pseudo-TTY).</p>
        </div>
        <div>
            <span class="badge badge-warning" style="font-size:11px;">MODO BÁSICO</span>
        </div>
    </div>

    <div class="glass lp-panel" style="padding:0;overflow:hidden;display:flex;flex-direction:column;height:65vh;border-color:rgba(99,102,241,0.3);">
        <div style="background:rgba(0,0,0,0.4);padding:8px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--glass-border);">
            <div style="display:flex;gap:6px;">
                <div style="width:12px;height:12px;border-radius:50%;background:#ff5f56;"></div>
                <div style="width:12px;height:12px;border-radius:50%;background:#ffbd2e;"></div>
                <div style="width:12px;height:12px;border-radius:50%;background:#27c93f;"></div>
            </div>
            <div style="font-family:monospace;font-size:11px;color:var(--text-muted);">
                root@larapanel: <span id="prompt-cwd-display">{{ $cwd }}</span>
            </div>
            <div style="width:48px;"></div>
        </div>

        <div id="terminal-container" wire:ignore style="flex:1;padding:12px;background:#000;"></div>

        <form wire:submit="runCommand" style="display:none;">
            <input type="text" wire:model="command" id="hidden-cmd-input">
            <button type="submit">Run</button>
        </form>
    </div>

    @assets
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css" />
    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js"></script>
    <style>
        #terminal-container { width:100%; height:100%; overflow:hidden; position:relative; }
        .xterm .xterm-viewport { overflow-y:auto !important; }
    </style>
    @endassets

    @script
    <script>
        function initTerminal() {
            if (typeof window.Terminal === 'undefined' || typeof window.FitAddon === 'undefined') {
                setTimeout(initTerminal, 50);
                return;
            }

            const container = document.getElementById('terminal-container');
            if (!container) return;
            container.innerHTML = '';

            const term = new Terminal({
                cursorBlink: true,
                cursorStyle: 'block',
                theme: {
                    background: '#090b10', foreground: '#cdd6f4', cursor: '#6366f1',
                    selectionBackground: 'rgba(99, 102, 241, 0.3)', black: '#1e1e2e',
                    red: '#f38ba8', green: '#a6e3a1', yellow: '#f9e2af', blue: '#89b4fa',
                    magenta: '#cba6f7', cyan: '#94e2d5', white: '#bac2de'
                },
                fontFamily: 'Fira Code, Menlo, Monaco, "Courier New", monospace',
                fontSize: 13,
                scrollback: 2000
            });

            const fitAddon = new FitAddon.FitAddon();
            term.loadAddon(fitAddon);
            term.open(container);
            setTimeout(() => { try { fitAddon.fit(); } catch (e) {} }, 80);

            window.addEventListener('resize', () => {
                if (container.offsetParent !== null) {
                    try { fitAddon.fit(); } catch (e) {}
                }
            });

            let currentLine = '';
            let cursorPos = 0;
            let history = [];
            let historyIndex = -1;
            let tempDraft = '';
            let livewireCwd = '{{ $cwd }}';

            function promptPrefix() {
                return '\x1b[1;32mroot@larapanel\x1b[0m:\x1b[1;34m' + livewireCwd + '\x1b[0m# ';
            }

            function writePrompt(addNewline = true) {
                term.write((addNewline ? '\r\n' : '') + promptPrefix());
                const cwdEl = document.getElementById('prompt-cwd-display');
                if (cwdEl) cwdEl.textContent = livewireCwd;
            }

            function setLine(newLine) {
                term.write('\r\x1b[K' + promptPrefix() + newLine);
                currentLine = newLine;
                cursorPos = currentLine.length;
            }

            term.writeln('\x1b[1;36m=============================================================\x1b[0m');
            term.writeln('\x1b[1;32m  LaraPanel Web Terminal v2.0 (Pseudo-TTY Terminal)  \x1b[0m');
            term.writeln('\x1b[1;36m=============================================================\x1b[0m');
            term.writeln('Comandos administrativos (apt, systemctl, ufw, docker) cuentan con auto-sudo.\r\n');
            writePrompt(false);

            container.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                if (pastedText) handleInputText(pastedText);
            });

            function handleInputText(text) {
                const clean = text.replace(/[\r\n]+/g, ' ').replace(/[\x00-\x08\x0b-\x1f\x7f]/g, '');
                if (!clean.length) return;
                currentLine = currentLine.slice(0, cursorPos) + clean + currentLine.slice(cursorPos);
                term.write(clean + currentLine.slice(cursorPos + clean.length));
                const moveBack = currentLine.length - (cursorPos + clean.length);
                if (moveBack > 0) term.write(`\x1b[${moveBack}D`);
                cursorPos += clean.length;
            }

            term.onData(data => {
                if (data === '\r' || data === '\n') {
                    const cmd = currentLine.trim();
                    term.write('\r\n');
                    if (cmd === 'clear') {
                        term.clear(); currentLine = ''; cursorPos = 0; historyIndex = -1; writePrompt(false); return;
                    }
                    if (cmd !== '') {
                        if (!history.length || history[0] !== cmd) history.unshift(cmd);
                        if (history.length > 100) history.pop();
                        historyIndex = -1; tempDraft = '';
                        $wire.set('command', cmd); $wire.call('runCommand');
                    } else writePrompt(false);
                    currentLine = ''; cursorPos = 0;
                    return;
                }
                if (data === '\x7f' || data === '\x08') {
                    if (cursorPos > 0) {
                        currentLine = currentLine.slice(0, cursorPos - 1) + currentLine.slice(cursorPos);
                        cursorPos--; term.write('\b' + currentLine.slice(cursorPos) + ' \x1b[' + (currentLine.length - cursorPos + 1) + 'D');
                    }
                    return;
                }
                if (data === '\x1b[A' || data === '\x1bOA') {
                    if (history.length) { if (historyIndex === -1) tempDraft = currentLine; if (historyIndex < history.length - 1) setLine(history[++historyIndex]); }
                    return;
                }
                if (data === '\x1b[B' || data === '\x1bOB') {
                    if (historyIndex > -1) { historyIndex--; setLine(historyIndex === -1 ? tempDraft : history[historyIndex]); }
                    return;
                }
                if (data === '\x1b[D' || data === '\x1bOD') { if (cursorPos > 0) { cursorPos--; term.write('\x1b[D'); } return; }
                if (data === '\x1b[C' || data === '\x1bOC') { if (cursorPos < currentLine.length) { cursorPos++; term.write('\x1b[C'); } return; }
                if (data === '\x03') { term.write('^C'); currentLine = ''; cursorPos = 0; historyIndex = -1; writePrompt(true); return; }
                if (data === '\x0c') { term.clear(); term.write(promptPrefix() + currentLine); if (currentLine.length - cursorPos > 0) term.write(`\x1b[${currentLine.length - cursorPos}D`); return; }
                handleInputText(data);
            });

            $wire.on('terminal-output', (events) => {
                const data = events[0];
                livewireCwd = data.cwd;
                if (data.output) data.output.split('\n').forEach(line => { if (line) term.writeln(line.replace(/\r/g, '')); });
                writePrompt(false);
            });

            $wire.on('terminal-clear', () => { term.clear(); writePrompt(false); });
        }

        initTerminal();
    </script>
    @endscript
</div>
