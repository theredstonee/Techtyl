<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Server extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'game_type',
        'cpu',
        'memory',
        'disk',
        'status',
    ];

    protected $casts = [
        'cpu' => 'integer',
        'memory' => 'integer',
        'disk' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the user that owns the server
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the allocations for the server
     */
    public function allocations()
    {
        return $this->hasMany(Allocation::class);
    }

    /**
     * Check if server is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Check if server is stopped
     */
    public function isStopped(): bool
    {
        return $this->status === 'stopped';
    }

    /**
     * Get resource usage percentage
     */
    public function getResourceUsageAttribute(): array
    {
        return [
            'cpu' => rand(10, 80),
            'memory' => rand(20, 90),
            'disk' => rand(10, 70),
        ];
    }
}
