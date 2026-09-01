import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

/**
 * Arma los pasos de driver.js desde la configuración declarativa que viene
 * de Blade (window.appTours). Cuando un paso trae "panel" (el id de un panel
 * deslizante de Preline que ese paso necesita abierto), el avance NO depende
 * de que el usuario haga clic en el botón "Siguiente" del propio popover --
 * se engancha al evento real "open.hs.overlay" de Preline en cuanto el paso
 * se resalta, así que funciona igual si el usuario hace clic directo en el
 * elemento resaltado o en "Siguiente". Esto evita quedarse pegado en el
 * mismo paso y evita ubicar el popover sobre un elemento que sigue oculto
 * por una animación que aún no terminó.
 * @param {Array<Object>} tourSteps
 * @param {() => Object} getDriver
 * @returns {Array<Object>}
 */
function buildSteps(tourSteps, getDriver, tourKey) {
    return tourSteps.map((step, index) => {
        const config = {
            element: step.selector,
            // Si el selector de un paso no existe en la página (ej. la
            // guía apunta a una fila de la tabla pero la empresa no tiene
            // productos todavía), que se lo salte en vez de romper el tour
            // entero a la mitad.
            skipMissingElement: true,
            popover: {
                title: step.title,
                description: step.description,
            },
        };

        // Solo en el último paso: terminar el tour con "Terminar" te devuelve
        // a Ayuda -- cerrarlo a medias (con la X) te deja donde estabas, ahí
        // seguro quieres seguir viendo la pantalla, no que te saque de golpe.
        if (index === tourSteps.length - 1) {
            config.popover.onDoneClick = () => {
                markTourCompleted(tourKey);
                if (window.appHelpUrl) window.location.href = window.appHelpUrl;
            };
        } else {
            // driver.js decide si mostrar "Siguiente" o "Terminar" mirando
            // si el selector del PRÓXIMO paso existe ya en el DOM -- en una
            // guía "realNav" los pasos que siguen viven en otra página
            // todavía no cargada, así que ese chequeo siempre falla y el
            // botón se ve como "Terminar" aunque falten pasos de verdad.
            // Se fuerza el texto acá para que siempre diga "Siguiente"
            // salvo en el último paso real del arreglo completo.
            config.popover.nextBtnText = window.appTourLabels?.next;
        }

        if (step.panel) {
            config.onHighlighted = () => {
                const panel = document.querySelector(step.panel);
                if (!panel) return;

                const advanceOnce = () => {
                    panel.removeEventListener('open.hs.overlay', advanceOnce);
                    setTimeout(() => getDriver().moveNext(), 350);
                };
                panel.addEventListener('open.hs.overlay', advanceOnce);
            };

            config.popover.onNextClick = () => {
                const panel = document.querySelector(step.panel);
                const alreadyOpen = !!panel && panel.classList.contains('open') && !panel.classList.contains('hidden');

                if (alreadyOpen) {
                    getDriver().moveNext();
                    return;
                }

                document.querySelector(step.selector)?.click();
            };
        }

        // Para cambios instantáneos sin animación (cambiar de pestaña, marcar
        // un checkbox que revela más campos) -- a diferencia de "panel", acá
        // no hay ningún evento que esperar, así que se hace clic (solo al
        // avanzar, no apenas se resalta el paso) y se avanza ya mismo.
        if (step.clickAndAdvance) {
            config.popover.onNextClick = () => {
                const target = document.querySelector(step.clickAndAdvance);
                // Si es un checkbox que ya está en el estado que el clic
                // pondría (ej. editando un producto que ya tiene activado el
                // control de inventario), no hacerle clic -- lo apagaría en
                // vez de dejarlo como está, rompiendo lo que sigue del tour.
                if (!target || target.type !== 'checkbox' || !target.checked) {
                    target?.click();
                }
                getDriver().moveNext();
            };
        }

        // Pasos "realNav" resaltan un link/botón que de verdad te lleva a
        // otra página (ej. "Compañías" en el menú lateral, o "+ Nueva
        // empresa" en el dashboard) -- a diferencia de "panel", acá SÍ debe
        // navegar de una vez, tanto si el usuario le da clic directo al
        // elemento como si usa "Siguiente". Antes de que la página cambie se
        // guarda en sessionStorage en qué paso seguir, y al cargar la
        // siguiente página (ver resumePendingTour) el tour se retoma justo
        // ahí, para que la guía completa se sienta como un solo recorrido
        // aunque cruce varias pantallas.
        if (step.realNav) {
            const remember = () => rememberTourResume(tourKey, index + 1);

            config.popover.onNextClick = () => {
                remember();
                document.querySelector(step.selector)?.click();
            };

            // Un solo listener de "click" en el propio elemento llega
            // TARDE cuando wire:navigate intercepta la navegación real
            // desde "mousedown" (ver el comentario más abajo, en el bloque
            // "solo mira esto") -- si el click directo del usuario dispara
            // primero el mousedown de Livewire y recién después nuestro
            // listener, seguía funcionando por las puras, pero cualquier
            // variación en el orden (u otro listener con
            // stopImmediatePropagation) podía comerse el evento antes de
            // que "remember()" llegara a guardar en qué paso seguir. Se
            // escucha en captura desde "document" sobre los tres eventos,
            // igual que el bloqueo de navegación de abajo, para que quede
            // guardado ANTES de que cualquier navegación (Livewire o una
            // recarga completa de página) alcance a completarse.
            let navListener;
            const navEvents = ['pointerdown', 'mousedown', 'click'];

            config.onHighlighted = (element) => {
                if (!element) return;
                navListener = (event) => {
                    if (element !== event.target && !element.contains(event.target)) return;
                    remember();
                };
                navEvents.forEach((type) => document.addEventListener(type, navListener, true));
            };

            config.onDeselected = () => {
                if (navListener) navEvents.forEach((type) => document.removeEventListener(type, navListener, true));
            };
        } else if (!step.panel && !step.clickAndAdvance) {
            // Pasos "solo mira esto" que resaltan un link real pero NO están
            // marcados como "realNav" (la guía no sigue del otro lado) --
            // bloquea la navegación directa sobre el elemento mientras el
            // paso está resaltado, para que no rompa el tour a medias. El
            // elemento resaltado sigue siendo el real; solo se frena la
            // navegación hasta que el usuario avance con el popover.
            //
            // wire:navigate (Alpine "x-navigate" por debajo) dispara la
            // navegación real desde "mousedown", directo sobre el <a>, NO
            // desde "click" -- un listener de "click" puesto en el propio
            // elemento llega tarde, la navegación ya arrancó. Por eso acá se
            // escucha en fase de captura desde "document" (se ejecuta antes
            // de que el evento baje hasta el elemento) y se cubren
            // "pointerdown"/"mousedown" además de "click".
            let blockNav;
            const blockedEvents = ['pointerdown', 'mousedown', 'click'];

            config.onHighlighted = (element) => {
                if (!element) return;
                blockNav = (event) => {
                    if (element !== event.target && !element.contains(event.target)) return;
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                };
                blockedEvents.forEach((type) => document.addEventListener(type, blockNav, true));
            };

            config.onDeselected = () => {
                if (blockNav) {
                    blockedEvents.forEach((type) => document.removeEventListener(type, blockNav, true));
                }
            };
        }

        return config;
    });
}

