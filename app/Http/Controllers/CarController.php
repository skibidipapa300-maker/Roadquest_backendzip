<?php

namespace App\Http\Controllers;

use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Models\Rental;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class CarController extends Controller
{
    public function index(Request $request)
    {
        $query = Car::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } elseif (!$request->has('all')) {
             $query->where('status', 'available');
        }

        if ($request->has('transmission')) {
            $query->where('transmission', $request->transmission);
        }

        if ($request->has('fuel_type')) {
            $query->where('fuel_type', $request->fuel_type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('make', 'like', "%{$search}%")
                  ->orWhere('model', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        return response()->json($query->get());
    }

    public function popular(Request $request)
    {
        // Get cars with most completed/approved rentals
        $limit = $request->get('limit', 3); // Default to 3 cars
        
        $popularCars = Car::select('cars.*')
            ->selectRaw('COUNT(CASE WHEN rentals.rental_status IN ("completed", "approved", "rented", "returned") THEN rentals.rental_id END) as rental_count')
            ->leftJoin('rentals', 'cars.car_id', '=', 'rentals.car_id')
            ->groupBy('cars.car_id', 'cars.make', 'cars.model', 'cars.year', 'cars.license_plate', 
                      'cars.category', 'cars.transmission', 'cars.fuel_type', 'cars.seat_capacity', 
                      'cars.daily_rate', 'cars.image_url', 'cars.status', 'cars.created_at', 'cars.updated_at')
            ->orderBy('rental_count', 'desc')
            ->orderBy('cars.created_at', 'desc') // Secondary sort by newest if same rental count
            ->limit($limit)
            ->get();

        // If we don't have enough popular cars, fill with available cars
        if ($popularCars->count() < $limit) {
            $availableCars = Car::where('status', 'available')
                ->whereNotIn('car_id', $popularCars->pluck('car_id'))
                ->limit($limit - $popularCars->count())
                ->get();
            
            foreach ($availableCars as $car) {
                $car->rental_count = 0;
            }
            
            $popularCars = $popularCars->merge($availableCars);
        }

        return response()->json($popularCars);
    }

    public function show($id)
    {
        $car = Car::find($id);
        if (!$car) {
            return response()->json(['message' => 'Car not found'], 404);
        }
        return response()->json($car);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'make' => 'required|string',
            'model' => 'required|string',
            'year' => 'required|integer',
            'license_plate' => 'required|string|unique:cars',
            'category' => 'required|string',
            'transmission' => 'required|in:automatic,manual',
            'fuel_type' => 'required|in:gasoline,diesel,electric',
            'seat_capacity' => 'required|integer',
            'daily_rate' => 'required|numeric',
            'status' => 'required|in:available,rented,maintenance',
            'image' => 'nullable|image|max:2048', // 2MB Max
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('cars', 'public');
            // Generate full URL
            $data['image_url'] = asset('storage/' . $path);
            $this->syncStorageLocal();
        }

        $car = Car::create($data);
        return response()->json(['message' => 'Car created successfully', 'car' => $car], 201);
    }

    public function update(Request $request, $id)
    {
        $car = Car::find($id);
        if (!$car) {
            return response()->json(['message' => 'Car not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'make' => 'sometimes|string',
            'model' => 'sometimes|string',
            'year' => 'sometimes|integer',
            'license_plate' => 'sometimes|string|unique:cars,license_plate,'.$id.',car_id',
            'category' => 'sometimes|string',
            'transmission' => 'sometimes|in:automatic,manual',
            'fuel_type' => 'sometimes|in:gasoline,diesel,electric',
            'seat_capacity' => 'sometimes|integer',
            'daily_rate' => 'sometimes|numeric',
            'status' => 'sometimes|in:available,rented,maintenance',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();

        if ($request->hasFile('image')) {
            // Store new image
            $path = $request->file('image')->store('cars', 'public');
            $data['image_url'] = asset('storage/' . $path);
            $this->safeSyncStorageLocal();
        }

        $car->update($data);
        return response()->json(['message' => 'Car updated successfully', 'car' => $car]);
    }

    public function destroy($id)
    {
        $car = Car::find($id);
        if (!$car) {
            return response()->json(['message' => 'Car not found'], 404);
        }

        $car->delete();
        return response()->json(['message' => 'Car deleted successfully']);
    }

    /**
     * Sync storage/app/public to public/storage locally (no symlink).
     */
    private function safeSyncStorageLocal(): void
    {
        try {
            $this->syncStorageLocal();
        } catch (\Throwable $e) {
            // Don't block update on sync errors; images may need manual sync.
        }
    }

    private function syncStorageLocal(): void
    {
        $source = storage_path('app/public');
        $dest = public_path('storage');

        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($dest) && !@mkdir($dest, 0755, true)) {
            return;
        }

        // Clear destination
        $this->clearDir($dest);

        // Copy files
        $this->copyDir($source, $dest);
    }

    private function clearDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->clearDir($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }

    private function copyDir(string $src, string $dst): void
    {
        $dir = @opendir($src);
        if ($dir === false) {
            return;
        }
        @mkdir($dst, 0755, true);
        while (false !== ($file = @readdir($dir))) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $srcPath = $src . DIRECTORY_SEPARATOR . $file;
            $dstPath = $dst . DIRECTORY_SEPARATOR . $file;
            if (is_dir($srcPath)) {
                $this->copyDir($srcPath, $dstPath);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }
}
