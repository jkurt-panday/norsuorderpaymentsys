<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Order of Payment - Public Form')</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e3f2fd 0%, #f8f9fa 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .form-container {
            max-width: 900px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .form-header {
            text-align: center;
            margin-bottom: 35px;
        }
        .form-header h1 {
            color: #0d6efd;
            font-weight: 700;
            font-size: 2.2rem;
        }
        .form-header p {
            color: #6c757d;
            font-size: 1.1rem;
        }
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .form-section h5 {
            color: #0d6efd;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 15px;
            border: 1px solid #dee2e6;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
        }
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        .required-field::after {
            content: ' *';
            color: #dc3545;
            font-weight: bold;
        }
        .btn-submit {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            border: none;
            padding: 15px 40px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(13,110,253,0.4);
        }
        .file-upload-area {
            border: 2px dashed #dee2e6;
            padding: 30px;
            text-align: center;
            border-radius: 12px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .file-upload-area:hover {
            border-color: #0d6efd;
            background: #f8f9ff;
        }
        .file-upload-area.dragover {
            border-color: #0d6efd;
            background: #e7f1ff;
        }
        .file-list {
            margin-top: 15px;
        }
        .file-item {
            display: inline-block;
            background: white;
            padding: 8px 15px;
            margin: 5px;
            border-radius: 20px;
            border: 1px solid #dee2e6;
            font-size: 14px;
        }
        .file-item i {
            margin-right: 8px;
            color: #0d6efd;
        }
        .file-item .remove-file {
            margin-left: 8px;
            color: #dc3545;
            cursor: pointer;
        }
        .success-container {
            text-align: center;
            padding: 40px 20px;
        }
        .success-container .icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .reference-number-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            font-weight: 700;
            color: #0d6efd;
            letter-spacing: 2px;
            display: inline-block;
            padding: 15px 30px;
        }
        @media (max-width: 768px) {
            .form-container {
                margin: 20px;
                padding: 20px;
            }
            .form-header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="container">
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        
        @yield('content')
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>