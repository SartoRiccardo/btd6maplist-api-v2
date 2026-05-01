<?php

namespace App\Console\Commands;

use App\Models\Completion;
use App\Models\CompletionMeta;
use App\Models\LeastCostChimps;
use App\Models\Map;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DevAddCompletion extends Command
{
    protected $signature = 'dev:completion:add
        {user_id : Discord ID of the player}
        {map_code : Map code}
        {--black-border : Mark as black border}
        {--no-geraldo : Mark as no geraldo}
        {--lcc= : LCC leftover amount}
        {--pending : Leave as pending (not accepted)}
        {--deleted : Mark as deleted}
        {--acceptor= : Discord ID of the acceptor (defaults to random user)}
        {--format= : Format ID (defaults to 1)}';

    protected $description = 'Dev tool: add a completion directly to the database';

    public function handle(): int
    {
        $userId = $this->argument('user_id');
        $mapCode = $this->argument('map_code');
        $blackBorder = $this->option('black-border');
        $noGeraldo = $this->option('no-geraldo');
        $lccAmount = $this->option('lcc');
        $pending = $this->option('pending');
        $deleted = $this->option('deleted');
        $acceptorId = $this->option('acceptor');
        $formatId = (int) ($this->option('format') ?? 1);

        // Validate user exists
        $user = User::find($userId);
        if (!$user) {
            $this->error("User {$userId} not found.");
            return 1;
        }

        // Validate map exists
        $map = Map::find($mapCode);
        if (!$map) {
            $this->error("Map {$mapCode} not found.");
            return 1;
        }

        // Resolve acceptor
        $acceptedBy = null;
        if (!$pending) {
            if ($acceptorId) {
                $acceptor = User::find($acceptorId);
                if (!$acceptor) {
                    $this->error("Acceptor {$acceptorId} not found.");
                    return 1;
                }
                $acceptedBy = $acceptor->discord_id;
            } else {
                $acceptor = User::where('discord_id', '!=', $userId)
                    ->inRandomOrder()
                    ->first();
                if (!$acceptor) {
                    $this->error('No other user found to act as acceptor. Create another user or use --pending.');
                    return 1;
                }
                $acceptedBy = $acceptor->discord_id;
            }
        }

        // Handle LCC
        $lccId = null;
        if ($lccAmount !== null) {
            $lcc = LeastCostChimps::create(['leftover' => (int) $lccAmount]);
            $lccId = $lcc->id;
        }

        $now = Carbon::now();

        $result = DB::transaction(function () use ($mapCode, $formatId, $blackBorder, $noGeraldo, $lccId, $acceptedBy, $deleted, $userId, $now) {
            $completion = Completion::create([
                'map_code' => $mapCode,
                'subm_notes' => null,
            ]);

            $meta = CompletionMeta::create([
                'completion_id' => $completion->id,
                'format_id' => $formatId,
                'black_border' => $blackBorder,
                'no_geraldo' => $noGeraldo,
                'lcc_id' => $lccId,
                'accepted_by_id' => $acceptedBy,
                'created_on' => $now,
                'deleted_on' => $deleted ? $now : null,
            ]);

            $meta->players()->attach([$userId]);

            return $completion;
        });

        $flags = collect();
        if ($blackBorder) $flags->push('BB');
        if ($noGeraldo) $flags->push('NG');
        if ($lccAmount !== null) $flags->push("LCC:{$lccAmount}");
        if ($pending) $flags->push('PENDING');
        if ($deleted) $flags->push('DELETED');

        $flagStr = $flags->isEmpty() ? '' : ' [' . $flags->implode(', ') . ']';
        $this->info("Completion #{$result->id} created for user {$userId} on map {$mapCode} (format {$formatId}){$flagStr}");

        return 0;
    }
}
