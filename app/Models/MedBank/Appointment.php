<?php

namespace App\Models\MedBank;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $connection = 'mysql_projectrbh';
    protected $table = 'medbank_appointment';

    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = ['vn', 'patientId', 'visitDate', 'clinic', 'doctor'];

    protected $casts = [
        'visitDate' => 'date',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patientId');
    }

    public function queues()
    {
        return $this->hasMany(Queue::class, 'appointmentId')->orderBy('queueNo');
    }

    public function notification()
    {
        return $this->hasOne(Notification::class, 'appointmentId');
    }
}
