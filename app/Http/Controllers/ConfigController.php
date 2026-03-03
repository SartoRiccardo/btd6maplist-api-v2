<?php

namespace App\Http\Controllers;

use App\Models\Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ConfigController
{
    /**
     * Get all config values as key-value object.
     *
     * @OA\Get(
     *     path="/config",
     *     summary="Get all configuration values",
     *     description="Returns all config values as a key-value object. Values are automatically cast to their correct types (int, float, string). Public endpoint, no authentication required.",
     *     tags={"Config"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             example={"map_count": 100, "points_multiplier": 1.5, "site_title": "BTD6 Maplist"},
     *             additionalProperties=true
     *         )
     *     )
     * )
     */
    public function index()
    {
        $configs = Config::all();
        $kvObject = $configs->mapWithKeys(fn($config) => [$config->name => $config->value]);
        return response()->json($kvObject);
    }

    /**
     * Update config values.
     *
     * @OA\Put(
     *     path="/config",
     *     summary="Update configuration values",
     *     description="Updates multiple config values atomically. User must have 'edit:config' permission for each config being updated. For global configs (no format bindings), requires global permission. For format-specific configs, requires permission for at least one bound format. Returns 422 with generic message for invalid keys or insufficient permissions (same message to avoid leaking). Returns 422 with type-specific message for type mismatches. All validations must pass for any changes to persist.",
     *     tags={"Config"},
     *     security={{"discord_auth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 example={"map_count": 150, "points_multiplier": 2.0},
     *                 additionalProperties=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Config updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             example={"map_count": 150, "points_multiplier": 2.0, "site_title": "BTD6 Maplist"}
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error - invalid key, insufficient permissions, or type mismatch")
     * )
     */
    public function update(Request $request)
    {
        $user = auth()->guard('discord')->user();
        $data = $request->all();

        // Get user's permitted formats for edit:config permission
        $userFormatIds = $user->formatsWithPermission('edit:config');
        $hasGlobalPermission = in_array(null, $userFormatIds, true);

        // Load all configs being updated in a single query
        $configs = Config::whereIn('name', array_keys($data))->get()->keyBy('name');

        $errors = [];
        foreach ($data as $key => $value) {
            // Check if config exists
            if (!$configs->has($key)) {
                $errors[$key] = ['Invalid configuration key or insufficient permissions.'];
                continue;
            }

            $config = $configs->get($key);
            $configFormatIds = $config->formats; // Uses appended attribute

            // Check permissions
            // User can edit if they have global permission OR permission for at least one of the config's bound formats
            $hasPermission = $hasGlobalPermission || !empty(array_intersect($configFormatIds, $userFormatIds));
            if (!$hasPermission) {
                $errors[$key] = ['Invalid configuration key or insufficient permissions.'];
                continue;
            }

            // Type validation
            $expectedType = $config->type;
            $actualType = gettype($value);

            $typeMismatch = match ($expectedType) {
                'int' => $actualType !== 'integer',
                'float' => $actualType !== 'double' && $actualType !== 'integer',
                'string' => $actualType !== 'string',
                default => false,
            };

            if ($typeMismatch) {
                $errors[$key] = ["Value for '{$key}' must be {$expectedType}."];
            }
        }

        if (!empty($errors)) {
            return response()->json(['errors' => $errors], 422);
        }

        return DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                $config = Config::where('name', $key)->firstOrFail();
                $config->setAttribute('value', (string) $value); // Store as string in DB
                $config->save();
            }

            // Return full updated config object (same format as GET)
            $allConfigs = Config::all();
            $kvObject = $allConfigs->mapWithKeys(fn($config) => [$config->name => $config->value]);
            return response()->json($kvObject);
        });
    }
}
