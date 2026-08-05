<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Módulos de Billingo
    |--------------------------------------------------------------------------
    |
    | Catálogo de módulos que una compañía puede activar. Cada módulo define
    | su propio set de roles asignables (independiente de los otros módulos).
    | El rol 'owner' de la compañía siempre tiene acceso total a los módulos
    | que la compañía tenga activos, sin necesidad de asignación explícita.
    |
    */

    'invoicing' => [
        'name' => 'Emisión de facturas',
        'roles' => ['administrador', 'vendedor', 'auditor'],
    ],

    'receiving' => [
        'name' => 'Recepción de documentos',
        'roles' => ['administrador', 'comprador', 'auditor'],
    ],

    'payroll' => [
        'name' => 'Nómina',
        'roles' => ['administrador', 'analista', 'auditor'],
    ],

];