const COMPLETED_TOURS_STORAGE_KEY = 'completedTours';

/**
 * @returns {Array<string>} Claves de los tours que el usuario ya terminó (persistido en localStorage).
 */
function getCompletedTours() {
    try {
        return JSON.parse(localStorage.getItem(COMPLETED_TOURS_STORAGE_KEY) || '[]');
    } catch (error) {
        return [];
    }
}

/**
 * @param {string} key
 * @returns {void}
 */
function markTourCompleted(key) {
    if (!key) return;
    const completed = new Set(getCompletedTours());
    completed.add(key);
    localStorage.setItem(COMPLETED_TOURS_STORAGE_KEY, JSON.stringify([...completed]));
}

/**
 * En la página de Ayuda, le pone un check a cada tarjeta de guía que el
 * usuario ya completó antes -- así sabe cuáles le faltan sin tener que
 * recordarlo, y sigue pudiendo repetir cualquiera con solo hacer clic.
 * @returns {void}
 */
function decorateCompletedGuides() {
    const completed = new Set(getCompletedTours());
    if (!completed.size) return;

    document.querySelectorAll('a[href*="tour="]').forEach((link) => {
        let tourKey;
        try {
            tourKey = new URL(link.href, window.location.origin).searchParams.get('tour');
        } catch (error) {
            return;
        }

        if (!tourKey || !completed.has(tourKey) || link.querySelector('.tour-completed-badge')) return;

        const badge = document.createElement('span');
        badge.className = 'tour-completed-badge absolute right-3 top-3 flex size-5 items-center justify-center rounded-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
        badge.title = window.appTourLabels?.completed ?? '';
        badge.innerHTML = '<svg class="size-3 shrink-0" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>';
        link.appendChild(badge);
    });
}

document.addEventListener('DOMContentLoaded', decorateCompletedGuides);
document.addEventListener('livewire:navigated', decorateCompletedGuides);

/**
 * Si el usuario cierra a mano un panel que el tour necesitaba abierto (antes
 * de terminar el recorrido), los pasos siguientes apuntarían a elementos ya
 * ocultos -- mejor cerrar el tour de una vez que dejarlo mostrando un
 * popover fantasma en cualquier parte de la pantalla.
 * @param {Array<Object>} tourSteps
 * @param {() => Object} getDriver
 * @returns {() => void} Función para dejar de escuchar (se llama al terminar el tour).
 */
