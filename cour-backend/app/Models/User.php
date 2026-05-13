<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
class User extends Authenticatable implements JWTSubject
{
    // use HasApiTokens, Notifiable;
    // /** @use HasFactory<UserFactory> */
    // use HasFactory, Notifiable;

    // /**
    //  * The attributes that are mass assignable.
    //  *
    //  * @var list<string>
    //  */
    // protected $fillable = [
    //     'name',
    //     'email',
    //     'password',
    // ];

    // /**
    //  * The attributes that should be hidden for serialization.
    //  *
    //  * @var list<string>
    //  */
    // protected $hidden = [
    //     'password',
    //     'remember_token',
    // ];

    // /**
    //  * Get the attributes that should be cast.
    //  *
    //  * @return array<string, string>
    //  */
    // protected function casts(): array
    // {
    //     return [
    //         'email_verified_at' => 'datetime',
    //         'password' => 'hashed',
    //     ];
    // }

    public function getJWTIdentifier()// trả về giá trị định danh của người dùng để sử dụng trong JWT
    {
        return $this->getKey();// trả về giá trị của khóa chính (primary key) của người dùng, thường là trường 'id' trong cơ sở dữ liệu
    }
    public function getJWTCustomClaims()// trả về một mảng các thông tin tùy chỉnh sẽ được thêm vào payload của JWT
    {
        return [];// trả về 1 mảng rỗng
    }
    protected $table = 'users';

    protected $fillable = [
        'username',
        'email',
        'password',
        'role_id',
        'branch_id',
        'full_name',
        'phone',
        'address',
        'city',
        'status'
    ];

    protected $hidden = [
        'password'
    ];

    protected $casts = [
        'password' => 'hashed',// chuyển đổi mật khẩu thành dạng hash khi lưu vào cơ sở dữ liệu
    ];

    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;
    public function shipmentAssignments()
    {
        return $this->hasMany(ShipmentAssignment::class, 'agent_id');
    }
}
