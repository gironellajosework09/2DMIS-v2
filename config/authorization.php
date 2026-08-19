<?php

/*
|--------------------------------------------------------------------------
| Action + municipality scope authorization (P12)
|--------------------------------------------------------------------------
|
| S2 grant-then-flip rollout (P9 §12, P11 §21, P12 §13). `enforcement`
| defaults to FALSE so the shipped behavior is byte-identical to the
| pre-P12 page-only model. Adoption of each pilot page is a deliberate,
| audited event:
|
|   1. grant action rows (tbl_action_permissions) and scope rows / the ALL
|      marker (tbl_user_municipalities) to the page's current holders, then
|   2. flip `enforcement` to true for that page.
|
| `canAccessAction` and the scope composer read this file, so rolling back a
| page is flipping its flag back to false.
|
| The canonical action catalog (P9 §4): VIEW is page-implied (no row is
| stored for it); CREATE / EDIT / DELETE / EXPORT rows are stored in
| tbl_action_permissions. SCAN and MANAGE are reserved and never appear
| here in Phase 1.
*/

return [
    'catalog' => ['VIEW', 'CREATE', 'EDIT', 'DELETE', 'EXPORT'],

    'pages' => [
        'clients.php' => [
            'enforcement' => false,
            'actions' => ['VIEW', 'CREATE', 'EDIT', 'DELETE'],
        ],
        'household.php' => [
            'enforcement' => false,
            'actions' => ['VIEW', 'CREATE', 'DELETE'],
        ],
        'all_transactions.php' => [
            'enforcement' => false,
            'actions' => ['VIEW', 'CREATE', 'EDIT', 'DELETE', 'EXPORT'],
        ],
        'scholars.php' => [
            'enforcement' => false,
            'actions' => ['VIEW', 'CREATE', 'EDIT'],
        ],
        'register.php' => [
            'enforcement' => false,
            'actions' => ['CREATE'],
        ],
    ],
];
