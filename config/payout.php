<?php

/*
|--------------------------------------------------------------------------
| Payout attendance screens (P5)
|--------------------------------------------------------------------------
|
| One entry per v1 list screen (scanned_payouts*.php). The three screens are
| near-identical — they differ only in the backing scan table, the title, the
| seat table used for the Section/Box/Row/Seat columns, the program filter
| options, the Open Scanner target and the client-name source. Driving the
| shared controller + view from this config is the P5 lesson: config drives
| the variant, not three copies of the screen.
|
| client_name: 'client'        -> CONCAT(c.lastname, ', ', c.firstname[, ' ' c.middlename])
|              'patient_name'  -> t.patient_name (unpaid attendance, v1 quirk)
| seat_table:  null            -> the screen has no Section/Box/Row/Seat columns
*/

return [

    'attendance' => [

        'scanned_payouts' => [
            'variant' => 'scanned_payouts',
            'page' => 'scanned_payouts.php',
            'title' => 'Scanned Payouts',
            'table' => 'tbl_payout_scans',
            'seat_table' => 'tbl_seats',
            'client_name' => 'client',
            'programs' => ['AICS', 'AKAP', 'MAIP', 'TUPAD', 'CEDSSG', 'CEAP', 'CEAP_NEW', 'CEDSSG_NEW', 'OTEA', 'OTCES'],
            'scanner_route' => 'scanners.payout',
            'scanner_label' => 'Open Scanner',
            'modal_title' => 'Scanned Payout Details',
        ],

        'scanned_payouts2' => [
            'variant' => 'scanned_payouts2',
            'page' => 'scanned_payouts2.php',
            'title' => 'Scanned Payouts',
            'table' => 'tbl_payout_scans2',
            'seat_table' => 'tbl_seats2',
            'client_name' => 'client',
            'programs' => ['AICS', 'AKAP', 'MAIP', 'TUPAD', 'CEDSSG', 'CEAP', 'CEAP_NEW', 'CEDSSG_NEW', 'OTEA', 'OTCES'],
            'scanner_route' => 'scanners.payout',
            'scanner_label' => 'Open Scanner',
            'modal_title' => 'Scanned Payout Details',
        ],

        'scanned_payouts_unpaid' => [
            'variant' => 'scanned_payouts_unpaid',
            'page' => 'scanned_payouts_unpaid.php',
            'title' => 'Scanned Unpaid Payouts',
            'table' => 'tbl_payout_scans_unpaid',
            'seat_table' => null,
            'client_name' => 'patient_name',
            'programs' => ['CEAP', 'CEAP_NEW', 'OTEA', 'OTCES'],
            'scanner_route' => 'scanners.payout_unpaid',
            'scanner_label' => 'Open Unpaid Scanner',
            'modal_title' => 'Scanned Unpaid Payout Details',
        ],

    ],

];
