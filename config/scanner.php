<?php

use App\Services\TransactionService;

/*
|--------------------------------------------------------------------------
| Scanner engine configuration (P4)
|--------------------------------------------------------------------------
|
| One entry per scanner page. All v1 business behavior is driven from here:
| the behavioral mode, the allowed program list (or per-program template for
| the derived scanners), the duplicate rule, the transaction insert/update
| templates, the payout-attendance target table, the audit event, the ACL
| page gate and the UI options. Semester-bound constants (remarks strings,
| amounts, payout dates) live here so staff can move a batch without code
| changes. Programs NOT in this file are not reachable through the scanner
| engine (they are served by the P3 transaction module / later phases).
|
| Behavioral modes:
|   scholarship_transaction    fixed insert + remark-key dup (CEAP family, OTEA, OTCES)
|   date_guarded_transaction   insert with date inputs + date-applied dup (TODA, TUPAD)
|   update_in_place            lookup pending 2nd-sem tx + idempotent UPDATE (cedssg_update)
|   exam_derived               client -> exam -> results, program derived server-side
|   validate_existing          program from latest tx, remark+name dup, no audit
|   seat_attendance            seats2 JOIN lookup, one-scan-per-tx (payout)
|   unpaid_attendance          partial patient match, one-scan-per-tx (payout_unpaid)
|   generic_form               free-form insert, beneficiary options, no dup, no audit
|
| Duplicate rules:
|   remark_key         client_id + program + remarks
|   remark_key_with_name  remark_key + patient_name
|   date_applied       client_id + program + date_applied
|   one_scan           DB UNIQUE(transaction_id) + app pre-check
|   none
*/

