<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UAE Abu Dhabi License Plate</title>
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
            border-radius: 6px;
            display: flex;
            flex-direction: column;
            position: relative;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.3);
        }

        /* Header avec numéro de catégorie */
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
            /* Augmenté */
            font-weight: 900;
            color: #000;
            line-height: 1;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.3);
            /* Ajouté */

        }

        /* Logo Abu Dhabi en haut à droite */
        .logo-top {
            position: absolute;
            top: 15px;
            right: 20px;
            width: AUTO;
            /* Augmenté */
            height: 130px;
            /* Augmenté */
        }

        .logo-top img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        /* Section principale avec numéros */
        .main-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: white;
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
            /* Augmenté */
            font-weight: 900;
            color: #000;
            line-height: 1;
            letter-spacing: 5px;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.3);
            /* Ajouté */

        }
        
        /* Conteneur pour les lettres arabes détachées */
        .arabic-char-container {
            display: flex;
            flex-direction: row-reverse; /* RTL for Arabic */
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
            gap: 5px;
        }
        
        .arabic-char {
            display: inline-block;
            width: 70px; /* Wider for UAE big font */
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="plate">
        <!-- Header avec numéro de catégorie -->
        <div class="header">
            <div class="category-box">
                <div class="category-number">{{ $categoryNumber }}</div>
            </div>

            <!-- Logo en haut à droite -->
            <div class="logo-top">
                <img src="{{ $logoBase64 }}" alt="Abu Dhabi Logo">
            </div>
        </div>

        <!-- Section principale -->
        <div class="main-section">
            <!-- Numéros de plaque -->
            <div class="plate-numbers">
            <!-- Numéros de plaque -->
            <div class="plate-numbers">
                <div class="plate-number">
                    @php
                       $pNum = $plateNumber ?? '';
                       // Check if string contains Arabic characters
                       $isArabic = preg_match('/\p{Arabic}/u', $pNum);
                    @endphp
                    
                    @if($isArabic)
                         <div class="arabic-char-container">
                            @foreach(mb_str_split($pNum) as $char)
                                <span class="arabic-char">{{ $char }}</span>
                            @endforeach
                        </div>
                    @else
                        {{ $pNum }}
                    @endif
                </div>
            </div>
            </div>
        </div>
    </div>
</body>

</html>
