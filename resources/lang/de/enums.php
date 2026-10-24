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
        'gt' => 'größer als',
        'greater_than' => 'größer als',
        'gte' => 'größer oder gleich',
        'greater_than_or_equal' => 'größer oder gleich',
        'lt' => 'kleiner als',
        'less_than' => 'kleiner als',
        'lte' => 'kleiner oder gleich',
        'less_than_or_equal' => 'kleiner oder gleich',
        'between' => 'zwischen',
        'contains' => 'enthält',
        'starts_with' => 'beginnt mit',
        'ends_with' => 'endet mit',
        'date_range' => 'im Zeitraum',
        'not_in_date_range' => 'nicht im Zeitraum',
        'regex' => 'Regex',
        'empty' => 'ist leer',
        'not_empty' => 'ist nicht leer',
    ],

    'group_operator' => [
        'and' => 'UND',
        'or' => 'ODER',
    ],
];
