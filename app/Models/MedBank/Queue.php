<?php

namespace App\Models\MedBank;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Queue extends Model
{
    use HasFactory;

    protected $connection = 'mysql_projectrbh';
    protected $table = 'medbank_queue';

    public $timestamps = false;

    protected $fillable = ['appointmentId', 'queueNo', 'date', 'status'];

    protected $casts = [
        'date' => 'date',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointmentId');
    }
}
