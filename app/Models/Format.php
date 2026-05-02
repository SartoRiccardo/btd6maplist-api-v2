<?php

namespace App\Models;

use App\Casts\MapSubmissionStatusCast;
use App\Casts\RunSubmissionStatusCast;
use App\Traits\TestableStructure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Format",
 *     required={"id", "name", "hidden", "run_submission_status", "map_submission_status"},
 *     @OA\Property(property="id", type="integer", description="Format ID", example=1),
 *     @OA\Property(property="name", type="string", description="Format name", example="Maplist"),
 *     @OA\Property(property="slug", type="string", description="URL-friendly identifier", example="maplist"),
 *     @OA\Property(property="description", type="string", description="Format description", example="The official maplist format"),
 *     @OA\Property(property="button_text", type="string", description="Text for submission buttons", example="Submit Run"),
 *     @OA\Property(property="preview_map_1_code", type="string", nullable=true, description="First preview map code", example="TKIEXYSQ"),
 *     @OA\Property(property="preview_map_2_code", type="string", nullable=true, description="Second preview map code", example="TKIEXYSQ"),
 *     @OA\Property(property="preview_map_3_code", type="string", nullable=true, description="Third preview map code", example="TKIEXYSQ"),
 *     @OA\Property(property="map_submission_rules", type="string", description="Rules for map submissions"),
 *     @OA\Property(property="completion_submission_rules", type="string", description="Rules for completion submissions"),
 *     @OA\Property(property="discord_server_url", type="string", format="uri", nullable=true, description="Discord server invite URL"),
 *     @OA\Property(property="hidden", type="boolean", description="Whether the format is hidden", example=false),
 *     @OA\Property(property="run_submission_status", type="string", enum={"closed", "open", "lcc_only"}, description="Run submission status", example="open"),
 *     @OA\Property(property="map_submission_status", type="string", enum={"closed", "open"}, description="Map submission status", example="open"),
 *     @OA\Property(property="proposed_difficulties", type="array", nullable=true, description="List of proposed difficulty names", @OA\Items(type="string")),
 *     @OA\Property(property="is_no_geraldo_enabled", type="boolean", description="Whether No Geraldo runs are tracked for this format", example=true),
 *     @OA\Property(property="is_lcc_leaderboard_enabled", type="boolean", description="Whether the LCC leaderboard is enabled for this format", example=true),
 *     @OA\Property(property="is_black_border_leaderboard_enabled", type="boolean", description="Whether the Black Border leaderboard is enabled for this format", example=true),
 *     @OA\Property(property="is_no_geraldo_leaderboard_enabled", type="boolean", description="Whether the No Geraldo leaderboard is enabled for this format", example=true)
 * )
 */
class Format extends Model
{
    use HasFactory, TestableStructure;

    public $timestamps = false;

    protected $hidden = [
        'map_submission_wh',
        'run_submission_wh',
        'preview_map_1_code',
        'preview_map_2_code',
        'preview_map_3_code',
    ];

    protected $fillable = [
        'id',
        'name',
        'slug',
        'description',
        'button_text',
        'preview_map_1_code',
        'preview_map_2_code',
        'preview_map_3_code',
        'map_submission_rules',
        'completion_submission_rules',
        'discord_server_url',
        'map_submission_wh',
        'run_submission_wh',
        'hidden',
        'run_submission_status',
        'map_submission_status',
        'emoji',
        'proposed_difficulties',
        'is_no_geraldo_enabled',
        'is_lcc_leaderboard_enabled',
        'is_black_border_leaderboard_enabled',
        'is_no_geraldo_leaderboard_enabled',
    ];

    public $incrementing = false;

    protected $casts = [
        'id' => 'integer',
        'hidden' => 'boolean',
        'is_no_geraldo_enabled' => 'boolean',
        'is_lcc_leaderboard_enabled' => 'boolean',
        'is_black_border_leaderboard_enabled' => 'boolean',
        'is_no_geraldo_leaderboard_enabled' => 'boolean',
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

    public function previewMap1()
    {
        return $this->belongsTo(Map::class, 'preview_map_1_code', 'code');
    }

    public function previewMap2()
    {
        return $this->belongsTo(Map::class, 'preview_map_2_code', 'code');
    }

    public function previewMap3()
    {
        return $this->belongsTo(Map::class, 'preview_map_3_code', 'code');
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
    public static function defaults(array $data = []): array
    {
        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'slug' => $data['slug'] ?? '',
            'description' => $data['description'] ?? '',
            'button_text' => $data['button_text'] ?? 'Submit',
            'map_submission_rules' => $data['map_submission_rules'] ?? '',
            'completion_submission_rules' => $data['completion_submission_rules'] ?? '',
            'discord_server_url' => $data['discord_server_url'] ?? null,
            'hidden' => $data['hidden'] ?? false,
            'run_submission_status' => $data['run_submission_status'] ?? 'closed',
            'map_submission_status' => $data['map_submission_status'] ?? 'closed',
            'proposed_difficulties' => $data['proposed_difficulties'] ?? null,
        ];
    }

    public static function strictFields(): array
    {
        return [
            'id',
            'name',
            'slug',
            'description',
            'button_text',
            'map_submission_rules',
            'completion_submission_rules',
            'discord_server_url',
            'hidden',
            'run_submission_status',
            'map_submission_status',
            'proposed_difficulties',
            'map_submission_wh',
            'run_submission_wh',
            'emoji',
        ];
    }
}
