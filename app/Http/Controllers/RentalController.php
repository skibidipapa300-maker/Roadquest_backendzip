<?php

namespace App\Http\Controllers;

use App\Models\Rental;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class RentalController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,user_id',
            'car_id' => 'required|exists:cars,car_id',
            'pickup_date' => 'required|date|after:today',
            'return_date' => 'required|date|after:pickup_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $car = \App\Models\Car::find($request->car_id);
        $start = Carbon::parse($request->pickup_date);
        $end = Carbon::parse($request->return_date);
        
        // Basic double booking check for new creation
        $conflict = Rental::where('car_id', $request->car_id)
            ->whereIn('rental_status', ['approved', 'rented', 'active', 'Pending Return'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('pickup_date', [$start, $end])
                      ->orWhereBetween('return_date', [$start, $end])
                      ->orWhere(function ($q) use ($start, $end) {
                          $q->where('pickup_date', '<=', $start)
                            ->where('return_date', '>=', $end);
                      });
            })
            ->exists();

        if ($conflict) {
            return response()->json(['message' => 'Car is not available for selected dates'], 400);
        }

        $days = $start->diffInDays($end) ?: 1;
        $total_price = $car->daily_rate * $days;

        $rental = Rental::create([
            'user_id' => $request->user_id,
            'car_id' => $request->car_id,
            'pickup_date' => $request->pickup_date,
            'return_date' => $request->return_date,
            'total_price' => $total_price,
            'payment_status' => 'unpaid',
            'rental_status' => 'pending',
        ]);

        return response()->json(['message' => 'Booking created successfully', 'rental' => $rental], 201);
    }

    public function index(Request $request)
    {
        if ($request->has('user_id')) {
            $rentals = Rental::where('user_id', $request->user_id)
                            ->with('car')
                            ->orderBy('created_at', 'desc')
                            ->get();
            return response()->json($rentals);
        }
        
        $query = Rental::with(['car', 'user', 'processedByUser']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('user', function($u) use ($search) {
                    $u->where('username', 'like', "%{$search}%")
                      ->orWhere('full_name', 'like', "%{$search}%");
                })
                ->orWhereHas('car', function($c) use ($search) {
                    $c->where('make', 'like', "%{$search}%")
                      ->orWhere('model', 'like', "%{$search}%");
                });
            });
        }

        if ($request->has('status')) {
            $query->where('rental_status', $request->status);
        }

        return response()->json($query->orderBy('created_at', 'desc')->get());
    }

    // Legacy generic update - restricted or deprecated in favor of updateStatus
    public function update(Request $request, $id)
    {
        // Redirect to new logic if it's a status update
        if ($request->has('rental_status')) {
            return $this->updateStatus($request, $id);
        }
        
        // Allow other updates (like payment) for Staff/Admin
        if (!$request->user() || !in_array($request->user()->role, ['admin', 'staff'])) {
             return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rental = Rental::find($id);
        if (!$rental) return response()->json(['message' => 'Rental not found'], 404);
        
        $rental->update($request->all());
        return response()->json(['message' => 'Rental updated', 'rental' => $rental]);
    }

    public function updateStatus(Request $request, $id)
    {
        $rental = Rental::find($id);
        if (!$rental) {
            return response()->json(['message' => 'Rental not found'], 404);
        }

        $user = $request->user();
        $newStatus = $request->rental_status;
        $currentStatus = $rental->rental_status;

        // State Machine Logic
        switch ($currentStatus) {
            case 'pending':
                if (!in_array($user->role, ['admin', 'staff'])) {
                    if ($newStatus === 'cancelled' && $user->user_id === $rental->user_id) {
                        // Allow customer to cancel pending
                        break; 
                    }
                    return response()->json(['message' => 'Unauthorized'], 403);
                }
                // Staff/Admin actions
                if (!in_array($newStatus, ['approved', 'denied'])) {
                    return response()->json(['message' => 'Invalid transition from Pending'], 400);
                }
                
                if ($newStatus === 'approved') {
                    // Double Booking Check Trigger
                    $this->handleDoubleBookingPrevention($rental);
                    // Update car status to rented
                    $rental->car()->update(['status' => 'rented']);
                }
                break;

            case 'approved':
                if ($user->user_id === $rental->user_id) {
                    // Customer Actions
                    if (!in_array($newStatus, ['rented', 'cancelled'])) {
                        return response()->json(['message' => 'Invalid transition from Approved'], 400);
                    }
                } else {
                    return response()->json(['message' => 'Only customer can start rental or cancel'], 403);
                }
                
                if ($newStatus === 'rented') {
                    // Status already updated to rented when approved, but ensure it sticks or update if needed
                    $rental->car()->update(['status' => 'rented']);
                }
                break;

            case 'rented': // or 'active'
            case 'active':
                if ($user->user_id === $rental->user_id) {
                    // Customer Action
                    if ($newStatus !== 'Pending Return') {
                        return response()->json(['message' => 'Invalid transition from Rented'], 400);
                    }
                } else {
                    return response()->json(['message' => 'Only customer can return car'], 403);
                }
                break;

            case 'Pending Return':
                if (!in_array($user->role, ['admin', 'staff'])) {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }
                if ($newStatus !== 'returned') {
                    return response()->json(['message' => 'Invalid transition from Pending Return'], 400);
                }
                
                // Car is physically back but process not complete
                $rental->car()->update(['status' => 'available']); // Or maintenance if needed? Assuming available.
                break;

            case 'returned':
                if (!in_array($user->role, ['admin', 'staff'])) {
                    return response()->json(['message' => 'Unauthorized'], 403);
                }
                if ($newStatus !== 'completed') {
                    return response()->json(['message' => 'Invalid transition from Returned'], 400);
                }
                break;

            default:
                return response()->json(['message' => 'Cannot update status from ' . $currentStatus], 400);
        }

        $rental->rental_status = $newStatus;
        if(in_array($user->role, ['admin', 'staff'])) {
            $rental->processed_by = $user->user_id;
        }
        $rental->save();

        return response()->json(['message' => 'Status updated successfully', 'rental' => $rental]);
    }

    private function handleDoubleBookingPrevention(Rental $approvedRental)
    {
        $start = $approvedRental->pickup_date;
        $end = $approvedRental->return_date;
        $carId = $approvedRental->car_id;

        // Find other PENDING rentals for the same car with overlapping dates
        $conflicts = Rental::where('car_id', $carId)
            ->where('rental_status', 'pending')
            ->where('rental_id', '!=', $approvedRental->rental_id)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('pickup_date', [$start, $end])
                      ->orWhereBetween('return_date', [$start, $end])
                      ->orWhere(function ($q) use ($start, $end) {
                          $q->where('pickup_date', '<=', $start)
                            ->where('return_date', '>=', $end);
                      });
            })
            ->get();

        // Bulk update to denied
        foreach ($conflicts as $conflict) {
            $conflict->update(['rental_status' => 'denied']);
        }
    }

    // Keep for backward compatibility if needed, but updateStatus handles logic
    public function cancel(Request $request, $id)
    {
        $request->merge(['rental_status' => 'cancelled']);
        return $this->updateStatus($request, $id);
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user() || $request->user()->role !== 'admin') {
             return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rental = Rental::find($id);
        if (!$rental) {
            return response()->json(['message' => 'Rental not found'], 404);
        }

        $rental->delete();
        return response()->json(['message' => 'Rental deleted successfully']);
    }
}
