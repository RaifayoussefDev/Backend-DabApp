<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>DabApp - Authentification</title>
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-auth-compat.js"></script>
    <script src="https://www.google.com/recaptcha/api.js?render=explicit" async defer></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .auth-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 20px 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        input[type="text"],
        input[type="tel"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }

        input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }

        button {
            background: #007bff;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin-bottom: 10px;
        }

        button:hover {
            background: #0056b3;
        }

        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .secondary-btn {
            background: #6c757d;
        }

        .secondary-btn:hover {
            background: #545b62;
        }

        #result {
            margin-top: 20px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 14px;
        }

        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }

        .success {
            color: #155724;
            background: #d4edda;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #c3e6cb;
        }

        .info {
            color: #0c5460;
            background: #d1ecf1;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
            border: 1px solid #bee5eb;
        }

        .step {
            display: none;
        }

        .step.active {
            display: block;
        }

        .step-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .step-header h2 {
            color: #333;
            margin-bottom: 10px;
        }

        .step-header p {
            color: #666;
            font-size: 14px;
        }

        #recaptcha-container {
            margin: 20px 0;
            min-height: 78px;
        }

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .phone-input {
            position: relative;
        }

        .phone-input input {
            padding-left: 50px;
        }

        .phone-input::before {
            content: "🇲🇦";
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            z-index: 1;
        }

        .user-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }

        .user-info h3 {
            margin: 0 0 10px 0;
            color: #1976d2;
        }
    </style>
</head>

