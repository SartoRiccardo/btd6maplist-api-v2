<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshUserAvatarCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(UserService $userService): void
    {
        $user = User::find($this->userId);

        if (!$user) {
            Log::warning('User not found for avatar cache refresh', [
                'user_id' => $this->userId,
            ]);
            return;
        }

        // Skip if user has no OAK
        if (!$user->nk_oak) {
            return;
        }

        $userService->refreshAvatarCache($user);
    }
}
