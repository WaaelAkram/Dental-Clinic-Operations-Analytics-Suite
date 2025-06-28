<?php
// app/Models/SentReminder.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SentReminder extends Model
{
    use HasFactory;
    public $timestamps = false; // We only need the creation timestamp.
    protected $fillable = ['appointment_id', 'sent_at'];
}