<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OtpCode;
use App\Models\UserToken;
use App\Mail\OtpMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|unique:users',
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // Combine first_name and last_name into full_name
            $fullName = trim($request->first_name . ' ' . $request->last_name);

            $user = User::create([
                'username' => $request->username,
                'full_name' => $fullName,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'customer',
                'is_verified' => false, 
            ]);

            // Generate OTP
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            OtpCode::create([
                'user_id' => $user->user_id,
                'otp_code' => $otp,
                'type' => 'activation',
                'expires_at' => Carbon::now()->addMinutes(10),
            ]);

            // Send Email
            Mail::to($user->email)->send(new OtpMail($otp, $user->full_name, 'activation', $user->username, $user->email));

            DB::commit();

            return response()->json([
                'message' => 'Registration successful. Please check your email for verification code.', 
                'user' => $user
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Registration failed', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'otp_code' => 'required'
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->is_verified) {
             return response()->json(['message' => 'User already verified'], 200);
        }

        $otpRecord = OtpCode::where('user_id', $user->user_id)
            ->where('otp_code', $request->otp_code)
            ->where('type', 'activation')
            ->where('is_used', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otpRecord) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        // Mark OTP as used
        $otpRecord->update(['is_used' => true]);

        // Verify User
        $user->update(['is_verified' => true]);

        return response()->json(['message' => 'Account verified successfully. You can now login.', 'user' => $user]);
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'username' => 'required'
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->is_verified) {
            return response()->json(['message' => 'User already verified'], 200);
        }

        // Invalidate previous unused activation OTPs
        OtpCode::where('user_id', $user->user_id)
               ->where('type', 'activation')
               ->where('is_used', false)
               ->update(['is_used' => true]);

        // Generate new OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        OtpCode::create([
            'user_id' => $user->user_id,
            'otp_code' => $otp,
            'type' => 'activation',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        // Send Email
        try {
            Mail::to($user->email)->send(new OtpMail($otp, $user->full_name, 'activation', $user->username, $user->email));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send email.'], 500);
        }

        return response()->json(['message' => 'New OTP code sent to your email.']);
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (!$user->is_verified) {
            return response()->json(['message' => 'Account not verified. Please verify your email.'], 403);
        }

        // Generate Token
        $tokenString = Str::random(60);
        $expiresAt = Carbon::now()->addDays(7); // Token valid for 7 days

        UserToken::create([
            'user_id' => $user->user_id,
            'token' => $tokenString,
            'expires_at' => $expiresAt
        ]);

        return response()->json([
            'message' => 'Login successful', 
            'user' => $user,
            'token' => $tokenString
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->header('Authorization');
        
        if ($token) {
            // Remove 'Bearer ' prefix if present
            $token = str_replace('Bearer ', '', $token);
            
            UserToken::where('token', $token)->delete();
        }

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Security: Don't reveal if user exists or not
            return response()->json(['message' => 'If your email is registered, you will receive a reset code.']);
        }

        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Invalidate previous unused reset tokens? Optional but good practice
        OtpCode::where('user_id', $user->user_id)
               ->where('type', 'reset')
               ->where('is_used', false)
               ->update(['is_used' => true]);

        OtpCode::create([
            'user_id' => $user->user_id,
            'otp_code' => $otp,
            'type' => 'reset',
            'expires_at' => Carbon::now()->addMinutes(10),
        ]);

        try {
            Mail::to($user->email)->send(new OtpMail($otp, $user->full_name, 'reset', $user->username, $user->email));
        } catch (\Exception $e) {
             return response()->json(['message' => 'Failed to send email.'], 500);
        }

        return response()->json(['message' => 'Reset code sent to your email.']);
    }

    public function verifyResetOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp_code' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $otpRecord = OtpCode::where('user_id', $user->user_id)
            ->where('otp_code', $request->otp_code)
            ->where('type', 'reset')
            ->where('is_used', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otpRecord) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        return response()->json(['message' => 'OTP verified. Proceed to reset password.']);
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp_code' => 'required',
            'new_password' => 'required|min:6|confirmed' // requires new_password_confirmation field
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $otpRecord = OtpCode::where('user_id', $user->user_id)
            ->where('otp_code', $request->otp_code)
            ->where('type', 'reset')
            ->where('is_used', false)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$otpRecord) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }

        // Mark OTP as used
        $otpRecord->update(['is_used' => true]);

        // Update Password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password reset successful. You can now login with your new password.']);
    }
}
