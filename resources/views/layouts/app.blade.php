<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>{{ config('app.name', 'Laravel') }}</title>
	<!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
	<!-- Google Font: Source Sans Pro -->
	<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
	<!-- Font Awesome -->
	<link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
	<!-- Ionicons -->
	<link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
	<!-- Tempusdominus Bootstrap 4 -->
	<link rel="stylesheet" href="{{ asset('plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css') }}">
	<!-- iCheck -->
	<link rel="stylesheet" href="{{ asset('plugins/icheck-bootstrap/icheck-bootstrap.min.css') }}">
	<!-- JQVMap -->
	<link rel="stylesheet" href="{{ asset('plugins/jqvmap/jqvmap.min.css') }}">
	<!-- Theme style -->
	<link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
	<!-- Custom style -->
	<link rel="stylesheet" href="{{ asset('dist/css/style.css') }}">
	@stack('styles')
</head>
<body class="hold-transition layout-fixed control-sidebar-slide-open layout-navbar-fixed sidebar-mini text-sm">
	<div class="wrapper">

		@include('layouts.nav')

		@include('layouts.sidebar')

		<!-- Content Wrapper. Contains page content -->
		<div class="content-wrapper">
			@yield('header')

			<!-- Main content -->
			<section class="content">
				<div class="container-fluid">
					@if (Session::has('success'))
						<div class="mb-3">
							<div class="alert alert-success alert-dismissible fade show">
								<strong>{{ Session::get('success') }}</strong>
								<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
							</div>
						</div>
					@endif
					@if (Session::has('error'))
						<div class="mb-3">
							<div class="alert alert-danger alert-dismissible fade show">
								<strong>{{ Session::get('error') }}</strong>
								<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
							</div>
						</div>
					@endif
					@yield('content')
				</div><!-- /.container-fluid -->
			</section>
			<!-- /.content -->
		</div>
		<!-- /.content-wrapper -->
		@include('layouts.footer')

	</div>
	<!-- ./wrapper -->
	
	<!-- jQuery -->
	<script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
	<!-- jQuery UI 1.11.4 -->
	<script src="{{ asset('plugins/jquery-ui/jquery-ui.min.js') }}"></script>
	<!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
	<script>
		$.widget.bridge('uibutton', $.ui.button)
	</script>
	<!-- Bootstrap 4 -->
	<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
	<!-- AdminLTE App -->
	<script src="{{ asset('dist/js/adminlte.js') }}"></script>
	<!-- AdminLTE for demo purposes -->
	{{-- <script src="{{ asset('dist/js/demo.js') }}"></script> --}}
	@stack('scripts')
</body>
</html>
