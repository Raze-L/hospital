<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CtScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'image_url',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function analysis()
    {
        return $this->hasOne(Analysis::class);
    }
}