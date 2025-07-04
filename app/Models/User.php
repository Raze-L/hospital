<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'account',
        'username',
        'password',
        'email',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function patients()
    {
        return $this->hasMany(Patient::class);
    }

    /**
     * 获取用户的 JWT 标识
     * @return mixed 返回用户的主键值
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * 返回包含自定义声明的数组，用于添加到 JWT 令牌中
     * @return array 返回自定义声明数组
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
}