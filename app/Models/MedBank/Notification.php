<?php

namespace App\Models\MedBank;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $connection = 'mysql_projectrbh';
    protected $table = 'medbank_notification';

    public $timestamps = false;

    protected $fillable = [
        'appointmentId',
        'queueNo',
        'status',
        'deliveryMethod',
        'deliveryAddress',
        'deliveryPhone',
        'pharmacistId',
        'notifiedAt',
        'processedAt',
        'dispatchedAt',
        'completedAt',
    ];

    protected $casts = [
        'notifiedAt'   => 'datetime',
        'processedAt'  => 'datetime',
        'dispatchedAt' => 'datetime',
        'completedAt'  => 'datetime',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointmentId');
    }

    public function pharmacist()
    {
        return $this->belongsTo(Pharmacist::class, 'pharmacistId');
    }
}
