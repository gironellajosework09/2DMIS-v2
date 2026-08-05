<?php

namespace App\Services;

use App\Models\MultiDeviceExemption;
use App\Models\Permission;
use App\Models\ProgramPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Single authorization service for the whole application (ADR-003).
 *
 * Replaces v1's dual ACL (restriction.php + hard-coded usernames) and the
 * implicit `user_id = 1` super-user. There are no username checks or magic ids
 * anywhere in the app — super-admin status is a data row: a tbl_permissions
 * entry whose page_name equals SUPER_ADMIN_PAGE.
 */
class AccessControlService
{
    public const SUPER_ADMIN_PAGE = '*';

    /** @var array<int, Collection<int, Permission>> */
    private array $pagePermissions = [];

    /** @var array<int, Collection<int, ProgramPermission>> */
    private array $programPermissions = [];

    /** @var array<int, bool> */
    private array $multiDeviceExemptions = [];

    public function isSuperAdmin(User $user): bool
    {
        return $this->pagePermissions($user)
            ->contains(fn (Permission $p) => $p->page_name === self::SUPER_ADMIN_PAGE && $p->can_access);
    }

    public function canAccessPage(User $user, string $pageName): bool
    {
        if ($pageName === self::SUPER_ADMIN_PAGE) {
            return $this->isSuperAdmin($user);
        }

        return $this->isSuperAdmin($user)
            || $this->pagePermissions($user)
                ->contains(fn (Permission $p) => $p->page_name === $pageName && $p->can_access);
    }

    public function canAccessProgram(User $user, string $programName): bool
    {
        return $this->isSuperAdmin($user)
            || $this->programPermissions($user)
                ->contains(fn (ProgramPermission $p) => $p->program_name === $programName);
    }

    /**
     * v1 exempted the hard-coded admin usernames and multi-device-exempt users
     * from the single-device token check. Super-admin here is a permission, so
     * it is expressed through the same service.
     */
    public function isSingleDeviceExempt(User $user): bool
    {
        return $this->isSuperAdmin($user) || $this->isMultiDeviceExempt($user);
    }

    public function isMultiDeviceExempt(User $user): bool
    {
        if (! array_key_exists($user->id, $this->multiDeviceExemptions)) {
            $this->multiDeviceExemptions[$user->id] = MultiDeviceExemption::query()
                ->where('user_id', $user->id)
                ->exists();
        }

        return $this->multiDeviceExemptions[$user->id];
    }

    /**
     * @return list<string>
     */
    public function permittedPages(User $user): array
    {
        return $this->pagePermissions($user)
            ->where('can_access', true)
            ->pluck('page_name')
            ->all();
    }

    /**
     * @return list<string>
     */
    public function permittedPrograms(User $user): array
    {
        return $this->programPermissions($user)->pluck('program_name')->all();
    }

    /**
     * @return Collection<int, Permission>
     */
    private function pagePermissions(User $user): Collection
    {
        return $this->pagePermissions[$user->id]
            ??= $user->permissions()->get(['page_name', 'can_access']);
    }

    /**
     * @return Collection<int, ProgramPermission>
     */
    private function programPermissions(User $user): Collection
    {
        return $this->programPermissions[$user->id]
            ??= $user->programPermissions()->get(['program_name']);
    }
}
