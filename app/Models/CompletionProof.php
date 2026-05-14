<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompletionProof extends Model
{
    use HasFactory;

    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'run',
        'proof_url',
        'proof_type',
        'is_added_by_admin',
    ];

    protected $casts = [
        'is_added_by_admin' => 'boolean',
    ];

    /**
     * Get the completion metadata this proof belongs to.
     */
    public function completionMeta()
    {
        return $this->belongsTo(CompletionMeta::class, 'run');
    }
}
