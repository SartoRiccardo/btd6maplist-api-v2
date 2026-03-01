<?php

namespace App\Services;

use App\Constants\ProofType;
use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\CompletionProof;
use App\Models\LeastCostChimps;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CompletionService
{
    /**
     * Create a completion with all related records.
     *
     * @param array $data Validated completion data
     * @param User $performingUser User creating the completion
     * @param bool $autoAccept Whether to auto-accept (admin) or leave pending (user submission)
     * @return array ['completion_id' => int, 'meta_id' => int]
     */
    public function create(array $data, User $performingUser, bool $autoAccept = false): array
    {
        $now = Carbon::now();

        return DB::transaction(function () use ($data, $performingUser, $autoAccept, $now) {
            // Create the Completion base record
            $completion = Completion::create([
                'map_code' => $data['map'],
                'submitted_on' => Carbon::now(),
                'subm_notes' => $data['subm_notes'] ?? null,
            ]);

            // Handle proof image uploads
            if (isset($data['proof_images']) && is_array($data['proof_images'])) {
                $this->storeProofImages($completion->id, $data['proof_images']);
            }

            // Handle video proof URLs
            if (!empty($data['proof_videos'])) {
                $this->storeProofVideos($completion->id, $data['proof_videos']);
            }

            // Handle LCC - create new record if provided
            $lccId = $this->createLcc($data['lcc'] ?? null);

            // Set accepted_by_id based on autoAccept flag
            $acceptedBy = $autoAccept ? $performingUser->discord_id : null;

            // Create CompletionMeta
            $meta = CompletionMeta::create([
                'completion_id' => $completion->id,
                'format_id' => $data['format_id'],
                'black_border' => $data['black_border'] ?? false,
                'no_geraldo' => $data['no_geraldo'] ?? false,
                'lcc_id' => $lccId,
                'accepted_by_id' => $acceptedBy,
                'created_on' => $now,
                'deleted_on' => null,
            ]);
            $meta->players()->attach($data['players']);

            return [
                'completion_id' => $completion->id,
                'meta_id' => $meta->id,
            ];
        });
    }

    /**
     * Store proof images and create CompletionProof records.
     *
     * @param int $completionId
     * @param array $imageFiles
     * @return void
     */
    protected function storeProofImages(int $completionId, array $imageFiles): void
    {
        $timestamp = now()->format('Ymd_His');

        foreach ($imageFiles as $index => $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $extension = $file->getClientOriginalExtension();
            $path = $file->storeAs(
                "completion_proofs/{$completionId}",
                "img_{$timestamp}_{$index}.{$extension}",
                'public'
            );

            CompletionProof::create([
                'run' => $completionId,
                'proof_url' => Storage::disk('public')->url($path),
                'proof_type' => ProofType::IMAGE,
            ]);
        }
    }

    /**
     * Create CompletionProof records for video URLs.
     *
     * @param int $completionId
     * @param array $videoUrls
     * @return void
     */
    protected function storeProofVideos(int $completionId, array $videoUrls): void
    {
        foreach ($videoUrls as $videoUrl) {
            CompletionProof::create([
                'run' => $completionId,
                'proof_url' => $videoUrl,
                'proof_type' => ProofType::VIDEO,
            ]);
        }
    }

    /**
     * Create LeastCostChimps record if LCC data provided.
     *
     * @param array|null $lccData
     * @return int|null
     */
    protected function createLcc(?array $lccData): ?int
    {
        if ($lccData === null) {
            return null;
        }

        $lcc = LeastCostChimps::create([
            'leftover' => $lccData['leftover'],
        ]);

        return $lcc->id;
    }
}
