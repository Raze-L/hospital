<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CtScan extends Model
{
    protected $fillable = ['patient_id', 'is_analysed', 'image_url'];


//    protected $casts = [
//        'is_analysed' => 'boolean'
//    ];

    public function analysis()
    {
        return $this->hasOne(Analysis::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
