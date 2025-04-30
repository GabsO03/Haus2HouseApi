<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Worker extends Model
{
    protected $table = 'workers';

    protected $fillable = [
        'user_id',
        'dni',
        'bio',
        'habilidades',
        'disponibilidad',
        'rating',
        'cantidad_ratings',
        'current_location',
        'available',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'worker_id');
    }
}
