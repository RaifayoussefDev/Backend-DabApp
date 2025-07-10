<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Test Google Firebase Login</title>
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-auth-compat.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>
    <h2>Connexion avec Google via Firebase</h2>
    <button onclick="signInWithGoogle()">Connexion Google</button>
<?php
echo date('Y-m-d H:i:s');
echo "<br>";
;?>
    <pre id="result" style="margin-top:20px; background:#f4f4f4; padding:10px;"></pre>

    <script>
        const firebaseConfig = {
            apiKey: "AIzaSyCPGsHiy6Eq2J8bnHi2xo9rx-1nIXM-p-o",
            authDomain: "dabapp-3d853.firebaseapp.com",
            projectId: "dabapp-3d853",
            storageBucket: "dabapp-3d853.appspot.com",
            messagingSenderId: "988124060172",
            appId: "1:988124060172:web:xxxxxxxxxxxxxx" // ⚠️ Remplace-le par le bon appId complet
        };

        firebase.initializeApp(firebaseConfig);

        async function signInWithGoogle() {
            const provider = new firebase.auth.GoogleAuthProvider();

            try {
                const result = await firebase.auth().signInWithPopup(provider);
                const idToken = await result.user.getIdToken();

                // Appel backend Laravel avec le idToken
                const response = await fetch('/api/firebase-login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + idToken
                    },
                    body: JSON.stringify({ idToken })
                });

                const data = await response.json();
                document.getElementById("result").innerText = JSON.stringify(data, null, 2);

            } catch (error) {
                console.error("Erreur Firebase:", error);
                document.getElementById("result").innerText = "Erreur Google login: " + error.message;
            }
        }
    </script>
</body>
</html>
