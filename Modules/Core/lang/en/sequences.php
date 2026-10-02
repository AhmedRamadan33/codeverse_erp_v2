<?php

return [
    'reset' => [
        'never' => 'Never',
        'yearly' => 'Every year',
        'monthly' => 'Every month',
    ],
    'fields' => [
        'document' => 'Document',
        'branch' => 'Branch',
        'prefix' => 'Prefix',
        'prefix_hint' => 'Tokens: {branch} {yyyy} {yy} {mm}',
        'padding' => 'Digits',
        'reset' => 'Restart numbering',
        'example' => 'Example',
    ],
    'all_branches' => 'All branches',
    'add_branch_override' => 'Separate numbering for a branch',
    'reset_needs_year' => 'A sequence that restarts yearly or monthly needs {yyyy} or {yy} in the prefix, or numbers would repeat.',
    'reset_needs_month' => 'A sequence that restarts monthly needs {mm} in the prefix, or numbers would repeat.',
    'empty' => 'Sequences appear here when modules that issue documents are enabled.',
];
