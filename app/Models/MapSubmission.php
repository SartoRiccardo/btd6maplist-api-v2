<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'accepted_meta_id',
        'created_on',
        'completion_proof',
        'wh_data',
        'wh_msg_id',
    ];

    protected $hidden = [
        'wh_data',
        'wh_msg_id',
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
     * Get the map list meta this submission was accepted for (if any).
     */
    public function acceptedMeta(): BelongsTo
    {
        return $this->belongsTo(MapListMeta::class, 'accepted_meta_id', 'id');
    }
}
