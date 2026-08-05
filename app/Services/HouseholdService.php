<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Household;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HouseholdService
{
    public function __construct(private readonly AuditService $auditService) {}

    public function generateHouseholdId(int $municipalityId): string
    {
        $municipality = Municipality::query()->find($municipalityId);

        if (! $municipality) {
            throw new \InvalidArgumentException('Invalid municipality.');
        }

        $code = $municipality->code !== null && trim($municipality->code) !== ''
            ? strtoupper(trim($municipality->code))
            : $this->generateFallbackCode($municipality->name);

        $last = DB::table('tbl_household')
            ->where('household_id', 'LIKE', $code.'-%')
            ->orderByDesc('household_id')
            ->value('household_id');

        if ($last) {
            $lastNumber = (int) substr($last, strlen($code) + 1);
            $next = $lastNumber + 1;
        } else {
            $next = 1;
        }

        return sprintf('%s-%05d', $code, $next);
    }

    public function create(int $headHousehold, User $actor): Household
    {
        return DB::transaction(function () use ($headHousehold, $actor) {
            $client = Client::query()->find($headHousehold);

            if (! $client) {
                throw new \InvalidArgumentException('Selected client was not found.');
            }

            if (Household::query()->where('head_household', $headHousehold)->exists()) {
                throw new \InvalidArgumentException('This client is already the head of another household.');
            }

            if (empty($client->city_municipality)) {
                throw new \InvalidArgumentException(
                    'Selected client has no municipality on file, so a household ID cannot be generated.'
                );
            }

            $household = Household::query()->create([
                'household_id' => $this->generateHouseholdId($client->city_municipality),
                'head_household' => $headHousehold,
            ]);

            $this->auditService->log($actor->id, 'ADD_HOUSEHOLD', 'tbl_household', $household->id, null, json_encode([
                'household_id' => $household->household_id,
                'head_household' => $headHousehold,
                'head_name' => $client->full_name,
            ]));

            return $household;
        });
    }

    public function destroy(Household $household, User $actor): void
    {
        DB::transaction(function () use ($household, $actor) {
            $headName = Client::query()->where('id', $household->head_household)->value('full_name');

            $old = json_encode([
                'household_id' => $household->household_id,
                'head_household' => $household->head_household,
                'head_name' => $headName,
            ]);

            Client::query()
                ->where('household_id', $household->id)
                ->update(['household_id' => null]);

            $household->delete();

            $this->auditService->log($actor->id, 'DELETE_HOUSEHOLD', 'tbl_household', $household->id, $old, null);
        });
    }

    private function generateFallbackCode(string $name): string
    {
        $clean = (string) preg_replace('/\b(CITY|MUNICIPALITY|OF)\b/i', '', $name);
        $clean = (string) preg_replace('/[^A-Za-z]/', '', $clean);
        $clean = strtoupper(substr($clean, 0, 3));

        return $clean !== '' ? $clean : 'HHD';
    }
}
