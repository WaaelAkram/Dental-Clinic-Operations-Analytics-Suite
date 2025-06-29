<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketingExclusion extends Model
{
    protected $fillable = ['patient_id', 'mobile', 'reason'];
}