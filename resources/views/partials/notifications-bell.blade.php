<div class="relative inline-flex" wire:ignore>
    <button
        id="hs-notifications-bell"
        type="button"
        class="relative inline-flex justify-center items-center size-11 text-sm font-semibold rounded-lg bg-white border border-gray-200 text-gray-600 shadow-2xs hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-700 dark:focus:bg-neutral-700"
        aria-haspopup="menu"
        aria-expanded="false"
        aria-label="Notifications"
    >
        <svg class="shrink-0 size-5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>

        <span id="notifications-unread-badge" class="hidden absolute top-0 right-0 transform -translate-y-1/2 translate-x-1/2">
            <span class="animate-ping absolute inline-flex size-full rounded-full bg-red-400 opacity-75 dark:bg-red-600"></span>
            <span id="notifications-unread-count" class="relative inline-flex items-center py-0.5 px-1.5 rounded-full text-xs font-medium bg-red-500 text-white"></span>
        </span>
    </button>

    {{-- Este panel se mueve por JS a <body> al iniciar: el sidebar tiene la
         utilidad `transform` para su animación de deslizamiento, y eso hace
         que cualquier `position: fixed` anidado adentro deje de posicionarse
         contra la ventana y quede atrapado/recortado dentro del sidebar. Al
         vivir en <body> directamente, el panel sí puede extenderse libremente
         hacia el contenedor de contenido de la derecha. --}}
    <div
        id="notifications-panel"
        class="hidden fixed w-96 z-[100] bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-neutral-900 dark:border-neutral-700"
        role="menu"
        aria-orientation="vertical"
        aria-labelledby="hs-notifications-bell"
    >
        <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-gray-200 dark:border-neutral-700">
            <span class="text-sm font-semibold text-gray-800 dark:text-neutral-200">{{ __('Notifications') }}</span>
            <button type="button" id="notifications-mark-all-read" class="text-xs font-medium text-accent hover:underline">
                {{ __('Mark all as read') }}
            </button>
        </div>

        <div id="notifications-list" class="max-h-[22rem] overflow-y-auto divide-y divide-gray-100 dark:divide-neutral-800 [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500">
            <div class="px-3 py-4 text-center text-sm text-gray-400 dark:text-neutral-500">{{ __('Loading...') }}</div>
        </div>
    </div>
</div>

