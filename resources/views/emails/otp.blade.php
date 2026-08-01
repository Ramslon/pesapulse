<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Password Reset OTP</title>
</head>

<body style="margin:0;padding:0;background:#f5f7fa;font-family:Arial,sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;background:#f5f7fa;">
        <tr>
            <td align="center">

                <table width="600" cellpadding="0" cellspacing="0"
                    style="background:#ffffff;border-radius:12px;overflow:hidden;">

                    <tr>
                        <td
                            style="background:#16a34a;padding:24px;text-align:center;color:white;font-size:28px;font-weight:bold;">
                            PesaPulse
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:40px;">

                            <h2>Hello {{ $name }},</h2>

                            <p>
                                We received a request to reset your password.
                            </p>

                            <p>
                                Use the verification code below:
                            </p>

                            <div style="
                                margin:30px 0;
                                text-align:center;
                                font-size:42px;
                                letter-spacing:8px;
                                font-weight:bold;
                                color:#16a34a;
                            ">
                                {{ $otp }}
                            </div>

                            <p>
                                This verification code expires in
                                <strong>10 minutes</strong>.
                            </p>

                            <p>
                                If you didn't request a password reset,
                                simply ignore this email.
                            </p>

                            <hr>

                            <p style="font-size:13px;color:#777;">
                                PesaPulse Security Team
                            </p>

                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>

</html>