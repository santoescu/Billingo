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
                document.querySelector(step.clickAndAdvance)?.click();
                getDriver().moveNext();
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

window.startTour = function (key) {
    const tour = window.appTours && window.appTours[key];
    if (!tour || !tour.steps || !tour.steps.length) return;

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
    driverInstance.drive();
};

let lastAutoStartedUrl = null;

/**
 * "livewire:navigated" también se dispara en la carga inicial de la página
 * (no solo en navegaciones internas por wire:navigate), así que sin este
 * guard "DOMContentLoaded" y "livewire:navigated" arrancaban DOS tours a la
 * vez en el mismo load -- cada uno avanzando por su cuenta sin saber del
 * otro (dos popovers superpuestos, cada uno en un paso distinto).
 * @returns {void}
 */
function autoStartFromQuery() {
    const tour = new URLSearchParams(window.location.search).get('tour');
    if (!tour || lastAutoStartedUrl === window.location.href) return;

    lastAutoStartedUrl = window.location.href;
    setTimeout(() => window.startTour(tour), 300);
}

document.addEventListener('DOMContentLoaded', autoStartFromQuery);
document.addEventListener('livewire:navigated', autoStartFromQuery);
