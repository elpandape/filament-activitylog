<?php

declare(strict_types=1);

return [

    'model' => 'Actividad',
    'models' => 'Actividad',

    'today' => 'Hoy',
    'yesterday' => 'Ayer',
    'system' => 'Sistema',

    'columns' => [
        'time' => 'Hora',
        'event' => 'Evento',
        'description' => 'Descripción',
        'log' => 'Registro',
        'subject' => 'Afectado',
        'causer' => 'Autor',
    ],

    'events' => [
        'created' => 'Creado',
        'updated' => 'Actualizado',
        'deleted' => 'Eliminado',
        'restored' => 'Restaurado',
    ],

    'filters' => [
        'from' => 'Desde',
        'until' => 'Hasta',
    ],

    'actions' => [
        'activity' => 'Actividad',
        'heading' => 'Actividad de :record',
        'close' => 'Cerrar',
        'empty' => 'Todavía no se ha registrado nada sobre este registro.',
        'truncated' => 'Se muestran los últimos :count de :total asientos. La historia completa está en el registro de actividad.',
    ],

    'detail' => [
        'title' => 'Asiento n.º :id',
        'system' => 'Sistema',
        'changes' => 'Cambios',
        'changes_hint' => 'Los atributos como estaban y como quedaron.',
        'before' => 'Antes',
        'after' => 'Después',
        'attribute' => 'Atributo',
        'no_changes' => 'Ningún atributo cambió',
        'context' => 'Contexto',
        'causer' => 'Autor',
        'hidden' => 'oculto · el valor no se muestra ni antes ni después',
        'empty' => 'sin valor',
        'ip' => 'IP',
        'via' => 'Vía',
        'system_note' => 'Nadie había iniciado sesión: un comando, un job o una operación de despliegue.',
        'subject' => 'Registro afectado',
        'first_value' => 'primer valor',
        'cleared' => 'vaciado',
        'no_event' => 'asiento',
        'origin' => 'Origen',
        'entry_point' => 'Punto de entrada',
        'no_request' => 'Fuera de una petición web: un comando, un job o una operación de despliegue.',
        'logged_at' => 'Registrado el',
    ],

];
