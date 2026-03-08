<?php

namespace App\Services;

use App\Constants\FormatConstants;
use App\Jobs\UpdateMapSubmissionWebhookJob;
use App\Models\Config;
use App\Models\MapListMeta;
use App\Models\MapSubmission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MapService
{
    /**
     * Rerank map placements after a position change.
     *
     * This implements the SQL from the Python codebase to efficiently update
     * placements for all affected maps in a single query.
     *
     * @param int|null $curPositionFrom Old current version position (or null if wasn't set)
     * @param int|null $curPositionTo New current version position (or null if being cleared)
     * @param int|null $allPositionFrom Old all-time version position (or null if wasn't set)
     * @param int|null $allPositionTo New all-time version position (or null if being cleared)
     * @param string $ignoreCode Map code to exclude from reranking (the map being edited)
     * @param Carbon $now Timestamp for the operation
     * @return void
     */
    public function rerankPlacements(
        ?int $curPositionFrom,
        ?int $curPositionTo,
        ?int $allPositionFrom,
        ?int $allPositionTo,
        string $ignoreCode,
        Carbon $now
    ): void {
        $bigNumber = 1_000_000;

        $curChanged = $curPositionFrom !== $curPositionTo;
        $allChanged = $allPositionFrom !== $allPositionTo;

        if (!$curChanged && !$allChanged) {
            return;
        }

        $whereClauses = [];
        $curverSelector = 'placement_curver';
        $allverSelector = 'placement_allver';
        $selectBindings = [];
        $whereBindings = [];

        if ($curChanged) {
            $from = $curPositionFrom ?? $bigNumber;
            $to = $curPositionTo ?? $bigNumber;

            $curverSelector = "CASE WHEN (placement_curver BETWEEN LEAST(?::int, ?::int) AND GREATEST(?::int, ?::int))
            THEN placement_curver + SIGN(?::int - ?::int)
            ELSE placement_curver END";
            $selectBindings = array_merge($selectBindings, [$from, $to, $from, $to, $from, $to]);

            $whereClauses[] = "placement_curver BETWEEN LEAST(?::int, ?::int) AND GREATEST(?::int, ?::int)";
            $whereBindings = array_merge($whereBindings, [$from, $to, $from, $to]);
        }

        if ($allChanged) {
            $from = $allPositionFrom ?? $bigNumber;
            $to = $allPositionTo ?? $bigNumber;

            $allverSelector = "CASE WHEN (placement_allver BETWEEN LEAST(?::int, ?::int) AND GREATEST(?::int, ?::int))
            THEN placement_allver + SIGN(?::int - ?::int)
            ELSE placement_allver END";
            $selectBindings = array_merge($selectBindings, [$from, $to, $from, $to, $from, $to]);

            $whereClauses[] = "placement_allver BETWEEN LEAST(?::int, ?::int) AND GREATEST(?::int, ?::int)";
            $whereBindings = array_merge($whereBindings, [$from, $to, $from, $to]);
        }

        $whereClause = implode(' OR ', $whereClauses);

        $bindings = array_merge(
            $selectBindings,
            [$now->toDateTimeString()],
            [$now->toDateTimeString()],
            $whereBindings,
            [$ignoreCode],
        );

        DB::statement(
            "INSERT INTO map_list_meta
            (placement_curver, placement_allver, code, difficulty, botb_difficulty, optimal_heros, created_on)
        SELECT
            {$curverSelector},
            {$allverSelector},
            code, difficulty, botb_difficulty, optimal_heros,
            ?::timestamp
        FROM latest_maps_meta(?::timestamp) mlm
        WHERE mlm.deleted_on IS NULL
            AND ({$whereClause})
            AND mlm.code != ?",
            $bindings
        );

        Log::info("Reranked maps", [
            'curPositionFrom' => $curPositionFrom,
            'curPositionTo' => $curPositionTo,
            'allPositionFrom' => $allPositionFrom,
            'allPositionTo' => $allPositionTo,
            'ignoreCode' => $ignoreCode,
        ]);
    }

    /**
     * Clear the remake_of reference from the previous map that had this remake_of.
     *
     * When setting a remake_of on a map, we need to ensure that only one map
     * has this remake_of set at any given time. This creates a new MapListMeta
     * for the previous map to clear its remake_of field.
     *
     * @param int $remakeOf The retro_map ID that is being claimed
     * @param string $ignoreCode Map code to exclude (the map being set)
     * @param Carbon $now Timestamp for the operation
     * @return void
     */
    public function clearPreviousRemakeOf(int $remakeOf, string $ignoreCode, Carbon $now): void
    {
        $previousMeta = MapListMeta::activeAtTimestamp($now)
            ->where('remake_of', $remakeOf)
            ->where('code', '!=', $ignoreCode)
            ->first();

        if ($previousMeta) {
            MapListMeta::create([
                'code' => $previousMeta->code,
                'placement_curver' => $previousMeta->placement_curver,
                'placement_allver' => $previousMeta->placement_allver,
                'difficulty' => $previousMeta->difficulty,
                'optimal_heros' => $previousMeta->optimal_heros,
                'botb_difficulty' => $previousMeta->botb_difficulty,
                'remake_of' => null,
                'created_on' => $now,
                'deleted_on' => null,
            ]);

            Log::info('Cleared previous remake_of', [
                'remake_of' => $remakeOf,
                'previous_code' => $previousMeta->code,
                'new_code' => $ignoreCode,
            ]);
        }
    }

    /**
     * Permission to field mapping for MapListMeta
     */
    public function getPermissionFieldMapping(): array
    {
        return [
            FormatConstants::MAPLIST => 'placement_curver',
            FormatConstants::MAPLIST_ALL_VERSIONS => 'placement_allver',
            FormatConstants::EXPERT_LIST => 'difficulty',
            FormatConstants::BEST_OF_THE_BEST => 'botb_difficulty',
            FormatConstants::NOSTALGIA_PACK => 'remake_of',
        ];
    }

    /**
     * Filter meta fields based on user's format permissions
     *
     * @param array $input Validated request input
     * @param array $userFormatIds Format IDs where user has edit:map permission
     * @param MapListMeta|null $existingMeta Existing meta for PUT (null for POST)
     * @return array Filtered meta fields
     */
    public function filterMetaFieldsByPermissions(
        array $input,
        array $userFormatIds,
        ?MapListMeta $existingMeta = null
    ): array {
        $permissionFields = $this->getPermissionFieldMapping();
        $hasGlobalPermissions = in_array(null, $userFormatIds);
        $filtered = [];

        foreach ($permissionFields as $formatId => $field) {
            if ($hasGlobalPermissions || in_array($formatId, $userFormatIds)) {
                // User has permission for this field, use the value from input
                if (array_key_exists($field, $input)) {
                    $filtered[$field] = $input[$field];
                }
            } else {
                // User lacks permission for this field
                if ($existingMeta) {
                    // PUT: Use existing value
                    $filtered[$field] = $existingMeta->$field;
                } else {
                    // POST: Set to null
                    $filtered[$field] = null;
                }
            }
        }

        return $filtered;
    }

    /**
     * Validate that at least one meta field is non-null after permission filtering.
     *
     * @param array $filteredMetaFields The filtered meta fields from filterMetaFieldsByPermissions()
     * @param MapListMeta|null $existingMeta Existing meta for PUT (null for POST)
     * @throws ValidationException If all meta fields are null
     */
    public function validateAtLeastOneMetaFieldIsSet(array $filteredMetaFields, ?MapListMeta $existingMeta = null): void
    {
        $permissionFields = $this->getPermissionFieldMapping();
        $metaFields = array_values($permissionFields); // ['placement_curver', 'placement_allver', 'difficulty', 'botb_difficulty', 'remake_of']

        $allNull = true;
        foreach ($metaFields as $field) {
            $value = null;

            if (array_key_exists($field, $filteredMetaFields)) {
                // Field is in filtered array (user provided it or it was filtered to null)
                $value = $filteredMetaFields[$field];
            } elseif ($existingMeta !== null) {
                // For PUT: Field not in filtered array means it retains existing value
                $value = $existingMeta->$field;
            }

            if ($value !== null) {
                $allNull = false;
                break;
            }
        }

        if ($allNull) {
            throw ValidationException::withMessages([
                'meta_fields' => 'At least one of the following fields must be provided: placement_curver, placement_allver, difficulty, botb_difficulty, remake_of',
            ]);
        }
    }

    /**
     * Validate that placement values don't exceed the maximum allowed.
     *
     * Max = current max in list + (1 if map's value is NULL, else 0)
     *
     * @param MapListMeta|null $existingMeta The existing meta (null for new maps)
     * @param int|null $placementCurver New placement_curver value
     * @param int|null $placementAllver New placement_allver value
     * @param Carbon $now Timestamp to check at
     * @return void
     * @throws ValidationException
     */
    public function validatePlacementMax(?MapListMeta $existingMeta, ?int $placementCurver, ?int $placementAllver, Carbon $now): void
    {
        $this->validateSinglePlacementMax($existingMeta, 'placement_curver', $placementCurver, $now);
        $this->validateSinglePlacementMax($existingMeta, 'placement_allver', $placementAllver, $now);
    }

    /**
     * Validate a single placement field value.
     */
    protected function validateSinglePlacementMax(?MapListMeta $existingMeta, string $field, ?int $value, Carbon $now): void
    {
        if ($value === null) {
            return;
        }

        // Get current active metas and find max (includes all maps)
        $latestMetaCte = MapListMeta::activeAtTimestamp($now);
        $maxValue = MapListMeta::from(DB::raw("({$latestMetaCte->toSql()}) as map_list_meta"))
            ->setBindings($latestMetaCte->getBindings())
            ->max($field) ?? 0;

        // Check if this map was previously in the list
        $wasInList = $existingMeta && $existingMeta->$field !== null;

        $maxAllowed = $maxValue + ($wasInList ? 0 : 1);

        if ($value > $maxAllowed) {
            $fieldName = $field === 'placement_curver' ? 'Current version placement' : 'All-time version placement';
            throw ValidationException::withMessages([
                $field => "The {$fieldName} cannot exceed {$maxAllowed}. Maximum available position is {$maxAllowed}.",
            ]);
        }
    }

    /**
     * Validate that aliases don't exist for other active maps.
     *
     * @param array $aliases Array of lowercase alias strings
     * @param string|null $ignoreCode Map code to ignore (for updates)
     * @param Carbon $now Timestamp to check at
     * @return void
     * @throws ValidationException
     */
    public function validateAliases(array $aliases, ?string $ignoreCode, Carbon $now): void
    {
        if (empty($aliases)) {
            return;
        }

        $activeMetasCte = MapListMeta::activeAtTimestamp($now);
        $placeholders = implode(',', array_fill(0, count($aliases), '?'));
        $bindings = [...$activeMetasCte->getBindings(), ...$aliases];

        $sql = "
            WITH active_metas AS ({$activeMetasCte->toSql()})
            SELECT lower(a.alias) as alias, a.map_code
            FROM map_aliases a
            JOIN active_metas am
                ON a.map_code = am.code
            WHERE am.deleted_on IS NULL
                AND lower(a.alias) = ANY(ARRAY[{$placeholders}])
        ";

        if ($ignoreCode !== null) {
            $sql .= " AND a.map_code != ?";
            $bindings[] = $ignoreCode;
        }

        $existingAliases = DB::select($sql, $bindings);

        if (!empty($existingAliases)) {
            // Build map of alias -> conflicting map codes
            $conflictsByAlias = [];
            foreach ($existingAliases as $row) {
                $conflictsByAlias[$row->alias][] = $row->map_code;
            }

            // Build error messages for each conflicting alias at its index
            $errors = [];
            foreach ($aliases as $idx => $alias) {
                if (isset($conflictsByAlias[$alias])) {
                    $errors["aliases.{$idx}"] = "This alias already exists for map: " . $conflictsByAlias[$alias][0];
                }
            }

            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Implicitly accept pending map submissions when a map is added/updated on the list.
     *
     * When a map is added to a format and there's a pending submission for that format,
     * the submission is implicitly accepted, webhook is updated to green, and linked to the new meta.
     *
     * @param MapListMeta $newMeta The newly created MapListMeta
     * @param MapListMeta|null $existingMeta Existing meta for PUT (null for POST)
     * @return void
     */
    public function implicitlyAcceptPendingSubmissions(
        MapListMeta $newMeta,
        ?MapListMeta $existingMeta = null
    ): void {
        // Determine which formats to check based on which fields are non-null
        $formatIdsToCheck = [];

        if ($newMeta->placement_curver !== null) {
            $formatIdsToCheck[] = FormatConstants::MAPLIST;
        }
        if ($newMeta->placement_allver !== null) {
            $formatIdsToCheck[] = FormatConstants::MAPLIST_ALL_VERSIONS;
        }
        if ($newMeta->difficulty !== null) {
            $formatIdsToCheck[] = FormatConstants::EXPERT_LIST;
        }
        if ($newMeta->botb_difficulty !== null) {
            $formatIdsToCheck[] = FormatConstants::BEST_OF_THE_BEST;
        }
        if ($newMeta->remake_of !== null) {
            $formatIdsToCheck[] = FormatConstants::NOSTALGIA_PACK;
        }

        // Single query to get all pending submissions for this map across all relevant formats
        $pendingSubmissions = MapSubmission::where('code', $newMeta->code)
            ->whereIn('format_id', $formatIdsToCheck)
            ->withStatus('pending')
            ->get();

        foreach ($pendingSubmissions as $pendingSubmission) {
            if (!$pendingSubmission || !$pendingSubmission->wh_msg_id) {
                continue;
            }

            // Check if format requires placement validation
            $shouldAccept = true;

            if (in_array($formatId, [FormatConstants::MAPLIST, FormatConstants::MAPLIST_ALL_VERSIONS])) {
                $mapCount = Config::loadVars(['map_count'])->get('map_count', 50);
                $placement = $formatId === FormatConstants::MAPLIST
                    ? $newMeta->placement_curver
                    : $newMeta->placement_allver;

                $shouldAccept = $placement !== null && $placement <= $mapCount;
            }

            if (!$shouldAccept) {
                continue;
            }

            // Accept the submission
            UpdateMapSubmissionWebhookJob::dispatch($pendingSubmission->id, fail: false);
            $pendingSubmission->accepted_meta_id = $newMeta->id;
            $pendingSubmission->save();

            Log::info('Implicitly accepted map submission', [
                'map_code' => $newMeta->code,
                'format_id' => $formatId,
                'submission_id' => $pendingSubmission->id,
                'meta_id' => $newMeta->id,
            ]);
        }
    }
}
