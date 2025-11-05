<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Allocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'ip',
        'port',
        'is_primary',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_primary' => 'boolean',
    ];

    /**
     * Get the server that owns the allocation
     */
    public function server()
    {
        return $this->belongsTo(Server::class);
    }
}
