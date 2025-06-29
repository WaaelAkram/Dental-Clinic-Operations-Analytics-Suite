<?php
namespace App\Models;
use App\Gateways\ClinicPatientGateway;
use Illuminate\Database\Eloquent\Model;
class SentMarketingMessage extends Model
{
    protected $fillable = ['patient_id', 'mobile', 'status', 'process_date', 'message_sent_at', 'converted_at', 'new_appointment_id', 'new_appointment_date'];
    protected function casts(): array {
        return [
            'process_date' => 'date',
            'message_sent_at' => 'datetime',
            'converted_at' => 'datetime',
            'new_appointment_date' => 'date',
        ];
    }
    public function getPatientAttribute(): ?object {
        static $gateway;
        if (!$gateway) { $gateway = app(ClinicPatientGateway::class); }
        return $gateway->findPatientById($this->patient_id);
    }
}