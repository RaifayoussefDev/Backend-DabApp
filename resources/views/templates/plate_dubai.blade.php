<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dubai Motorcycle License Plate</title>
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
            background: #d0d0d0;
            font-family: 'Arial', 'Helvetica', sans-serif;
        }

        .plate {
            width: 600px;
            height: 300px;
            background: white;
            border: 4px solid #000;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            position: relative;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }

        /* Header avec catégorie et logo - PAS DE BORDER-BOTTOM */
        .header {
            height: 80px;
            background: white;
            display: flex;
            align-items: center;
            padding: 0 20px;
            position: relative;
        }

        .category-box {
            background: white;
            border-radius: 4px;
            padding: 5px 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .category-number {
            font-size: 70px;
            font-weight: 900;
            color: #000;
            line-height: 1;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.3);
        }

        /* Logo moto en haut à droite */
        .logo-top {
            position: absolute;
            top: 15px;
            right: 20px;
            width: auto;
            height: 130px;
        }

        .logo-top img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* Section principale avec texte DUBAI et numéros */
        .main-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background: white;
        }

        /* Texte DUBAI à gauche */
        .dubai-text-container {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 100px;
        }

        .dubai-arabic {
            font-size: 24px;
            font-weight: bold;
            color: #000;
            direction: rtl;
            text-align: center;
        }

        .dubai-english {
            font-size: 20px;
            font-weight: bold;
            color: #000;
            text-align: center;
            letter-spacing: 2px;
        }

        /* Numéros de plaque */
        .plate-numbers {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .plate-number {
            font-size: 120px;
            font-weight: 900;
            color: #000;
            line-height: 1;
            letter-spacing: 5px;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.3);
        }
        
        }
    </style>
</head>

<body>
    <div class="plate">
        <!-- Header avec numéro de catégorie et logo moto -->
        <div class="header">
            <div class="category-box">
                <div class="category-number">{{ $categoryNumber }}</div>
            </div>

            <!-- Logo moto en haut à droite -->
            <div class="logo-top">
                <img src="{{ $motoLogoBase64 }}" alt="Motorcycle Logo">
            </div>
        </div>

        <!-- Section principale -->
        <div class="main-section">
            <div class="dubai-text-container">
                <div class="dubai-arabic">{{ $cityNameAr ?? 'دبي' }}</div>
                <div class="dubai-english">{{ strtoupper($cityNameEn ?? 'DUBAI') }}</div>
            </div>

            <!-- Numéros de plaque -->
            <div class="plate-numbers">
            <!-- Numéros de plaque -->
            <div class="plate-numbers">
                <div class="plate-number">{{ $plateNumber }}</div>
            </div>
            </div>
        </div>
    </div>
</body>

</html>
