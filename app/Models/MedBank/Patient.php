<?php

namespace App\Models\MedBank;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    protected $connection = 'mysqlHyggeRBH';
    protected $table = 'medbank_patient';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = ['hn', 'name'];

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'patientId');
    }
}
