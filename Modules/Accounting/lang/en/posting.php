<?php

return [
    'too_few_lines' => 'A journal entry needs at least two lines.',
    'negative_amount' => 'Line :line: amounts cannot be negative.',
    'one_side_per_line' => 'Line :line: enter either a debit or a credit.',
    'too_many_decimals' => 'Line :line: amounts can have at most 4 decimal places.',
    'account_not_postable' => 'Line :line: account :account is inactive or a group account.',
    'partner_required' => 'Line :line: account :account needs a customer or supplier.',
    'account_currency' => 'Line :line: account :account accepts only its own currency.',
    'amount_currency_required' => 'Line :line: enter the amount in the foreign currency.',
    'unbalanced' => 'The entry is not balanced: debit :debit, credit :credit.',
    'no_fiscal_period' => 'There is no fiscal period for :date. Create the fiscal year first.',
    'period_closed' => 'The fiscal period of :date is closed.',
    'before_lock_date' => 'Entries are locked up to :date.',
    'missing_mapping' => 'No account is set for ":key". Set it in account mappings.',
    'reverse_draft' => 'Only posted entries can be reversed.',
    'already_posted' => 'Entry :number is already posted.',
    'already_reversed' => 'Entry :number is already reversed or is itself a reversal.',
    'reversal_before_original' => 'The reversal cannot be dated before the original entry (:date).',
];
