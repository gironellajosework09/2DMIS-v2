<?php

namespace App\Support;

use App\Models\Barangay;
use App\Services\AccessControlService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Builds the shared FilterChips component configuration (Phase 2C).
 *
 * Returns the shape expected by resources/views/partials/filter-chips.blade.php:
 *   categories: municipality/barangay (ACL-scoped, cascade-aware) and program
 *               option lists; dateRanges. Option lists are ALWAYS scoped
 *               server-side (see DECISION REQUIRED #9/#12): a restricted user
 *               is never offered an out-of-scope municipality/barangay/program.
 *
 * This class only shapes presentation data; the actual server-side feed filter
 * enforcement stays in the data() methods (which apply ACL scope before any
 * user-supplied filter value), so a hostile parameter can never widen access.
 */
class FilterConfig
{
    /**
     * Municipality + cascade-aware Barangay categories (ACL-scoped).
     *
     * @param  Request  $request  for reading current URL query selections
     */
    public static function geography(
        Request $request,
        AccessControlService $acl,
        string $componentId,
        string $municipalityPage,
    ): array {
        $municipalities = $acl->accessibleMunicipalities($request->user());

        $barangays = Barangay::query()
            ->whereIn('municipality_id', $municipalities->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'name', 'municipality_id']);

        return [
            'id' => $componentId,
            'categories' => [
                self::municipalityCategory($municipalities, $request, $municipalityPage),
                self::barangayCategory($barangays, $request),
            ],
            'dateRanges' => [],
        ];
    }

    public static function municipalityCategory(Collection $municipalities, Request $request, string $page): array
    {
        return [
            'key' => 'municipality',
            'label' => 'Municipality',
            'searchable' => true,
            'feedParam' => 'municipality',
            'page' => $page,
            'options' => $municipalities->map(fn ($m) => [
                'value' => (string) $m->id,
                'label' => $m->name,
            ])->values()->all(),
            'selected' => self::selectedValues($request, 'municipality'),
        ];
    }

    public static function barangayCategory(Collection $barangays, Request $request): array
    {
        return [
            'key' => 'barangay',
            'label' => 'Barangay',
            'searchable' => true,
            'feedParam' => 'barangay',
            'dependsOn' => 'municipality',
            'options' => $barangays->map(fn ($b) => [
                'value' => (string) $b->id,
                'label' => $b->name,
                'muni' => (string) $b->municipality_id,
            ])->values()->all(),
            'selected' => self::selectedValues($request, 'barangay'),
        ];
    }

    /**
     * Program category with an ACL-scoped option list.
     *
     * @param  list<string>  $programs
     */
    public static function programCategory(Request $request, array $programs): array
    {
        return [
            'key' => 'program',
            'label' => 'Program',
            'searchable' => true,
            'feedParam' => 'program',
            'options' => array_map(fn ($p) => ['value' => $p, 'label' => $p], $programs),
            'selected' => self::selectedValues($request, 'program'),
        ];
    }

    /**
     * Category category with a fixed option list (the authoritative deriveCategory
     * values) — a filter-options equivalent of programCategory for fields that
     * are not ACL-scoped (category has no municipality dimension).
     *
     * @param  list<string>  $values
     */
    public static function staticCategory(Request $request, array $values, string $key = 'category'): array
    {
        return [
            'key' => $key,
            'label' => 'Category',
            'searchable' => true,
            'feedParam' => $key,
            'options' => array_map(fn ($v) => ['value' => (string) $v, 'label' => (string) $v], $values),
            'selected' => self::selectedValues($request, $key),
        ];
    }

    /**
     * A date range block wired to the module's EXISTING start/end params.
     */
    public static function dateRange(string $label, string $startParam, string $endParam, Request $request): array
    {
        return [
            'key' => $startParam.'__'.$endParam,
            'label' => $label,
            'startParam' => $startParam,
            'endParam' => $endParam,
            'start' => (string) $request->query($startParam, ''),
            'end' => (string) $request->query($endParam, ''),
        ];
    }

    /**
     * @return list<string>
     */
    public static function selectedValues(Request $request, string $key): array
    {
        $raw = (string) $request->query($key, '');

        return $raw === ''
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    /**
     * Apply a filter value additively, preserving backward compatibility:
     * a single value uses where(=), multiple comma-separated values use
     * whereIn(OR). Callers pass the ACL-scoped query and must invoke this
     * AFTER applyMunicipalityScope so a hostile parameter can never widen
     * the accessible set. Used by the server-side data() feed contract.
     *
     * @param  array|string  $value  raw input (may be comma-separated)
     */
    public static function applyMultiValue(Builder $query, string $column, $value): void
    {
        $values = is_array($value)
            ? array_values(array_filter(array_map('trim', $value), 'strlen'))
            : array_values(array_filter(array_map('trim', explode(',', (string) $value)), 'strlen'));

        if ($values === []) {
            return;
        }

        if (count($values) === 1) {
            $query->where($column, $values[0]);
        } else {
            $query->whereIn($column, $values);
        }
    }
}
