<?php

namespace App\Services;

use App\Models\RetroMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RetroMapService
{
    /**
     * Shift map sort orders to handle insert, update, or delete operations.
     *
     * This unified method handles all reordering scenarios:
     * - Insert: $oldPosition is null, $newPosition is set → shifts up from $newPosition
     * - Delete: $oldPosition is set, $newPosition is null → shifts down from $oldPosition
     * - Update within scope: both set → shifts between positions
     *
     * @param int $gameId The retro_game_id to shift orders in
     * @param int|null $oldPosition The old sort_order (null for inserts)
     * @param int|null $newPosition The new sort_order (null for deletes)
     * @param int|null $ignoreId Map ID to exclude from shifting (the map being moved)
     */
    public function shiftMapOrder(int $gameId, ?int $oldPosition, ?int $newPosition, ?int $ignoreId = null): void
    {
        // No-op if positions are the same
        if ($oldPosition === $newPosition) {
            return;
        }

        // Calculate direction and range
        $direction = match (true) {
            $oldPosition === null => 1, // Insert: shift up
            $newPosition === null => -1, // Delete: shift down
            $newPosition < $oldPosition => 1, // Moving up: shift others up (to make room at top)
            default => -1, // Moving down: shift others down (to fill gap left behind)
        };

        $from = match (true) {
            $oldPosition === null => $newPosition, // Insert: from new position onwards
            $newPosition === null => $oldPosition + 1, // Delete: from old position + 1 onwards
            $newPosition < $oldPosition => $newPosition, // Moving up: from new position onwards (to oldPosition - 1)
            default => $oldPosition + 1, // Moving down: from oldPosition + 1 onwards (to newPosition)
        };

        $to = match (true) {
            $oldPosition === null, $newPosition === null => null, // Insert/Delete: no upper bound
            $newPosition < $oldPosition => $oldPosition - 1, // Moving up: up to oldPosition - 1
            default => $newPosition, // Moving down: up to newPosition
        };

        $ignoreClause = $ignoreId !== null ? 'AND id != ?' : '';
        $rangeClause = $to !== null ? 'AND sort_order BETWEEN ? AND ?' : 'AND sort_order >= ?';
        $bindings = $ignoreId !== null
            ? ($to !== null ? [$direction, $gameId, $ignoreId, $from, $to] : [$direction, $gameId, $ignoreId, $from])
            : ($to !== null ? [$direction, $gameId, $from, $to] : [$direction, $gameId, $from]);

        DB::statement(
            "UPDATE retro_maps
             SET sort_order = sort_order + ?
             WHERE retro_game_id = ?
             {$ignoreClause}
             {$rangeClause}",
            $bindings
        );
    }

    /**
     * Validate sort_order doesn't exceed maximum allowed.
     * Max = current max in retro_game + 1 (for new maps).
     */
    public function validateSortOrderMax(?RetroMap $existingMap, ?int $sortOrder, int $retroGameId): void
    {
        if ($sortOrder === null) {
            return;
        }

        $maxSortOrder = RetroMap::where('retro_game_id', $retroGameId)
            ->max('sort_order') ?? 0;

        $wasInGame = $existingMap && $existingMap->retro_game_id === $retroGameId;
        $maxAllowed = $maxSortOrder + ($wasInGame ? 0 : 1);

        if ($sortOrder > $maxAllowed) {
            throw ValidationException::withMessages([
                'sort_order' => "The sort order cannot exceed {$maxAllowed}. Maximum available position is {$maxAllowed}.",
            ]);
        }
    }
}
