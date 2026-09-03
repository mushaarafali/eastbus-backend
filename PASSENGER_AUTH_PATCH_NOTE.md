# PassengerAuthController safety patch

This bundle does NOT overwrite your current PassengerAuthController because your current
email OTP, reset OTP, SMTP and existing passenger token flow are already working.

Confirm these two rules in your current controller:

## Registration phone
```php
'phone' => [
    'required',
    'string',
    'regex:/^\+94[0-9]{9}$/',
],
```

## Login
Before issuing a passenger token, reject an account whose email is not verified.

If your project uses `email_verified_at`:
```php
if (empty($user->email_verified_at)) {
    return response()->json([
        'success' => false,
        'message' => 'Please verify your email before login.',
    ], 403);
}
```

If your current controller uses a different verified field, keep its existing implementation.
Do not change a working OTP schema just to match this note.
