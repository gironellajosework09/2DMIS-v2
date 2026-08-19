<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $table = 'tbl_users';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
    ];

    protected $hidden = [
        'password',
        'session_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /**
     * Users are identified by their username, not by an email address.
     */
    public function getAuthIdentifierName(): string
    {
        return 'username';
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'user_id');
    }

    public function programPermissions(): HasMany
    {
        return $this->hasMany(ProgramPermission::class, 'user_id');
    }

    public function multiDeviceExemptions(): HasMany
    {
        return $this->hasMany(MultiDeviceExemption::class, 'user_id');
    }

    public function actionPermissions(): HasMany
    {
        return $this->hasMany(ActionPermission::class, 'user_id');
    }

    public function municipalityScope(): HasMany
    {
        return $this->hasMany(UserMunicipality::class, 'user_id');
    }
}
