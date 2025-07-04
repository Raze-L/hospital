<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'name',
        'gender',
        'age',
        'birth_date',
        'phone',
        'address',
        'emergency_contact',
        'blood_type',
        'allergy_history',
        'medical_history',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ctScans()
    {
        return $this->hasMany(CtScan::class);
    }

    // 获取患者的状态（是否有分析记录）
    public function getStatusAttribute()
    {
        return $this->ctScans()->has('analysis')->exists() ? 'analyzed' : 'pending';
    }

    // 获取已分析的CT扫描数量
    public function getAnalyzedCountAttribute()
    {
        return $this->ctScans()->has('analysis')->count();
    }

    // 获取待分析的CT扫描数量
    public function getPendingCountAttribute()
    {
        return $this->ctScans()->doesntHave('analysis')->count();
    }
}