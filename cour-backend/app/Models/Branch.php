<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    //
    protected $table = 'branches';

    protected $fillable = [
        'branch_name',
        'city',
        'address',
        'phone',
        
    ];

    public $timestamps = false;

    public function agents()
    {
        return $this->hasMany(User::class, 'branch_id')->where('role_id', 2);
    }
 
    public function assignments()
    {
        return $this->hasMany(ShipmentAssignment::class);
    }
}
