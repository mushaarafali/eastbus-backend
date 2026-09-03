<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\FixedScheduleController;
use App\Http\Controllers\Api\PassengerAuthController;
use App\Http\Controllers\Api\PassengerBookingController;
use App\Http\Controllers\Api\PassengerChatController;
use App\Http\Controllers\Api\PassengerFeedbackController;
use App\Http\Controllers\Api\PassengerProfileController;
use App\Http\Controllers\Api\PassengerRecommendationController;
use App\Http\Controllers\Api\StaffAuthController;
use App\Http\Controllers\Api\TicketValidationController;
use App\Http\Controllers\Api\TripStaffController;

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'service' => 'EastBus API',
        'time' => now()->toIso8601String(),
    ]);
});

/*
|--------------------------------------------------------------------------
| Passenger API
|--------------------------------------------------------------------------
*/

Route::prefix('passenger')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentication - Public
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/register',
        [PassengerAuthController::class, 'register']
    );

    Route::post(
        '/verify-otp',
        [PassengerAuthController::class, 'verifyOtp']
    );

    Route::post(
        '/resend-otp',
        [PassengerAuthController::class, 'resendOtp']
    );

    Route::post(
        '/login',
        [PassengerAuthController::class, 'login']
    );

    /*
    |--------------------------------------------------------------------------
    | Password Reset - Public
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/forgot-password',
        [PassengerAuthController::class, 'forgotPassword']
    );

    Route::post(
        '/verify-reset-otp',
        [PassengerAuthController::class, 'verifyResetOtp']
    );

    Route::post(
        '/reset-password',
        [PassengerAuthController::class, 'resetPassword']
    );

    /*
    |--------------------------------------------------------------------------
    | Locations
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/locations',
        [PassengerBookingController::class, 'locations']
    );

    /*
    |--------------------------------------------------------------------------
    | Online Booking Trip Search
    |--------------------------------------------------------------------------
    |
    | Registered operator buses.
    |
    | Supports:
    |
    | - Starting location
    | - Destination
    | - Intermediate stops
    | - Travel date
    | - Minimum booking distance
    |
    */

    Route::get(
        '/trips/search',
        [PassengerBookingController::class, 'searchTrips']
    );

    Route::get(
        '/trips/{id}',
        [PassengerBookingController::class, 'tripDetails']
    );

    /*
    |--------------------------------------------------------------------------
    | Fixed Bus Timetables
    |--------------------------------------------------------------------------
    |
    | Admin-managed timetable information.
    |
    | These services do not support:
    |
    | - Online booking
    | - Payment
    | - QR tickets
    | - Live tracking
    |
    */

    Route::get(
        '/fixed-schedules/search',
        [FixedScheduleController::class, 'search']
    );

    Route::get(
        '/fixed-schedules/{id}',
        [FixedScheduleController::class, 'show']
    );

    /*
    |--------------------------------------------------------------------------
    | Passenger Protected API
    |--------------------------------------------------------------------------
    */

    Route::middleware('passenger.api')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Passenger Account
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/me',
            [PassengerAuthController::class, 'me']
        );

        Route::post(
            '/logout',
            [PassengerAuthController::class, 'logout']
        );

        Route::patch(
            '/profile',
            [PassengerProfileController::class, 'update']
        );

        /*
        |--------------------------------------------------------------------------
        | Passenger Bookings
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/bookings',
            [PassengerBookingController::class, 'bookings']
        );

        Route::post(
            '/bookings',
            [PassengerBookingController::class, 'createBooking']
        );

        Route::get(
            '/bookings/{id}',
            [PassengerBookingController::class, 'bookingDetails']
        );

        Route::post(
            '/bookings/{id}/cancel',
            [PassengerBookingController::class, 'cancelBooking']
        );

        /*
        |--------------------------------------------------------------------------
        | Seat Selection
        |--------------------------------------------------------------------------
        |
        | Rules:
        |
        | - Maximum 6 seats
        | - Gender selected for each seat
        | - Passenger details stored per seat
        | - Booking closes after trip starts
        |
        */

        Route::get(
            '/trips/{id}/seats',
            [PassengerBookingController::class, 'availableSeats']
        );

        /*
        |--------------------------------------------------------------------------
        | Payment
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/bookings/{id}/payment',
            [PassengerBookingController::class, 'payment']
        );

        /*
        |--------------------------------------------------------------------------
        | QR E-Ticket
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/bookings/{id}/ticket',
            [PassengerBookingController::class, 'ticket']
        );

        /*
        |--------------------------------------------------------------------------
        | Live Tracking
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/bookings/{id}/tracking',
            [PassengerBookingController::class, 'tracking']
        );

        /*
        |--------------------------------------------------------------------------
        | Passenger Notifications
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/notifications',
            [PassengerBookingController::class, 'notifications']
        );

        /*
        |--------------------------------------------------------------------------
        | Trip Feedback
        |--------------------------------------------------------------------------
        |
        | Complete flow:
        |
        | Staff ends trip
        |       ↓
        | Trip status = completed
        |       ↓
        | Passenger booking becomes feedback eligible
        |       ↓
        | GET /feedback/pending
        |       ↓
        | Passenger sees Feedback button
        |       ↓
        | Passenger submits ratings
        |       ↓
        | POST /feedback
        |       ↓
        | Feedback stored in trip_feedback
        |       ↓
        | Feedback contributes to recommendations
        |
        */

        Route::get(
            '/feedback/pending',
            [PassengerFeedbackController::class, 'pending']
        );

        Route::post(
            '/feedback',
            [PassengerFeedbackController::class, 'store']
        );

        /*
        |--------------------------------------------------------------------------
        | Personalized Bus Recommendations
        |--------------------------------------------------------------------------
        |
        | Recommendation engine can use:
        |
        | - Overall passenger ratings
        | - Passenger's own previous rating
        | - Route / journey history
        | - Punctuality rating
        | - Comfort rating
        | - Safety rating
        | - Cleanliness rating
        | - Staff rating
        | - Travel-again preference
        | - Number of reviews
        |
        | Result:
        |
        | Highest scoring buses are returned first.
        |
        */

        Route::get(
            '/recommendations',
            [PassengerRecommendationController::class, 'index']
        );

        /*
        |--------------------------------------------------------------------------
        | AI Assistant
        |--------------------------------------------------------------------------
        |
        | Supported languages:
        |
        | - English
        | - Tamil
        | - Sinhala
        |
        */

        Route::post(
            '/chat',
            [PassengerChatController::class, 'chat']
        );
    });
});

