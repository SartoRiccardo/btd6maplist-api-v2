<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * @OA\Schema(
 *     schema="PaginationMeta",
 *     type="object",
 *     @OA\Property(property="current_page", type="integer", description="Current page number", example=1),
 *     @OA\Property(property="last_page", type="integer", description="Last page number", example=5),
 *     @OA\Property(property="per_page", type="integer", description="Number of items per page", example=100),
 *     @OA\Property(property="total", type="integer", description="Total number of items", example=450)
 * )
 * 
 * @OA\Info(
 *     title="BTD6 Maplist API",
 *     version="2.0.0",
 *     description="API for managing BTD6 map list data"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="botAuth",
 *     type="apiKey",
 *     in="header",
 *     name="X-Signature",
 *     description="HMAC-SHA256 signature of `{timestamp}\n{METHOD}\n{path}\n{body}` using the shared bot secret. Must be paired with the X-Timestamp header."
 * )
 *
 * @OA\Schema(
 *     schema="BotUser",
 *     type="object",
 *     required={"discord_id", "name"},
 *     description="Authenticated Discord user injected by the bot. Trusted without further verification.",
 *     @OA\Property(property="discord_id", type="string", example="123456789012345678"),
 *     @OA\Property(property="name", type="string", example="CoolPlayer")
 * )
 */
abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;
}
