<!DOCTYPE html>
<html>
<head>
    <title><?php echo e($type === 'reset' ? 'Password Reset' : 'Verification Code'); ?></title>
</head>
<body>
    <h1>Hello, <?php echo e($name); ?></h1>
    
    <?php if($type === 'reset'): ?>
        <p>You requested to reset your password for RoadQuest Rentals.</p>
        <p>Your password reset code is:</p>
    <?php else: ?>
        <p>Thank you for registering with RoadQuest Rentals.</p>
        <p>Your verification code is:</p>
    <?php endif; ?>

    <h2 style="color: #0d6efd; letter-spacing: 5px;"><?php echo e($otp); ?></h2>
    <p>This code will expire in 10 minutes.</p>
    <p>If you did not request this, please ignore this email.</p>
</body>
</html>
<?php /**PATH C:\Users\numbread\Desktop\ITEL4FINAL\backend\resources\views/emails/otp.blade.php ENDPATH**/ ?>