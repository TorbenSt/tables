<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyResponse extends Model
{
    protected $fillable = [
        'respondent_name',
        'respondent_role',
    ];

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class);
    }
}
