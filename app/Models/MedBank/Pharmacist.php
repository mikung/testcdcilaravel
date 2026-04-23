<?php

namespace App\Models\MedBank;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;

class Pharmacist extends Authenticatable
{
    use HasApiTokens, HasFactory;

    protected $connection = 'mysqlHyggeRBH';
    protected $table = 'medbank_pharmacist';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = ['username', 'passwordHash', 'name', 'role', 'status'];

    protected $hidden = ['passwordHash'];

    // Sanctum/Auth ต้องการ getAuthPassword() ที่คืนค่า hash
    public function getAuthPassword()
    {
        return $this->passwordHash;
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'pharmacistId');
    }
}
