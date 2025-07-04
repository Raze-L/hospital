<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重置密码</title>
</head>
<body>
<p>您收到这封邮件是因为您请求重置您的账户密码。</p>
<p>请点击以下链接重置您的密码：</p>
<a href="{{ url('reset-password/' . $token) }}">重置密码</a>
<p>如果您没有请求重置密码，请忽略此邮件。</p>
</body>
</html>
