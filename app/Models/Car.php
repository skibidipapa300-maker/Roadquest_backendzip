<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Car extends Model
{
    use HasFactory;

    protected $primaryKey = 'car_id';

    protected $fillable = [
        'make',
        'model',
        'year',
        'license_plate',
        'category',
        'transmission',
        'fuel_type',
        'seat_capacity',
        'daily_rate',
        'image_url',
        'status',
    ];
}

