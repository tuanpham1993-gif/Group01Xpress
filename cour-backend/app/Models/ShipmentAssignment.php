<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentAssignment extends Model
{
    protected $table = 'shipment_assignments';

    public $timestamps = false;

    protected $fillable = [
        'shipment_id',
        'agent_id',
        'branch_id',

        // thêm
        'assignment_type',
        'assignment_status',

        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    // ── Relations ──

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}