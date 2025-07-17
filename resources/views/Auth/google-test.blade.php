<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>DabApp - Authentification</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .auth-container {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="tel"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        button {
            background: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }

        button:hover {
            background: #0056b3;
        }

        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .google-btn {
            background: #db4437;
        }

        .google-btn:hover {
            background: #c23321;
        }

        #result {
            margin-top: 20px;
            background: #f4f4f4;
            padding: 10px;
            border-radius: 5px;
            white-space: pre-wrap;
        }

        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .success {
            color: #155724;
            background: #d4edda;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .auth-tabs {
            display: flex;
            margin-bottom: 20px;
        }

        .auth-tab {
            flex: 1;
            padding: 10px;
            background: #e9ecef;
            border: none;
            cursor: pointer;
            border-radius: 5px 5px 0 0;
            margin-right: 2px;
        }

        .auth-tab.active {
            background: #007bff;
            color: white;
        }

        .auth-form {
            display: none;
        }

        .auth-form.active {
            display: block;
        }

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
    </style>
</head>

<body>
    <?php
    $country = $_SERVER['HTTP_X_FORWARDED_COUNTRY'] ?? 'Unknown';
    $continent = $_SERVER['HTTP_X_FORWARDED_CONTINENT'] ?? 'Unknown';
    echo $country . "-" . $continent;
    ?>

    <h1>🔐 DabApp - Authentification</h1>

    <div class="auth-container">
        <!-- Onglets de navigation -->
        <div class="auth-tabs">
            <button class="auth-tab active" onclick="showAuthForm('phone')">Connexion par téléphone</button>
            <button class="auth-tab" onclick="showAuthForm('google')">Connexion Google</button>
        </div>

        <!-- Formulaire de connexion par téléphone -->
        <div id="phone-auth" class="auth-form active">
            <h2>📱 Connexion par téléphone</h2>
            <div class="form-group">
                <label for="phoneNumber">Numéro de téléphone :</label>
                <input type="tel" id="phoneNumber" placeholder="+212612345678" value="+212">
            </div>
            <div class="form-group">
                <label for="password">Mot de passe :</label>
                <input type="password" id="password" placeholder="Votre mot de passe">
            </div>
            <button onclick="loginWithPhone()">Se connecter</button>
        </div>

        <!-- Formulaire de connexion Google -->
        <div id="google-auth" class="auth-form">
            <h2>🔍 Connexion Google</h2>
            <p>Cliquez sur le bouton ci-dessous pour vous connecter avec votre compte Google.</p>
            <button onclick="signInWithGoogle()" class="google-btn">Connexion Google</button>
        </div>
    </div>

    <pre id="result"></pre>

    <!-- Firebase SDK -->
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-auth-compat.js"></script>

    <script>
        const firebaseConfig = {
            apiKey: "AIzaSyCPGsHiy6Eq2J8bnHi2xo9rx-1nIXM-p-o",
            authDomain: "dabapp-3d853.firebaseapp.com",
            projectId: "dabapp-3d853",
            storageBucket: "dabapp-3d853.firebasestorage.app",
            messagingSenderId: "988124060172",
            appId: "1:988124060172:web:6a7b2aeb937a44fa196c29",
            measurementId: "G-RELFGL4QX8"
        };

        firebase.initializeApp(firebaseConfig);

        // Fonction pour changer d'onglet
        function showAuthForm(formType) {
            // Masquer tous les formulaires
            document.querySelectorAll('.auth-form').forEach(form => {
                form.classList.remove('active');
            });
            
            // Masquer tous les onglets actifs
            document.querySelectorAll('.auth-tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Afficher le formulaire sélectionné
            document.getElementById(formType + '-auth').classList.add('active');
            event.target.classList.add('active');
        }

        // Connexion avec téléphone et mot de passe
        async function loginWithPhone() {
            const phoneNumber = document.getElementById('phoneNumber').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!phoneNumber || phoneNumber.length < 10) {
                showError('Veuillez saisir un numéro de téléphone valide');
                return;
            }

            if (!password) {
                showError('Veuillez saisir votre mot de passe');
                return;
            }

            try {
                showSuccess('Connexion en cours...');

                const response = await fetch('/api/phone-login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        phone: phoneNumber,
                        password: password
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    showSuccess('Connexion réussie !');
                    document.getElementById("result").innerText = JSON.stringify(data, null, 2);
                    
                    // Optionnel : rediriger vers le dashboard
                    // window.location.href = '/dashboard';
                } else {
                    showError(data.error || 'Erreur de connexion');
                }

            } catch (error) {
                console.error('Erreur connexion:', error);
                showError('Erreur lors de la connexion: ' + error.message);
            }
        }

        // Connexion avec Google (inchangée)
        async function signInWithGoogle() {
            const provider = new firebase.auth.GoogleAuthProvider();

            try {
                showSuccess('Connexion Google en cours...');
                
                const result = await firebase.auth().signInWithPopup(provider);
                const idToken = await result.user.getIdToken();

                await loginWithFirebase(idToken);

            } catch (error) {
                console.error("Erreur Firebase:", error);
                showError("Erreur Google login: " + error.message);
            }
        }

        // Fonction pour la connexion Firebase (pour Google)
        async function loginWithFirebase(idToken) {
            try {
                const response = await fetch('/api/firebase-login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + idToken,
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        idToken
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    showSuccess('Connexion réussie !');
                    document.getElementById("result").innerText = JSON.stringify(data, null, 2);
                } else {
                    showError('Erreur de connexion: ' + data.error);
                }

            } catch (error) {
                console.error('Erreur connexion:', error);
                showError('Erreur lors de la connexion: ' + error.message);
            }
        }

        // Fonctions utilitaires
        function showError(message) {
            const existingError = document.querySelector('.error');
            if (existingError) existingError.remove();

            const errorDiv = document.createElement('div');
            errorDiv.className = 'error';
            errorDiv.textContent = message;
            document.querySelector('.auth-container').appendChild(errorDiv);

            // Supprimer le message après 5 secondes
            setTimeout(() => {
                if (errorDiv.parentNode) {
                    errorDiv.parentNode.removeChild(errorDiv);
                }
            }, 5000);
        }

        function showSuccess(message) {
            const existingSuccess = document.querySelector('.success');
            if (existingSuccess) existingSuccess.remove();

            const successDiv = document.createElement('div');
            successDiv.className = 'success';
            successDiv.textContent = message;
            document.querySelector('.auth-container').appendChild(successDiv);

            // Supprimer le message après 3 secondes
            setTimeout(() => {
                if (successDiv.parentNode) {
                    successDiv.parentNode.removeChild(successDiv);
                }
            }, 3000);
        }
    </script>

</body>

</html>