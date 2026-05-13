<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordReset extends Model
{
    //

    protected $table = 'password_resets';

    protected $fillable = [
        'user_id',
        'token',
    ];

    public $timestamps = false;

    // Quan hệ với User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
