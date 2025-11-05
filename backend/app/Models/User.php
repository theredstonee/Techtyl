<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'server_limit',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_admin' => 'boolean',
        'server_limit' => 'integer',
    ];

    /**
     * Get the servers owned by the user
     */
    public function servers()
    {
        return $this->hasMany(Server::class);
    }

    /**
     * Check if user can create more servers
     */
    public function canCreateServer(): bool
    {
        return $this->servers()->count() < $this->server_limit;
    }

    /**
     * Get remaining server slots
     */
    public function getRemainingServersAttribute(): int
    {
        return max(0, $this->server_limit - $this->servers()->count());
    }
}
