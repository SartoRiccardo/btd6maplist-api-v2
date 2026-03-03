<?php

namespace App\Models;

use App\Casts\MapSubmissionStatusCast;
use App\Casts\RunSubmissionStatusCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Format extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $hidden = [
        'map_submission_wh',
        'run_submission_wh',
        'emoji',
    ];

    protected $fillable = [
        'id',
        'name',
        'map_submission_wh',
        'run_submission_wh',
        'hidden',
        'run_submission_status',
        'map_submission_status',
        'emoji',
        'proposed_difficulties',
    ];

    public $incrementing = false;

    protected $casts = [
        'id' => 'integer',
        'hidden' => 'boolean',
        'proposed_difficulties' => 'array',
        'run_submission_status' => RunSubmissionStatusCast::class,
        'map_submission_status' => MapSubmissionStatusCast::class,
    ];

    public function configFormats()
    {
        return $this->hasMany(ConfigFormat::class, 'format_id');
    }

    public function roleFormatPermissions()
    {
        return $this->hasMany(RoleFormatPermission::class, 'format_id');
    }

    public function formatRulesSubsets()
    {
        return $this->belongsToMany(Format::class, 'formats_rules_subsets', 'format_parent', 'format_child');
    }

    /**
     * Get full format representation including sensitive fields (webhooks, emoji).
     */
    public function toFullArray(): array
    {
        return [
            ...$this->toArray(),
            'map_submission_wh' => $this->map_submission_wh,
            'run_submission_wh' => $this->run_submission_wh,
            'emoji' => $this->emoji,
        ];
    }

    /**
     * Get the JSON structure for API responses.
     */
    public static function jsonStructure(array $data = []): array
    {
        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'hidden' => $data['hidden'] ?? false,
            'run_submission_status' => $data['run_submission_status'] ?? 'closed',
            'map_submission_status' => $data['map_submission_status'] ?? 'closed',
            'proposed_difficulties' => $data['proposed_difficulties'] ?? null,
        ];
    }
}
