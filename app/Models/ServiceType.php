<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceType extends Model
{
    protected $table = 'service_types';

    protected $fillable = [
        'name',
        'description',
        'base_rate_per_hour',
        'adicionales',
        'active',
    ];

    public function services()
    {
        return $this->hasMany(Service::class, 'service_type_id');
    }
}
