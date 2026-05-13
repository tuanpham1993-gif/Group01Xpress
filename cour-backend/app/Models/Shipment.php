<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ShipmentTracking;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\invoice;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\ShipmentDetail;
class Shipment extends Model
{

    protected $table = 'shipments';

    protected $fillable = [
        'tracking_code',
        'sender_name',
        'sender_phone',
        'sender_address',
        'sender_city',

        'receiver_name',
        'receiver_phone',
        'receiver_address',
        'receiver_city',

        'branch_id',

        'service_type',
        'shipment_status',
        'booking_date',
        'delivery_date',
        'expected_arrival_time',
        'created_by',
    ];

    public $timestamps = false;

    public function trackings(): HasMany
    {
        return $this->hasMany(ShipmentTracking::class, 'shipment_id');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(invoice::class, 'shipment_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function created_by()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function details()
    {
        return $this->hasMany(ShipmentDetail::class, 'shipment_id');
    }
}
