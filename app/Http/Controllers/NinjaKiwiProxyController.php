<?php

namespace App\Http\Controllers;

use App\Models\Map;
use App\Models\MapSubmission;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class NinjaKiwiProxyController
{
    public function mapPreview(string $code)
    {
        $cachePath = "ninjakiwi_cache/{$code}.webp";

        if (Storage::disk('local')->exists($cachePath)) {
            return response(Storage::disk('local')->get($cachePath), 200)
                ->header('Content-Type', 'image/webp');
        }

        $exists = Map::where('code', $code)->exists()
            || MapSubmission::where('code', $code)->exists();

        if (!$exists) {
            return response()->json(['error' => 'Map not found'], 404);
        }

        $nkResponse = Http::get("https://data.ninjakiwi.com/btd6/maps/map/{$code}/preview");

        if (!$nkResponse->successful()) {
            return response('', $nkResponse->status());
        }

        $image = @imagecreatefromstring($nkResponse->body());
        if ($image === false) {
            return response('', 502);
        }
        ob_start();
        imagewebp($image, null, 85);
        $webp = ob_get_clean();
        imagedestroy($image);

        Storage::disk('local')->put($cachePath, $webp);

        return response($webp, 200)
            ->header('Content-Type', 'image/webp');
    }
}