<body>
    <div class="auth-container">
        <div class="step-header">
            <h1>🔐 DabApp</h1>
            <p>Authentification sécurisée</p>
        </div>

        <!-- Étape 1: Authentification avec numéro et mot de passe -->
        <div id="step1" class="step active">
            <div class="step-header">
                <h2>📱 Connexion</h2>
                <p>Saisissez votre numéro de téléphone et mot de passe</p>
            </div>

            <div class="form-group">
                <label for="phoneNumber">Numéro de téléphone :</label>
                <div class="phone-input">
                    <input type="tel" id="phoneNumber" placeholder="612345678" value="">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe :</label>
                <input type="password" id="password" placeholder="Votre mot de passe">
            </div>

            <button id="loginBtn" onclick="loginWithPassword()">Se connecter</button>
        </div>

        <!-- Étape 2: Vérification OTP (méthode classique) -->
        <div id="step2" class="step">
            <div class="step-header">
                <h2>🔢 Vérification OTP</h2>
                <p>Saisissez le code à 4 chiffres reçu par SMS</p>
            </div>

            <div class="user-info" id="userInfo"></div>

            <div class="form-group">
                <label for="otpCode">Code OTP :</label>
                <input type="text" id="otpCode" placeholder="1234" maxlength="4">
            </div>

            <button onclick="verifyOTP()">Vérifier le code</button>
            <button onclick="goBack()" class="secondary-btn">Retour</button>
        </div>

        <!-- Étape 3: Vérification Firebase OTP (SMS) -->
        <div id="step3" class="step">
            <div class="step-header">
                <h2>📲 Vérification SMS</h2>
                <p>Nous allons vous envoyer un code de vérification par SMS</p>
            </div>

            <div class="user-info" id="userInfoFirebase"></div>

            <div class="info">
                ℹ️ Un code de vérification sera envoyé à votre numéro de téléphone
            </div>

            <button onclick="sendFirebaseOTP()">Envoyer le code SMS</button>
            <button onclick="goBack()" class="secondary-btn">Retour</button>
        </div>

        <!-- Étape 4: Vérification code Firebase -->
        <div id="step4" class="step">
            <div class="step-header">
                <h2>🔢 Code SMS</h2>
                <p>Saisissez le code reçu par SMS</p>
            </div>

            <div class="form-group">
                <label for="firebaseCode">Code SMS :</label>
                <input type="text" id="firebaseCode" placeholder="123456" maxlength="6">
            </div>

            <button onclick="verifyFirebaseOTP()">Vérifier le code</button>
            <button onclick="goToStep('step3')" class="secondary-btn">Retour</button>
        </div>

        <!-- Container pour reCAPTCHA -->
        <div id="recaptcha-container"></div>
    </div>

    <pre id="result"></pre>

    <script>
        // Configuration Firebase
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

        let currentUser = null;
        let confirmationResult = null;
        let recaptchaVerifier = null;

        // Authentification avec numéro et mot de passe
        async function loginWithPassword() {
            const phone = document.getElementById('phoneNumber').value.trim();
            const password = document.getElementById('password').value.trim();
            const loginBtn = document.getElementById('loginBtn');

            if (!phone || !password) {
                showError('Veuillez remplir tous les champs');
                return;
            }

            // Ajouter le préfixe +212 si nécessaire
            const formattedPhone = phone.startsWith('+212') ? phone : '+212' + phone;

            loginBtn.disabled = true;
            loginBtn.textContent = 'Connexion...';

            try {
                const response = await fetch('/api/login-phone-password', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        phone: formattedPhone,
                        password: password
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    if (data.requiresOTP) {
                        // OTP classique requis
                        currentUser = data;
                        showUserInfo(data);
                        goToStep('step2');
                    } else {
                        // Connexion directe
                        showSuccess('Connexion réussie !');
                        document.getElementById("result").innerText = JSON.stringify(data, null, 2);
                    }
                } else {
                    showError(data.error);
                }

            } catch (error) {
                console.error('Erreur login:', error);
                showError('Erreur de connexion: ' + error.message);
            } finally {
                loginBtn.disabled = false;
                loginBtn.textContent = 'Se connecter';
            }
        }

        // Vérification OTP classique
        async function verifyOTP() {
            const otpCode = document.getElementById('otpCode').value.trim();

            if (!otpCode || otpCode.length !== 4) {
                showError('Veuillez saisir un code à 4 chiffres');
                return;
            }

            if (!currentUser) {
                showError('Session expirée. Veuillez vous reconnecter.');
                goToStep('step1');
                return;
            }

            try {
                const response = await fetch('/api/verify-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: currentUser.user_id,
                        otp: otpCode
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    showSuccess('Authentification réussie !');
                    document.getElementById("result").innerText = JSON.stringify(data, null, 2);
                } else {
                    showError(data.error);
                }

            } catch (error) {
                console.error('Erreur vérification OTP:', error);
                showError('Erreur de vérification: ' + error.message);
            }
        }

        // Envoi OTP Firebase
        async function sendFirebaseOTP() {
            if (!currentUser) {
                showError('Session expirée. Veuillez vous reconnecter.');
                goToStep('step1');
                return;
            }

            try {
                // Initialiser reCAPTCHA
                await initRecaptcha();

                showSuccess('Envoi du code SMS...');

                // Envoyer SMS via Firebase
                confirmationResult = await firebase.auth().signInWithPhoneNumber(currentUser.phone, recaptchaVerifier);

                showSuccess('Code SMS envoyé !');
                goToStep('step4');

            } catch (error) {
                console.error('Erreur envoi SMS:', error);
                showError('Erreur envoi SMS: ' + error.message);
            }
        }

        // Vérification code Firebase
        async function verifyFirebaseOTP() {
            const code = document.getElementById('firebaseCode').value.trim();

            if (!code || code.length !== 6) {
                showError('Veuillez saisir un code à 6 chiffres');
                return;
            }

            if (!confirmationResult) {
                showError('Veuillez d\'abord demander un code SMS');
                return;
            }

            try {
                const result = await confirmationResult.confirm(code);
                const idToken = await result.user.getIdToken();

                // Finaliser l'authentification
                const response = await fetch('/api/complete-firebase-auth', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: currentUser.user_id,
                        idToken: idToken
                    })
                });

                const data = await response.json();

                if (response.ok) {
                    showSuccess('Authentification réussie !');
                    document.getElementById("result").innerText = JSON.stringify(data, null, 2);
                } else {
                    showError(data.error);
                }

            } catch (error) {
                console.error('Erreur vérification Firebase:', error);
                showError('Code incorrect ou expiré');
            }
        }

        // Initialisation reCAPTCHA
        function initRecaptcha() {
            return new Promise((resolve, reject) => {
                if (recaptchaVerifier) {
                    resolve();
                    return;
                }

                try {
                    const container = document.getElementById('recaptcha-container');
                    container.innerHTML = '';

                    recaptchaVerifier = new firebase.auth.RecaptchaVerifier('recaptcha-container', {
                        'size': 'normal',
                        'callback': resolve,
                        'expired-callback': () => {
                            showError('reCAPTCHA expiré. Veuillez recharger la page.');
                            reject(new Error('reCAPTCHA expiré'));
                        }
                    });

                    recaptchaVerifier.render().then(resolve).catch(reject);

                } catch (error) {
                    reject(error);
                }
            });
        }

        // Afficher les informations utilisateur
        function showUserInfo(user) {
            const userInfoHtml = `
                <h3>👤 ${user.first_name} ${user.last_name}</h3>
                <p><strong>Téléphone:</strong> ${user.phone}</p>
                <p><strong>Email:</strong> ${user.email || 'Non renseigné'}</p>
            `;
            document.getElementById('userInfo').innerHTML = userInfoHtml;
            document.getElementById('userInfoFirebase').innerHTML = userInfoHtml;
        }

        // Navigation
        function goToStep(stepId) {
            document.querySelectorAll('.step').forEach(step => step.classList.remove('active'));
            document.getElementById(stepId).classList.add('active');
        }

        function goBack() {
            goToStep('step1');
            currentUser = null;
            confirmationResult = null;
            recaptchaVerifier = null;
        }

        // Messages
        function showError(message) {
            clearMessages();
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error';
            errorDiv.textContent = message;
            document.querySelector('.auth-container').appendChild(errorDiv);
        }

        function showSuccess(message) {
            clearMessages();
            const successDiv = document.createElement('div');
            successDiv.className = 'success';
            successDiv.textContent = message;
            document.querySelector('.auth-container').appendChild(successDiv);
        }

        function clearMessages() {
            document.querySelectorAll('.error, .success, .info').forEach(el => {
                if (!el.classList.contains('info') || el.textContent.includes('ℹ️')) {
                    if (!el.textContent.includes('ℹ️')) {
                        el.remove();
                    }
                }
            });
        }

        // Permettre l'envoi du formulaire avec Entrée
        document.getElementById('phoneNumber').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') loginWithPassword();
        });

        document.getElementById('password').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') loginWithPassword();
        });

        document.getElementById('otpCode').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') verifyOTP();
        });

        document.getElementById('firebaseCode').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') verifyFirebaseOTP();
        });
    </script>
</body>

</html>
