<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
