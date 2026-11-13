<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $email_subject ?? "" }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f4f6; font-family: 'Segoe UI', Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased;">
    <div style="padding: 32px 16px;">
        <div style="max-width: 600px; width: 100%; margin: 0 auto; padding: 36px 32px; background-color: #ffffff; border: 1px solid #ececf3; border-radius: 12px; box-sizing: border-box;">
            {!! \App\Models\Admin\EmailTemplate::embedImages($email_body, $message ?? null) !!}
        </div>
    </div>
</body>
</html>
