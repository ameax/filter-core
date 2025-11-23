<?php

return [
    'filter_type' => [
        'select' => 'Select',
        'multi_select' => 'Multi Select',
        'integer' => 'Integer',
        'decimal' => 'Decimal',
        'date' => 'Date',
        'datetime' => 'Date & Time',
        'text' => 'Text',
        'boolean' => 'Boolean',
    ],

    'match_mode' => [
        'is' => 'is',
        'is_not' => 'is not',
        'any' => 'is any of',
        'all' => 'is all of',
        'none' => 'is none of',
        'greater_than' => 'greater than',
        'greater_than_or_equal' => 'greater than or equal',
        'less_than' => 'less than',
        'less_than_or_equal' => 'less than or equal',
        'between' => 'between',
        'contains' => 'contains',
        'starts_with' => 'starts with',
        'ends_with' => 'ends with',
        'empty' => 'is empty',
        'not_empty' => 'is not empty',
    ],

    'group_operator' => [
        'and' => 'AND',
        'or' => 'OR',
    ],
];
