<?php defined('BASEPATH') OR exit('No direct script access allowed');
$tema = $this->config->item('tema');
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title>Login — Dashboard Peta Vokasi</title>
	<link href="<?= base_url('assets/vendor/fontawesome-free/css/all.min.css') ?>" rel="stylesheet">
	<link href="<?= base_url('assets/vendor/nunito/nunito.css') ?>" rel="stylesheet">
	<link href="<?= base_url('assets/css/sb-admin-2.min.css') ?>" rel="stylesheet">
	<style>
		body { background: linear-gradient(135deg, <?= $tema['gold_dark'] ?>, <?= $tema['gold_deep'] ?>); min-height: 100vh; }
		.login-card { max-width: 400px; width: 100%; }
		.btn-gold { background: <?= $tema['gold'] ?>; border-color: <?= $tema['gold'] ?>; color: #fff; }
		.btn-gold:hover { background: <?= $tema['gold_dark'] ?>; color: #fff; }
	</style>
</head>
<body class="d-flex align-items-center justify-content-center px-3">
	<div class="card shadow-lg border-0 login-card">
		<div class="card-body p-4 p-sm-5">
			<div class="text-center mb-4">
				<i class="fas fa-map-marked-alt fa-2x mb-2" style="color: <?= $tema['gold'] ?>"></i>
				<h1 class="h5 text-gray-900 font-weight-bold mb-0">Dashboard Peta Vokasi</h1>
				<div class="small text-muted">Login admin — akses data lengkap &amp; export</div>
			</div>

			<?php if ($error): ?>
			<div class="alert alert-danger py-2 small"><?= html_escape($error) ?></div>
			<?php endif; ?>

			<form method="post" action="<?= site_url('login') ?>" autocomplete="off">
				<input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
				<div class="form-group">
					<label class="small font-weight-bold" for="username">Username</label>
					<input type="text" class="form-control" id="username" name="username" required autofocus
						value="<?= html_escape((string) $this->input->post('username')) ?>">
				</div>
				<div class="form-group">
					<label class="small font-weight-bold" for="password">Password</label>
					<input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
				</div>
				<button type="submit" class="btn btn-gold btn-block font-weight-bold">
					<i class="fas fa-sign-in-alt mr-1"></i> Masuk
				</button>
			</form>
			<div class="text-center mt-3"><a class="small" href="<?= site_url() ?>">&larr; Kembali sebagai guest</a></div>
		</div>
	</div>
</body>
</html>
