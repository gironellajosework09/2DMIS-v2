<?php

namespace App\Models;

use App\Services\ClientService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Client extends Model
{
    use HasFactory;

    protected $table = 'tbl_clients';

    public $timestamps = false;

    protected $fillable = [
        'family_id',
        'household_id',
        'lastname',
        'firstname',
        'middlename',
        'extensionname',
        'region',
        'province',
        'city_municipality',
        'barangay',
        'house_no',
        'mobile_no',
        'email',
        'birthdate',
        'age',
        'sex',
        'civil_status',
        'pwd',
        'ip',
        'ip_group',
        'occupation',
        'monthly_income',
        'category',
        'aff_org',
        'precinct_no',
        'voter_id',
        'full_name',
        'match_name',
    ];

    protected function casts(): array
    {
        return [
            'household_id' => 'integer',
            'city_municipality' => 'integer',
            'barangay' => 'integer',
            'age' => 'integer',
            'monthly_income' => 'decimal:2',
        ];
    }

    /**
     * C3-C lifecycle: every newly created client receives exactly one qr_token.
     *
     * qr_token is deliberately NOT in $fillable, so normal create/update
     * (including the single ClientService write path) can never mass-assign or
     * overwrite it. The creating hook is the one guaranteed assignment point:
     * it fires on insert only, then the token stays unchanged for the
     * client's lifetime (name/extension/household/program edits never touch it).
     */
    protected static function booted(): void
    {
        static::creating(function (Client $client) {
            if (empty($client->qr_token)) {
                $client->qr_token = self::generateQrToken();
            }
        });
    }

    /**
     * Generate a fresh opaque client QR identity token.
     *
     * Cryptographically secure (Str::random uses random_int): 16 chars drawn
     * uniformly from the base62 alphabet [0-9A-Za-z] (~95 bits of entropy).
     * The token encodes no name, client id, date, program, or transaction data
     * and is never derived deterministically from any client attribute.
     */
    public static function generateQrToken(): string
    {
        return Str::random(16);
    }

    /**
     * Idempotent backfill: assign qr_token to every client row that does not
     * already carry one.
     *
     * Only NULL/blank rows are written; existing non-null tokens are never
     * overwritten, so re-running is safe. A collision is detected against the
     * in-run set of assigned tokens and retried before hitting the database;
     * the unique database constraint remains the final authority.
     *
     * @return int number of tokens assigned
     */
    public static function ensureQrTokens(): int
    {
        $used = array_flip(
            Client::query()
                ->whereNotNull('qr_token')
                ->where('qr_token', '<>', '')
                ->pluck('qr_token')
                ->all(),
        );

        $assigned = 0;

        Client::query()
            ->whereNull('qr_token')
            ->orWhere('qr_token', '')
            ->select('id')
            ->chunkById(500, function ($rows) use (&$used, &$assigned) {
                foreach ($rows as $row) {
                    do {
                        $token = self::generateQrToken();
                    } while (isset($used[$token]));

                    $used[$token] = true;

                    DB::table('tbl_clients')
                        ->where('id', $row->id)
                        ->update(['qr_token' => $token]);

                    $assigned++;
                }
            });

        return $assigned;
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class, 'city_municipality');
    }

    public function barangayInfo(): BelongsTo
    {
        return $this->belongsTo(Barangay::class, 'barangay');
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class, 'household_id');
    }

    public function affOrgs(): HasMany
    {
        return $this->hasMany(ClientAffOrg::class, 'client_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ClientPhoto::class, 'client_id');
    }

    /**
     * The client's current profile photo. tbl_client_photos accumulates one
     * row per upload, so the "current" photo is the most recently added row
     * (highest id), not photos.first() (which is the oldest). PhotoService
     * appends a new row per upload; every display point reads this helper so
     * a fresh upload is shown immediately.
     */
    public function currentPhoto(): ?ClientPhoto
    {
        return $this->photos()->orderByDesc('id')->first();
    }

    /**
     * Canonical display name (C3-B): "LAST, FIRST (EXT) MIDDLE".
     *
     * Human-facing rendering ONLY via the single ClientService formatter. It
     * is deliberately decoupled from the persisted full_name machine/lookup
     * key (C3-A) and must never be used by scanner lookups or QR payloads.
     */
    public function displayFullName(): string
    {
        return app(ClientService::class)->deriveDisplayName(
            $this->lastname ?? '',
            $this->firstname ?? '',
            $this->middlename ?? null,
            $this->extensionname ?? null,
        );
    }

    public function familyMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class, 'client_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'client_id');
    }

    public function gipInfo(): HasMany
    {
        return $this->hasMany(GipInfo::class, 'client_id');
    }
}
