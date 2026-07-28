<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>PesaPulse OTP</title>
</head>

<body style="background:#f4f6f9;
             font-family:Arial,sans-serif;
             padding:30px;">

<div style="
    max-width:600px;
    margin:auto;
    background:#ffffff;
    border-radius:16px;
    padding:40px;
    box-shadow:0 4px 18px rgba(0,0,0,.08);
">

    <h2 style="color:#2E7D32;">
        🔐 Password Reset
    </h2>

    <p>Hello {{ $name }},</p>

    <p>
        We received a request to reset your
        <strong>PesaPulse</strong> password.
    </p>

    <p>
        Use the OTP below to continue.
    </p>

    <div
        style="
        text-align:center;
        margin:35px 0;
        letter-spacing:8px;
        font-size:34px;
        font-weight:bold;
        color:#2E7D32;
    ">
        {{ $otp }}
    </div>

    <p>
        This OTP will expire in
        <strong>10 minutes</strong>.
    </p>

    <p>
        If you didn't request a password reset,
        you can safely ignore this email.
    </p>

    <hr>

    <p
        style="
        color:#777;
        font-size:13px;
        margin-top:20px;
    ">
        PesaPulse Team
    </p>

</div>

</body>

</html>