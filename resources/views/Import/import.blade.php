<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import de Motos - Excel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@7.2.96/css/materialdesignicons.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="#">
            <i class="mdi mdi-motorbike me-2"></i>
            Import Motos Excel
        </a>
    </div>
</nav>
<div class="container py-4">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box">
                <h4 class="page-title">Import de Motos depuis Excel</h4>
            </div>
        </div>
    </div>

    {{-- Messages d'alerte --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="mdi mdi-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="mdi mdi-alert-circle me-2"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('errors') && count(session('errors')) > 0)
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <h5><i class="mdi mdi-alert me-2"></i>Erreurs rencontrées lors de l'import:</h5>
            <ul class="mb-0">
                @foreach(session('errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h5><i class="mdi mdi-alert me-2"></i>Erreurs de validation:</h5>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        {{-- Instructions --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="mdi mdi-information-outline me-2"></i>
                        Instructions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6><i class="mdi mdi-lightbulb-outline"></i> Comment utiliser l'import</h6>
                        <ol class="mb-0">
                            <li>Téléchargez le template Excel ci-dessous</li>
                            <li>Remplissez le fichier avec vos données</li>
                            <li>Uploadez le fichier rempli</li>
                            <li>Lancez l'import</li>
                        </ol>
                    </div>

                    <h6 class="mt-3"><i class="mdi mdi-format-list-bulleted"></i> Colonnes obligatoires:</h6>
                    <ul class="text-muted">
                        <li><strong>Make</strong> - Marque de la moto</li>
                        <li><strong>Model</strong> - Modèle de la moto</li>
                        <li><strong>Year</strong> - Année</li>
                    </ul>

                    <h6 class="mt-3"><i class="mdi mdi-file-excel-outline"></i> Formats supportés:</h6>
                    <ul class="text-muted mb-0">
                        <li>.xlsx</li>
                        <li>.xls</li>
                        <li>.csv</li>
                    </ul>

                    <div class="mt-3">
                        <a href="{{ route('motorcycles.import.template') }}" class="btn btn-outline-success btn-sm">
                            <i class="mdi mdi-download me-1"></i>
                            Télécharger le Template
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Formulaire d'upload --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="mdi mdi-cloud-upload-outline me-2"></i>
                        Uploader le fichier Excel
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('motorcycles.import.process') }}" method="POST" enctype="multipart/form-data" id="importForm">
                        @csrf

                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="excel_files" class="form-label">
                                        Fichiers Excel <span class="text-danger">*</span>
                                    </label>
                                    <input type="file"
                                           class="form-control @error('excel_files') is-invalid @enderror"
                                           id="excel_files"
                                           name="excel_files[]"
                                           accept=".xlsx,.xls,.csv"
                                           multiple
                                           required>
                                    @error('excel_files')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">
                                        <i class="mdi mdi-information-outline"></i>
                                        Sélectionnez un ou plusieurs fichiers. Taille max par fichier: 10MB.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="overwrite_existing" name="overwrite_existing" value="1">
                                        <label class="form-check-label" for="overwrite_existing">
                                            Écraser les données existantes
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        <i class="mdi mdi-alert-outline"></i>
                                        Si coché, les données existantes seront mises à jour. Sinon, seules les nouvelles données seront ajoutées.
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Preview du fichier --}}
                        <div id="filePreview" class="mb-3" style="display: none;">
                            <div class="alert alert-secondary">
                                <h6><i class="mdi mdi-file-check-outline"></i> Fichier sélectionné:</h6>
                                <div id="fileName"></div>
                                <div id="fileSize"></div>
                                <div id="fileType"></div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <a href="{{ route('admin.motorcycles.index') }}" class="btn btn-secondary">
                                        <i class="mdi mdi-arrow-left me-1"></i>
                                        Retour
                                    </a>

                                    <button type="submit" class="btn btn-primary" id="submitBtn">
                                        <i class="mdi mdi-cloud-upload me-1"></i>
                                        <span id="submitText">Lancer l'Import</span>
                                        <span id="loadingSpinner" class="spinner-border spinner-border-sm ms-1" style="display: none;"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Historique des imports --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="mdi mdi-history me-2"></i>
                        Statistiques actuelles
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="p-3">
                                <i class="mdi mdi-factory text-primary" style="font-size: 2rem;"></i>
                                <h4 class="mt-2 mb-1">{{ \App\Models\MotorcycleBrand::count() }}</h4>
                                <p class="text-muted mb-0">Marques</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3">
                                <i class="mdi mdi-car-sports text-success" style="font-size: 2rem;"></i>
                                <h4 class="mt-2 mb-1">{{ \App\Models\MotorcycleModel::count() }}</h4>
                                <p class="text-muted mb-0">Modèles</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3">
                                <i class="mdi mdi-calendar text-warning" style="font-size: 2rem;"></i>
                                <h4 class="mt-2 mb-1">{{ \App\Models\MotorcycleYear::count() }}</h4>
                                <p class="text-muted mb-0">Années</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3">
                                <i class="mdi mdi-motorbike text-info" style="font-size: 2rem;"></i>
                                <h4 class="mt-2 mb-1">{{ \App\Models\MotorcycleDetail::count() }}</h4>
                                <p class="text-muted mb-0">Motos détaillées</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.page-title-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 1.5rem;
    border-radius: 10px;
    margin-bottom: 2rem;
}

.page-title-box .breadcrumb-item a {
    color: rgba(255, 255, 255, 0.8);
}

.page-title-box .breadcrumb-item.active {
    color: white;
}

.card {
    border: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border-radius: 10px;
}

.card-header {
    background: #f8f9fc;
    border-bottom: 1px solid #e3e6f0;
    border-radius: 10px 10px 0 0 !important;
}

.btn {
    border-radius: 8px;
}

.form-control {
    border-radius: 8px;
}

#filePreview {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert {
    border-radius: 8px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('excel_file');
    const filePreview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const fileType = document.getElementById('fileType');
    const form = document.getElementById('importForm');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const loadingSpinner = document.getElementById('loadingSpinner');

    // Preview du fichier sélectionné
    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            fileName.innerHTML = `<strong>Nom:</strong> ${file.name}`;
            fileSize.innerHTML = `<strong>Taille:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB`;
            fileType.innerHTML = `<strong>Type:</strong> ${file.type || 'Non détecté'}`;
            filePreview.style.display = 'block';
        } else {
            filePreview.style.display = 'none';
        }
    });

    // Animation du bouton de soumission
    form.addEventListener('submit', function() {
        submitBtn.disabled = true;
        submitText.textContent = 'Import en cours...';
        loadingSpinner.style.display = 'inline-block';
    });

    // Auto-hide des alertes après 10 secondes
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-info)');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 10000);
});
</script>
@endsection
