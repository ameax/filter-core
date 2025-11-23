<?php

return [
    'filter_type' => [
        'select' => 'Auswahl',
        'multi_select' => 'Mehrfachauswahl',
        'integer' => 'Ganzzahl',
        'decimal' => 'Dezimalzahl',
        'date' => 'Datum',
        'datetime' => 'Datum & Uhrzeit',
        'text' => 'Text',
        'boolean' => 'Ja/Nein',
    ],

    'match_mode' => [
        'is' => 'ist',
        'is_not' => 'ist nicht',
        'any' => 'enthält einen von',
        'all' => 'enthält alle',
        'none' => 'enthält keinen von',
        'greater_than' => 'größer als',
        'greater_than_or_equal' => 'größer oder gleich',
        'less_than' => 'kleiner als',
        'less_than_or_equal' => 'kleiner oder gleich',
        'between' => 'zwischen',
        'contains' => 'enthält',
        'starts_with' => 'beginnt mit',
        'ends_with' => 'endet mit',
        'empty' => 'ist leer',
        'not_empty' => 'ist nicht leer',
    ],

    'group_operator' => [
        'and' => 'UND',
        'or' => 'ODER',
    ],
];
