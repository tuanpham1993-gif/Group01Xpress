<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    //
    use HasFactory;// Đặt tên bảng nếu không theo quy ước Laravel
    protected $table = 'invoices';
        const UPDATED_AT = null; // Nếu không có trường updated_at, đặt nó thành null để Laravel không cố gắng cập nhật

     // Định nghĩa các trường có thể gán hàng loạt
    protected $fillable = [
        'shipment_id',
        'invoice_code',
        'shipping_fee',
        'tax',
        'total_amount',
        'created_at',
    ];

    /**
     * Định nghĩa mối quan hệ: Một hóa đơn thuộc về một vận đơn
     */
    public function shipment()
    {
        // 'shipment_id' là khóa ngoại nằm trong bảng invoices
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }
}
