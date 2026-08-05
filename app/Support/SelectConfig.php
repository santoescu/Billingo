<?php

namespace App\Support;

/**
 * Configuración JSON compartida para los selects de Preline (@preline/select)
 * usados en toda la aplicación -- antes cada vista tenía su propia copia de
 * este mismo bloque pegado a mano, lo que hacía que un ajuste de estilo (p.
 * ej. cómo se marca la opción seleccionada) hubiera que repetirlo en cada
 * archivo por separado.
 */
class SelectConfig
{
    /**
     * Config para un select simple (sin buscador).
     *
     * @param  string|null  $placeholder  Texto del placeholder; por defecto __('Select...').
     */
    public static function basic(?string $placeholder = null): string
    {
        $json = <<<'JSON'
        {
            "placeholder": "__SELECT_PLACEHOLDER__",
            "toggleTag": "<button type=\"button\" aria-expanded=\"false\"></button>",
            "toggleClasses": "hs-select-disabled:pointer-events-none hs-select-disabled:opacity-50 relative h-10 py-2 ps-3 pe-10 flex min-w-0 overflow-hidden w-full cursor-pointer [&>*]:min-w-0 [&>*]:truncate appearance-none bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-start text-base sm:text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-accent",
            "dropdownClasses": "mt-2 z-50 w-full max-h-72 p-1 space-y-0.5 bg-white border border-zinc-200 rounded-lg shadow-xl overflow-hidden overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500 dark:bg-zinc-700 dark:border-white/10",
            "optionClasses": "py-2 px-4 w-full text-sm text-zinc-700 cursor-pointer rounded-lg hover:bg-zinc-100 focus:outline-hidden focus:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/10 dark:focus:bg-white/10 hs-selected:bg-accent hs-selected:text-white dark:hs-selected:bg-accent dark:hs-selected:text-white",
            "optionTemplate": "<div class=\"flex items-center justify-between w-full\"><span data-title></span><span class=\"hidden hs-selected:block\"><svg class=\"shrink-0 size-4 text-white\" xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" fill=\"currentColor\" viewBox=\"0 0 16 16\"><path d=\"M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425a.247.247 0 0 1 .02-.022Z\"/></svg></span></div>"
        }
        JSON;

        return str_replace('__SELECT_PLACEHOLDER__', $placeholder ?? __('Select...'), $json);
    }

    /**
     * Config para un select con buscador (dropdown con campo de texto para filtrar).
     *
     * @param  string|null  $placeholder  Texto del placeholder; por defecto __('Select...').
     * @param  string|null  $searchPlaceholder  Texto del buscador; por defecto __('Search').
     */
    public static function searchable(?string $placeholder = null, ?string $searchPlaceholder = null): string
    {
        $json = <<<'JSON'
        {
            "hasSearch": true,
            "searchPlaceholder": "__SEARCH_PLACEHOLDER__",
            "searchClasses": "block w-full text-sm border border-zinc-200 dark:border-white/10 rounded-lg bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 placeholder:text-zinc-400 focus:outline-hidden focus:ring-2 focus:ring-accent py-2 px-3",
            "searchWrapperClasses": "bg-white dark:bg-zinc-700 p-2 -mx-1 sticky top-0",
            "placeholder": "__SELECT_PLACEHOLDER__",
            "toggleTag": "<button type=\"button\" aria-expanded=\"false\"></button>",
            "toggleClasses": "hs-select-disabled:pointer-events-none hs-select-disabled:opacity-50 relative h-10 py-2 ps-3 pe-10 flex text-nowrap w-full cursor-pointer appearance-none bg-white dark:bg-white/10 border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 text-zinc-700 dark:text-zinc-300 rounded-lg text-start text-base sm:text-sm shadow-xs focus:outline-hidden focus:ring-2 focus:ring-accent",
            "dropdownClasses": "mt-2 z-50 w-full max-h-72 p-1 space-y-0.5 bg-white border border-zinc-200 rounded-lg shadow-xl overflow-hidden overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500 dark:bg-zinc-700 dark:border-white/10",
            "optionClasses": "py-2 px-4 w-full text-sm text-zinc-700 cursor-pointer rounded-lg hover:bg-zinc-100 focus:outline-hidden focus:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/10 dark:focus:bg-white/10 hs-selected:bg-accent hs-selected:text-white dark:hs-selected:bg-accent dark:hs-selected:text-white",
            "optionTemplate": "<div class=\"flex items-center justify-between w-full\"><span data-title></span><span class=\"hidden hs-selected:block\"><svg class=\"shrink-0 size-4 text-white\" xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" fill=\"currentColor\" viewBox=\"0 0 16 16\"><path d=\"M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425a.247.247 0 0 1 .02-.022Z\"/></svg></span></div>"
        }
        JSON;

        return str_replace(
            ['__SEARCH_PLACEHOLDER__', '__SELECT_PLACEHOLDER__'],
            [$searchPlaceholder ?? __('Search'), $placeholder ?? __('Select...')],
            $json
        );
    }

