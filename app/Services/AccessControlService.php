<?php

namespace App\Services;

use App\Models\ActionPermission;
use App\Models\MultiDeviceExemption;
use App\Models\Municipality;
use App\Models\Permission;
use App\Models\ProgramPermission;
use App\Models\User;
use App\Models\UserMunicipality;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;

/**
 * Single authorization service for the whole application (ADR-003).
 *
 * Replaces v1's dual ACL (restriction.php + hard-coded usernames) and the
 * implicit `user_id = 1` super-user. There are no username checks or magic ids
 * anywhere in the app — super-admin status is a data row: a tbl_permissions
 * entry whose page_name equals SUPER_ADMIN_PAGE.
 *
 * P12 adds the action (tbl_action_permissions) and municipality
 * (tbl_user_municipalities) dimensions. The approved composition is
 * PAGE ∧ ACTION ∧ PROGRAM ∧ MUNICIPALITY, each dimension evaluated only for
 * the operations that consume it. This service is the single decision
 * authority for every dimension.
 */
class AccessControlService
{
    public const SUPER_ADMIN_PAGE = '*';

    /**
     * Reserved tbl_user_municipalities.municipality_id = "ALL" marker (P10 §16,
     * P11 §8/§9, P12 §3). Auto-increment municipality ids start at 1, so 0 can
     * never be a real municipality. Distinct from SUPER_ADMIN_PAGE ('*').
     */
    public const ALL_MUNICIPALITY_MARKER = 0;

    /** @var array<int, Collection<int, Permission>> */
    private array $pagePermissions = [];

    /** @var array<int, Collection<int, ProgramPermission>> */
    private array $programPermissions = [];

    /** @var array<int, bool> */
    private array $multiDeviceExemptions = [];

    /** @var array<int, Collection<int, ActionPermission>> */
    private array $actionPermissions = [];

    /** @var array<int, Collection<int, UserMunicipality>> */
    private array $municipalityScope = [];

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

    /**
     * Action gate (P12 §5). Truth table:
     *
     * - '*' holder            → true (bypasses ACTION; no rows needed, P9 §10)
     * - non-adopted page      → true (page-only = today's behavior, P9 §11.B)
     * - adopted, enforcement off → true (S2 default, byte-identical to today)
     * - adopted, VIEW         → canAccessPage (page row IS the VIEW grant, P9 §6)
     * - adopted, other action → presence of the (user, page, action) row
     * - unknown page/action   → false (fail closed, P9 §11)
     */
    public function canAccessAction(User $user, string $pageName, string $action): bool
    {
        $action = strtoupper($action);

        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $page = $this->pageConfig($pageName);

        if ($page === null || ! ($page['enforcement'] ?? false)) {
            return true;
        }

        if (! in_array($action, $page['actions'] ?? [], true)) {
            return false;
        }

        if ($action === 'VIEW') {
            return $this->canAccessPage($user, $pageName);
        }

        return $this->actionPermissions($user)
            ->contains(fn (ActionPermission $p) => $p->page_name === $pageName && $p->action === $action);
    }

    /**
     * The actions a user may currently perform on a page (presentation only —
     * the middleware/Gate is the boundary, P9 §13.5).
     *
     * @return list<string>
     */
    public function permittedActions(User $user, string $pageName): array
    {
        $page = $this->pageConfig($pageName);

        if ($page === null) {
            return [];
        }

        $actions = $page['actions'] ?? [];

        if ($this->isSuperAdmin($user) || ! ($page['enforcement'] ?? false)) {
            return $actions;
        }

        return array_values(array_filter(
            $actions,
            fn (string $action) => $this->canAccessAction($user, $pageName, $action)
        ));
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
     * The municipality scope pivot holds the reserved ALL marker (0) or real
     * municipality ids. Marker present ⇒ the user's effective scope is all
     * municipalities (P12 §3).
     */
    public function hasAllMunicipalities(User $user): bool
    {
        return $this->municipalityScope($user)
            ->contains(fn (UserMunicipality $m) => $m->municipality_id === self::ALL_MUNICIPALITY_MARKER);
    }

    /**
     * The user's effective municipality id set (P12 §5).
     *
     * - '*' or ALL marker → every municipality id
     * - explicit rows     → those ids (possibly empty ⇒ fail closed)
     *
     * @return list<int>
     */
    public function effectiveMunicipalityIds(User $user): array
    {
        if ($this->isSuperAdmin($user) || $this->hasAllMunicipalities($user)) {
            return Municipality::query()->orderBy('id')->pluck('id')->all();
        }

        return $this->municipalityScope($user)
            ->where('municipality_id', '>', self::ALL_MUNICIPALITY_MARKER)
            ->pluck('municipality_id')
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Record-level scope check for single-ID / write endpoints (P10 §12.B.3,
     * P11 §19). The record's municipality is resolved from the DB row by the
     * controller (via App\Support\RecordMunicipality), never from the request.
     *
     * When a page key is given, scope only applies to pages with S2
     * enforcement on (P12 §13); null page = strict (enforced).
     */
    public function canAccessRecord(User $user, int $municipalityId, ?string $pageName = null): bool
    {
        if ($pageName !== null && ! $this->isEnforced($pageName)) {
            return true;
        }

        if ($this->isSuperAdmin($user) || $this->hasAllMunicipalities($user)) {
            return true;
        }

        return in_array($municipalityId, $this->effectiveMunicipalityIds($user), true);
    }

    /**
     * Query-scope composer (P10 §12.B.2, P11 §18.D/§19). Injects the
     * whereIn(scope) clause into a municipality-sensitive query. Feeds, exports
     * and searches use DB::table, so an Eloquent global scope would never run —
     * this is the server-side enforcement seam. The client-supplied
     * municipality/barangay parameters are presentation-only.
     *
     * A page key makes the composer inert until that page's S2 enforcement flag
     * flips on; null page = always apply (strict).
     */
    public function applyMunicipalityScope(Builder $query, User $user, string $column, ?string $pageName = null): Builder
    {
        if ($pageName !== null && ! $this->isEnforced($pageName)) {
            return $query;
        }

        if ($this->isSuperAdmin($user) || $this->hasAllMunicipalities($user)) {
            return $query;
        }

        return $query->whereIn($column, $this->effectiveMunicipalityIds($user));
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

    /**
     * @return Collection<int, ActionPermission>
     */
    private function actionPermissions(User $user): Collection
    {
        return $this->actionPermissions[$user->id]
            ??= $user->actionPermissions()->get(['page_name', 'action']);
    }

    /**
     * @return Collection<int, UserMunicipality>
     */
    private function municipalityScope(User $user): Collection
    {
        return $this->municipalityScope[$user->id]
            ??= $user->municipalityScope()->get(['municipality_id']);
    }

    private function isEnforced(string $pageName): bool
    {
        return ($this->pageConfig($pageName)['enforcement'] ?? false) === true;
    }

    /**
     * Page settings for the adopted page key. Read as a literal array index —
     * dot-notation would split "clients.php" on the dot and miss the key.
     */
    private function pageConfig(string $pageName): ?array
    {
        return config('authorization.pages', [])[$pageName] ?? null;
    }
}
