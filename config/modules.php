<?php

return [

    'invoicing' => [
        'name' => 'Emisión de documentos',
        'roles' => ['administrador', 'vendedor', 'auditor'],
        // Mismo color que ya se usa para este módulo en el panel de login
        // (components/layouts/auth/split.blade.php) -- se centraliza acá
        // para no repetir la paleta en cada lugar que muestre módulos.
        'badge_classes' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    ],

    'receiving' => [
        'name' => 'Recepción de documentos',
        'roles' => ['administrador', 'comprador', 'auditor'],
        'badge_classes' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
    ],

    'payroll' => [
        'name' => 'Nómina',
        'roles' => ['administrador', 'analista', 'auditor'],
        'badge_classes' => 'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-300',
    ],

    'pos' => [
        'name' => 'Punto de venta',
        'roles' => ['administrador', 'cajero', 'auditor'],
        'badge_classes' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
    ],

    'cotizaciones' => [
        'name' => 'Cotizaciones',
        'roles' => ['administrador', 'vendedor', 'auditor'],
        'badge_classes' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
    ],

];
