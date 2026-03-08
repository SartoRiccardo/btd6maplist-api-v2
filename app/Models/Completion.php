<?php

namespace App\Models;

use App\Constants\ProofType;
use App\Traits\TestableStructure;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @OA\Schema(
 *     schema="Completion",
 *     type="object",
 *     @OA\Property(property="id", type="integer", description="Completion ID"),
 *     @OA\Property(property="map_code", type="string", description="Map code"),
 *     @OA\Property(property="created_on", type="integer", description="Timestamp when completion was submitted"),
 *     @OA\Property(property="subm_notes", type="string", nullable=true, description="Submission notes"),
 *     @OA\Property(property="subm_proof_img", type="array", @OA\Items(type="string"), description="Image proof URLs"),
 *     @OA\Property(property="subm_proof_vid", type="array", @OA\Items(type="string"), description="Video proof URLs")
 * )
 */
class Completion extends Model
{
    use HasFactory, TestableStructure;

    public $timestamps = false;

    protected $appends = [
        'subm_proof_img',
        'subm_proof_vid',
    ];

    protected $hidden = [
        'wh_msg_id',
        'wh_data',
        'copied_from_id',
        'proofs',
        'completionMetas',
        'meta',
    ];

    protected $fillable = [
        'id',
        'map_code',
        'subm_notes',
        'wh_msg_id',
        'wh_data',
        'copied_from_id',
    ];

    protected $casts = [];

    /**
     * Get the map for this completion.
     */
    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class, 'map_code', 'code');
    }

    /**
     * Get all proofs for this completion.
     */
    public function proofs(): HasMany
    {
        return $this->hasMany(CompletionProof::class, 'run');
    }

    /**
     * Get image proof URLs for API responses.
     */
    public function getSubmProofImgAttribute(): array
    {
        if (!$this->relationLoaded('proofs')) {
            $this->load('proofs');
        }
        return $this->proofs->where('proof_type', ProofType::IMAGE)->pluck('proof_url')->values()->toArray();
    }

    /**
     * Get video proof URLs for API responses.
     */
    public function getSubmProofVidAttribute(): array
    {
        if (!$this->relationLoaded('proofs')) {
            $this->load('proofs');
        }
        return $this->proofs->where('proof_type', ProofType::VIDEO)->pluck('proof_url')->values()->toArray();
    }

    /**
     * Get submitted_on as a unix timestamp for API responses.
     */
    protected function getSubmittedOnAttribute(): int
    {
        return (int) strtotime($this->getRawOriginal('submitted_on'));
    }

    // --- TestableStructure --- //

    /**
     * Get the default values for the Completion JSON structure.
     */
    protected static function defaults(array $overrides = []): array
    {
        return [
            'id' => $overrides['id'] ?? 1,
            'map_code' => 'TESTCODE',
            'submitted_on' => now()->timestamp,
            'subm_notes' => null,
            'subm_proof_img' => [],
            'subm_proof_vid' => [],
            'map' => null,
        ];
    }

    /**
     * Get the fields that are allowed when strict mode is enabled.
     * Includes both Completion and CompletionMeta fields since the API merges them.
     */
    protected static function strictFields(): array
    {
        return [
            'id',
            'map_code',
            'submitted_on',
            'subm_notes',
            'subm_proof_img',
            'subm_proof_vid',
            'map',
            // CompletionMeta fields
            'black_border',
            'no_geraldo',
            'deleted_on',
            'accepted_by',
            'lcc',
            'players',
            'is_current_lcc',
            'format_id',
        ];
    }
}
