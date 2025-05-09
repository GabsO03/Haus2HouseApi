<?php

namespace App\Models;

use App\Enums\Estados;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Service extends Model
{
    protected $table = 'services';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'client_id',
        'worker_id',
        'service_type_id',
        'description',
        'specifications',
        'request_time',
        'start_time',
        'end_time',
        'duration_hours',
        'client_location',
        'worker_location',
        'status',
        'total_amount',
        'payment_stripe_id',
        'client_rating',
        'worker_rating',
        'client_comments',
        'worker_comments',
        'incident_report',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function worker()
    {
        return $this->belongsTo(Worker::class, 'worker_id');
    }

    public function serviceType()
    {
        return $this->belongsTo(ServiceType::class, 'service_type_id');
    }

    protected $casts = [
        'status' => Estados::class,
        'request_time' => 'datetime',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_hours' => 'decimal:2',
    ];
}
