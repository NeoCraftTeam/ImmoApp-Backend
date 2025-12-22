<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email vérifié - KeyHome</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            text-align: center;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            max-width: 450px;
            width: 100%;
        }

        .logo {
            font-size: 28px;
            font-weight: 800;
            color: #F6475F;
            margin-bottom: 24px;
            display: inline-block;
            letter-spacing: -0.5px;
        }

        .icon-circle {
            width: 80px;
            height: 80px;
            background-color: #fff1f2;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .icon {
            font-size: 40px;
            color: #F6475F;
            font-weight: bold;
        }

        h1 {
            margin: 0 0 16px;
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
        }

        p {
            margin: 0 0 24px;
            color: #64748b;
            line-height: 1.6;
        }

        .btn {
            background-color: #F6475F;
            color: white !important;
            padding: 14px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: background-color 0.2s;
            border: none;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
        }

        .btn:hover {
            background-color: #D5384EFF;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="logo">KeyHome</div>

    <div class="icon-circle">
        <span class="icon">&#10003;</span>
    </div>

    <h1>Email vérifié avec succès !</h1>

    <p>Merci <strong>{{ $user->firstname }}</strong>, votre compte est maintenant sécurisé et actif. Vous pouvez accéder
        à votre espace.</p>

    <a href="{{ config('app.email_verify_callback', 'http://localhost:8000') }}" class="btn">
        Accéder à mon espace
    </a>
</div>
</body>
</html>
