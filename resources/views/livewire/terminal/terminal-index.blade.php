<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-terminal" style="color:var(--accent-light);margin-right:10px;"></i>
                Terminal Web
            </h1>
            <p class="page-subtitle">Acceso CLI interactivo al servidor con elevación de permisos (TTY Virtual).</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <span class="badge badge-success" style="font-size:11px;">
                <i class="fa-solid fa-shield-halved" style="margin-right:4px;"></i> ELEVACIÓN SUDO
            </span>
            <span class="badge badge-info" style="font-size:11px;">MODO TTY COMPLETO</span>
        </div>
    </div>

    <div class="glass lp-panel" style="padding:0;overflow:hidden;display:flex;flex-direction:column;height:68vh;border-color:rgba(99,102,241,0.3);">
        {{-- Terminal Header bar --}}
        <div style="background:rgba(0,0,0,0.5);padding:10px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--glass-border);">
            <div style="display:flex;gap:6px;align-items:center;">
                <div style="width:12px;height:12px;border-radius:50%;background:#ff5f56;"></div>
                <div style="width:12px;height:12px;border-radius:50%;background:#ffbd2e;"></div>
                <div style="width:12px;height:12px;border-radius:50%;background:#27c93f;"></div>
                <span style="margin-left:10px;font-family:monospace;font-size:11px;color:var(--text-muted);">
                    root@larapanel: <span id="prompt-cwd-display">{{ $cwd }}</span>
                </span>
            </div>
            <div style="display:flex;gap:12px;font-size:11px;color:var(--text-muted);">
                <span><kbd style="background:rgba(255,255,255,0.1);padding:2px 6px;border-radius:4px;">↑ / ↓</kbd> Historial</span>
                <span><kbd style="background:rgba(255,255,255,0.1);padding:2px 6px;border-radius:4px;">Ctrl+V</kbd> Pegar</span>
                <span><kbd style="background:rgba(255,255,255,0.1);padding:2px 6px;border-radius:4px;">Ctrl+L</kbd> Limpiar</span>
            </div>
        </div>

        {{-- Xterm Container --}}
        <div id="terminal-container" wire:ignore style="flex:1;padding:12px;background:#000;"></div>

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
                    background: '#090b10',
                    foreground: '#cdd6f4',
                    cursor: '#6366f1',
                    selectionBackground: 'rgba(99, 102, 241, 0.3)',
                    black: '#1e1e2e',
                    red: '#f38ba8',
                    green: '#a6e3a1',
                    yellow: '#f9e2af',
                    blue: '#89b4fa',
                    magenta: '#cba6f7',
                    cyan: '#94e2d5',
                    white: '#bac2de'
                },
                fontFamily: 'Fira Code, Menlo, Monaco, "Courier New", monospace',
                fontSize: 13,
                scrollback: 2000
            });

            const fitAddon = new FitAddon.FitAddon();
            term.loadAddon(fitAddon);
            term.open(container);
            
            setTimeout(() => {
                try { fitAddon.fit(); } catch(e) {}
            }, 80);

            window.addEventListener('resize', () => {
                if (container.offsetParent !== null) {
                    try { fitAddon.fit(); } catch(e) {}
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
                if (addNewline) {
                    term.write('\r\n' + promptPrefix());
                } else {
                    term.write(promptPrefix());
                }
                const cwdEl = document.getElementById('prompt-cwd-display');
                if (cwdEl) cwdEl.textContent = livewireCwd;
            }

            function setLine(newLine) {
                term.write('\r\x1b[K');
                term.write(promptPrefix());
                term.write(newLine);
                currentLine = newLine;
                cursorPos = currentLine.length;
            }

            term.writeln('\x1b[1;36m=============================================================\x1b[0m');
            term.writeln('\x1b[1;32m  LaraPanel Web Terminal v2.0 (Pseudo-TTY Terminal)  \x1b[0m');
            term.writeln('\x1b[1;36m=============================================================\x1b[0m');
            term.writeln('Comandos administrativos (apt, systemctl, ufw, docker) cuentan con auto-sudo.\r\n');
            writePrompt(false);

            // Handle Paste Events on container
            container.addEventListener('paste', (e) => {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                if (pastedText) {
                    handleInputText(pastedText);
                }
            });

            function handleInputText(text) {
                const clean = text.replace(/[\r\n]+/g, ' ').replace(/[\x00-\x08\x0b-\x1f\x7f]/g, '');
                if (clean.length === 0) return;

                if (cursorPos === currentLine.length) {
                    currentLine += clean;
                    cursorPos += clean.length;
                    term.write(clean);
                } else {
                    currentLine = currentLine.slice(0, cursorPos) + clean + currentLine.slice(cursorPos);
                    const tail = currentLine.slice(cursorPos);
                    term.write(tail);
                    cursorPos += clean.length;
                    const moveBack = currentLine.length - cursorPos;
                    if (moveBack > 0) {
                        term.write(`\x1b[${moveBack}D`);
                    }
                }
            }

            term.onData(data => {
                // Enter Key
                if (data === '\r' || data === '\n') {
                    const cmd = currentLine.trim();
                    term.write('\r\n');

                    if (cmd === 'clear') {
                        term.clear();
                        currentLine = '';
                        cursorPos = 0;
                        historyIndex = -1;
                        writePrompt(false);
                        return;
                    }

                    if (cmd !== '') {
                        if (history.length === 0 || history[0] !== cmd) {
                            history.unshift(cmd);
                            if (history.length > 100) history.pop();
                        }
                        historyIndex = -1;
                        tempDraft = '';

                        $wire.set('command', cmd);
                        $wire.call('runCommand');
                    } else {
                        currentLine = '';
                        cursorPos = 0;
                        writePrompt(false);
                    }
                    currentLine = '';
                    cursorPos = 0;
                    return;
                }

                // Backspace Key
                if (data === '\x7f' || data === '\x08') {
                    if (cursorPos > 0) {
                        if (cursorPos === currentLine.length) {
                            currentLine = currentLine.slice(0, -1);
                            cursorPos--;
                            term.write('\b \b');
                        } else {
                            currentLine = currentLine.slice(0, cursorPos - 1) + currentLine.slice(cursorPos);
                            cursorPos--;
                            term.write('\b');
                            term.write(currentLine.slice(cursorPos) + ' ');
                            const moveBack = currentLine.length - cursorPos + 1;
                            term.write(`\x1b[${moveBack}D`);
                        }
                    }
                    return;
                }

                // Escape Sequences (Arrows, Home, End, Delete)
                if (data.startsWith('\x1b')) {
                    // Up Arrow
                    if (data === '\x1b[A' || data === '\x1bOA') {
                        if (history.length > 0) {
                            if (historyIndex === -1) {
                                tempDraft = currentLine;
                            }
                            if (historyIndex < history.length - 1) {
                                historyIndex++;
                                setLine(history[historyIndex]);
                            }
                        }
                        return;
                    }

                    // Down Arrow
                    if (data === '\x1b[B' || data === '\x1bOB') {
                        if (historyIndex > -1) {
                            historyIndex--;
                            if (historyIndex === -1) {
                                setLine(tempDraft);
                            } else {
                                setLine(history[historyIndex]);
                            }
                        }
                        return;
                    }

                    // Left Arrow
                    if (data === '\x1b[D' || data === '\x1bOD') {
                        if (cursorPos > 0) {
                            cursorPos--;
                            term.write('\x1b[D');
                        }
                        return;
                    }

                    // Right Arrow
                    if (data === '\x1b[C' || data === '\x1bOC') {
                        if (cursorPos < currentLine.length) {
                            cursorPos++;
                            term.write('\x1b[C');
                        }
                        return;
                    }

                    // Home Key
                    if (data === '\x1b[H' || data === '\x1b[1~') {
                        if (cursorPos > 0) {
                            term.write(`\x1b[${cursorPos}D`);
                            cursorPos = 0;
                        }
                        return;
                    }

                    // End Key
                    if (data === '\x1b[F' || data === '\x1b[4~') {
                        if (cursorPos < currentLine.length) {
                            term.write(`\x1b[${currentLine.length - cursorPos}C`);
                            cursorPos = currentLine.length;
                        }
                        return;
                    }

                    // Delete Key
                    if (data === '\x1b[3~') {
                        if (cursorPos < currentLine.length) {
                            currentLine = currentLine.slice(0, cursorPos) + currentLine.slice(cursorPos + 1);
                            term.write(currentLine.slice(cursorPos) + ' ');
                            term.write(`\x1b[${currentLine.length - cursorPos + 1}D`);
                        }
                        return;
                    }

                    return;
                }

                // Ctrl+C
                if (data === '\x03') {
                    term.write('^C');
                    currentLine = '';
                    cursorPos = 0;
                    historyIndex = -1;
                    writePrompt(true);
                    return;
                }

                // Ctrl+L
                if (data === '\x0c') {
                    term.clear();
                    term.write(promptPrefix());
                    term.write(currentLine);
                    if (currentLine.length - cursorPos > 0) {
                        term.write(`\x1b[${currentLine.length - cursorPos}D`);
                    }
                    return;
                }

                // Normal printable characters or multi-character paste input
                handleInputText(data);
            });

            $wire.on('terminal-output', (events) => {
                const data = events[0];
                livewireCwd = data.cwd;
                
                if (data.output) {
                    const lines = data.output.split('\n');
                    for (let i = 0; i < lines.length; i++) {
                        if (i === lines.length - 1 && lines[i] === '') continue;
                        term.writeln(lines[i].replace(/\r/g, ''));
                    }
                }
                
                writePrompt(false);
            });

            $wire.on('terminal-clear', () => {
                term.clear();
                writePrompt(false);
            });
        }

        initTerminal();
    </script>
    @endscript
</div>
