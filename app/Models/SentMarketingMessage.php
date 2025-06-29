<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SentMarketingMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'mobile',
        'status',
        'process_date',
        'message_sent_at',
        'converted_at',
        'new_appointment_id',
        'new_appointment_date',
    ];

    protected function casts(): array
    {
        return [
            'process_date' => 'date',
            'message_sent_at' => 'datetime',
            'converted_at' => 'datetime',
            'new_appointment_date' => 'date',
        ];
    }
     public function getPatientAttribute(): ?object
    {
        // Use a static variable to cache the result for the lifetime of the request
        // to avoid repeatedly querying the gateway for the same patient.
        static $gateway;
        if (!$gateway) {
            $gateway = app(ClinicPatientGateway::class);
        }
        
        return $gateway->findPatientById($this->patient_id);
    }
}