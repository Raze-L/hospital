<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Analysis extends Model
{
    use HasFactory;

    protected $fillable = [
        'ct_scan_id',
        'image_analysis',
        'diagnostic_opinion',
        'treatment_recommendation',
        'result_image_url',
    ];

    public function ctScan()
    {
        return $this->belongsTo(CtScan::class);
    }
}