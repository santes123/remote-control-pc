<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Remote Control PC') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-neutral-950 text-white">
        <div class="pointer-events-none fixed inset-0 overflow-hidden">
            <div class="absolute -left-20 top-0 h-72 w-72 rounded-full bg-cyan-500/20 blur-3xl"></div>
            <div class="absolute right-0 top-1/3 h-80 w-80 rounded-full bg-fuchsia-500/15 blur-3xl"></div>
        </div>

        <div
            id="action-overlay"
            class="fixed inset-0 z-50 hidden items-center justify-center bg-neutral-950/70 px-4 backdrop-blur-sm"
        >
            <div class="w-full max-w-sm rounded-3xl border border-white/10 bg-neutral-900/90 p-6 text-center shadow-2xl shadow-black/40">
                <div class="mx-auto h-14 w-14 animate-spin rounded-full border-4 border-cyan-400/20 border-t-cyan-300"></div>
                <h2 id="overlay-title" class="mt-5 text-xl font-semibold text-white">Ejecutando acción...</h2>
                <p id="overlay-detail" class="mt-2 text-sm text-neutral-300">Esperando respuesta del backend.</p>
            </div>
        </div>

        <main class="relative mx-auto flex min-h-screen max-w-7xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
            <section class="rounded-3xl border border-white/10 bg-white/5 p-6 shadow-2xl shadow-black/30">
                <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <p class="text-sm font-medium uppercase tracking-[0.26em] text-cyan-300">Remote Control PC</p>
                            <h1 class="text-3xl font-semibold text-white sm:text-4xl">Panel de control remoto</h1>
                            <p class="max-w-3xl text-sm text-neutral-300 sm:text-base">
                                Enciende el equipo con Wake-on-LAN, comprueba RDP, SSH y Guacamole en tiempo real, y lanza el
                                escritorio remoto cuando todo esté listo.
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2 text-xs font-medium">
                            <span
                                id="stage-offline"
                                class="rounded-full border border-rose-400/30 bg-rose-400/10 px-3 py-1 text-rose-200 opacity-60"
                            >
                                1. Apagado
                            </span>
                            <span
                                id="stage-booting"
                                class="rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-amber-100 opacity-60"
                            >
                                2. Arrancando
                            </span>
                            <span
                                id="stage-available"
                                class="rounded-full border border-emerald-400/30 bg-emerald-400/10 px-3 py-1 text-emerald-100 opacity-60"
                            >
                                3. Disponible
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 xl:min-w-[27rem]">
                        <div class="rounded-3xl border border-white/10 bg-black/20 p-5">
                            <div class="flex items-center gap-3">
                                <span id="status-indicator" class="h-3.5 w-3.5 rounded-full bg-amber-400 shadow-[0_0_16px_rgba(251,191,36,0.5)]"></span>
                                <div>
                                    <p class="text-xs uppercase tracking-[0.2em] text-neutral-400">Estado general</p>
                                    <p id="status-label" class="text-xl font-semibold text-white">Cargando...</p>
                                </div>
                            </div>
                            <p id="status-message" class="mt-4 text-sm text-neutral-300">Esperando la primera comprobación.</p>
                        </div>

                        <div class="rounded-3xl border border-white/10 bg-black/20 p-5">
                            <p class="text-xs uppercase tracking-[0.2em] text-neutral-400">Panel</p>
                            <div class="mt-4 space-y-2 text-sm text-neutral-300">
                                <p>
                                    Polling:
                                    <span id="polling-label" class="font-medium text-white">cada 5s</span>
                                </p>
                                <p>
                                    Última actualización:
                                    <span id="last-updated" class="font-medium text-white">Sin datos</span>
                                </p>
                                <p>
                                    Host objetivo:
                                    <span id="host-label" class="font-medium text-white">-</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 lg:grid-cols-[1.45fr_0.95fr]">
                <div class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-xl font-semibold">Acciones</h2>
                                <p class="mt-1 text-sm text-neutral-400">Confirmaciones visuales, bloqueo mientras se ejecutan y seguimiento rápido del estado.</p>
                            </div>
                            <button
                                id="refresh-button"
                                class="rounded-full border border-white/15 px-4 py-2 text-sm font-medium text-white transition hover:border-cyan-400 hover:bg-cyan-400/10 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Refrescar ahora
                            </button>
                        </div>

                        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <button
                                id="wake-button"
                                class="rounded-2xl bg-emerald-500 px-4 py-3 text-sm font-semibold text-emerald-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:bg-emerald-900/40 disabled:text-emerald-200/50"
                            >
                                Encender PC
                            </button>
                            <button
                                id="restart-button"
                                class="rounded-2xl bg-amber-400 px-4 py-3 text-sm font-semibold text-amber-950 transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:bg-amber-900/40 disabled:text-amber-200/50"
                            >
                                Reiniciar
                            </button>
                            <button
                                id="shutdown-button"
                                class="rounded-2xl bg-rose-500 px-4 py-3 text-sm font-semibold text-rose-950 transition hover:bg-rose-400 disabled:cursor-not-allowed disabled:bg-rose-900/40 disabled:text-rose-200/50"
                            >
                                Apagar
                            </button>
                            <button
                                id="guacamole-button"
                                class="rounded-2xl border border-cyan-400/50 bg-cyan-400/10 px-4 py-3 text-sm font-semibold text-cyan-200 transition hover:bg-cyan-400/20 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                Abrir Guacamole
                            </button>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 class="text-xl font-semibold">Diagnóstico</h2>
                                <p class="mt-1 text-sm text-neutral-400">Comprobaciones separadas para RDP, puerto SSH, sonda SSH y gateway Guacamole.</p>
                            </div>
                            <div class="rounded-full border border-white/10 bg-black/20 px-3 py-1 text-xs text-neutral-300">
                                Modo de enlace:
                                <span id="control-mode" class="font-medium text-white">-</span>
                            </div>
                        </div>

                        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <article class="rounded-2xl border border-white/10 bg-black/25 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-neutral-400">RDP</p>
                                <p id="rdp-value" class="mt-2 text-lg font-semibold">-</p>
                                <p id="rdp-meta" class="mt-1 text-sm text-neutral-400">-</p>
                            </article>
                            <article class="rounded-2xl border border-white/10 bg-black/25 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-neutral-400">Puerto SSH</p>
                                <p id="ssh-port-value" class="mt-2 text-lg font-semibold">-</p>
                                <p id="ssh-port-meta" class="mt-1 text-sm text-neutral-400">-</p>
                            </article>
                            <article class="rounded-2xl border border-white/10 bg-black/25 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-neutral-400">Sonda SSH</p>
                                <p id="ssh-probe-value" class="mt-2 text-lg font-semibold">-</p>
                                <p id="ssh-probe-meta" class="mt-1 text-sm text-neutral-400">-</p>
                            </article>
                            <article class="rounded-2xl border border-white/10 bg-black/25 p-4">
                                <p class="text-xs uppercase tracking-[0.2em] text-neutral-400">Guacamole</p>
                                <p id="guac-value" class="mt-2 text-lg font-semibold">-</p>
                                <p id="guac-meta" class="mt-1 text-sm text-neutral-400">-</p>
                            </article>
                        </div>

                        <div class="mt-6 grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
                            <div class="rounded-2xl border border-white/10 bg-black/25 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-medium text-neutral-200">URL de control resuelta</p>
                                    <span id="guac-badge" class="rounded-full border border-white/10 px-2.5 py-1 text-xs text-neutral-300">Sin validar</span>
                                </div>
                                <p id="control-url" class="mt-3 break-all font-mono text-xs text-cyan-100">-</p>
                            </div>

                            <div class="rounded-2xl border border-white/10 bg-black/25 p-4">
                                <p class="text-sm font-medium text-neutral-200">Diagnóstico rápido</p>
                                <p id="guac-hint" class="mt-3 text-sm text-neutral-300">
                                    Si Guacamole no responde en la URL configurada, el botón no puede arreglarlo por sí solo.
                                </p>
                            </div>
                        </div>

                        <div class="mt-6 rounded-2xl border border-white/10 bg-black/30 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm font-medium text-neutral-200">Última respuesta JSON</p>
                                <span class="text-xs text-neutral-500">API `GET /api/pc/status`</span>
                            </div>
                            <pre id="json-output" class="mt-4 overflow-x-auto whitespace-pre-wrap break-words text-xs text-cyan-100"></pre>
                        </div>
                    </div>
                </div>

                <aside class="space-y-6">
                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6">
                        <h2 class="text-xl font-semibold">Historial de acciones</h2>
                        <p class="mt-1 text-sm text-neutral-400">Se guarda en este navegador para que tengas contexto aunque recargues la página.</p>
                        <div id="activity-log" class="mt-4 space-y-3"></div>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6">
                        <h2 class="text-xl font-semibold">Flujo recomendado</h2>
                        <ol class="mt-4 space-y-3 text-sm text-neutral-300">
                            <li><span class="font-semibold text-white">1.</span> Si el equipo está `offline`, pulsa <span class="font-semibold text-emerald-300">Encender PC</span>.</li>
                            <li><span class="font-semibold text-white">2.</span> El panel acelera temporalmente el polling tras acciones sensibles para que notes antes los cambios.</li>
                            <li><span class="font-semibold text-white">3.</span> Cuando el estado pase a `available`, ya puedes usar <span class="font-semibold text-cyan-300">Abrir Guacamole</span>.</li>
                            <li><span class="font-semibold text-white">4.</span> `Reiniciar` y `Apagar` piden confirmación antes de enviar el comando por SSH.</li>
                        </ol>
                    </div>

                    <div class="rounded-3xl border border-white/10 bg-white/5 p-6">
                        <h2 class="text-xl font-semibold">Guacamole</h2>
                        <div class="mt-4 space-y-3 text-sm text-neutral-300">
                            <p>
                                `REMOTE_PC_GUACAMOLE_BASE_URL` define la puerta de entrada a Guacamole. Si usas `http://localhost:8080/guacamole/`,
                                solo funcionará cuando el navegador esté en la misma máquina donde corre Guacamole.
                            </p>
                            <p>
                                Si además defines una conexión concreta, el panel abrirá directamente `#/client/...` en vez de la home. Eso evita
                                tener que entrar primero a la lista de conexiones.
                            </p>
                            <p>
                                Puedes configurar esa conexión concreta copiando el identificador real de la URL de Guacamole o indicando el ID de
                                la conexión y el proveedor de autenticación.
                            </p>
                        </div>
                    </div>
                </aside>
            </section>
        </main>

        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            const ui = {
                actionOverlay: document.getElementById('action-overlay'),
                overlayTitle: document.getElementById('overlay-title'),
                overlayDetail: document.getElementById('overlay-detail'),
                statusIndicator: document.getElementById('status-indicator'),
                statusLabel: document.getElementById('status-label'),
                statusMessage: document.getElementById('status-message'),
                hostLabel: document.getElementById('host-label'),
                pollingLabel: document.getElementById('polling-label'),
                lastUpdated: document.getElementById('last-updated'),
                wakeButton: document.getElementById('wake-button'),
                restartButton: document.getElementById('restart-button'),
                shutdownButton: document.getElementById('shutdown-button'),
                guacamoleButton: document.getElementById('guacamole-button'),
                refreshButton: document.getElementById('refresh-button'),
                rdpValue: document.getElementById('rdp-value'),
                rdpMeta: document.getElementById('rdp-meta'),
                sshPortValue: document.getElementById('ssh-port-value'),
                sshPortMeta: document.getElementById('ssh-port-meta'),
                sshProbeValue: document.getElementById('ssh-probe-value'),
                sshProbeMeta: document.getElementById('ssh-probe-meta'),
                guacValue: document.getElementById('guac-value'),
                guacMeta: document.getElementById('guac-meta'),
                controlMode: document.getElementById('control-mode'),
                controlUrl: document.getElementById('control-url'),
                guacBadge: document.getElementById('guac-badge'),
                guacHint: document.getElementById('guac-hint'),
                jsonOutput: document.getElementById('json-output'),
                activityLog: document.getElementById('activity-log'),
                stages: {
                    offline: document.getElementById('stage-offline'),
                    booting: document.getElementById('stage-booting'),
                    available: document.getElementById('stage-available'),
                },
            };

            const state = {
                pollTimer: null,
                aggressivePollTimer: null,
                aggressivePollStopAt: 0,
                pollIntervalMs: 5000,
                latestStatus: null,
                pendingAction: null,
                history: [],
            };

            const endpoints = {
                status: '/api/pc/status',
                wake: '/api/pc/wake',
                restart: '/api/pc/restart',
                shutdown: '/api/pc/shutdown',
                controlLink: '/api/pc/control-link',
            };

            const historyStorageKey = 'remote-control-pc-history';

            function getSwal() {
                return window.Swal ?? null;
            }

            function formatTime(timestamp) {
                return new Date(timestamp).toLocaleString('es-ES', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    day: '2-digit',
                    month: '2-digit',
                });
            }

            function setOverlay(visible, title = 'Ejecutando acción...', detail = 'Esperando respuesta del backend.') {
                ui.overlayTitle.textContent = title;
                ui.overlayDetail.textContent = detail;
                ui.actionOverlay.classList.toggle('hidden', !visible);
                ui.actionOverlay.classList.toggle('flex', visible);
            }

            function setBusy(isBusy) {
                const buttons = [
                    ui.wakeButton,
                    ui.restartButton,
                    ui.shutdownButton,
                    ui.guacamoleButton,
                    ui.refreshButton,
                ];

                buttons.forEach((button) => {
                    button.disabled = isBusy || button.disabled;
                });
            }

            function loadHistory() {
                try {
                    state.history = JSON.parse(localStorage.getItem(historyStorageKey) ?? '[]');
                } catch (error) {
                    state.history = [];
                }
            }

            function saveHistory() {
                localStorage.setItem(historyStorageKey, JSON.stringify(state.history.slice(0, 20)));
            }

            function renderHistory() {
                if (state.history.length === 0) {
                    ui.activityLog.innerHTML = '<p class="rounded-2xl border border-dashed border-white/10 px-4 py-5 text-sm text-neutral-400">Todavía no hay acciones registradas.</p>';
                    return;
                }

                const toneClasses = {
                    info: 'border-cyan-400/30 bg-cyan-400/10 text-cyan-50',
                    success: 'border-emerald-400/30 bg-emerald-400/10 text-emerald-50',
                    warning: 'border-amber-400/30 bg-amber-400/10 text-amber-50',
                    error: 'border-rose-400/30 bg-rose-400/10 text-rose-50',
                };

                ui.activityLog.innerHTML = state.history.map((entry) => `
                    <article class="rounded-2xl border p-4 ${toneClasses[entry.type] ?? toneClasses.info}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold">${entry.title}</p>
                                <p class="mt-1 text-sm opacity-90">${entry.message}</p>
                            </div>
                            <span class="shrink-0 text-xs opacity-80">${formatTime(entry.timestamp)}</span>
                        </div>
                        ${entry.meta ? `<p class="mt-2 text-xs opacity-75">${entry.meta}</p>` : ''}
                    </article>
                `).join('');
            }

            function pushHistory(title, message, type = 'info', meta = '') {
                state.history.unshift({
                    title,
                    message,
                    type,
                    meta,
                    timestamp: Date.now(),
                });

                state.history = state.history.slice(0, 20);
                saveHistory();
                renderHistory();
            }

            async function showToast(icon, title, text = '') {
                const swal = getSwal();

                if (!swal) {
                    if (text) {
                        window.alert(`${title}\n\n${text}`);
                    } else {
                        window.alert(title);
                    }

                    return;
                }

                await swal.fire({
                    icon,
                    title,
                    text,
                    confirmButtonColor: '#22d3ee',
                    background: '#171717',
                    color: '#f5f5f5',
                });
            }

            async function confirmAction(title, text, confirmButtonText, confirmButtonColor) {
                const swal = getSwal();

                if (!swal) {
                    return window.confirm(`${title}\n\n${text}`);
                }

                const result = await swal.fire({
                    title,
                    text,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText,
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor,
                    background: '#171717',
                    color: '#f5f5f5',
                });

                return result.isConfirmed;
            }

            async function fetchJson(url, options = {}) {
                const controller = new AbortController();
                const timeoutMs = options.timeoutMs ?? 12000;
                const timeoutId = window.setTimeout(() => controller.abort(), timeoutMs);

                try {
                    const response = await fetch(url, {
                        method: options.method ?? 'GET',
                        headers: {
                            'Accept': 'application/json',
                            ...options.headers,
                        },
                        body: options.body,
                        signal: controller.signal,
                    });

                    const text = await response.text();
                    let payload = {};

                    if (text.trim() !== '') {
                        try {
                            payload = JSON.parse(text);
                        } catch (error) {
                            payload = { message: text.trim() };
                        }
                    }

                    if (!response.ok) {
                        const message = payload?.data?.message || payload?.message || `La petición ha fallado (${response.status}).`;
                        throw new Error(message);
                    }

                    return payload;
                } catch (error) {
                    if (error.name === 'AbortError') {
                        throw new Error('La petición ha tardado demasiado en responder.');
                    }

                    throw error;
                } finally {
                    window.clearTimeout(timeoutId);
                }
            }

            function setStage(status) {
                Object.entries(ui.stages).forEach(([key, element]) => {
                    element.classList.toggle('opacity-100', key === status);
                    element.classList.toggle('opacity-60', key !== status);
                });
            }

            function setMetric(valueElement, metaElement, ok, okLabel, errorLabel, meta) {
                valueElement.textContent = ok ? okLabel : errorLabel;
                valueElement.className = `mt-2 text-lg font-semibold ${ok ? 'text-emerald-300' : 'text-rose-300'}`;
                metaElement.textContent = meta;
            }

            function formatControlMode(mode) {
                return mode === 'direct_connection' ? 'conexion directa' : 'home de Guacamole';
            }

            function updateButtons(data) {
                const canWake = data.status === 'offline' && !state.pendingAction;
                const canPower = data.checks?.ssh_probe?.success && !state.pendingAction;
                const canOpenGuac = Boolean(data.flow?.control_url) && data.status === 'available' && !state.pendingAction;

                ui.wakeButton.disabled = !canWake;
                ui.restartButton.disabled = !canPower;
                ui.shutdownButton.disabled = !canPower;
                ui.guacamoleButton.disabled = !canOpenGuac;
                ui.refreshButton.disabled = Boolean(state.pendingAction);
            }

            function updateStatusUi(data) {
                state.latestStatus = data;
                state.pollIntervalMs = data.flow?.poll_interval_ms ?? 5000;

                const statusConfig = {
                    offline: {
                        label: 'Apagado',
                        color: 'bg-rose-400 shadow-[0_0_18px_rgba(251,113,133,0.55)]',
                        message: 'El PC no responde todavía. Si lo acabas de apagar, espera unos segundos antes de volver a encenderlo.',
                    },
                    booting: {
                        label: 'Arrancando',
                        color: 'bg-amber-400 shadow-[0_0_18px_rgba(251,191,36,0.55)]',
                        message: 'Ya hay actividad parcial. El panel seguirá comprobando hasta que SSH quede operativo.',
                    },
                    available: {
                        label: 'Disponible',
                        color: 'bg-emerald-400 shadow-[0_0_18px_rgba(52,211,153,0.55)]',
                        message: 'La sonda SSH responde bien y ya puedes abrir Guacamole.',
                    },
                };

                const current = statusConfig[data.status] ?? statusConfig.offline;
                const guacCheck = data.checks?.guacamole ?? {};
                const controlUrl = data.flow?.control_url ?? '-';
                const isGuacReachable = Boolean(guacCheck.reachable);

                ui.statusIndicator.className = `h-3.5 w-3.5 rounded-full ${current.color}`;
                ui.statusLabel.textContent = current.label;
                ui.statusMessage.textContent = current.message;
                ui.hostLabel.textContent = `${data.label} (${data.host})`;
                ui.pollingLabel.textContent = `cada ${Math.round(state.pollIntervalMs / 1000)}s`;
                ui.lastUpdated.textContent = new Date().toLocaleTimeString('es-ES');
                ui.controlMode.textContent = formatControlMode(data.flow?.control_url_mode);
                ui.controlUrl.textContent = controlUrl;
                setStage(data.status);

                setMetric(
                    ui.rdpValue,
                    ui.rdpMeta,
                    data.checks?.rdp?.reachable,
                    'Responde',
                    'No responde',
                    `${data.checks?.rdp?.host ?? '-'}:${data.checks?.rdp?.port ?? '-'}`
                );

                setMetric(
                    ui.sshPortValue,
                    ui.sshPortMeta,
                    data.checks?.ssh_port?.reachable,
                    'Abierto',
                    'Cerrado',
                    `${data.checks?.ssh_port?.host ?? '-'}:${data.checks?.ssh_port?.port ?? '-'}`
                );

                setMetric(
                    ui.sshProbeValue,
                    ui.sshProbeMeta,
                    data.checks?.ssh_probe?.success,
                    'Correcta',
                    'Fallando',
                    data.checks?.ssh_probe?.message ?? '-'
                );

                setMetric(
                    ui.guacValue,
                    ui.guacMeta,
                    isGuacReachable,
                    'Escuchando',
                    'No accesible',
                    `${guacCheck.scheme ?? 'http'}://${guacCheck.host ?? '-'}:${guacCheck.port ?? '-'}${guacCheck.path ?? ''}`
                );

                ui.guacBadge.textContent = isGuacReachable ? 'Gateway visible' : 'Gateway caido';
                ui.guacBadge.className = `rounded-full border px-2.5 py-1 text-xs ${isGuacReachable
                    ? 'border-emerald-400/30 bg-emerald-400/10 text-emerald-100'
                    : 'border-rose-400/30 bg-rose-400/10 text-rose-100'}`;

                ui.guacHint.textContent = isGuacReachable
                    ? (data.flow?.control_url_mode === 'direct_connection'
                        ? 'El botón abrirá la conexión configurada directamente.'
                        : 'El botón abrirá la home de Guacamole; desde ahí eliges la conexión RDP.')
                    : 'La URL configurada de Guacamole no responde en este momento. Arranca Docker Desktop y los contenedores antes de probar el botón.';

                ui.jsonOutput.textContent = JSON.stringify(data, null, 2);
                updateButtons(data);
            }

            async function refreshStatus(silent = false) {
                try {
                    const payload = await fetchJson(endpoints.status, { timeoutMs: 10000 });
                    updateStatusUi(payload.data);

                    if (!silent) {
                        pushHistory('Estado actualizado', `Nuevo estado: ${payload.data.status}`, payload.data.status === 'available' ? 'success' : 'info');
                    }
                } catch (error) {
                    pushHistory('Error al actualizar', error.message, 'error');
                }
            }

            function restartPolling() {
                if (state.pollTimer) {
                    window.clearInterval(state.pollTimer);
                }

                state.pollTimer = window.setInterval(() => {
                    refreshStatus(true);
                }, state.pollIntervalMs);
            }

            function startAggressivePolling(durationMs = 20000, intervalMs = 1500) {
                if (state.aggressivePollTimer) {
                    window.clearInterval(state.aggressivePollTimer);
                }

                state.aggressivePollStopAt = Date.now() + durationMs;
                state.aggressivePollTimer = window.setInterval(async () => {
                    await refreshStatus(true);

                    if (Date.now() >= state.aggressivePollStopAt) {
                        window.clearInterval(state.aggressivePollTimer);
                        state.aggressivePollTimer = null;
                        restartPolling();
                    }
                }, intervalMs);
            }

            async function executeAction({
                url,
                label,
                overlayTitle,
                overlayDetail,
                confirm,
                timeoutMs = 12000,
                followUpPolling = false,
            }) {
                if (state.pendingAction) {
                    return;
                }

                if (confirm) {
                    const accepted = await confirmAction(
                        confirm.title,
                        confirm.text,
                        confirm.confirmButtonText,
                        confirm.confirmButtonColor
                    );

                    if (!accepted) {
                        return;
                    }
                }

                state.pendingAction = label;
                updateButtons(state.latestStatus ?? { status: 'offline', checks: {} });
                setOverlay(true, overlayTitle, overlayDetail);

                const startedAt = performance.now();

                try {
                    const payload = await fetchJson(url, { method: 'POST', timeoutMs });
                    const elapsed = `${Math.round(performance.now() - startedAt)} ms`;
                    const message = payload.data?.message || `${label} ejecutado correctamente.`;

                    pushHistory(label, message, 'success', `Duracion: ${elapsed}`);
                    setOverlay(false);
                    await showToast('success', label, message);
                    await refreshStatus(true);

                    if (followUpPolling) {
                        startAggressivePolling();
                    } else {
                        restartPolling();
                    }
                } catch (error) {
                    pushHistory(label, error.message, 'error');
                    setOverlay(false);
                    await showToast('error', label, error.message);
                } finally {
                    state.pendingAction = null;
                    updateButtons(state.latestStatus ?? { status: 'offline', checks: {} });
                }
            }

            async function openGuacamole() {
                if (state.pendingAction) {
                    return;
                }

                try {
                    const payload = await fetchJson(endpoints.controlLink, { timeoutMs: 8000 });
                    const controlUrl = payload.data?.control_url;

                    if (!controlUrl) {
                        throw new Error('No hay URL de control disponible.');
                    }

                    window.open(controlUrl, '_blank', 'noopener,noreferrer');
                    pushHistory(
                        'Abrir Guacamole',
                        `Se ha abierto ${payload.data?.status === 'available' ? 'la sesion de control' : 'Guacamole'} en otra pestaña.`,
                        'success',
                        controlUrl
                    );
                } catch (error) {
                    pushHistory('Abrir Guacamole', error.message, 'warning');
                    await showToast('warning', 'Guacamole', error.message);
                }
            }

            ui.refreshButton.addEventListener('click', async () => {
                await refreshStatus();
                restartPolling();
            });

            ui.wakeButton.addEventListener('click', async () => {
                await executeAction({
                    url: endpoints.wake,
                    label: 'Wake-on-LAN',
                    overlayTitle: 'Enviando paquete Wake-on-LAN',
                    overlayDetail: 'El backend está mandando el magic packet a la red local.',
                    timeoutMs: 8000,
                    followUpPolling: true,
                });
            });

            ui.restartButton.addEventListener('click', async () => {
                await executeAction({
                    url: endpoints.restart,
                    label: 'Reinicio',
                    overlayTitle: 'Solicitando reinicio',
                    overlayDetail: 'Esperando a que SSH confirme el envio del comando.',
                    confirm: {
                        title: 'Confirmar reinicio',
                        text: 'El PC se reiniciará inmediatamente. La sesión remota actual se cerrará.',
                        confirmButtonText: 'Sí, reiniciar',
                        confirmButtonColor: '#f59e0b',
                    },
                    timeoutMs: 10000,
                    followUpPolling: true,
                });
            });

            ui.shutdownButton.addEventListener('click', async () => {
                await executeAction({
                    url: endpoints.shutdown,
                    label: 'Apagado',
                    overlayTitle: 'Solicitando apagado',
                    overlayDetail: 'Esperando a que SSH confirme el envio del comando.',
                    confirm: {
                        title: 'Confirmar apagado',
                        text: 'El PC se apagará inmediatamente. Tendrás que encenderlo otra vez con Wake-on-LAN.',
                        confirmButtonText: 'Sí, apagar',
                        confirmButtonColor: '#ef4444',
                    },
                    timeoutMs: 10000,
                    followUpPolling: true,
                });
            });

            ui.guacamoleButton.addEventListener('click', async () => {
                await openGuacamole();
            });

            window.addEventListener('load', async () => {
                loadHistory();
                renderHistory();
                await refreshStatus(true);
                restartPolling();
            });
        </script>
    </body>
</html>
