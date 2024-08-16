<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;



class ForgotPasswordController extends Controller
{
   public function sendOTP(Request $request)
    {
        // Validate the request
        $request->validate([
            'email' => 'required|email',
        ]);

        // Check if the email exists in the users table
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Email not found'], 404);
        }

        // Generate a random OTP
        $otp = mt_rand(100000, 999999);

        try {
            // Start transaction
            DB::beginTransaction();

            // Store OTP in the database
            $otpRecord = Otp::updateOrCreate(
                ['email' => $request->email], // Where condition
                ['otp' => $otp]               // Update or create with this OTP
            );
        $utcDate = Carbon::now('UTC');
            // Convert the created_at field to local time zone
            $createdAtLocal = Carbon::parse($utcDate, 'UTC')
                                    ->setTimezone('Asia/Kolkata')
                                    ->format('Y-m-d H:i:s');

            // Update the created_at field in the database with the local time
            $otpRecord->created_at = $createdAtLocal;
            $otpRecord->save();

            // Commit transaction
            DB::commit();

            // Log before sending email
            Log::info('Sending OTP email to ' . $request->email . ' with OTP: ' . $otp);

            // Send OTP via email
            Mail::raw("Your OTP for password reset is $otp", function ($message) use ($request) {
                $message->to($request->email)
                        ->subject('OTP for Password Reset');
            });

            // Log after sending email
            Log::info('Email sent', ['time' => $createdAtLocal]);

            return response()->json([
                'message' => 'OTP sent successfully',
                'otp' => $otp,
                'created_at' => $createdAtLocal
            ]);

        } catch (\Exception $e) {
            // Log the error
            Log::error('Failed to send OTP', ['error' => $e->getMessage()]);

            // Rollback transaction in case of an error
            DB::rollBack();
            return response()->json(['message' => 'Failed to send OTP. Please try again later.'], 500);
        }
    }

public function verifyOTP(Request $request)
{
    // Validate incoming request
    $request->validate([
        'email' => 'required|email',
        'otp' => 'required|numeric',
    ]);

    // Find the OTP record for the provided email
    $otpRecord = Otp::where('email', $request->email)->first();

    if (!$otpRecord) {
        return response()->json(['message' => 'Invalid email'], 404);
    }

    // Check if the provided OTP matches the record
    if ($otpRecord->otp != $request->otp) {
        return response()->json(['message' => 'Invalid OTP'], 400);
    }

    // Get the current UTC time
    $utcDate = Carbon::now('UTC');
     $utcparseDate = Carbon::parse($utcDate, 'UTC')
                                    ->setTimezone('Asia/Kolkata')
                                    ->format('Y-m-d H:i:s');
    Log::info(['utcparseDate' => $utcparseDate]);

    // Parse the OTP creation time
    $otpCreatedAt = Carbon::parse($otpRecord->created_at, 'UTC');
    Log::info(['otpCreatedAt' => $otpCreatedAt]);

    // Add 3 minutes to the OTP creation time
    $threeMinutesLater = $otpCreatedAt->copy()->addMinutes(3);
    Log::info(['threeMinutesLater' => $threeMinutesLater]);

    // Compare the current time with the adjusted OTP creation time
  if ($utcparseDate >= $threeMinutesLater) {
        // OTP is expired
        return response()->json(['message' => 'OTP has expired. Please request a new OTP.'], 400);
    }

    // OTP is valid and not expired
    return response()->json(['message' => 'OTP verified successfully'], 200);
}

 public function resetPassword(Request $request)
{
    // Validate the request
    $request->validate([
        'email' => 'required|email',
        'newPassword' => 'required|min:4',
    ]);

    // Retrieve the user record for the given email
    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json(['message' => 'Invalid email'], 404);
    }

    // Update the user's password
    $user->password = Hash::make($request->newPassword);
    $user->save();

    // Optionally, you can delete the OTP record after successful password reset
    Otp::where('email', $request->email)->delete();

    return response()->json(['message' => 'Password reset successfully'], 200);
}



}
