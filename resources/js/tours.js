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
function buildSteps(tourSteps, getDriver) {
    return tourSteps.map((step, index) => {
        const config = {
            element: step.selector,
            popover: {
                title: step.title,
                description: step.description,
            },
        };

        // Solo en el último paso: terminar el tour con "Terminar" te devuelve
        // a Ayuda -- cerrarlo a medias (con la X) te deja donde estabas, ahí
        // seguro quieres seguir viendo la pantalla, no que te saque de golpe.
        if (index === tourSteps.length - 1 && window.appHelpUrl) {
            config.popover.onDoneClick = () => {
                window.location.href = window.appHelpUrl;
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
        steps: buildSteps(tour.steps, () => driverInstance),
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
