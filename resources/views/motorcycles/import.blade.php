<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Motorcycle Import - Excel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@7.2.96/css/materialdesignicons.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #101828;">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="mdi mdi-motorbike me-2"></i>
                DabApp - Motorcycle Import
            </a>
        </div>
    </nav>
    <div class="container py-4">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">Import Motorcycles from Excel</h4>
                </div>
            </div>
        </div>

        {{-- Success messages --}}
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="mdi mdi-check-circle me-2"></i>
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        {{-- Error messages --}}
        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="mdi mdi-alert-circle me-2"></i>
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        {{-- Excel import errors --}}
        @if(session('importErrors') && is_array(session('importErrors')))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <h5><i class="mdi mdi-alert me-2"></i>Errors encountered during import:</h5>
            <ul class="mb-0">
                @foreach(session('importErrors') as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        @endif

        {{-- Laravel validation errors --}}
        @if(isset($errors) && is_object($errors) && $errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <h5><i class="mdi mdi-alert me-2"></i>Validation errors:</h5>
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
                            <h6><i class="mdi mdi-lightbulb-outline"></i> How to use the import</h6>
                            <ol class="mb-0">
                                <li>Download the Excel template below</li>
                                <li>Fill the file with your data</li>
                                <li>Upload the completed file</li>
                                <li>Start the import process</li>
                            </ol>
                        </div>

                        <h6 class="mt-3"><i class="mdi mdi-format-list-bulleted"></i> Required columns:</h6>
                        <ul class="text-muted">
                            <li><strong>Make</strong> - Motorcycle brand</li>
                            <li><strong>Model</strong> - Motorcycle model</li>
                            <li><strong>Year</strong> - Year (optional)</li>
                        </ul>

                        <h6 class="mt-3"><i class="mdi mdi-file-excel-outline"></i> Supported formats:</h6>
                        <ul class="text-muted mb-0">
                            <li>.xlsx</li>
                            <li>.xls</li>
                            <li>.csv</li>
                        </ul>

                        <div class="mt-3">
                            <a href="{{ route('motorcycles.import.template') }}" class="btn btn-outline-success btn-sm">
                                <i class="mdi mdi-download me-1"></i>
                                Download Template
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Upload form --}}
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="mdi mdi-cloud-upload-outline me-2"></i>
                            Upload Excel File
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('motorcycles.import.process') }}" method="POST" enctype="multipart/form-data" id="importForm">
                            @csrf

                            <div class="row">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="excel_file" class="form-label">
                                            Excel File <span class="text-danger">*</span>
                                        </label>
                                        <input type="file"
                                            class="form-control @if(isset($errors) && is_object($errors) && $errors->has('excel_file')) is-invalid @endif"
                                            id="excel_file"
                                            name="excel_file"
                                            accept=".xlsx,.xls,.csv"
                                            required>
                                        @if(isset($errors) && is_object($errors) && $errors->has('excel_file'))
                                        <div class="invalid-feedback">{{ $errors->first('excel_file') }}</div>
                                        @endif
                                        <div class="form-text">
                                            <i class="mdi mdi-information-outline"></i>
                                            Accepted formats: .xlsx, .xls, .csv
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
                                                Overwrite existing data
                                            </label>
                                        </div>
                                        <div class="form-text">
                                            <i class="mdi mdi-alert-outline"></i>
                                            If checked, existing data will be updated. Otherwise, only new data will be added.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- File preview --}}
                            <div id="filePreview" class="mb-3" style="display: none;">
                                <div class="alert alert-secondary">
                                    <h6><i class="mdi mdi-file-check-outline"></i> Selected file:</h6>
                                    <div id="fileName"></div>
                                    <div id="fileSize"></div>
                                    <div id="fileType"></div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between">
                                        <a href="{{ route('motorcycles.index') }}" class="btn btn-secondary">
                                            <i class="mdi mdi-arrow-left me-1"></i>
                                            Back
                                        </a>

                                        <button type="submit" class="btn btn-primary dab-btn-primary" id="submitBtn">
                                            <i class="mdi mdi-cloud-upload me-1"></i>
                                            <span id="submitText">Start Import</span>
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

        {{-- Current statistics --}}
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="mdi mdi-chart-bar me-2"></i>
                            Current Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="p-3">
                                    <i class="mdi mdi-factory dab-text-primary" style="font-size: 2rem;"></i>
                                    <h4 class="mt-2 mb-1">{{ \App\Models\MotorcycleBrand::count() ?? 0 }}</h4>
                                    <p class="text-muted mb-0">Brands</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3">
                                    <i class="mdi mdi-car-sports dab-text-orange" style="font-size: 2rem;"></i>
                                    <h4 class="mt-2 mb-1">{{ \App\Models\MotorcycleModel::count() ?? 0 }}</h4>
                                    <p class="text-muted mb-0">Models</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3">
                                    <i class="mdi mdi-calendar dab-text-primary" style="font-size: 2rem;"></i>
                                    <h4 class="mt-2 mb-1">{{ \App\Models\MotorcycleYear::count() ?? 0 }}</h4>
                                    <p class="text-muted mb-0">Years</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="p-3">
                                    <i class="mdi mdi-motorbike dab-text-orange" style="font-size: 2rem;"></i>
                                    <h4 class="mt-2 mb-1">{{ \App\Models\MotorcycleDetail::count() ?? 0 }}</h4>
                                    <p class="text-muted mb-0">Detailed Motorcycles</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        :root {
            --dab-dark-blue: #101828;
            --dab-orange: #F03D24;
        }

        .page-title-box {
            background: linear-gradient(135deg, var(--dab-dark-blue) 0%, var(--dab-orange) 100%);
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
            box-shadow: 0 4px 6px rgba(16, 24, 40, 0.1);
            border-radius: 10px;
        }

        .card-header {
            background: linear-gradient(135deg, #f8f9fc 0%, rgba(16, 24, 40, 0.05) 100%);
            border-bottom: 1px solid #e3e6f0;
            border-radius: 10px 10px 0 0 !important;
        }

        .btn {
            border-radius: 8px;
        }

        .dab-btn-primary {
            background-color: var(--dab-dark-blue);
            border-color: var(--dab-dark-blue);
            transition: all 0.3s ease;
        }

        .dab-btn-primary:hover {
            background-color: var(--dab-orange);
            border-color: var(--dab-orange);
            transform: translateY(-1px);
        }

        .dab-text-primary {
            color: var(--dab-dark-blue) !important;
        }

        .dab-text-orange {
            color: var(--dab-orange) !important;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid #e3e6f0;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--dab-orange);
            box-shadow: 0 0 0 0.2rem rgba(240, 61, 36, 0.25);
        }

        #filePreview {
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert {
            border-radius: 8px;
        }

        .alert-info {
            background-color: rgba(16, 24, 40, 0.05);
            border-color: rgba(16, 24, 40, 0.15);
            color: var(--dab-dark-blue);
        }

        .navbar-brand {
            font-weight: bold;
            font-size: 1.1rem;
        }

        .navbar-brand:hover {
            color: var(--dab-orange) !important;
        }

        /* Accent colors for statistics */
        .statistics-card {
            transition: transform 0.3s ease;
        }

        .statistics-card:hover {
            transform: translateY(-5px);
        }

        /* Button animations */
        .btn:not(.btn-secondary):hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        /* Loading spinner color */
        .spinner-border {
            border-color: currentColor;
            border-right-color: transparent;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

            // File preview
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    fileName.innerHTML = `<strong>Name:</strong> ${file.name}`;
                    fileSize.innerHTML = `<strong>Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB`;
                    fileType.innerHTML = `<strong>Type:</strong> ${file.type || 'Not detected'}`;
                    filePreview.style.display = 'block';
                } else {
                    filePreview.style.display = 'none';
                }
            });

            // Submit button animation
            form.addEventListener('submit', function() {
                submitBtn.disabled = true;
                submitText.textContent = 'Import in progress...';
                loadingSpinner.style.display = 'inline-block';
            });

            // Auto-hide alerts after 10 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert:not(.alert-info)');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    if (bsAlert) {
                        bsAlert.close();
                    }
                });
            }, 10000);
        });
    </script>
</body>
</html>
