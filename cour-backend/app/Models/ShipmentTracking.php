<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentTracking extends Model
{
    protected $table = 'shipment_tracking';

    protected $fillable = [
        'shipment_id',
        'status',
        'updated_by',
        'note',
        'created_at',
    ];

    public $timestamps = false;

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }

    public function trackings()
    {
        return $this->hasMany(ShipmentTracking::class, 'shipment_id');
    }
}