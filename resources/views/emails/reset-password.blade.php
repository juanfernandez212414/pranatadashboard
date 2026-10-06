{-- Template email yang dikirim untuk reset password. --}
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - PRANATA</title>
</head>

<body
    style="background-color: #f3f4f6; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 40px 20px; color: #374151;">

    <table width="100%" cellpadding="0" cellspacing="0" border="0"
        style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">

        <tr>
            <td
                style="background-color: #ffffff; padding: 35px 30px; text-align: center; border-bottom: 3px solid #2563eb;">
                <img src="{{ asset('images/LogoPRANATA.png') }}" alt="Logo PRANATA"
                    style="height: 65px; width: auto; display: block; margin: 0 auto 10px auto; object-contain: contain;">
                <h1 style="color: #1f2937; margin: 0; font-size: 22px; font-weight: 800; letter-spacing: 0.5px;">PRANATA
                </h1>
                <p style="color: #6b7280; margin: 4px 0 0 0; font-size: 13px; font-weight: 500;">Portal Otomatisasi
                    Narasi Statistik</p>
            </td>
        </tr>

        <tr>
            <td style="padding: 40px 30px; background-color: #ffffff;">
                <h2 style="margin-top: 0; font-size: 18px; color: #1f2937; font-weight: 700;">Halo!</h2>
                <p style="line-height: 1.6; margin-bottom: 30px; font-size: 15px; color: #4b5563;">
                    Anda menerima email ini karena kami menerima permintaan pembaruan kata sandi (*reset password*)
                    untuk akun Anda di sistem PRANATA BPS Kota Pematangsiantar.
                </p>

                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td align="center" style="padding-bottom: 30px;">
                            <a href="{{ url('reset-password/' . $token . '?email=' . $email) }}"
                                style="display: inline-block; background-color: #2563eb; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 15px; box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);">Reset
                                Password Sekarang</a>
                        </td>
                    </tr>
                </table>

                <p style="line-height: 1.6; margin-bottom: 20px; font-size: 14px; color: #6b7280;">
                    Tautan reset password ini akan kedaluwarsa dalam waktu <strong>60 menit</strong>. Jika Anda tidak
                    merasa melakukan permintaan ini, abaikan saja email ini dan tidak ada tindakan lebih lanjut yang
                    perlu dilakukan.
                </p>

                <hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 30px 0 20px 0;">

                <p style="line-height: 1.6; margin-bottom: 0; font-size: 14px; color: #4b5563;">
                    Salam hangat,<br>
                    <strong>Tim Admin PRANATA</strong>
                </p>
            </td>
        </tr>

        <tr>
            <td style="background-color: #f9fafb; padding: 25px 30px; border-top: 1px solid #e5e7eb;">
                <p style="margin: 0; font-size: 12px; color: #9ca3af; line-height: 1.5; word-break: break-all;">
                    Jika Anda mengalami kendala mengklik tombol "Reset Password" di atas, silakan salin dan tempel URL
                    di bawah ini ke peramban (*browser*) Anda:<br><br>
                    <a href="{{ url('reset-password/' . $token . '?email=' . $email) }}"
                        style="color: #2563eb; text-decoration: underline;">{{ url('reset-password/' . $token . '?email=' . $email) }}</a>
                </p>
            </td>
        </tr>

    </table>

</body>

</html>
