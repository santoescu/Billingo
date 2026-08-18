<?php

return [

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

    'pos' => [
        'name' => 'Punto de venta',
        'roles' => ['administrador', 'cajero', 'auditor'],
    ],

    'cotizaciones' => [
        'name' => 'Cotizaciones',
        'roles' => ['administrador', 'vendedor', 'auditor'],
    ],

];
