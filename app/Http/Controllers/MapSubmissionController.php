<?php

namespace App\Http\Controllers;

use App\Http\Requests\MapSubmission\IndexMapSubmissionRequest;
use App\Http\Requests\MapSubmission\StoreMapSubmissionRequest;
use App\Jobs\DeleteMapSubmissionWebhookJob;
use App\Jobs\SendMapSubmissionWebhookJob;
use App\Models\MapSubmission;
use App\Services\MapSubmission\MapSubmissionValidatorFactory;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class MapSubmissionController extends Controller
{
    /**
     * Get a paginated list of map submissions.
     *
     * @OA\Get(
     *     path="/maps/submissions",
     *     summary="Get list of map submissions",
     *     description="Retrieves a paginated list of map submissions with optional filters. Public access.",
     *     tags={"Map Submissions"},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapSubmissionRequest/properties/page")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapSubmissionRequest/properties/per_page")),
     *     @OA\Parameter(name="format_id", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapSubmissionRequest/properties/format_id")),
     *     @OA\Parameter(name="submitter_id", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapSubmissionRequest/properties/submitter_id")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapSubmissionRequest/properties/status")),
     *     @OA\Parameter(name="include", in="query", required=false, @OA\Schema(ref="#/components/schemas/IndexMapSubmissionRequest/properties/include")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/MapSubmission")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(IndexMapSubmissionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $page = $validated['page'];
        $perPage = $validated['per_page'];
        $formatId = $validated['format_id'] ?? null;
        $submitterId = $validated['submitter_id'] ?? null;
        $status = $validated['status'] ?? null;
        $include = $validated['include'] ?? [];

        // Build query
        $query = MapSubmission::with(['submitter', 'rejecter', 'format']);

        // Apply filters
        if ($formatId) {
            $query->where('format_id', $formatId);
        }

        if ($submitterId) {
            $query->where('submitter_id', $submitterId);
        }

        if ($status) {
            $query->withStatus($status);
        }

        // Paginate
        $paginated = $query->orderBy('created_on', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Load flair if requested
        $userService = app(UserService::class);
        $data = $paginated->map(function ($submission) use ($include, $userService) {
            if (in_array('submitter.flair', $include) && $submission->submitter) {
                $submission->submitter->appendFlair();
                $userService->refreshUserCache($submission->submitter);
            }

            return $submission->toArray();
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /**
     * Store a newly created map submission.
     *
     * @OA\Post(
     *     path="/maps/submissions",
     *     summary="Submit a map",
     *     description="Creates a pending map submission for review. Requires create:map_submission permission. Use multipart/form-data for image upload.",
     *     tags={"Map Submissions"},
     *     security={{"discord_auth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(ref="#/components/schemas/StoreMapSubmissionRequest")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Map submission created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", description="The created submission ID", example=123)
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Permission or validation violation"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(StoreMapSubmissionRequest $request, MapSubmissionValidatorFactory $validatorFactory): JsonResponse
    {
        $user = auth()->guard('discord')->user();
        $data = $request->validated();

        // Run format-specific validation
        $validator = $validatorFactory->getValidator($data['format_id']);
        $validator->validate($data, $user);

        // Upload proof image
        $path = $request->file('completion_proof')->store('map_submission_proofs', 'public');

        // Create submission
        $submission = MapSubmission::create([
            'code' => $data['code'],
            'submitter_id' => $user->discord_id,
            'subm_notes' => $data['subm_notes'] ?? null,
            'format_id' => $data['format_id'],
            'proposed' => $data['proposed'],
            'completion_proof' => $path,
            'created_on' => now(),
        ]);

        // Dispatch webhook job
        dispatch(new SendMapSubmissionWebhookJob($submission->id));

        return response()->json(['id' => $submission->id], 201);
    }

    /**
     * Get a single map submission by ID.
     *
     * @OA\Get(
     *     path="/maps/submissions/{id}",
     *     summary="Get a single map submission",
     *     description="Retrieves a single map submission. Public access.",
     *     tags={"Map Submissions"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The submission ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         required=false,
     *         description="Comma-separated list of additional data to include. Use 'submitter.flair' to include submitter avatar and banner URLs from Ninja Kiwi.",
     *         @OA\Schema(type="string", example="submitter.flair")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(ref="#/components/schemas/MapSubmission")
     *     ),
     *     @OA\Response(response=404, description="Submission not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function show(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'include' => 'nullable|string',
        ]);

        $include = array_filter(explode(',', $validated['include'] ?? ''));
        $includeSubmitterFlair = in_array('submitter.flair', $include);

        $submission = MapSubmission::with(['submitter', 'rejecter', 'format', 'acceptedMeta'])->find($id);

        if (!$submission) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        if ($includeSubmitterFlair && $submission->submitter) {
            $submission->submitter->appendFlair();
            $userService = app(UserService::class);
            $userService->refreshUserCache($submission->submitter);
        }

        return response()->json($submission->toArray());
    }

    /**
     * Remove the specified map submission from storage.
     *
     * @OA\Delete(
     *     path="/maps/submissions/{id}",
     *     summary="Delete a map submission",
     *     description="Hard-deletes a map submission and its proof image. Only the submitter can delete their own submission. Only pending submissions (not rejected or accepted) can be deleted.",
     *     tags={"Map Submissions"},
     *     security={{"discord_auth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The submission ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Submission deleted successfully"
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Not the owner or submission already processed"),
     *     @OA\Response(response=404, description="Submission not found"),
     *     @OA\Response(response=422, description="Submission already processed")
     * )
     */
    public function destroy($id): Response|JsonResponse
    {
        $user = auth()->guard('discord')->user();

        $submission = MapSubmission::find($id);

        if (!$submission) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Check ownership
        if ($submission->submitter_id !== $user->discord_id) {
            return response()->json(['message' => 'Forbidden - You can only delete your own submissions.'], 403);
        }

        // Check if pending (not rejected and not accepted)
        if ($submission->rejected_by !== null || $submission->accepted_meta_id !== null) {
            return response()->json(['message' => 'Cannot delete a submission that has already been processed.'], 422);
        }

        // Delete webhook message if it exists
        if ($submission->wh_msg_id && $submission->format->map_submission_wh) {
            dispatch(new DeleteMapSubmissionWebhookJob(
                $submission->format->map_submission_wh,
                $submission->wh_msg_id
            ));
        }

        // Delete proof image
        if ($submission->completion_proof && Storage::disk('public')->exists($submission->completion_proof)) {
            Storage::disk('public')->delete($submission->completion_proof);
        }

        // Hard delete the submission
        $submission->delete();

        return response()->noContent();
    }

    /**
     * Reject the specified map submission.
     *
     * @OA\Put(
     *     path="/maps/submissions/{id}/reject",
     *     summary="Reject a map submission",
     *     description="Rejects a pending map submission by setting rejected_by to the current user. Requires edit:map_submission permission for the submission's format. Only pending submissions can be rejected.",
     *     tags={"Map Submissions"},
     *     security={{"discord_auth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The submission ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Submission rejected successfully"
     *     ),
     *     @OA\Response(response=403, description="Forbidden - Permission or business rule violation"),
     *     @OA\Response(response=404, description="Submission not found"),
     *     @OA\Response(response=422, description="Submission already processed")
     * )
     */
    public function reject($id): Response|JsonResponse
    {
        $user = auth()->guard('discord')->user();

        $submission = MapSubmission::find($id);

        if (!$submission) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        // Check permission
        $userFormatIds = $user->formatsWithPermission('edit:map_submission');
        $hasGlobalPermission = in_array(null, $userFormatIds, true);
        $hasFormatPermission = in_array($submission->format_id, $userFormatIds, true);

        if (!$hasGlobalPermission && !$hasFormatPermission) {
            return response()->json(['message' => 'Forbidden - You do not have permission to reject submissions for this format.'], 403);
        }

        // Check if pending (not already rejected and not accepted)
        if ($submission->rejected_by !== null || $submission->accepted_meta_id !== null) {
            return response()->json(['message' => 'Cannot reject a submission that has already been processed.'], 422);
        }

        // Reject the submission
        $submission->rejected_by = $user->discord_id;
        $submission->save();

        return response()->noContent();
    }
}
