<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>DabApp - Authentification par téléphone</title>
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-auth-compat.js"></script>
    <script src="https://www.google.com/recaptcha/api.js?render=explicit" async defer></script>

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
        input[type="tel"] {
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

        .step {
            display: none;
        }

        .step.active {
            display: block;
        }

        #recaptcha-container {
            margin: 20px 0;
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
        <!-- Étape 1: Saisie du numéro de téléphone -->
        <div id="step1" class="step active">
            <h2>📱 Connexion par téléphone</h2>
            <div class="form-group">
                <label for="phoneNumber">Numéro de téléphone :</label>
                <input type="tel" id="phoneNumber" placeholder="+212612345678" value="+212">
            </div>
            <button onclick="sendOTP()">Envoyer le code SMS</button>
            <button onclick="signInWithGoogle()" class="google-btn">Connexion Google</button>
        </div>

        <!-- Étape 2: Vérification du code OTP -->
        <div id="step2" class="step">
            <h2>🔢 Vérification du code</h2>
            <div class="form-group">
                <label for="verificationCode">Code de vérification :</label>
                <input type="text" id="verificationCode" placeholder="123456" maxlength="6">
            </div>
            <button onclick="verifyOTP()">Vérifier le code</button>
            <button onclick="goBack()">Retour</button>
        </div>

        <!-- Étape 3: Informations utilisateur -->
        <div id="step3" class="step">
            <h2>👤 Informations utilisateur</h2>
            <div class="form-group">
                <label for="firstName">Prénom :</label>
                <input type="text" id="firstName" placeholder="Votre prénom">
            </div>
            <div class="form-group">
                <label for="lastName">Nom :</label>
                <input type="text" id="lastName" placeholder="Votre nom">
            </div>
            <button onclick="completePhoneAuth()">Finaliser l'inscription</button>
        </div>

        <!-- Container pour reCAPTCHA -->
        <div id="recaptcha-container"></div>
    </div>

    <pre id="result"></pre>

    <script>
        const firebaseConfig = {
            apiKey: "AIzaSyCPGsHiy6Eq2J8bnHi2xo9rx-1nIXM-p-o",
            authDomain: "dabapp-3d853.firebaseapp.com",
            projectId: "dabapp-3d853",
            storageBucket: "dabapp-3d853.appspot.com",
            messagingSenderId: "988124060172",
            appId: "1:988124060172:web:xxxxxxxxxxxxxx"
        };

        firebase.initializeApp(firebaseConfig);

        let confirmationResult;
        let recaptchaVerifier;
        let recaptchaInitialized = false;

        function initRecaptcha() {
            if (!recaptchaInitialized) {
                recaptchaVerifier = new firebase.auth.RecaptchaVerifier('recaptcha-container', {
                    'size': 'invisible',
                    'callback': (response) => {
                        console.log('reCAPTCHA résolu', response);
                    },
                    'expired-callback': () => {
                        console.warn('reCAPTCHA expiré');
                        recaptchaVerifier.clear();
                        recaptchaInitialized = false;
                        initRecaptcha();
                    },
                    'error-callback': (err) => {
                        console.error('Erreur reCAPTCHA:', err);
                    }
                });

                recaptchaVerifier.render().then((widgetId) => {
                    window.recaptchaWidgetId = widgetId;
                    recaptchaInitialized = true;
                    console.log('reCAPTCHA initialisé avec widgetId:', widgetId);
                });
            }
        }

        async function sendOTP() {
            const phoneNumber = document.getElementById('phoneNumber').value.trim();

            if (!phoneNumber || phoneNumber.length < 10) {
                showError('Veuillez saisir un numéro de téléphone valide');
                return;
            }

            try {
                if (!recaptchaInitialized) {
                    initRecaptcha();
                    await new Promise(res => setTimeout(res, 1000));
                } else if (typeof grecaptcha !== 'undefined' && window.recaptchaWidgetId !== undefined) {
                    grecaptcha.reset(window.recaptchaWidgetId);
                }

                showSuccess('Envoi du code SMS en cours...');

                confirmationResult = await firebase.auth().signInWithPhoneNumber(phoneNumber, recaptchaVerifier);

                showSuccess('Code SMS envoyé avec succès !');
                showStep('step2');

            } catch (error) {
                console.error('Erreur envoi SMS:', error);
                let message = 'Erreur lors de l\'envoi du SMS: ';

                if (error.code === 'auth/invalid-app-credential') {
                    message += 'Le token reCAPTCHA est invalide ou expiré. Veuillez recharger la page.';
                } else if (error.code === 'auth/too-many-requests') {
                    message += 'Trop de tentatives. Veuillez patienter un moment.';
                } else {
                    message += error.message;
                }

                showError(message);

                if (recaptchaVerifier) {
                    recaptchaVerifier.clear();
                    recaptchaInitialized = false;
                }
            }
        }

        async function verifyOTP() {
            const code = document.getElementById('verificationCode').value.trim();

            if (code.length !== 6) {
                showError('Veuillez saisir un code à 6 chiffres');
                return;
            }

            try {
                const result = await confirmationResult.confirm(code);
                const user = result.user;

                const idToken = await user.getIdToken();
                const backendResponse = await checkUserExists(idToken);

                if (backendResponse.userExists) {
                    await loginUser(idToken);
                } else {
                    showStep('step3');
                }

            } catch (error) {
                console.error('Erreur vérification:', error);
                showError('Code incorrect. Veuillez réessayer.');
            }
        }

        async function completePhoneAuth() {
            const firstName = document.getElementById('firstName').value.trim();
            const lastName = document.getElementById('lastName').value.trim();

            if (!firstName || !lastName) {
                showError('Veuillez remplir tous les champs');
                return;
            }

            try {
                const user = firebase.auth().currentUser;
                const idToken = await user.getIdToken();

                const response = await fetch('/api/firebase-phone-login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + idToken
                    },
                    body: JSON.stringify({
                        idToken,
                        firstName,
                        lastName,
                        phoneNumber: user.phoneNumber
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    showSuccess('Inscription réussie !');
                    document.getElementById("result").innerText = JSON.stringify(data, null, 2);
                } else {
                    showError('Erreur: ' + data.error);
                }

            } catch (error) {
                console.error('Erreur finalisation:', error);
                showError('Erreur lors de la finalisation: ' + error.message);
            }
        }

        async function signInWithGoogle() {
            const provider = new firebase.auth.GoogleAuthProvider();

            try {
                const result = await firebase.auth().signInWithPopup(provider);
                const idToken = await result.user.getIdToken();

                await loginUser(idToken);

            } catch (error) {
                console.error("Erreur Firebase:", error);
                showError("Erreur Google login: " + error.message);
            }
        }

        async function loginUser(idToken) {
            try {
                const response = await fetch('/api/firebase-login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + idToken
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

        async function checkUserExists(idToken) {
            try {
                const response = await fetch('/api/check-user-exists', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + idToken
                    },
                    body: JSON.stringify({
                        idToken
                    })
                });

                return await response.json();
            } catch (error) {
                console.error('Erreur vérification utilisateur:', error);
                return {
                    userExists: false
                };
            }
        }

        function showStep(stepId) {
            document.querySelectorAll('.step').forEach(step => step.classList.remove('active'));
            document.getElementById(stepId).classList.add('active');
        }

        function showError(message) {
            const existingError = document.querySelector('.error');
            if (existingError) existingError.remove();

            const errorDiv = document.createElement('div');
            errorDiv.className = 'error';
            errorDiv.textContent = message;
            document.querySelector('.auth-container').appendChild(errorDiv);
        }

        function showSuccess(message) {
            const existingSuccess = document.querySelector('.success');
            if (existingSuccess) existingSuccess.remove();

            const successDiv = document.createElement('div');
            successDiv.className = 'success';
            successDiv.textContent = message;
            document.querySelector('.auth-container').appendChild(successDiv);
        }

        window.addEventListener('load', initRecaptcha);
    </script>

</body>

</html>