    /**
     * Config para un select "solo ícono": el botón nunca muestra el texto de
     * la opción elegida (solo una flechita), pensado para embeber un select
     * dentro de otro campo (ej. la lista de precios dentro del campo de
     * precio) donde no hay espacio para mostrar el valor seleccionado y no
     * hace falta, porque ya se refleja en otro campo. El dropdown/opciones
     * usan el mismo diseño que el resto de los selects de la app.
     */
    public static function iconTrigger(): string
    {
        $json = <<<'JSON'
        {
            "toggleTag": "<button type=\"button\" aria-expanded=\"false\"><svg class=\"shrink-0 size-3.5\" xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"m6 9 6 6 6-6\"></path></svg><span data-title class=\"hidden\"></span></button>",
            "toggleClasses": "hs-select-disabled:pointer-events-none hs-select-disabled:opacity-50 relative flex items-center justify-center size-8 cursor-pointer rounded-lg text-zinc-500 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-white/10 focus:outline-hidden focus:ring-2 focus:ring-accent",
            "dropdownClasses": "mt-2 z-50 w-48 max-h-72 p-1 space-y-0.5 bg-white border border-zinc-200 rounded-lg shadow-xl overflow-hidden overflow-y-auto [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500 dark:bg-zinc-700 dark:border-white/10",
            "optionClasses": "py-2 px-4 w-full text-sm text-zinc-700 cursor-pointer rounded-lg hover:bg-zinc-100 focus:outline-hidden focus:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/10 dark:focus:bg-white/10 hs-selected:bg-accent hs-selected:text-white dark:hs-selected:bg-accent dark:hs-selected:text-white",
            "optionTemplate": "<div class=\"flex items-center justify-between w-full\"><span data-title></span><span class=\"hidden hs-selected:block\"><svg class=\"shrink-0 size-4 text-white\" xmlns=\"http://www.w3.org/2000/svg\" width=\"16\" height=\"16\" fill=\"currentColor\" viewBox=\"0 0 16 16\"><path d=\"M12.736 3.97a.733.733 0 0 1 1.047 0c.286.289.29.756.01 1.05L7.88 12.01a.733.733 0 0 1-1.065.02L3.217 8.384a.757.757 0 0 1 0-1.06.733.733 0 0 1 1.047 0l3.052 3.093 5.4-6.425a.247.247 0 0 1 .02-.022Z\"/></svg></span></div>"
        }
        JSON;

        return $json;
    }

    /**
     * Config compacta usada por los selects de mes/año de los calendarios
     * (date-picker, date-range-picker): sin borde de "toggle" propio (el texto
     * hace de botón) y ancho de dropdown ajustable.
     *
     * @param  string  $dropdownWidth  Clase Tailwind de ancho del dropdown, ej. "w-36".
     */
    public static function calendar(string $dropdownWidth = 'w-36'): string
    {
        $json = <<<'JSON'
        {
            "scrollToSelected": true,
            "toggleTag": "<button type=\"button\" aria-expanded=\"false\"></button>",
            "toggleClasses": "hs-select-disabled:pointer-events-none hs-select-disabled:opacity-50 relative flex text-nowrap w-full cursor-pointer text-start font-medium text-zinc-700 dark:text-zinc-300 hover:text-accent focus:outline-hidden focus:text-accent before:absolute before:inset-0 before:z-1",
            "dropdownClasses": "mt-2 z-50 __DROPDOWN_WIDTH__ max-h-72 p-1 space-y-0.5 overflow-hidden overflow-y-auto bg-white border border-zinc-200 rounded-lg shadow-xl [&::-webkit-scrollbar]:w-2 [&::-webkit-scrollbar-track]:rounded-full [&::-webkit-scrollbar-track]:bg-stone-100 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-stone-300 dark:[&::-webkit-scrollbar-track]:bg-neutral-700 dark:[&::-webkit-scrollbar-thumb]:bg-neutral-500 dark:bg-zinc-700 dark:border-white/10",
            "optionClasses": "py-2 px-3 w-full text-sm text-zinc-700 dark:text-zinc-300 cursor-pointer hover:bg-zinc-100 dark:hover:bg-white/10 rounded-md focus:outline-hidden focus:bg-zinc-100 dark:focus:bg-white/10 hs-selected:bg-accent hs-selected:text-white dark:hs-selected:bg-accent dark:hs-selected:text-white",
            "optionTemplate": "<div class=\"flex justify-between items-center w-full\"><span data-title></span><span class=\"hidden hs-selected:block\"><svg class=\"shrink-0 size-3.5 text-white\" xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><polyline points=\"20 6 9 17 4 12\"/></svg></span></div>"
        }
        JSON;

        return str_replace('__DROPDOWN_WIDTH__', $dropdownWidth, $json);
    }
}
