<!DOCTYPE html>
<html lang="ar">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saudi Arabia License Plate</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #e0e0e0;
            /* ✅ POLICES SYSTEM GARANTIES */
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
        }

        .plate {
            width: 600px;
            height: 300px;
            background: #eff0eb;
            border: 6px solid #000;
            border-radius: 30px;
            display: flex;
            position: relative;
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.4);
            overflow: hidden;
        }

        /* Section principale avec grille 2x2 */
        .main-section {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* Ligne du haut */
        .top-row {
            flex: 1;
            display: flex;
            border-bottom: 4px solid #000;
        }

        /* Ligne du bas */
        .bottom-row {
            flex: 1;
            display: flex;
        }

        /* Cellules */
        .cell {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eff0eb;
        }

        .cell-top-left {
            border-right: 4px solid #000;
        }

        .cell-bottom-left {
            border-right: 4px solid #000;
        }

        /* Textes - ✅ POLICES BOLD GARANTIES */
        .arabic-number {
            font-size: 55px;
            font-weight: 900;
            color: #000;
            direction: rtl;
            line-height: 1;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            /* ✅ FALLBACK POUR ARABE */
            font-family: 'Traditional Arabic', 'Arabic Typesetting', 'Arial', sans-serif;
        }

        .latin-number {
            font-size: 55px;
            font-weight: 900;
            color: #000;
            line-height: 1;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            font-family: 'Helvetica Neue', 'Arial', sans-serif;
        }

        .latin-letters {
            font-size: 55px;
            font-weight: 900;
            color: #000;
            line-height: 1;
            letter-spacing: 8px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            font-family: 'Helvetica Neue', 'Arial', sans-serif;
        }

        /* Section bleue à droite */
        .blue-section {
            width: 80px;
            background: #eff0eb;
            border-left: 4px solid #000;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 12px 0 0 0;
            position: relative;
        }

        /* Logo container */
        .logo-container {
            width: 55px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
        }

        .logo {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* Texte السعودية */
        .saudi-text {
            font-size: 13px;
            font-weight: bold;
            color: black;
            direction: rtl;
            text-align: center;
            line-height: 1.2;
            margin-bottom: 5px;
            font-family: 'Traditional Arabic', 'Arabic Typesetting', 'Arial', sans-serif;
        }

        /* K S A lettres */
        .ksa-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            flex: 1;
            justify-content: center;
            margin-bottom: 25px;
        }

        .ksa-letter {
            font-size: 30px;
            font-weight: bold;
            color: black;
            line-height: 1;
            text-align: center;
            font-family: 'Helvetica Neue', 'Arial', sans-serif;
        }
        
        /* Conteneur pour les lettres arabes détachées */
        .arabic-char-container {
            display: flex;
            flex-direction: row-reverse; /* RTL natural flow */
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            gap: 5px; /* Espacement fixe comme demandé "ajouter un peut d'espace" */
        }
        
        .arabic-char {
            display: inline-block !important; /* Force isolation */
            width: 45px; /* Largeur fixe pour alignement "toujour fix" */
            text-align: center;
            white-space: nowrap;
        }
    </style>
</head>

<body>
    <div class="plate">
        <!-- Section principale avec grille 2x2 -->
        <div class="main-section">
            <!-- Ligne du haut -->
            <div class="top-row">
                <div class="cell cell-top-left">
                    <div class="arabic-number">{{ $topLeft ?? $top_left ?? '' }}</div>
                </div>
                <div class="cell">
                    <div class="arabic-number">
                        @php
                            $text = $topRight ?? $top_right ?? '';
                            // Use preg_split for unicode safety, ensuring we get individual characters
                            $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
                        @endphp
                        <div class="arabic-char-container">
                             @foreach($chars as $char)
                                <span class="arabic-char">{{ $char }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ligne du bas -->
            <div class="bottom-row">
                <div class="cell cell-bottom-left">
                    <div class="latin-number">{{ $bottomLeft ?? $bottom_left ?? '' }}</div>
                </div>
                <div class="cell">
                    <div class="latin-letters">{{ $bottomRight ?? $bottom_right ?? '' }}</div>
                </div>
            </div>
        </div>

        <!-- Section bleue -->
        <div class="blue-section">
            <div class="logo-container">
                <img src="{{ $logoBase64 ?? $logo_base64 ?? '' }}" alt="KSA Logo" class="logo">
            </div>

            <div class="saudi-text">السعودية</div>

            <div class="ksa-container">
                <div class="ksa-letter">K</div>
                <div class="ksa-letter">S</div>
                <div class="ksa-letter">A</div>
            </div>
        </div>
    </div>
</body>

</html>
