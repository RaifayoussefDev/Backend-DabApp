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
            font-family: 'Arial Black', 'Arial', sans-serif;
        }

        .plate {
            width: 600px;
            height: 280px;
            background: linear-gradient(to bottom, #f5f5f5 0%, #e8e8e8 100%);
            border: 8px solid #1a1a1a;
            border-radius: 30px;
            display: flex;
            position: relative;
            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.5),
                inset 0 2px 5px rgba(255, 255, 255, 0.3);
        }

        /* Section principale avec la grille */
        .main-section {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            gap: 0;
            padding: 15px;
        }

        /* Ligne horizontale centrale */
        .main-section::before {
            content: '';
            position: absolute;
            left: 15px;
            right: 105px;
            top: 50%;
            height: 5px;
            background: #1a1a1a;
            transform: translateY(-50%);
        }

        /* Ligne verticale centrale */
        .main-section::after {
            content: '';
            position: absolute;
            left: 50%;
            top: 15px;
            bottom: 15px;
            width: 5px;
            background: #1a1a1a;
            transform: translateX(-210%);
        }

        /* Cases de la grille */
        .cell {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 90px;
            font-weight: 900;
            color: #000;
            line-height: 1;
        }

        .cell-1 {
            grid-column: 1;
            grid-row: 1;
        }

        .cell-2 {
            grid-column: 2;
            grid-row: 1;
        }

        .cell-3 {
            grid-column: 1;
            grid-row: 2;
        }

        .cell-4 {
            grid-column: 2;
            grid-row: 2;
        }

        /* Texte arabe (direction RTL) */
        .arabic {
            direction: rtl;
        }

        /* Section droite avec logo et texte */
        .right-section {
            width: 90px;
            background: linear-gradient(to bottom, #f5f5f5 0%, #e8e8e8 100%);
            border-left: 5px solid #1a1a1a;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            padding: 20px 0;
        }

        .logo-container {
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }

        .logo {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .country-text-arabic {
            font-size: 16px;
            font-weight: bold;
            color: #000;
            direction: rtl;
            text-align: center;
            line-height: 1.2;
            margin-bottom: 15px;
        }

        .ksa-letters {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .ksa-letter {
            font-size: 32px;
            font-weight: 900;
            color: #000;
            line-height: 1;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="plate">
        <!-- Section principale avec 4 cases -->
        <div class="main-section">
            <div class="cell cell-1 arabic">{{ $cell1 }}</div>
            <div class="cell cell-2 arabic">{{ $cell2 }}</div>
            <div class="cell cell-3 arabic">{{ $cell3 }}</div>
            <div class="cell cell-4">{{ $cell4 }}</div>
        </div>

        <!-- Section droite -->
        <div class="right-section">
            <div class="logo-container">
                <img src="{{ $logoBase64 }}" alt="KSA Logo" class="logo">
            </div>

            <div class="country-text-arabic">السعودية</div>

            <div class="ksa-letters">
                <div class="ksa-letter">K</div>
                <div class="ksa-letter">S</div>
                <div class="ksa-letter">A</div>
            </div>
        </div>
    </div>
</body>

</html>