<script>
    var notificationsEmptyLabel = @json(__('No notifications yet.'));
    var notificationsErrorLabel = @json(__('Could not load notifications.'));

    function notificationsCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.content : '';
    }

    function notificationsEscapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);

        return div.innerHTML;
    }

    function renderNotificationsBadge(count) {
        var badge = document.getElementById('notifications-unread-badge');
        var countEl = document.getElementById('notifications-unread-count');

        if (!badge) {
            return;
        }

        if (countEl) {
            countEl.textContent = count > 9 ? '9+' : count;
        }

        badge.classList.toggle('hidden', count <= 0);
    }

    function renderNotificationsList(notifications) {
        var list = document.getElementById('notifications-list');

        if (!list) {
            return;
        }

        if (!notifications.length) {
            list.innerHTML = '<div class="px-3 py-4 text-center text-sm text-gray-400 dark:text-neutral-500">' + notificationsEmptyLabel + '</div>';
            return;
        }

        list.innerHTML = notifications.map(function (n) {
            var unreadDot = n.read ? '' : '<span class="inline-block size-2 rounded-full bg-accent shrink-0 mt-1.5"></span>';
            var content = '<div class="min-w-0 flex-1">'
                + '<p class="text-sm font-medium text-gray-800 dark:text-neutral-200">' + notificationsEscapeHtml(n.title) + '</p>'
                + '<p class="text-sm text-gray-500 dark:text-neutral-400 break-words">' + notificationsEscapeHtml(n.body) + '</p>'
                + '<p class="mt-1 text-xs text-gray-400 dark:text-neutral-500">' + notificationsEscapeHtml(n.created_at) + '</p>'
                + '</div>';

            var inner = '<div class="flex items-start gap-2 px-3 py-3 ' + (n.read ? '' : 'bg-accent/5') + '">' + unreadDot + content + '</div>';

            if (n.url) {
                return '<a href="' + notificationsEscapeHtml(n.url) + '" data-id="' + notificationsEscapeHtml(n.id) + '" class="notification-item block hover:bg-gray-50 dark:hover:bg-neutral-800">' + inner + '</a>';
            }

            return '<div data-id="' + notificationsEscapeHtml(n.id) + '" class="notification-item cursor-pointer hover:bg-gray-50 dark:hover:bg-neutral-800">' + inner + '</div>';
        }).join('');

        list.querySelectorAll('.notification-item').forEach(function (el) {
            el.addEventListener('click', function (event) {
                var id = el.getAttribute('data-id');
                var href = el.getAttribute('href');

                // Si el clic navega a otra página, esperamos a que el marcado
                // como leído confirme antes de dejar seguir la navegación,
                // para que no se cancele el fetch a mitad de camino.
                if (href) {
                    event.preventDefault();
                }

                fetch('/notifications/' + id + '/read', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': notificationsCsrfToken() },
                })
                    .catch(function () {})
                    .then(function () {
                        loadNotifications();

                        if (href) {
                            window.location.href = href;
                        }
                    });
            });
        });
    }

    function loadNotifications() {
        fetch('/notifications', {
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                renderNotificationsBadge(data.unread_count);
                renderNotificationsList(data.notifications);
            })
            .catch(function () {
                var list = document.getElementById('notifications-list');

                if (list) {
                    list.innerHTML = '<div class="px-3 py-4 text-center text-sm text-red-400">' + notificationsErrorLabel + '</div>';
                }
            });
    }

    function positionNotificationsPanel(bell, panel) {
        var rect = bell.getBoundingClientRect();
        var gap = 8;
        var width = panel.offsetWidth || 384;

        var left = rect.left;
        if (left + width > window.innerWidth - gap) {
            left = window.innerWidth - width - gap;
        }
        if (left < gap) {
            left = gap;
        }

        panel.style.left = left + 'px';
        panel.style.bottom = (window.innerHeight - rect.top + gap) + 'px';
        panel.style.top = 'auto';
    }

    function closeNotificationsPanel(panel, bell) {
        panel.classList.add('hidden');
        bell.setAttribute('aria-expanded', 'false');
    }

    function initNotificationsBell() {
        var bell = document.getElementById('hs-notifications-bell');

        if (!bell) {
            return;
        }

        // Cada navegación con wire:navigate vuelve a renderizar este partial
        // en su sitio original dentro del sidebar, aunque el panel de una
        // navegación anterior ya lo hayamos movido a <body> (ver más abajo).
        // Eso puede dejar dos nodos con el mismo id: nos quedamos con el que
        // ya vive en <body> (el que está funcionando) y descartamos el nuevo.
        var panelCandidates = document.querySelectorAll('[id="notifications-panel"]');
        var panel = null;
        panelCandidates.forEach(function (candidate) {
            if (candidate.parentElement === document.body) {
                panel = candidate;
            }
        });
        if (!panel) {
            panel = panelCandidates[panelCandidates.length - 1];
        }
        panelCandidates.forEach(function (candidate) {
            if (candidate !== panel) {
                candidate.remove();
            }
        });

        if (!panel) {
            return;
        }

        // El sidebar tiene `transform`, así que sacamos el panel a <body>
        // para que su `position: fixed` sea realmente contra la ventana y
        // pueda extenderse hacia el contenido, no solo dentro del sidebar.
        if (panel.parentElement !== document.body) {
            document.body.appendChild(panel);
        }

        var markAllBtn = panel.querySelector('#notifications-mark-all-read');

        // La barra lateral usa wire:navigate (navegación SPA de Livewire): el
        // markup de este partial se vuelve a renderizar en cada cambio de
        // página, pero cuando el botón de la campana no cambia, Livewire lo
        // conserva tal cual, así que solo enganchamos sus listeners una vez
        // (para no duplicarlos) pero SIEMPRE recargamos los datos abajo, ya
        // que el contenido de la lista/badge sí puede haber sido reseteado
        // por el morph del servidor.
        if (bell.dataset.notificationsBound !== 'true') {
            bell.dataset.notificationsBound = 'true';

            bell.addEventListener('click', function (event) {
                event.stopPropagation();

                var isHidden = panel.classList.contains('hidden');

                if (isHidden) {
                    loadNotifications();
                    panel.classList.remove('hidden');
                    bell.setAttribute('aria-expanded', 'true');
                    positionNotificationsPanel(bell, panel);
                } else {
                    closeNotificationsPanel(panel, bell);
                }
            });

            document.addEventListener('click', function (event) {
                if (!panel.classList.contains('hidden') && !panel.contains(event.target) && event.target !== bell && !bell.contains(event.target)) {
                    closeNotificationsPanel(panel, bell);
                }
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeNotificationsPanel(panel, bell);
                }
            });

            window.addEventListener('resize', function () {
                if (!panel.classList.contains('hidden')) {
                    positionNotificationsPanel(bell, panel);
                }
            });
        }

        if (markAllBtn && markAllBtn.dataset.notificationsBound !== 'true') {
            markAllBtn.dataset.notificationsBound = 'true';

            markAllBtn.addEventListener('click', function () {
                fetch('/notifications/read-all', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': notificationsCsrfToken() },
                }).then(loadNotifications);
            });
        }

        loadNotifications();

        if (window.notificationsPollInterval) {
            clearInterval(window.notificationsPollInterval);
        }
        window.notificationsPollInterval = setInterval(loadNotifications, 60000);
    }

    document.addEventListener('DOMContentLoaded', initNotificationsBell);
    document.addEventListener('livewire:navigated', initNotificationsBell);
</script>