function watchPanelsForEarlyClose(tourSteps, getDriver) {
    const panels = [...new Set(tourSteps.map((step) => step.panel).filter(Boolean))]
        .map((selector) => document.querySelector(selector))
        .filter(Boolean);

    const onClose = () => getDriver().destroy();
    panels.forEach((panel) => panel.addEventListener('close.hs.overlay', onClose));

    return () => panels.forEach((panel) => panel.removeEventListener('close.hs.overlay', onClose));
}

/**
 * @param {string} key
 * @param {number} [startIndex] Paso por el que arrancar -- 0 si es la
 * primera vez, o el que quedó guardado si la guía sigue después de una
 * navegación real de página (ver "realNav" en buildSteps()).
 * @returns {void}
 */
window.startTour = function (key, startIndex = 0) {
    const tour = window.appTours && window.appTours[key];
    if (!tour || !tour.steps || !tour.steps.length) return;

    // Al retomar una guía después de una navegación real (ver "realNav" en
    // buildSteps()), la pantalla a la que en teoría se llega puede depender
    // de un estado que no está garantizado (ej. "Vender" manda a la
    // pantalla de venta en vez de a la de abrir turno si ya hay un turno
    // abierto) -- si el paso al que se debería retomar no existe en esta
    // página, antes el tour simplemente desaparecía sin explicación. Mejor
    // avisar por qué no se puede seguir en vez de fallar en silencio.
    if (startIndex > 0) {
        const resumeStep = tour.steps[startIndex];
        if (resumeStep && !document.querySelector(resumeStep.selector)) {
            window.appConfirmDialog.notify(
                resumeStep.resumeMissingMessage || window.appTourLabels?.resumeMissing,
                window.appTourLabels?.noticeTitle
            );
            return;
        }
    }

    let driverInstance;
    let stopWatching = () => {};

    driverInstance = driver({
        showProgress: true,
        nextBtnText: window.appTourLabels?.next,
        prevBtnText: window.appTourLabels?.prev,
        doneBtnText: window.appTourLabels?.done,
        progressText: window.appTourLabels?.progress,
        steps: buildSteps(tour.steps, () => driverInstance, key),
        onDestroyed: () => stopWatching(),
    });

    stopWatching = watchPanelsForEarlyClose(tour.steps, () => driverInstance);
    driverInstance.drive(startIndex);
};

const TOUR_RESUME_STORAGE_KEY = 'pendingTourResume';

/**
 * @param {string} tourKey
 * @param {number} stepIndex
 * @returns {void}
 */
function rememberTourResume(tourKey, stepIndex) {
    sessionStorage.setItem(TOUR_RESUME_STORAGE_KEY, JSON.stringify({ tourKey, stepIndex }));
}

/**
 * Se consume una sola vez (se borra al leerlo) -- así, si tanto
 * "DOMContentLoaded" como "livewire:navigated" disparan en la misma carga,
 * el segundo simplemente no encuentra nada pendiente, sin necesitar un
 * guard aparte como el de autoStartFromQuery().
 * @returns {{tourKey: string, stepIndex: number}|null}
 */
function consumePendingTourResume() {
    try {
        const raw = sessionStorage.getItem(TOUR_RESUME_STORAGE_KEY);
        if (!raw) return null;
        sessionStorage.removeItem(TOUR_RESUME_STORAGE_KEY);
        return JSON.parse(raw);
    } catch (error) {
        return null;
    }
}

let lastAutoStartedUrl = null;

/**
 * "livewire:navigated" también se dispara en la carga inicial de la página
 * (no solo en navegaciones internas por wire:navigate), así que sin este
 * guard "DOMContentLoaded" y "livewire:navigated" arrancaban DOS tours a la
 * vez en el mismo load -- cada uno avanzando por su cuenta sin saber del
 * otro (dos popovers superpuestos, cada uno en un paso distinto).
 *
 * Primero se revisa si hay un tour pendiente por retomar (ver "realNav" en
 * buildSteps()) -- si lo hay, tiene prioridad y no se evalúa el "?tour="
 * de la URL, porque esa página normalmente no lo trae (se navegó por un
 * link real del menú, no por el link de una tarjeta de Ayuda).
 * @returns {void}
 */
function autoStartFromQuery() {
    const pending = consumePendingTourResume();
    if (pending) {
        setTimeout(() => window.startTour(pending.tourKey, pending.stepIndex), 300);
        return;
    }

    const tour = new URLSearchParams(window.location.search).get('tour');
    if (!tour || lastAutoStartedUrl === window.location.href) return;

    lastAutoStartedUrl = window.location.href;
    setTimeout(() => window.startTour(tour), 300);
}

document.addEventListener('DOMContentLoaded', autoStartFromQuery);
document.addEventListener('livewire:navigated', autoStartFromQuery);
