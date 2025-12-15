<?php

namespace Database\Seeders;

use App\Models\Car;
use Illuminate\Database\Seeder;

class CarSeeder extends Seeder
{
    public function run(): void
    {
        $cars = [
            [
                'make' => 'Toyota',
                'model' => 'Camry',
                'year' => 2023,
                'license_plate' => 'ABC-123',
                'category' => 'Sedan',
                'transmission' => 'automatic',
                'fuel_type' => 'gasoline',
                'seat_capacity' => 5,
                'daily_rate' => 50.00,
                'image_url' => 'https://images.unsplash.com/photo-1621007947382-bb3c3968e3bb?auto=format&fit=crop&w=500&q=60',
                'status' => 'available',
            ],
            [
                'make' => 'Honda',
                'model' => 'CR-V',
                'year' => 2024,
                'license_plate' => 'XYZ-789',
                'category' => 'SUV',
                'transmission' => 'automatic',
                'fuel_type' => 'gasoline',
                'seat_capacity' => 5,
                'daily_rate' => 75.00,
                'image_url' => 'https://images.unsplash.com/photo-1568844293986-8d0400bd4745?auto=format&fit=crop&w=500&q=60',
                'status' => 'available',
            ],
            [
                'make' => 'Tesla',
                'model' => 'Model 3',
                'year' => 2023,
                'license_plate' => 'ELN-456',
                'category' => 'Sedan',
                'transmission' => 'automatic',
                'fuel_type' => 'electric',
                'seat_capacity' => 5,
                'daily_rate' => 120.00,
                'image_url' => 'https://images.unsplash.com/photo-1536700503339-1e4b06520771?auto=format&fit=crop&w=500&q=60',
                'status' => 'available',
            ],
            [
                'make' => 'Ford',
                'model' => 'Mustang',
                'year' => 2022,
                'license_plate' => 'MUS-999',
                'category' => 'Sports',
                'transmission' => 'automatic',
                'fuel_type' => 'gasoline',
                'seat_capacity' => 4,
                'daily_rate' => 150.00,
                'image_url' => 'https://images.unsplash.com/photo-1584345604476-8ec5e12e42dd?auto=format&fit=crop&w=500&q=60',
                'status' => 'available',
            ],
        ];

        foreach ($cars as $car) {
            Car::create($car);
        }
    }
}

