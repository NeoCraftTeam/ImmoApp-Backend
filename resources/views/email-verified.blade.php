{{-- resources/views/email-verified.blade.php --}}
    <!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email vérifié</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 400px;
        }
        .success-icon {
            font-size: 3rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="success-icon">✅</div>

    {{-- Afficher le message --}}
    <h1>{{ $message }}</h1>

    {{-- Afficher des infos sur l'utilisateur --}}
    <p>Bonjour <strong>{{ $user->name }}</strong> !</p>
    <p>Votre adresse email <strong>{{ $user->email }}</strong> a été confirmée.</p>
    <p>Vous pouvez maintenant accéder à toutes les fonctionnalités de l'application.</p>

    <button class="btn" onclick="returnToApp()">
        Retourner à l'application
    </button>
</div>

<script>
    function returnToApp() {
        // Option 1: Simple redirection vers votre app Vue.js
        window.location.href = '{{ config("app.frontend_url") }}';

        // Option 2: Si vous voulez passer des données via postMessage (pour une popup)
        // if (window.opener) {
        //     window.opener.postMessage({
        //         type: 'email-verified',
        //         verified: true
        //     }, '{{ config("app.frontend_url") }}');
        //     window.close();
        // }
    }
</script>
</body>
</html>
