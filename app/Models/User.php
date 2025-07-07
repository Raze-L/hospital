<?php

namespace App\Models;

use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;



class User extends Authenticatable implements JWTSubject
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'account',
        'username',
        'password',
        'phone',
        'email',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];




    public static function register($validated)
    {
        try {
            return  User::create([
                'account' => $validated['account'],
                'username' => $validated['username'],
                'password' => $validated['password'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
            ]);

        }catch (\Exception $e){
            return response()->json(['error' => $e->getMessage()], 500);
        }

    }

    // 一个医生可以管理多个患者
    public function allPatients()
    {
        return $this->hasMany(Patient::class, 'user_id', 'id');
    }

    // 一个医生可以进行多个分析记录
    public function analysisRecords()
    {
        return $this->hasMany(AnalysisRecord::class);
    }


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
