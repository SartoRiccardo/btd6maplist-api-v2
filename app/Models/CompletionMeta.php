<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CompletionMeta extends Model
{
    use HasFactory;

    protected $table = 'completions_meta';

    public $timestamps = false;

    protected $hidden = [
        'accepted_by_id',
        'lcc_id',
        'completion_id',
        'copied_from_id',
        'comp_players',
        'proofs',
        'completion',
        'format',
        'acceptedBy',
        'copied_from',
        // 'format_id',
        'copied_to',
        'created_on',
    ];

    protected $fillable = [
        'id',
        'completion_id',
        'black_border',
        'no_geraldo',
        'lcc_id',
        'created_on',
        'deleted_on',
        'accepted_by_id',
        'format_id',
        'copied_from_id',
    ];

    protected $appends = [
        'accepted_by',
        'lcc',
    ];

    protected $casts = [
        'accepted_by_id' => 'string',
        'black_border' => 'boolean',
        'no_geraldo' => 'boolean',
    ];

    /**
     * Get the completion this metadata belongs to.
     */
    public function completion(): BelongsTo
    {
        return $this->belongsTo(Completion::class);
    }

    /**
     * Get the format of this completion.
     */
    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class);
    }

    /**
     * Get the user who accepted this completion.
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_id');
    }

    /**
     * Get the LCC data for this completion.
     */
    public function lcc(): HasOne
    {
        return $this->hasOne(LeastCostChimps::class, 'id', 'lcc_id');
    }

    /**
     * Get all players in this completion.
     */
    public function compPlayers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comp_players', 'run', 'user_id', 'id', 'discord_id');
    }

    /**
     * Alias for compPlayers().
     */
    public function players(): BelongsToMany
    {
        return $this->compPlayers();
    }

    /**
     * Get all proofs for this completion.
     */
    public function proofs(): HasMany
    {
        return $this->hasMany(CompletionProof::class, 'run');
    }

    /**
     * Get accepted_by as an alias for accepted_by_id (for API compatibility).
     */
    public function getAcceptedByAttribute(): ?string
    {
        return $this->attributes['accepted_by_id'] ?? null;
    }

    /**
     * Get lcc as an alias for the lcc relationship data.
     */
    public function getLccAttribute(): ?array
    {
        if (!$this->lcc_id) {
            return null;
        }
        $lcc = $this->getRelationValue('lcc');
        return $lcc?->toArray();
    }

    /**
     * Partial raw query to get the active metas at a timestamp.
     *
     * @param mixed $timestamp
     */
    public static function activeAtTimestamp($timestamp): \Illuminate\Database\Eloquent\Builder
    {
        return self::selectRaw('DISTINCT ON (completion_id) *')
            ->where('created_on', '<=', $timestamp)
            ->orderBy('completion_id')
            ->orderBy('created_on', 'desc');
    }

    /**
     * Get the active completion meta for a completion at a certain timestamp.
     *
     * @param int $completionId
     * @param Carbon $timestamp
     * @return CompletionMeta|null
     */
    public static function activeForCompletion(int $completionId, Carbon $timestamp): CompletionMeta|null
    {
        return self::where('completion_id', $completionId)
            ->where('created_on', '<=', $timestamp)
            ->orderBy('created_on', 'desc')
            ->first();
    }
}
