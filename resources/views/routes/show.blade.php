<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $route->title }} - Route Harley Davidson</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial Black', Arial, sans-serif;
            background: linear-gradient(135deg, #2c2c2c 0%, #1a1a1a 100%);
            color: #fff;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(90deg, #ff6b00 0%, #cc5500 100%);
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(255, 107, 0, 0.3);
        }

        .header h1 {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .header .meta {
            display: flex;
            gap: 20px;
            font-size: 14px;
            opacity: 0.9;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
        }

        .map-section {
            background: #1a1a1a;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
        }

        #map {
            width: 100%;
            height: 600px;
        }

        .info-panel {
            background: #2c2c2c;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
        }

        .info-section {
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 2px solid #444;
        }

        .info-section:last-child {
            border-bottom: none;
        }

        .info-section h2 {
            color: #ff6b00;
            font-size: 18px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 14px;
        }

        .info-label {
            color: #999;
        }

        .info-value {
            color: #fff;
            font-weight: bold;
        }

        .description {
            line-height: 1.6;
            color: #ccc;
        }

        .waypoint-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .waypoint-item {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 4px solid #ff6b00;
        }

        .waypoint-item h4 {
            color: #ff6b00;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .waypoint-item p {
            font-size: 12px;
            color: #999;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-easy { background: #4caf50; }
        .badge-moderate { background: #ff9800; }
        .badge-difficult { background: #f44336; }
        .badge-expert { background: #9c27b0; }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .stat-card {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 2px solid #ff6b00;
        }

        .stat-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #ff6b00;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 11px;
            color: #999;
            text-transform: uppercase;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            text-transform: uppercase;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
            text-align: center;
        }

        .btn-like {
            background: #d32f2f;
            color: white;
        }

        .btn-favorite {
            background: #ffa726;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .waypoint-list::-webkit-scrollbar {
            width: 6px;
        }

        .waypoint-list::-webkit-scrollbar-track {
            background: #1a1a1a;
        }

        .waypoint-list::-webkit-scrollbar-thumb {
            background: #ff6b00;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏍️ {{ $route->title }}</h1>
            <div class="meta">
                <span>📍 {{ $route->waypoints->count() }} points</span>
                <span>📏 {{ $route->total_distance }} km</span>
                <span>👁️ {{ $route->views_count }} vues</span>
                <span>❤️ {{ $route->likes_count }} likes</span>
            </div>
        </div>

        <div class="main-grid">
            <!-- Carte -->
            <div class="map-section">
                <div id="map"></div>
            </div>

            <!-- Panneau d'informations -->
            <div class="info-panel">
                <div class="info-section">
                    <h2>📊 Statistiques</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="value">{{ $route->total_distance }}</div>
                            <div class="label">Kilomètres</div>
                        </div>
                        <div class="stat-card">
                            <div class="value">{{ $route->waypoints->count() }}</div>
                            <div class="label">Points</div>
                        </div>
                        <div class="stat-card">
                            <div class="value">{{ $route->estimated_duration ?? 'N/A' }}</div>
                            <div class="label">Durée</div>
                        </div>
                        <div class="stat-card">
                            <div class="value">
                                <span class="badge badge-{{ $route->difficulty }}">
                                    {{ ucfirst($route->difficulty) }}
                                </span>
                            </div>
                            <div class="label">Difficulté</div>
                        </div>
                    </div>
                </div>

                <div class="info-section">
                    <h2>📝 Description</h2>
                    <p class="description">{{ $route->description }}</p>
                </div>

                <div class="info-section">
                    <h2>ℹ️ Informations</h2>
                    <div class="info-item">
                        <span class="info-label">Meilleure saison:</span>
                        <span class="info-value">{{ $route->best_season ?? 'Toute l\'année' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">État de la route:</span>
                        <span class="info-value">{{ ucfirst($route->road_condition ?? 'N/A') }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Créée par:</span>
                        <span class="info-value">{{ $route->creator->name }}</span>
                    </div>
                </div>

                <div class="info-section">
                    <h2>📍 Points de Passage</h2>
                    <div class="waypoint-list">
                        @foreach($route->waypoints as $index => $waypoint)
                        <div class="waypoint-item">
                            <h4>
                                @if($waypoint->waypoint_type === 'start')
                                    🏁 Point {{ $index + 1 }}: DÉPART
                                @elseif($waypoint->waypoint_type === 'end')
                                    🏁 Point {{ $index + 1 }}: ARRIVÉE
                                @else
                                    📍 Point {{ $index + 1 }}: ÉTAPE
                                @endif
                            </h4>
                            <p>{{ $waypoint->name }}</p>
                            @if($waypoint->distance_from_previous)
                                <p style="color: #ff6b00; margin-top: 5px;">
                                    +{{ number_format($waypoint->distance_from_previous, 2) }} km
                                </p>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="action-buttons">
                    <button class="btn btn-like" onclick="toggleLike()">
                        ❤️ J'aime
                    </button>
                    <button class="btn btn-favorite" onclick="toggleFavorite()">
                        ⭐ Favori
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const routeData = @json($route);
        let map;
        let directionsService;
        let directionsRenderer;

        const mapStyles = [
            {"featureType": "all", "elementType": "geometry", "stylers": [{"color": "#242f3e"}]},
            {"featureType": "road", "elementType": "geometry.fill", "stylers": [{"color": "#2b2b2b"}]},
            {"featureType": "road", "elementType": "labels.text.fill", "stylers": [{"color": "#ff6b00"}]},
            {"featureType": "road.highway", "elementType": "geometry.stroke", "stylers": [{"color": "#ff6b00"}]},
            {"featureType": "water", "elementType": "geometry", "stylers": [{"color": "#17263c"}]}
        ];

        function initMap() {
            const center = {
                lat: parseFloat(routeData.waypoints[0].latitude),
                lng: parseFloat(routeData.waypoints[0].longitude)
            };

            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 8,
                center: center,
                styles: mapStyles,
                mapTypeControl: false,
                streetViewControl: false
            });

            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({
                map: map,
                suppressMarkers: true,
                polylineOptions: {
                    strokeColor: '#ff6b00',
                    strokeWeight: 5,
                    strokeOpacity: 0.8
                }
            });

            displayRoute();
        }

        function displayRoute() {
            const waypoints = routeData.waypoints;

            if (waypoints.length < 2) return;

            const origin = new google.maps.LatLng(
                parseFloat(waypoints[0].latitude),
                parseFloat(waypoints[0].longitude)
            );

            const destination = new google.maps.LatLng(
                parseFloat(waypoints[waypoints.length - 1].latitude),
                parseFloat(waypoints[waypoints.length - 1].longitude)
            );

            const waypointsForDirections = waypoints.slice(1, -1).map(wp => ({
                location: new google.maps.LatLng(parseFloat(wp.latitude), parseFloat(wp.longitude)),
                stopover: true
            }));

            const request = {
                origin: origin,
                destination: destination,
                waypoints: waypointsForDirections,
                travelMode: google.maps.TravelMode.DRIVING
            };

            directionsService.route(request, function(result, status) {
                if (status === 'OK') {
                    directionsRenderer.setDirections(result);

                    // Ajouter les marqueurs
                    waypoints.forEach((wp, index) => {
                        new google.maps.Marker({
                            position: {
                                lat: parseFloat(wp.latitude),
                                lng: parseFloat(wp.longitude)
                            },
                            map: map,
                            label: {
                                text: String(index + 1),
                                color: 'white',
                                fontWeight: 'bold'
                            },
                            icon: {
                                path: google.maps.SymbolPath.CIRCLE,
                                scale: 12,
                                fillColor: index === 0 || index === waypoints.length - 1 ? '#4caf50' : '#ff6b00',
                                fillOpacity: 1,
                                strokeColor: '#fff',
                                strokeWeight: 2
                            }
                        });
                    });
                }
            });
        }

        async function toggleLike() {
            try {
                const response = await fetch(`/api/routes/${routeData.id}/like`, {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + localStorage.getItem('auth_token'),
                        'Content-Type': 'application/json'
                    }
                });
                const data = await response.json();
                if (data.success) {
                    alert(data.message);
                    location.reload();
                }
            } catch (error) {
                alert('Erreur: ' + error.message);
            }
        }

        async function toggleFavorite() {
            try {
                const response = await fetch(`/api/routes/${routeData.id}/favorite`, {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + localStorage.getItem('auth_token'),
                        'Content-Type': 'application/json'
                    }
                });
                const data = await response.json();
                if (data.success) {
                    alert(data.message);
                    location.reload();
                }
            } catch (error) {
                alert('Erreur: ' + error.message);
            }
        }
    </script>

    <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&callback=initMap" async defer></script>
</body>
</html>
