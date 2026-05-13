<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Shipment;

class ShipmentDetail extends Model
{
    // Tên bảng trong database
    protected $table = 'shipment_detail';

    // Các cột được phép insert dữ liệu
    protected $fillable = [
        'shipment_id',
        'item_name',
        'weight',
        'quantity',
        'note',
        'created_at',
        'shipping_fee',
    ];

    // Laravel tự động quản lý created_at và updated_at
    public $timestamps = false;
    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }
}