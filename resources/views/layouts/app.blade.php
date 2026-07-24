<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Order of Payment System')</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-brand {
            font-weight: 700;
            color: #0d6efd !important;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #0d6efd 0%, #0a58ca 100%);
            padding-top: 20px;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 10px 20px;
            margin: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: #fff;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
        }
        .main-content {
            padding: 30px;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .card-header {
            background: transparent;
            border-bottom: 2px solid #f0f0f0;
            padding: 20px 25px;
            font-weight: 600;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending { background: #ffc107; color: #856404; }
        .status-approved { background: #28a745; color: #fff; }
        .status-cancelled { background: #dc3545; color: #fff; }
        .file-upload-box {
            border: 2px dashed #ddd;
            padding: 30px;
            text-align: center;
            border-radius: 8px;
            transition: all 0.3s;
            cursor: pointer;
        }
        .file-upload-box:hover {
            border-color: #0d6efd;
            background: #f8f9ff;
        }
        .file-preview {
            display: inline-block;
            margin: 5px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .btn-primary {
            padding: 10px 30px;
            border-radius: 8px;
            font-weight: 600;
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        .table td {
            vertical-align: middle;
        }
        .reference-number {
            font-family: 'Courier New', monospace;
            font-weight: 700;
            color: #0d6efd;
            letter-spacing: 0.5px;
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
            padding: 10px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 3px rgba(13,110,253,0.1);
        }
        .required-field::after {
            content: ' *';
            color: #dc3545;
            font-weight: bold;
        }
        .search-box {
            max-width: 400px;
        }
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
                padding: 10px;
            }
            .main-content {
                padding: 15px;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 sidebar">
                <div class="text-center mb-4">
                    <h4 class="text-white">OP System</h4>
                    <small class="text-white-50">Order of Payment</small>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link {{ request()->routeIs('staff.dashboard') ? 'active' : '' }}" 
                       href="{{ route('staff.dashboard') }}">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a class="nav-link {{ request()->routeIs('staff.requests.*') ? 'active' : '' }}" 
                       href="{{ route('staff.requests.index') }}">
                        <i class="fas fa-file-alt"></i> Requests
                    </a>
                    <hr class="border-light">
                    <a class="nav-link {{ request()->routeIs('staff.memberships.*') ? 'active' : '' }}" 
                       href="{{ route('staff.memberships.index') }}">
                        <i class="fas fa-users"></i> Memberships
                    </a>
                    <a class="nav-link {{ request()->routeIs('staff.payment-options.*') ? 'active' : '' }}" 
                       href="{{ route('staff.payment-options.index') }}">
                        <i class="fas fa-credit-card"></i> Payment Options
                    </a>
                    <a class="nav-link {{ request()->routeIs('staff.bank-accounts.*') ? 'active' : '' }}" 
                       href="{{ route('staff.bank-accounts.index') }}">
                        <i class="fas fa-university"></i> Bank Accounts
                    </a>
                    <a class="nav-link {{ request()->routeIs('staff.uacs.*') ? 'active' : '' }}" 
                       href="{{ route('staff.uacs.index') }}">
                        <i class="fas fa-code"></i> UACS
                    </a>
                    <hr class="border-light">
                    <a class="nav-link" href="{{ route('public.submit') }}" target="_blank">
                        <i class="fas fa-external-link-alt"></i> Public Form
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                
                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                
                @if(session('warning'))
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        {{ session('warning') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif
                
                @yield('content')
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').alert('close');
        }, 5000);
    </script>
    @stack('scripts')
</body>
</html>