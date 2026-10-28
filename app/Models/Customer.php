<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Customer vua la ban ghi CRM (dung trong Admin, khong bat buoc co password:
 * khach vang lai / khach NV tao tay khong can tai khoan), vua co the DANG
 * NHAP duoc o Storefront neu da dang ky (co password) - dung chung 1 bang,
 * phan biet bang cot password NULL hay khong.
 */
#[Fillable(['name', 'phone', 'email', 'address', 'password', 'loyalty_points'])]
class Customer extends Authenticatable
{
    use HasFactory;

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'loyalty_points' => 'integer',
            'password' => 'hashed',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
