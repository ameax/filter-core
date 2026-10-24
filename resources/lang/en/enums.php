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
        'gt' => 'greater than',
        'greater_than' => 'greater than',
        'gte' => 'greater than or equal',
        'greater_than_or_equal' => 'greater than or equal',
        'lt' => 'less than',
        'less_than' => 'less than',
        'lte' => 'less than or equal',
        'less_than_or_equal' => 'less than or equal',
        'between' => 'between',
        'contains' => 'contains',
        'starts_with' => 'starts with',
        'ends_with' => 'ends with',
        'date_range' => 'in date range',
        'not_in_date_range' => 'not in date range',
        'regex' => 'regex',
        'empty' => 'is empty',
        'not_empty' => 'is not empty',
    ],

    'group_operator' => [
        'and' => 'AND',
        'or' => 'OR',
    ],
];
