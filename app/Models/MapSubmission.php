<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="MapSubmission",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="code", type="string", example="RiverMoen"),
 *     @OA\Property(property="submitter_id", type="string", example="123456789012345678"),
 *     @OA\Property(property="subm_notes", type="string", nullable=true, example="Great map!"),
 *     @OA\Property(property="format_id", type="integer", example=1),
 *     @OA\Property(property="proposed", type="integer", example=1),
 *     @OA\Property(property="rejected_by", type="string", nullable=true, example="987654321098765432"),
 *     @OA\Property(property="created_on", type="string", format="date-time", example="2026-03-05T12:00:00+00:00"),
 *     @OA\Property(property="completion_proof", type="string", example="map_submission_proofs/abc123.jpg"),
 *     @OA\Property(property="status", type="string", enum={"pending", "accepted", "rejected"}, example="pending"),
 *     @OA\Property(property="submitter", ref="#/components/schemas/User"),
 *     @OA\Property(property="rejecter", ref="#/components/schemas/User"),
 *     @OA\Property(property="format", ref="#/components/schemas/Format")
 * )
 */
class MapSubmission extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'code',
        'submitter_id',
        'subm_notes',
        'format_id',
        'proposed',
        'rejected_by',
        'created_on',
        'completion_proof',
        'wh_data',
        'wh_msg_id',
        'accepted_meta_id',
    ];

    protected $hidden = [
        'wh_data',
        'wh_msg_id',
        'accepted_meta_id',
    ];

    protected $appends = [
        'status',
    ];

    protected $casts = [
        'created_on' => 'timestamp',
    ];

    /**
     * Get the user who submitted this map.
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitter_id', 'discord_id');
    }

    /**
     * Get the user who rejected this submission (if any).
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by', 'discord_id');
    }

    /**
     * Get the format for this submission.
     */
    public function format(): BelongsTo
    {
        return $this->belongsTo(Format::class);
    }

    /**
     * Get the map list meta that accepted this submission (if any).
     */
    public function acceptedMeta(): BelongsTo
    {
        return $this->belongsTo(MapListMeta::class, 'accepted_meta_id');
    }

    /**
     * Get the status of the submission.
     * Returns "pending", "accepted", or "rejected".
     */
    public function getStatusAttribute(): string
    {
        if ($this->rejected_by !== null) {
            return 'rejected';
        }

        if ($this->accepted_meta_id !== null) {
            return 'accepted';
        }

        return 'pending';
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus($query, ?string $status)
    {
        if (!$status) {
            return $query;
        }

        return match ($status) {
            'pending' => $query->whereNull('rejected_by')->whereNull('accepted_meta_id'),
            'rejected' => $query->whereNotNull('rejected_by'),
            'accepted' => $query->whereNotNull('accepted_meta_id'),
            default => $query,
        };
    }

    /**
     * Get the JSON structure for API responses.
     */
    public static function jsonStructure(array $data = []): array
    {
        // Calculate status if not provided
        if (!isset($data['status'])) {
            if (isset($data['rejected_by']) && $data['rejected_by'] !== null) {
                $data['status'] = 'rejected';
            } elseif (isset($data['accepted_meta_id']) && $data['accepted_meta_id'] !== null) {
                $data['status'] = 'accepted';
            } else {
                $data['status'] = 'pending';
            }
        }

        return [
            'id' => $data['id'] ?? null,
            'code' => $data['code'] ?? null,
            'submitter_id' => $data['submitter_id'] ?? null,
            'subm_notes' => $data['subm_notes'] ?? null,
            'format_id' => $data['format_id'] ?? null,
            'proposed' => $data['proposed'] ?? null,
            'rejected_by' => $data['rejected_by'] ?? null,
            'created_on' => $data['created_on'] ?? null,
            'completion_proof' => $data['completion_proof'] ?? null,
            'status' => $data['status'],
        ];
    }
}
