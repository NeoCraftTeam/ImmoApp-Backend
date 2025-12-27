<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte Activé - KeyHome</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #F6475F;
            --primary-hover: #D5384E;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .container {
            max-width: 480px;
            width: 90%;
            background: var(--card-bg);
            padding: 48px 32px;
            border-radius: 20px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            text-align: center;
        }

        .icon-circle {
            width: 80px;
            height: 80px;
            background-color: #f0fdf4;
            color: #16a34a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 40px;
        }

        h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -0.025em;
        }

        p {
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .btn {
            display: inline-block;
            background-color: var(--primary);
            color: #ffffff;
            padding: 14px 32px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(246, 71, 95, 0.2);
        }

        .btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(246, 71, 95, 0.3);
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 40px;
            display: block;
        }
    </style>
</head>

<body>
    <div class="container">
        <span class="logo">KeyHome</span>

        <div class="icon-circle">✓</div>

        <h1>Compte activé avec succès !</h1>
        <p>Merci d'avoir vérifié votre adresse email. Votre compte est maintenant pleinement opérationnel.</p>

        <a href="{{ $loginUrl }}" class="btn">Accéder à mon espace</a>
    </div>
</body>

</html>
