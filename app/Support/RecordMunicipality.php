<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Resolves the municipality id of a record for record-level scope checks
 * (P10 §12.B.3, P11 §18.D/§19).
 *
 * This class performs DATA RESOLUTION only — it never makes an authorization
 * decision. Controllers feed the result to
 * AccessControlService::canAccessRecord, which remains the single authority
 * (P9 §13.4). The row is always read from the DB, never from the request, so
 * a tampered id/hidden field cannot alter the checked value (P11 §15/§16).
 */
class RecordMunicipality
{
    /**
     * Direct municipality of a tbl_clients row (tbl_clients.city_municipality
     * holds int ids in a varchar, no FK — FACT, P10 §1).
     */
    public static function ofClient(int $clientId): int
    {
        return (int) DB::table('tbl_clients')->where('id', $clientId)->value('city_municipality');
    }

    /**
     * Municipality of the client bound to a transaction (derived, P10 §1/§14).
     */
    public static function ofTransaction(int $transactionId): int
    {
        return self::municipalityOfClientByJoin('tbl_transactions', 'id', 'client_id', $transactionId);
    }

    /**
     * Municipality of the head client of a household (derived). tbl_household
     * identifies its head by the head_household FK to tbl_clients (P10 §1/§14).
     */
    public static function ofHousehold(int $householdId): int
    {
        return self::municipalityOfClientByJoin('tbl_household', 'id', 'head_household', $householdId);
    }

    /**
     * Municipality of the client bound to a scholar row (derived, P10 §14).
     */
    public static function ofScholar(int $scholarId): int
    {
        return self::municipalityOfClientByJoin('tbl_scholar_info', 'id', 'client_id', $scholarId);
    }

    /**
     * Municipality of the client bound to a GIP row (derived, P10 §14).
     */
    public static function ofGip(int $gipId): int
    {
        return self::municipalityOfClientByJoin('tbl_gip_info', 'id', 'client_id', $gipId);
    }

    /**
     * Municipality of an unpaid verification row — the only table with its own
     * true FK to tbl_municipalities (FACT, P10 §1).
     */
    public static function ofUnpaidVerification(int $unpaidVerificationId): int
    {
        return (int) DB::table('tbl_unpaid_verifications')
            ->where('id', $unpaidVerificationId)
            ->value('municipality_id');
    }

    /**
     * Municipality of the client bound through a derived row's client column.
     *
     * @param  string  $table  derived table
     * @param  string  $idColumn  its PK column
     * @param  string  $clientColumn  its column referencing tbl_clients.id
     */
    private static function municipalityOfClientByJoin(string $table, string $idColumn, string $clientColumn, int $id): int
    {
        $clientId = DB::table($table)->where($idColumn, $id)->value($clientColumn);

        if ($clientId === null) {
            return 0;
        }

        return (int) DB::table('tbl_clients')->where('id', (int) $clientId)->value('city_municipality');
    }
}
