<div>
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fa-solid fa-terminal" style="color:var(--accent-light);margin-right:10px;"></i>
                Terminal Web
            </h1>
            <p class="page-subtitle">Sesión PTY en tiempo real sobre el servidor local o un VPS remoto.</p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <span id="terminal-status" class="badge badge-secondary" style="font-size:11px;">DESCONECTADA</span>
            <button id="terminal-disconnect" type="button" class="btn btn-danger btn-sm" hidden>
                <i class="fa-solid fa-stop"></i> Cerrar sesión
            </button>
        </div>
    </div>

    <div class="glass lp-panel" style="padding:0;overflow:hidden;display:flex;flex-direction:column;height:68vh;border-color:rgba(99,102,241,0.3);">
        <div style="background:rgba(0,0,0,0.5);padding:10px 16px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid var(--glass-border);gap:16px;flex-wrap:wrap;">
            <div style="display:flex;gap:6px;align-items:center;min-width:0;">
                <div style="width:12px;height:12px;border-radius:50%;background:#ff5f56;"></div>
                <div style="width:12px;height:12px;border-radius:50%;background:#ffbd2e;"></div>
                <div style="width:12px;height:12px;border-radius:50%;background:#27c93f;"></div>
                <span style="margin-left:10px;font-family:monospace;font-size:11px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <span id="terminal-target">Sin sesión</span>
                </span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;">
                <label for="terminal-server" style="font-size:11px;color:var(--text-muted);">Destino</label>
                <select id="terminal-server" class="form-control form-control-sm" style="min-width:190px;max-width:260px;">
                    <option value="local">Este servidor (local)</option>
                    @foreach ($remoteServers as $server)
                        <option value="ssh:{{ $server->id }}">{{ $server->name }} ({{ $server->hostname }})</option>
                    @endforeach
                </select>
                <button id="terminal-connect" type="button" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plug"></i> Conectar
                </button>
            </div>
        </div>

        <div id="terminal-container" wire:ignore style="flex:1;padding:12px;background:#090b10;"></div>
    </div>

    @assets
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/xterm@5.3.0/css/xterm.css" />
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm@5.3.0/lib/xterm.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xterm-addon-fit@0.8.0/lib/xterm-addon-fit.js"></script>
    <style>
        #terminal-container { width:100%; height:100%; overflow:hidden; position:relative; }
        #terminal-container .xterm { height:100%; }
        .xterm .xterm-viewport { overflow-y:auto !important; }
        @media (max-width: 700px) {
            #terminal-server { min-width:150px !important; max-width:180px !important; }
        }
    </style>
    @endassets

    @script
    <script>
        (() => {
            const container = document.getElementById('terminal-container');
            const connectButton = document.getElementById('terminal-connect');
            const disconnectButton = document.getElementById('terminal-disconnect');
            const serverSelect = document.getElementById('terminal-server');
            const status = document.getElementById('terminal-status');
            const target = document.getElementById('terminal-target');
            const reverb = @json($reverb);
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

            if (!container || !connectButton || !window.Pusher || !window.Terminal || !window.FitAddon) {
                console.error("LaraPanel Terminal: Faltan dependencias frontend.");
                return;
            }

            let terminal;
            let fitAddon;
            let pusher = null;
            let channel = null;
            let sessionId = null;
            let sessionToken = null;
            let connected = false;

            const setStatus = (label, tone = 'secondary') => {
                status.textContent = label;
                status.className = `badge badge-${tone}`;
            };

            const decodeBase64 = (value) => {
                try {
                    const binary = atob(value);
                    const bytes = new Uint8Array(binary.length);
                    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
                    return bytes;
                } catch (e) {
                    return new TextEncoder().encode(value);
                }
            };

            const encodeBase64 = (value) => {
                const bytes = new TextEncoder().encode(value);
                let binary = '';
                for (const byte of bytes) binary += String.fromCharCode(byte);
                return btoa(binary);
            };

            const writeNotice = (message, color = '\x1b[1;33m') => {
                if (terminal) {
                    terminal.writeln(`\r\n${color}${message}\x1b[0m`);
                }
            };

            const writeError = (title, details) => {
                if (terminal) {
                    terminal.writeln(`\r\n\x1b[1;31m[ERROR] ${title}\x1b[0m`);
                    if (details) {
                        terminal.writeln(`\x1b[0;31m  Detalle: ${details}\x1b[0m`);
                    }
                }
            };

            const sendResize = () => {
                if (!channel || !connected || !terminal) return;
                fitAddon.fit();
                channel.trigger('client-terminal-resize', {
                    cols: terminal.cols,
                    rows: terminal.rows,
                });
            };

            const destroySession = async (notify = true) => {
                connected = false;
                if (channel) channel.unbind_all();
                if (pusher) pusher.disconnect();
                channel = null;
                pusher = null;

                if (sessionId) {
                    try {
                        await fetch(`/terminal/session/${sessionId}`, {
                            method: 'DELETE',
                            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        });
                    } catch (error) {
                        // Cleanup handled by server
                    }
                }

                sessionId = null;
                sessionToken = null;
                disconnectButton.hidden = true;
                connectButton.hidden = false;
                serverSelect.disabled = false;
                target.textContent = 'Sin sesión';
                setStatus('DESCONECTADA');
                if (notify && terminal) writeNotice('Sesión cerrada.', '\x1b[1;30m');
            };

            const createSession = async () => {
                const selected = serverSelect.value;
                const [type, serverId] = selected.split(':');
                const body = { type };
                if (type === 'ssh') body.server_id = Number(serverId);

                writeNotice('[1/3] Solicitando sesión de terminal al backend...', '\x1b[1;34m');

                let response;
                try {
                    response = await fetch('{{ route('terminal.session.store') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(body),
                    });
                } catch (netErr) {
                    throw new Error(`Error de red al conectar con el servidor: ${netErr.message}`);
                }

                const responseText = await response.text();
                let payload = {};
                try {
                    payload = JSON.parse(responseText);
                } catch (e) {
                    if (response.status === 419) {
                        throw new Error("HTTP 419 (Página o Token CSRF expirado). Por favor recarga la página.");
                    }
                    if (response.status === 403) {
                        throw new Error("HTTP 403 (Acceso Denegado). Tu usuario no tiene permisos de administrador.");
                    }
                    throw new Error(`HTTP ${response.status}: ${responseText.substring(0, 150)}`);
                }

                if (!response.ok) {
                    const errorMsg = payload.message || payload.error || `Error HTTP ${response.status}`;
                    throw new Error(errorMsg);
                }

                writeNotice('[2/3] Sesión reservada correctamente.', '\x1b[1;32m');
                return payload.data;
            };

            const connect = async () => {
                if (connected) return;

                connectButton.disabled = true;
                serverSelect.disabled = true;
                setStatus('CONECTANDO', 'warning');

                try {
                    const session = await createSession();
                    sessionId = session.session_id;
                    sessionToken = session.token;
                    target.textContent = session.type === 'ssh' ? session.server.name : 'www-data@servidor-local';

                    // Resolver host de WebSocket
                    let wsHost = (reverb.host && !['localhost', '127.0.0.1'].includes(reverb.host))
                        ? reverb.host
                        : window.location.hostname;
                    const isSecure = reverb.scheme === 'https' || window.location.protocol === 'https:';
                    
                    // Si el puerto de reverb es el puerto interno (ej. 8081 o 8080), conectar a través del puerto público de Nginx (443 o 80)
                    let wsPort = parseInt(reverb.port, 10);
                    if (!wsPort || isNaN(wsPort) || wsPort === 8081 || wsPort === 8080) {
                        wsPort = parseInt(window.location.port || (isSecure ? 443 : 80), 10);
                    }

                    writeNotice(`[3/3] Conectando a WebSocket (${isSecure ? 'wss' : 'ws'}://${wsHost}:${wsPort})...`, '\x1b[1;34m');

                    pusher = new Pusher(reverb.key, {
                        wsHost: wsHost,
                        wsPort: wsPort,
                        wssPort: wsPort,
                        forceTLS: isSecure,
                        enabledTransports: ['ws', 'wss'],
                        authEndpoint: '{{ url('/broadcasting/auth') }}',
                        auth: { headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } },
                    });

                    channel = pusher.subscribe(session.channel);

                    channel.bind('pusher:subscription_error', (error) => {
                        const code = error?.status || error?.data?.code || 'desconocido';
                        const details = error?.error || error?.message || error?.type || (typeof error === 'object' ? JSON.stringify(error) : error);
                        writeError(`Falló la autenticación en el canal WebSocket (HTTP ${code})`, details);
                        setStatus('ERROR AUTH', 'danger');
                    });

                    channel.bind('pusher:subscription_succeeded', () => {
                        connected = true;
                        setStatus('CONECTADA', 'success');
                        connectButton.hidden = true;
                        disconnectButton.hidden = false;
                        terminal.focus();
                        fitAddon.fit();
                        channel.trigger('client-terminal-attach', { token: sessionToken });
                        sendResize();
                    });

                    channel.bind('terminal-attached', () => {
                        setStatus('CONECTADA', 'success');
                        writeNotice('¡Conexión establecida con el PTY interactivo!', '\x1b[1;32m');
                    });

                    channel.bind('terminal-output', (data) => {
                        if (data?.b64) terminal.write(decodeBase64(data.b64));
                    });

                    channel.bind('terminal-error', (data) => {
                        writeError('Error devuelto por la terminal', data?.message || 'Error desconocido.');
                        setStatus('ERROR', 'danger');
                    });

                    channel.bind('terminal-exit', (data) => {
                        connected = false;
                        writeNotice(`El proceso de terminal ha finalizado (código de salida: ${data?.code ?? 0}).`, '\x1b[1;33m');
                        setStatus('CERRADA', 'secondary');
                        disconnectButton.hidden = true;
                        connectButton.hidden = false;
                        serverSelect.disabled = false;
                    });

                    pusher.connection.bind('state_change', (states) => {
                        if (states.current === 'connected') setStatus('CONECTADA', 'success');
                        if (states.current === 'unavailable' || states.current === 'failed') {
                            writeError(`WebSocket Reverb no disponible (Estado: ${states.current})`,
                                       `Verifica que Reverb esté corriendo en el VPS ('supervisorctl status larapanel-reverb').`);
                            setStatus('ERROR WS', 'danger');
                        }
                    });

                    pusher.connection.bind('error', (error) => {
                        const message = error?.error?.data?.message || error?.message || (typeof error === 'object' ? JSON.stringify(error) : 'Error de conexión.');
                        writeError('Error de conexión WebSocket', message);
                        setStatus('ERROR WS', 'danger');
                    });

                } catch (error) {
                    await destroySession(false);
                    writeError('Fallo al conectar la terminal', error.message);
                    setStatus('ERROR', 'danger');
                } finally {
                    connectButton.disabled = false;
                }
            };

            const init = () => {
                terminal = new Terminal({
                    cursorBlink: true,
                    cursorStyle: 'block',
                    convertEol: false,
                    scrollback: 5000,
                    fontFamily: 'Fira Code, Menlo, Monaco, monospace',
                    fontSize: 13,
                    theme: {
                        background: '#090b10', foreground: '#cdd6f4', cursor: '#6366f1',
                        selectionBackground: 'rgba(99,102,241,.35)', black: '#1e1e2e',
                        red: '#f38ba8', green: '#a6e3a1', yellow: '#f9e2af', blue: '#89b4fa',
                        magenta: '#cba6f7', cyan: '#94e2d5', white: '#bac2de',
                    },
                });
                fitAddon = new FitAddon.FitAddon();
                terminal.loadAddon(fitAddon);
                terminal.open(container);
                fitAddon.fit();
                terminal.writeln('\x1b[1;36mLaraPanel Web Terminal v2.0\x1b[0m');
                terminal.writeln('Selecciona un destino y pulsa Conectar.\r\n');

                terminal.onData((data) => {
                    if (!channel || !connected) return;
                    channel.trigger('client-terminal-data', { b64: encodeBase64(data) });
                });

                window.addEventListener('resize', sendResize);
                connectButton.addEventListener('click', connect);
                disconnectButton.addEventListener('click', () => destroySession());
                window.addEventListener('beforeunload', () => {
                    if (sessionId) navigator.sendBeacon(`/terminal/session/${sessionId}`, new Blob([], { type: 'application/json' }));
                });
            };

            init();
        })();
    </script>
    @endscript
</div>
