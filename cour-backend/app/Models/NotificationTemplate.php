<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    //
    protected $table = 'notification_templates';


    public $timestamps = true;

    const CREATED_AT = 'created_at'; // Laravel tự động quản lý created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'template_name',
        'subject',
        'content',
        'created_at',
        'is_active',

    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relationships
    public function notifications()
    {
        return $this->hasMany(Notification::class, 'template_id');
    }
}
