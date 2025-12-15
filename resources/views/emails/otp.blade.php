<!DOCTYPE html>
<html>
<head>
    <title>{{ $type === 'reset' ? 'Password Reset' : 'Verification Code' }}</title>
</head>
<body>
    <h1>Hello, {{ $name }}</h1>
    
    @if($type === 'reset')
        <p>You requested to reset your password for RoadQuest Rentals.</p>
        <p>Your password reset code is:</p>
    @else
        <p>Thank you for registering with RoadQuest Rentals.</p>
        <p>Your verification code is:</p>
    @endif

    <h2 style="color: #0d6efd; letter-spacing: 5px;">{{ $otp }}</h2>
    <p>This code will expire in 10 minutes.</p>
    
    @if($type === 'reset')
        <p>If you closed or refreshed the page, you can continue resetting your password by clicking the link below:</p>
        <p><a href="{{ $frontendUrl }}/reset-password.html?email={{ urlencode($email) }}" style="color: #0d6efd; text-decoration: none; font-weight: bold;">Continue Password Reset →</a></p>
    @else
        <p>If you closed or refreshed the page, you can continue verification by clicking the link below:</p>
        <p><a href="{{ $frontendUrl }}/verify.html?username={{ urlencode($username) }}" style="color: #0d6efd; text-decoration: none; font-weight: bold;">Continue Verification →</a></p>
    @endif
    
    <p>If you did not request this, please ignore this email.</p>
</body>
</html>
