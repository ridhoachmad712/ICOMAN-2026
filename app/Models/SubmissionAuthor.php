<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionAuthor extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'name',
        'email',
        'affiliation',
        'is_corresponding',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'is_corresponding' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
