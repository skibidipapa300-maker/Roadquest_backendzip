<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('is_verified')) {
            $query->where('is_verified', $request->is_verified);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%");
            });
        }

        return response()->json($query->get());
    }

    public function show($id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        return response()->json($user);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|unique:users',
            'full_name' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'role' => 'required|in:admin,staff,customer',
            'password' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['password'] = Hash::make($data['password']);
        // Set is_verified to true by default for new users
        $data['is_verified'] = true;

        $user = User::create($data);
        return response()->json(['message' => 'User created successfully', 'user' => $user], 201);
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Prevent admin from editing their own role
        $currentUser = $request->user();
        if ($currentUser && $currentUser->user_id == $id && $currentUser->role === 'admin' && isset($request->role) && $request->role !== $user->role) {
            return response()->json(['message' => 'You cannot change your own role'], 403);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'sometimes|unique:users,username,'.$id.',user_id',
            'email' => 'sometimes|email|unique:users,email,'.$id.',user_id',
            'role' => 'sometimes|in:admin,staff,customer',
            'is_verified' => 'sometimes|boolean',
            'password' => 'sometimes|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);
        return response()->json(['message' => 'User updated successfully', 'user' => $user]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user(); // Get authenticated user

        $validator = Validator::make($request->all(), [
            'username' => 'sometimes|unique:users,username,'.$user->user_id.',user_id',
            'full_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:users,email,'.$user->user_id.',user_id',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'password' => 'nullable|min:6',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->except(['image', 'password', '_method']); // exclude image to handle manually

        if ($request->has('password') && !empty($request->password)) {
            $data['password'] = Hash::make($request->password);
        }

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($user->image) {
                $oldPath = str_replace(asset('storage/'), '', $user->image);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('image')->store('profiles', 'public');
            $data['image'] = asset('storage/' . $path);
            $this->safeSyncStorageLocal();
        }

        $user->update($data);

        return response()->json(['message' => 'Profile updated successfully', 'user' => $user]);
    }

    public function destroy(Request $request, $id)
    {
        // Prevent deleting the Super Admin (ID 6)
        if ($id == 6) {
            return response()->json(['message' => 'Cannot delete Super Admin'], 403);
        }

        // Prevent admin from deleting their own account
        $currentUser = $request->user();
        if ($currentUser && $currentUser->user_id == $id) {
            return response()->json(['message' => 'You cannot delete your own account'], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Sync storage/app/public to public/storage locally (no symlink).
     */
    private function safeSyncStorageLocal(): void
    {
        try {
            $this->syncStorageLocal();
        } catch (\Throwable $e) {
            // Don't block profile updates on sync errors.
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
