<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Créer une Route - Style Harley Davidson</title>
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
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(255, 107, 0, 0.3);
        }

        .header h1 {
            font-size: 32px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 400px 1fr;
            gap: 20px;
        }

        .sidebar {
            background: #2c2c2c;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            max-height: calc(100vh - 180px);
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #ff6b00;
            font-weight: bold;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            background: #1a1a1a;
            border: 2px solid #444;
            border-radius: 5px;
            color: #fff;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #ff6b00;
            box-shadow: 0 0 10px rgba(255, 107, 0, 0.3);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .waypoints-section {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .waypoint-item {
            background: #2c2c2c;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 10px;
            border-left: 4px solid #ff6b00;
            position: relative;
        }

        .waypoint-item h4 {
            color: #ff6b00;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .waypoint-item .coords {
            font-size: 12px;
            color: #999;
            font-family: monospace;
        }

        .waypoint-item .remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #d32f2f;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
        }

        .waypoint-item .remove-btn:hover {
            background: #b71c1c;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            text-transform: uppercase;
            cursor: pointer;
            font-size: 14px;
            letter-spacing: 1px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(90deg, #ff6b00 0%, #cc5500 100%);
            color: white;
            width: 100%;
            box-shadow: 0 5px 15px rgba(255, 107, 0, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 20px rgba(255, 107, 0, 0.5);
        }

        .btn-primary:disabled {
            background: #666;
            cursor: not-allowed;
            transform: none;
        }

        .map-container {
            background: #1a1a1a;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        #map {
            width: 100%;
            height: calc(100vh - 180px);
        }

        .map-instructions {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 107, 0, 0.95);
            padding: 15px 20px;
            border-radius: 8px;
            z-index: 1000;
            max-width: 300px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
        }

        .map-instructions h3 {
            margin-bottom: 10px;
            font-size: 16px;
        }

        .map-instructions p {
            font-size: 13px;
            line-height: 1.5;
        }

        .stats-box {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border: 2px solid #ff6b00;
        }

        .stats-box h3 {
            color: #ff6b00;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #333;
        }

        .stat-item:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: #999;
            font-size: 12px;
        }

        .stat-value {
            color: #fff;
            font-weight: bold;
            font-size: 14px;
        }

        /* Scrollbar personnalisée */
        .sidebar::-webkit-scrollbar {
            width: 8px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: #1a1a1a;
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: #ff6b00;
            border-radius: 10px;
        }

        .success-message {
            background: #4caf50;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }

        .error-message {
            background: #d32f2f;
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏍️ Créer Une Nouvelle Route</h1>
        </div>

        <div class="success-message" id="successMessage"></div>
        <div class="error-message" id="errorMessage"></div>

        <div class="main-grid">
            <!-- Sidebar avec formulaire -->
            <div class="sidebar">
                <form id="routeForm">
                    <div class="form-group">
                        <label>Titre de la Route *</label>
                        <input type="text" id="title" name="title" required
                               placeholder="Ex: Route des Alpes">
                    </div>

                    <div class="form-group">
                        <label>Description *</label>
                        <textarea id="description" name="description" required
                                  placeholder="Décrivez votre route..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Difficulté</label>
                        <select id="difficulty" name="difficulty">
                            <option value="easy">Facile</option>
                            <option value="moderate">Modérée</option>
                            <option value="difficult">Difficile</option>
                            <option value="expert">Expert</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Durée Estimée</label>
                        <input type="text" id="estimated_duration" name="estimated_duration"
                               placeholder="Ex: 3 heures">
                    </div>

                    <div class="form-group">
                        <label>Meilleure Saison</label>
                        <input type="text" id="best_season" name="best_season"
                               placeholder="Ex: Printemps, Été">
                    </div>

                    <div class="form-group">
                        <label>Condition de la Route</label>
                        <select id="road_condition" name="road_condition">
                            <option value="excellent">Excellente</option>
                            <option value="good">Bonne</option>
                            <option value="fair">Moyenne</option>
                            <option value="poor">Mauvaise</option>
                        </select>
                    </div>

                    <div class="waypoints-section">
                        <h3 style="color: #ff6b00; margin-bottom: 15px;">
                            📍 POINTS DE PASSAGE (<span id="waypointCount">0</span>)
                        </h3>
                        <div id="waypointsList"></div>
                    </div>

                    <div class="stats-box">
                        <h3>📊 STATISTIQUES</h3>
                        <div class="stat-item">
                            <span class="stat-label">Distance totale:</span>
                            <span class="stat-value" id="totalDistance">0 km</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Points de passage:</span>
                            <span class="stat-value" id="totalWaypoints">0</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        🏁 Créer la Route
                    </button>
                </form>
            </div>

            <!-- Map -->
            <div class="map-container">
                <div class="map-instructions">
                    <h3>📍 Comment utiliser :</h3>
                    <p>Cliquez sur la carte pour ajouter des points de passage. La route se créera automatiquement entre les points.</p>
                </div>
                <div id="map"></div>
            </div>
        </div>
    </div>

    <script>
        let map;
        let markers = [];
        let waypoints = [];
        let directionsService;
        let directionsRenderer;

        // Configuration des styles de la carte (Orange et Gris)
        const mapStyles = [
            {
                "featureType": "all",
                "elementType": "geometry",
                "stylers": [{"color": "#242f3e"}]
            },
            {
                "featureType": "all",
                "elementType": "labels.text.stroke",
                "stylers": [{"lightness": -80}]
            },
            {
                "featureType": "administrative",
                "elementType": "labels.text.fill",
                "stylers": [{"color": "#746855"}]
            },
            {
                "featureType": "poi",
                "elementType": "labels.text.fill",
                "stylers": [{"color": "#d59563"}]
            },
            {
                "featureType": "road",
                "elementType": "geometry.fill",
                "stylers": [{"color": "#2b2b2b"}]
            },
            {
                "featureType": "road",
                "elementType": "labels.text.fill",
                "stylers": [{"color": "#ff6b00"}]
            },
            {
                "featureType": "road.highway",
                "elementType": "geometry.stroke",
                "stylers": [{"color": "#ff6b00"}]
            },
            {
                "featureType": "water",
                "elementType": "geometry",
                "stylers": [{"color": "#17263c"}]
            }
        ];

        function initMap() {
            // Centre sur la France (vous pouvez changer)
            const center = { lat: 46.603354, lng: 1.888334 };

            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 6,
                center: center,
                styles: mapStyles,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: true
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

            // Clic sur la carte pour ajouter un waypoint
            map.addListener('click', function(event) {
                addWaypoint(event.latLng);
            });
        }

        function addWaypoint(location) {
            const position = {
                lat: location.lat(),
                lng: location.lng()
            };

            // Créer un marqueur
            const marker = new google.maps.Marker({
                position: position,
                map: map,
                label: {
                    text: String(markers.length + 1),
                    color: 'white',
                    fontWeight: 'bold'
                },
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 12,
                    fillColor: markers.length === 0 ? '#4caf50' : '#ff6b00',
                    fillOpacity: 1,
                    strokeColor: '#fff',
                    strokeWeight: 2
                }
            });

            markers.push(marker);

            // Géocodage inverse pour obtenir le nom du lieu
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode({ location: position }, function(results, status) {
                if (status === 'OK' && results[0]) {
                    const waypointData = {
                        name: results[0].formatted_address,
                        latitude: position.lat,
                        longitude: position.lng,
                        waypoint_type: markers.length === 1 ? 'start' : 'waypoint',
                        description: ''
                    };

                    waypoints.push(waypointData);
                    updateWaypointsList();
                    updateStats();

                    if (waypoints.length >= 2) {
                        calculateRoute();
                    }
                }
            });

            // Supprimer le marqueur au clic
            marker.addListener('click', function() {
                removeWaypoint(markers.indexOf(marker));
            });
        }

        function removeWaypoint(index) {
            markers[index].setMap(null);
            markers.splice(index, 1);
            waypoints.splice(index, 1);

            // Renommer les marqueurs
            markers.forEach((marker, i) => {
                marker.setLabel({
                    text: String(i + 1),
                    color: 'white',
                    fontWeight: 'bold'
                });

                // Couleur différente pour le point de départ
                marker.setIcon({
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 12,
                    fillColor: i === 0 ? '#4caf50' : '#ff6b00',
                    fillOpacity: 1,
                    strokeColor: '#fff',
                    strokeWeight: 2
                });
            });

            updateWaypointsList();
            updateStats();

            if (waypoints.length >= 2) {
                calculateRoute();
            } else {
                directionsRenderer.setMap(null);
                directionsRenderer.setMap(map);
            }
        }

        function calculateRoute() {
            if (waypoints.length < 2) return;

            const origin = new google.maps.LatLng(waypoints[0].latitude, waypoints[0].longitude);
            const destination = new google.maps.LatLng(
                waypoints[waypoints.length - 1].latitude,
                waypoints[waypoints.length - 1].longitude
            );

            const waypointsForDirections = waypoints.slice(1, -1).map(wp => ({
                location: new google.maps.LatLng(wp.latitude, wp.longitude),
                stopover: true
            }));

            const request = {
                origin: origin,
                destination: destination,
                waypoints: waypointsForDirections,
                travelMode: google.maps.TravelMode.DRIVING,
                optimizeWaypoints: false
            };

            directionsService.route(request, function(result, status) {
                if (status === 'OK') {
                    directionsRenderer.setDirections(result);

                    // Calculer la distance totale
                    let totalDistance = 0;
                    const legs = result.routes[0].legs;
                    legs.forEach(leg => {
                        totalDistance += leg.distance.value;
                    });

                    document.getElementById('totalDistance').textContent =
                        (totalDistance / 1000).toFixed(2) + ' km';
                }
            });
        }

        function updateWaypointsList() {
            const list = document.getElementById('waypointsList');
            list.innerHTML = '';

            waypoints.forEach((wp, index) => {
                const div = document.createElement('div');
                div.className = 'waypoint-item';
                div.innerHTML = `
                    <h4>Point ${index + 1}: ${wp.waypoint_type === 'start' ? '🏁 DÉPART' : '📍 ÉTAPE'}</h4>
                    <p style="font-size: 13px; margin-bottom: 5px;">${wp.name}</p>
                    <p class="coords">Lat: ${wp.latitude.toFixed(6)}, Lng: ${wp.longitude.toFixed(6)}</p>
                    <button type="button" class="remove-btn" onclick="removeWaypoint(${index})">✕</button>
                `;
                list.appendChild(div);
            });

            document.getElementById('waypointCount').textContent = waypoints.length;
        }

        function updateStats() {
            document.getElementById('totalWaypoints').textContent = waypoints.length;
        }

        // Soumettre le formulaire
        document.getElementById('routeForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            if (waypoints.length < 2) {
                showError('Vous devez ajouter au moins 2 points de passage !');
                return;
            }

            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ Création en cours...';

            // Marquer le dernier waypoint comme 'end'
            if (waypoints.length > 0) {
                waypoints[waypoints.length - 1].waypoint_type = 'end';
            }

            const formData = {
                title: document.getElementById('title').value,
                description: document.getElementById('description').value,
                difficulty: document.getElementById('difficulty').value,
                estimated_duration: document.getElementById('estimated_duration').value,
                best_season: document.getElementById('best_season').value,
                road_condition: document.getElementById('road_condition').value,
                waypoints: waypoints
            };

            try {
                const response = await fetch('/api/routes', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + localStorage.getItem('auth_token'),
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(formData)
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess('Route créée avec succès ! Redirection...');
                    setTimeout(() => {
                        window.location.href = '/routes/' + data.data.id;
                    }, 2000);
                } else {
                    showError('Erreur: ' + (data.message || 'Erreur inconnue'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = '🏁 Créer la Route';
                }
            } catch (error) {
                showError('Erreur de connexion: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.textContent = '🏁 Créer la Route';
            }
        });

        function showSuccess(message) {
            const el = document.getElementById('successMessage');
            el.textContent = message;
            el.style.display = 'block';
            setTimeout(() => el.style.display = 'none', 5000);
        }

        function showError(message) {
            const el = document.getElementById('errorMessage');
            el.textContent = message;
            el.style.display = 'block';
            setTimeout(() => el.style.display = 'none', 5000);
        }
    </script>

    <script src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&callback=initMap&libraries=places" async defer></script>
</body>
</html>
