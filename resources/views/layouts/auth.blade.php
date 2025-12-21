<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AdminLTE 3 | Log in</title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
    <!-- icheck bootstrap -->
    <link rel="stylesheet" href="{{ asset('plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
    <!-- Theme style -->
    <link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
    {{-- <style>
        .login-card-body,
        .register-card-body {
            background: #5f99b4;
            color: #fff;
        }
        
        .login-card-body a,
        .register-card-body a {
            color: #fff;
        }
        
        .login-card-body .input-group .input-group-text,
        .register-card-body .input-group .input-group-text {
            color: #fff;
        }
    </style> --}}
</head>
<body class="hold-transition login-page">
    <div class="login-box">
        <div class="login-logo">
            <!--<a href="#"><b>Admin</b>LTE</a>-->
            {{-- <img src="{{ asset('images/logo.png') }}" style="max-width: 180px;"> --}}
            <a href="#"><b>Inventory</b> Management System</a>
        </div>
        <!-- /.login-logo -->
        @yield('content')
    </div>
    <!-- /.login-box -->
    
    <!-- jQuery -->
    <script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
    <!-- Bootstrap 4 -->
    <script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <!-- AdminLTE App -->
    <script src="{{ asset('dist/js/adminlte.min.js') }}"></script>
</body>
</html>
