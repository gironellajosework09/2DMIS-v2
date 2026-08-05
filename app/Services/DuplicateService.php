<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Port of v1 preview_duplicates.php / fetch_duplicates.php /
 * delete_duplicates.php (P2). A duplicate is a set of clients sharing the
 * same (lastname, firstname, middlename, city_municipality), matching v1's
 * GROUP BY ... HAVING COUNT(*) > 1 contract.
 */
class DuplicateService
{
    /**
     * Shared base query: clients joined to municipalities/barangays and
     * restricted to duplicated (lastname, firstname, middlename, municipality)
     * groups.
     */
    public function baseQuery(): Builder
    {
        $dup = DB::table('tbl_clients')
            ->select('lastname', 'firstname', 'middlename', 'city_municipality')
            ->groupBy('lastname', 'firstname', 'middlename', 'city_municipality')
            ->havingRaw('COUNT(*) > 1');

        return DB::table('tbl_clients as c')
            ->join('tbl_municipalities as m', 'c.city_municipality', '=', 'm.id')
            ->leftJoin('tbl_barangays as b', 'c.barangay', '=', 'b.id')
            ->joinSub($dup, 'dup', function ($join) {
                $join->on('c.lastname', '=', 'dup.lastname')
                    ->on('c.firstname', '=', 'dup.firstname')
                    ->on('c.middlename', '=', 'dup.middlename')
                    ->on('c.city_municipality', '=', 'dup.city_municipality');
            });
    }

    /**
     * Count rows matching the municipality/barangay filters (feed
     * recordsFiltered).
     */
    public function countFiltered(string $municipality = '', string $barangay = ''): int
    {
        $query = $this->baseQuery();

        if ($municipality !== '') {
            $query->where('c.city_municipality', $municipality);
        }

        if ($barangay !== '') {
            $query->where('c.barangay', $barangay);
        }

        return $query->count();
    }

    /**
     * Total number of duplicate rows (feed recordsTotal) — v1 counted this
     * without the municipality/barangay filters.
     */
    public function countTotal(): int
    {
        return $this->baseQuery()->count();
    }

    /**
     * Delete a batch of duplicate client ids, one by one, so a single
     * FK-guarded row (client with transactions) does not abort the whole
     * batch. Each successful deletion is audited as DELETE_CLIENT.
     *
     * @param  array<int, int>  $ids
     * @return array{deleted: int, failed: list<int>}
     */
    public function destroyMany(array $ids, User $user): array
    {
        $deleted = 0;
        $failed = [];

        foreach (array_unique($ids) as $id) {
            $client = Client::query()->find($id);

            if (! $client) {
                $failed[] = $id;

                continue;
            }

            try {
                app(ClientService::class)->destroy($client, $user);
                $deleted++;
            } catch (InvalidArgumentException) {
                $failed[] = $id;
            }
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }
}