/*
|--------------------------------------------------------------------------
| Trip Management / Staff API
|--------------------------------------------------------------------------
*/

Route::prefix('staff')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Staff Authentication - Public
    |--------------------------------------------------------------------------
    */

    Route::post(
        '/login',
        [StaffAuthController::class, 'login']
    );

    /*
    |--------------------------------------------------------------------------
    | Staff Protected API
    |--------------------------------------------------------------------------
    */

    Route::middleware('staff.api')->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Staff Account
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/me',
            [StaffAuthController::class, 'me']
        );

        Route::post(
            '/logout',
            [StaffAuthController::class, 'logout']
        );

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/dashboard',
            [TripStaffController::class, 'dashboard']
        );

        /*
        |--------------------------------------------------------------------------
        | Assigned Trips
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/trips',
            [TripStaffController::class, 'trips']
        );

        /*
        |--------------------------------------------------------------------------
        | Start Trip
        |--------------------------------------------------------------------------
        |
        | Starting a trip:
        |
        | - Changes trip status to active
        | - Stores started_at
        | - Closes passenger booking
        | - Captures seat information
        | - Enables GPS tracking
        |
        */

        Route::post(
            '/trips/{id}/start',
            [TripStaffController::class, 'start']
        );

        /*
        |--------------------------------------------------------------------------
        | Live GPS Location
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/trips/{id}/location',
            [TripStaffController::class, 'location']
        );

        /*
        |--------------------------------------------------------------------------
        | Trip Passenger List
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/trips/{id}/passengers',
            [TripStaffController::class, 'passengers']
        );

        /*
        |--------------------------------------------------------------------------
        | Emergency Alert
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/trips/{id}/emergency',
            [TripStaffController::class, 'emergency']
        );

        /*
        |--------------------------------------------------------------------------
        | End Trip
        |--------------------------------------------------------------------------
        |
        | Ending a trip:
        |
        | - Changes trip status to completed
        | - Stores completed_at
        | - Stops active GPS tracking
        | - Makes completed bookings eligible for feedback
        |
        */

        Route::post(
            '/trips/{id}/end',
            [TripStaffController::class, 'end']
        );

        /*
        |--------------------------------------------------------------------------
        | Staff Notifications
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/notifications',
            [TripStaffController::class, 'notifications']
        );

        /*
        |--------------------------------------------------------------------------
        | QR Ticket Verification
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/tickets/verify',
            [TicketValidationController::class, 'verify']
        );

        /*
        |--------------------------------------------------------------------------
        | Passenger Check-In
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/tickets/check-in',
            [TicketValidationController::class, 'checkIn']
        );
    });
});