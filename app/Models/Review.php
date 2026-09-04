<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    public const RECOMMENDATIONS = [
        'accept',
        'minor_revision',
        'major_revision',
        'reject',
    ];

    protected $fillable = [
        'review_assignment_id',
        'score',
        'comments_for_author',
        'comments_for_committee',
        'recommendation',
        'recommends_sinta3',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer', // skala 1-100
            'recommends_sinta3' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ReviewAssignment::class, 'review_assignment_id');
    }
}
