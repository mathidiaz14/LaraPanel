<div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
        <div>
            <h1 style="font-size:20px;font-weight:700;margin-bottom:4px;">
                <i class="fa-solid fa-terminal" style="color:var(--accent-light);margin-right:10px;"></i>
                Terminal Web
            </h1>
            <p style="color:var(--text-secondary);font-size:13px;">Acceso CLI directo al servidor (Modo Pseudo-TTY).</p>
        </div>
        <div>
            <span class="badge badge-warning" style="font-size:11px;">MODO BÁSICO</span>
        </div>
    </div>

    <div class="glass" style="padding:0;overflow:hidden;display:flex;flex-direction:column;height:65vh;border-color:rgba(99,102,241,0.3);">
        {{-- Terminal Header bar --}}
        <div style="background:rgba(0,0,0,0.4);padding:8px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--glass-border);">
            <div style="display:flex;gap:6px;">
                <div style="width:12px;height:12px;border-radius:50%;background:#ff5f56;"></div>
                <div style="width:12px;height:12px;border-radius:50%;background:#ffbd2e;"></div>
                <div style="width:12px;height:12px;border-radius:50%;background:#27c93f;"></div>
            </div>
            <div style="font-family:monospace;font-size:11px;color:var(--text-muted);">
                root@larapanel:~
            </div>
            <div style="width:48px;"></div>
        </div>

        {{-- Xterm Container --}}
        <div id="terminal-container" style="flex:1;padding:12px;background:#000;"></div>

        {{-- Hidden Livewire Form --}}
        <form wire:submit="runCommand" style="display:none;">
            <input type="text" wire:model="command" id="hidden-cmd-input">
            <button type="submit">Run</button>
        </form>
    </div>

    {{-- CDN for Xterm.js --}}
    @assets
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css" />
    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js"></script>
    <style>
        /* Fix for xterm.js layout stretching */
        #terminal-container {
            width: 100%;
            height: 100%;
            overflow: hidden;
            position: relative;
        }
        .xterm .xterm-viewport {
            overflow-y: auto !important;
        }
    </style>
    @endassets

    @script
    <script>
        function initTerminal() {
            // Wait for Xterm.js to be downloaded and parsed by the browser
            if (typeof window.Terminal === 'undefined' || typeof window.FitAddon === 'undefined') {
                setTimeout(initTerminal, 50);
                return;
            }

            // Clean up any existing terminal instance if Livewire re-inits
            document.getElementById('terminal-container').innerHTML = '';

            const term = new Terminal({
                cursorBlink: true,
                theme: { background: '#000000', foreground: '#cdd6f4' },
                fontFamily: 'monospace',
                fontSize: 13,
                scrollback: 1000
            });

            const fitAddon = new FitAddon.FitAddon();
            term.loadAddon(fitAddon);
            
            term.open(document.getElementById('terminal-container'));
            
            // Give it a tiny delay to ensure DOM is fully painted before fitting
            setTimeout(() => {
                fitAddon.fit();
            }, 50);

            window.addEventListener('resize', () => {
                if (document.getElementById('terminal-container').offsetParent !== null) {
                    fitAddon.fit();
                }
            });

            let currentLine = '';
            let livewireCwd = '{{ $cwd }}';

            function writePrompt() {
                term.write('\r\n\x1b[1;32mroot@larapanel\x1b[0m:\x1b[1;34m' + livewireCwd + '\x1b[0m# ');
            }

            term.writeln('Welcome to LaraPanel Web Terminal (Pseudo-TTY mode).');
            term.writeln('Type commands and press Enter. Interactive commands are not supported.');
            term.write('\r\n\x1b[1;32mroot@larapanel\x1b[0m:\x1b[1;34m' + livewireCwd + '\x1b[0m# ');

            term.onKey(e => {
                const printable = !e.domEvent.altKey && !e.domEvent.altGraphKey && !e.domEvent.ctrlKey && !e.domEvent.metaKey;
                
                if (e.domEvent.keyCode === 13) { // Enter
                    if (currentLine.trim() === 'clear') {
                        term.clear();
                        currentLine = '';
                        writePrompt();
                        return;
                    }
                    if (currentLine.trim() !== '') {
                        term.write('\r\n');
                        // Execute via Livewire 3 $wire
                        $wire.set('command', currentLine);
                        $wire.call('runCommand');
                        currentLine = '';
                    } else {
                        writePrompt();
                    }
                } else if (e.domEvent.keyCode === 8) { // Backspace
                    if (currentLine.length > 0) {
                        currentLine = currentLine.substring(0, currentLine.length - 1);
                        term.write('\b \b');
                    }
                } else if (printable) {
                    currentLine += e.key;
                    term.write(e.key);
                }
            });

            $wire.on('terminal-output', (events) => {
                const data = events[0];
                livewireCwd = data.cwd;
                
                const lines = data.output.split('\n');
                for (let i = 0; i < lines.length; i++) {
                    if (lines[i] !== '') {
                        term.writeln(lines[i].replace(/\r/g, ''));
                    }
                }
                
                writePrompt();
            });

            $wire.on('terminal-clear', () => {
                term.clear();
                writePrompt();
            });
        }

        // Boot
        initTerminal();
    </script>
    @endscript
</div>