return [

    'scanners' => [

        'ceap' => [
            'key' => 'ceap',
            'mode' => 'scholarship_transaction',
            'title' => 'CEAP Scholarship',
            'page' => 'scanner_ceap.php',
            'lookup' => 'client',
            'lookup_miss_message' => "Client not found for scanned code: '{scanned}'",
            'programs' => ['CEAP'],
            'insert' => [
                'type' => 'SCHOLARSHIP',
                'status' => 'PENDING PAYOUT',
                'remarks' => '1ST SEM SY2025-2026 DOCS SUBMITTED',
                'suggested_amount' => 5000,
                'payout_date' => null,
            ],
            'duplicate' => [
                'rule' => 'remark_key',
                'message' => 'Transaction already recorded for this client.',
            ],
            'audit' => [
                'action' => 'SCAN-CEAP',
                'fields' => ['program', 'client_id', 'patient_name', 'remarks', 'status'],
            ],
            'ui' => [
                'fields' => [],
                'resume' => false,
                'success_message' => 'Transaction saved successfully!',
            ],
        ],

        'ceap_new' => [
            'key' => 'ceap_new',
            'mode' => 'scholarship_transaction',
            'title' => 'CEAP New Scholar',
            'page' => 'scanner_ceap_new.php',
            'lookup' => 'client',
            'lookup_miss_message' => "Client not found for scanned code: '{scanned}'",
            'programs' => ['CEAP_NEW'],
            'insert' => [
                'type' => 'SCHOLARSHIP',
                'status' => 'PENDING PAYOUT',
                'remarks' => '1ST SEM SY2025-2026 DOCS SUBMITTED',
                'suggested_amount' => 5000,
                'payout_date' => '2025-08-18',
            ],
            'duplicate' => [
                'rule' => 'remark_key',
                'message' => 'Transaction already recorded for this client.',
            ],
            'audit' => [
                'action' => 'SCAN-CEAP_NEW',
                'fields' => ['program', 'client_id', 'patient_name', 'remarks', 'status'],
            ],
            'ui' => [
                'fields' => [],
                'resume' => false,
                'success_message' => 'Transaction saved successfully!',
            ],
        ],

        'cedssg' => [
            'key' => 'cedssg',
            'mode' => 'scholarship_transaction',
            'title' => 'CEDSSG Scholarship',
            'page' => 'scanner_cedssg.php',
            'lookup' => 'client',
            'lookup_miss_message' => "Client not found for scanned code: '{scanned}'",
            'programs' => ['CEDSSG'],
            'insert' => [
                'type' => 'SCHOLARSHIP',
                'status' => 'PENDING PAYOUT',
                'remarks' => '1ST SEM SY2025-2026 DOCS SUBMITTED',
                'suggested_amount' => 10000,
                'payout_date' => null,
            ],
            'duplicate' => [
                'rule' => 'remark_key',
                'message' => 'Transaction already recorded for this client.',
            ],
            'audit' => [
                'action' => 'SCAN-CEDSSG',
                'fields' => ['program', 'client_id', 'patient_name', 'remarks', 'status'],
            ],
            'ui' => [
                'fields' => [],
                'resume' => false,
                'success_message' => 'Transaction saved successfully!',
            ],
        ],

        'cedssg_new' => [
            'key' => 'cedssg_new',
            'mode' => 'scholarship_transaction',
            'title' => 'CEDSSG New Scholar',
            'page' => 'scanner_cedssg_new.php',
            'lookup' => 'client',
            'lookup_miss_message' => "Client not found for scanned code: '{scanned}'",
            'programs' => ['CEDSSG_NEW'],
            'insert' => [
                'type' => 'SCHOLARSHIP',
                'status' => 'PENDING PAYOUT',
                'remarks' => '1ST SEM SY2025-2026 DOCS SUBMITTED',
                'suggested_amount' => 10000,
                'payout_date' => '2025-08-18',
            ],
            'duplicate' => [
                'rule' => 'remark_key',
                'message' => 'Transaction already recorded for this client.',
            ],
            'audit' => [
                'action' => 'SCAN-CEDSSG_NEW',
                'fields' => ['program', 'client_id', 'patient_name', 'remarks', 'status'],
            ],
            'ui' => [
                'fields' => [],
                'resume' => false,
                'success_message' => 'Transaction saved successfully!',
            ],
        ],

        'cedssg_update' => [
            'key' => 'cedssg_update',
            'mode' => 'update_in_place',
            'title' => 'CEDSSG 2nd Sem Payment',
            'page' => 'scanner_cedssg_update.php',
            'lookup' => 'transaction',
            'programs' => ['CEDSSG', 'CEDSSG_NEW'],
            'update' => [
                'status' => 'PAID',
                'amount_paid' => 12500,
                'date_paid' => 'input',
            ],
            'duplicate' => [
                'rule' => 'none',
            ],
            'audit' => [
                'action' => 'UPDATE-CEDSSG-PAYMENT',
                'fields' => ['status', 'amount_paid', 'date_paid'],
            ],
            'ui' => [
                'fields' => ['date_paid'],
                'amount_paid_readonly' => 12500,
                'resume' => true,
                'success_message' => 'Payment updated successfully!',
            ],
        ],

        'otces' => [
            'key' => 'otces',
            'mode' => 'scholarship_transaction',
            'title' => 'OTCES Scholarship',
            'page' => 'scanner_otces.php',
            'lookup' => 'client',
            'lookup_miss_message' => "Client not found for scanned code: '{scanned}'",
            'programs' => ['OTCES'],
            'insert' => [
                'type' => 'SCHOLARSHIP',
                'status' => 'PENDING PAYOUT',
                'remarks' => 'SCHOOL YEAR 2025-2026',
                'suggested_amount' => 3000,
                'payout_date' => '2025-08-25',
            ],
            'duplicate' => [
                'rule' => 'remark_key',
                'message' => 'Transaction already recorded for this client.',
            ],
            'audit' => [
                'action' => 'SCAN-OTCES',
                'fields' => ['program', 'client_id', 'patient_name', 'remarks', 'status'],
            ],
            'ui' => [
                'fields' => [],
                'resume' => false,
                'success_message' => 'Transaction saved successfully!',
            ],
        ],

        'otea' => [
            'key' => 'otea',
            'mode' => 'scholarship_transaction',
            'title' => 'OTEA Scholarship',
            'page' => 'scanner_otea.php',
            'lookup' => 'client',
            'lookup_miss_message' => "Client not found for scanned code: '{scanned}'",
            'programs' => ['OTEA'],
            'insert' => [
                'type' => 'SCHOLARSHIP',
                'status' => 'PENDING PAYOUT',
                'remarks' => 'SCHOOL YEAR 2025-2026',
                'suggested_amount' => 5000,
                'payout_date' => '2025-08-18',
            ],
            'duplicate' => [
                'rule' => 'remark_key',
                'message' => 'Transaction already recorded for this client.',
            ],
            'audit' => [
                'action' => 'SCAN-OTEA',
                'fields' => ['program', 'client_id', 'patient_name', 'remarks', 'status'],
            ],
            'ui' => [
                'fields' => [],
                'resume' => false,
                'success_message' => 'Transaction saved successfully!',
            ],
        ],

        'toda' => [
            'key' => 'toda',
            'mode' => 'date_guarded_transaction',
            'title' => 'TODA Cash Relief',
            'page' => 'scanner_toda.php',
            'lookup' => 'client_geo',
            'include_barangay' => true,
            'lookup_miss_message' => 'Client not found',
            'programs' => ['TODA'],
            'insert' => [
                'type' => 'CASH RELIEF ASSISTANCE',
                'status' => 'PAID',
                'remarks' => '',
                'suggested_amount' => 0,
                'amount_paid' => 'input',
            ],
            'duplicate' => [
                'rule' => 'date_applied',
                'message' => 'TODA transaction already exists for this client.',
                'show_existing' => false,
            ],
            'audit' => [
                'action' => 'SCAN-TODA',
                'fields' => ['program', 'client_id', 'patient_name', 'amount_paid', 'status'],
            ],
            'ui' => [
                'fields' => ['date_applied', 'date_paid', 'amount_paid'],
                'resume' => true,
                'success_message' => 'TODA transaction saved successfully!',
            ],
        ],

        'tupad' => [
            'key' => 'tupad',
            'mode' => 'date_guarded_transaction',
            'title' => 'TUPAD Cash for Work',
            'page' => 'scanner_tupad.php',
            'lookup' => 'client_geo',
            'lookup_miss_message' => "Client not found for scanned code: '{scanned}'",
            'programs' => ['TUPAD'],
            'insert' => [
                'type' => 'CASH FOR WORK',
                'status' => 'PAID',
                'remarks' => 'TUPAD LGBTQIA+',
                'suggested_amount' => 0,
                'amount_paid' => 4680.00,
            ],
            'duplicate' => [
                'rule' => 'date_applied',
                'message' => 'TUPAD transaction already recorded for this client on {date}.',
                'show_existing' => true,
            ],
            'audit' => [
                'action' => 'SCAN-TUPAD',
                'fields' => ['program', 'client_id', 'patient_name', 'remarks', 'status'],
                // v1 quirk preserved: the audit payload remarks differ from the
                // stored transaction remarks ("TUPAD REGISTRATION 2025" vs
                // "TUPAD LGBTQIA+").
                'values' => ['remarks' => 'TUPAD REGISTRATION 2025'],
            ],
            'ui' => [
                'fields' => ['date_applied', 'date_paid'],
                'resume' => true,
                'success_message' => 'TUPAD transaction saved successfully!',
            ],
        ],

        'generic' => [
            'key' => 'generic',
            'mode' => 'generic_form',
            'title' => 'Generic Transaction',
            'page' => 'scanner_generic.php',
            'lookup' => 'client',
            'lookup_miss_message' => "Client not found for '{scanned}'",
            // v1 offered exactly these six programs in the generic form.
            'programs' => ['AICS', 'AKAP', 'MAIP', 'TUPAD', 'CEDSSG', 'CEAP'],
            'patient_override' => true,
            'duplicate' => [
                'rule' => 'none',
            ],
            'ui' => [
                'fields' => ['program', 'patient_option', 'date_applied', 'type', 'status', 'remarks', 'comments', 'suggested_amount', 'amount_paid', 'payout_date', 'date_paid', 'gwa', 'units'],
                'types' => TransactionService::TYPES,
                'statuses' => TransactionService::STATUSES,
                'resume' => false,
                'scan_success_sound' => true,
                'success_message' => 'Transaction saved successfully',
            ],
        ],

        'new_scholars' => [
            'key' => 'new_scholars',
            'mode' => 'exam_derived',
            'title' => 'New Scholars (Exam)',
            'page' => 'scanner_new_scholars.php',
            'lookup' => 'exam_derived',
            'programs' => [
                'CEAP_NEW' => [
                    'remarks' => '1ST SEM SY2025-2026 DOCS SUBMITTED',
                    'suggested_amount' => 5000,
                    'payout_date' => '2025-08-18',
                ],
                'OTEA' => [
                    'remarks' => 'SCHOOL YEAR 2025-2026',
                    'suggested_amount' => 5000,
                    'payout_date' => '2025-08-18',
                ],
                'OTCES' => [
                    'remarks' => 'SCHOOL YEAR 2025-2026',
                    'suggested_amount' => 3000,
                    'payout_date' => '2025-08-25',
                ],
                'CEDSSG_NEW' => [
                    'remarks' => '1ST SEM SY2025-2026 DOCS SUBMITTED',
                    'suggested_amount' => 10000,
                    'payout_date' => '2025-08-18',
                ],
            ],
            'insert' => [
                'type' => 'SCHOLARSHIP',
                'status' => 'PENDING PAYOUT',
            ],
            'duplicate' => [
                'rule' => 'remark_key',
                'message' => 'Transaction already recorded for this client.',
            ],
            'audit' => [
                'action' => 'SCAN-{program}',
                'fields' => ['program', 'client_id', 'patient_name', 'remarks', 'status'],
            ],
            'ui' => [
                'fields' => [],
                'resume' => false,
                'success_message' => 'Transaction saved successfully!',
            ],
        ],

        'ongoing_scholars' => [
            'key' => 'ongoing_scholars',
            'mode' => 'validate_existing',
            'title' => 'Ongoing Scholars',
            'page' => 'scanner_ongoing_scholars.php',
            'lookup' => 'existing_program',
            'programs' => [
                'CEAP' => [
                    'remarks' => '2ND SEM SY 2025-2026 DOCS SUBMITTED',
                    'suggested_amount' => 5000,
                ],
                'CEAP_NEW' => [
                    'remarks' => '2ND SEM SY 2025-2026 DOCS SUBMITTED',
                    'suggested_amount' => 5000,
                ],
                'CEDSSG' => [
                    'remarks' => '2ND SEM SY 2025-2026 DOCS SUBMITTED',
                    'suggested_amount' => 11600,
                ],
                'CEDSSG_NEW' => [
                    'remarks' => '2ND SEM SY 2025-2026 DOCS SUBMITTED',
                    'suggested_amount' => 11600,
                ],
            ],
            'insert' => [
                'type' => 'SCHOLARSHIP',
                'status' => 'PENDING PAYOUT',
            ],
            'duplicate' => [
                'rule' => 'remark_key_with_name',
                'message' => 'Transaction already recorded',
            ],
            'audit' => null,
            'ui' => [
                'fields' => [],
                'resume' => false,
                'success_message' => 'Transaction saved successfully.',
            ],
        ],

        'payout' => [
            'key' => 'payout',
            'mode' => 'seat_attendance',
            'title' => 'Payout Attendance',
            'page' => 'scanner_payout.php',
            'lookup' => 'seat_join',
            'programs' => ['CEAP', 'CEDSSG', 'CEAP_NEW', 'CEDSSG_NEW', 'OTEA', 'OTCES'],
            'attendance' => 'tbl_payout_scans2',
            'duplicate' => [
                'rule' => 'one_scan',
                'message' => 'This QR code has already been scanned.',
            ],
            'audit' => null,
            'ui' => [
                'fields' => [],
                'resume' => false,
                'success_message' => 'Payout recorded successfully!',
            ],
        ],

        'payout_unpaid' => [
            'key' => 'payout_unpaid',
            'mode' => 'unpaid_attendance',
            'title' => 'Unpaid Payout Attendance',
            'page' => 'scanner_payout_unpaid.php',
            'lookup' => 'transaction_partial',
            'programs' => ['CEAP', 'CEAP_NEW', 'OTEA', 'OTCES'],
            'attendance' => 'tbl_payout_scans_unpaid',
            'duplicate' => [
                'rule' => 'one_scan',
                'message' => 'This QR code has already been scanned.',
            ],
            'audit' => null,
            'ui' => [
                'fields' => [],
                'resume' => false,
                'success_message' => 'Payout recorded successfully!',
            ],
        ],

    ],

];
