<?php

declare(strict_types=1);

return [

    'model' => 'Activity',
    'models' => 'Activity',

    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'system' => 'System',

    'columns' => [
        'time' => 'Time',
        'event' => 'Event',
        'description' => 'Description',
        'log' => 'Log',
        'subject' => 'Record',
        'causer' => 'By',
    ],

    'events' => [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'restored' => 'Restored',
    ],

    'filters' => [
        'from' => 'From',
        'until' => 'Until',
    ],

    'actions' => [
        'activity' => 'Activity',
        'heading' => 'Activity of :record',
        'close' => 'Close',
        'empty' => 'Nothing has been recorded about this record yet.',
        'truncated' => 'Showing the last :count of :total entries. The full history lives in the activity log.',
    ],

    'detail' => [
        'title' => 'Entry #:id',
        'system' => 'System',
        'changes' => 'Changes',
        'changes_hint' => 'The attributes as they were, and as they became.',
        'before' => 'Before',
        'after' => 'After',
        'attribute' => 'Attribute',
        'no_changes' => 'No attributes changed',
        'context' => 'Context',
        'causer' => 'Author',
        'hidden' => 'hidden · the value is not shown before or after',
        'empty' => 'no value',
        'ip' => 'IP',
        'via' => 'Via',
        'system_note' => 'Nobody was signed in: a command, a job or a deployment operation.',
        'subject' => 'Affected record',
        'first_value' => 'first value',
        'cleared' => 'cleared',
        'no_event' => 'entry',
        'origin' => 'Origin',
        'entry_point' => 'Entry point',
        'no_request' => 'Outside a web request: a command, a job or a deployment operation.',
        'logged_at' => 'Logged at',
    ],

];
