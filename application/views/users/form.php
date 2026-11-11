<div class="container-fluid">
	<h1 class="h3 mb-3 text-gray-800"><?= html_escape($title) ?></h1>
	<?php if ($errors): ?>
	<div class="alert alert-danger small"><?= implode('<br>', array_map('html_escape', $errors)) ?></div>
	<?php endif; ?>
	<div class="card shadow mb-4" style="max-width: 640px">
		<div class="card-body">
			<form method="post" action="<?= site_url('users/form' . ($user ? '/' . $user['id'] : '')) ?>" autocomplete="off">
				<input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
				<div class="form-group">
					<label class="small font-weight-bold" for="username">Username</label>
					<input type="text" class="form-control" id="username" name="username" required maxlength="50" pattern="[a-z0-9._\-]{3,50}" value="<?= html_escape($in['username']) ?>">
					<small class="text-muted">Huruf kecil, angka, titik, minus, garis bawah (3–50).</small>
				</div>
				<div class="form-group">
					<label class="small font-weight-bold" for="full_name">Nama Lengkap</label>
					<input type="text" class="form-control" id="full_name" name="full_name" maxlength="100" value="<?= html_escape($in['full_name']) ?>">
				</div>
				<div class="form-group">
					<label class="small font-weight-bold" for="role_id">Role</label>
					<select class="form-control" id="role_id" name="role_id" required <?= $isSelf ? 'disabled' : '' ?>>
						<option value="">— pilih —</option>
						<?php foreach ($roles as $r): ?>
						<option value="<?= $r['id'] ?>" <?= (int) $in['role_id'] === (int) $r['id'] ? 'selected' : '' ?>><?= html_escape($r['name']) ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ($isSelf): ?><small class="text-muted">Role akun sendiri tidak bisa diubah.</small><?php endif; ?>
				</div>
				<div class="form-row">
					<div class="form-group col-sm-6">
						<label class="small font-weight-bold" for="password">Password<?= $user ? ' baru' : '' ?></label>
						<input type="password" class="form-control" id="password" name="password" minlength="8" autocomplete="new-password" <?= $user ? '' : 'required' ?>>
						<?php if ($user): ?><small class="text-muted">Kosongkan bila tidak diganti.</small><?php endif; ?>
					</div>
					<div class="form-group col-sm-6">
						<label class="small font-weight-bold" for="password_confirm">Ulangi Password</label>
						<input type="password" class="form-control" id="password_confirm" name="password_confirm" autocomplete="new-password">
					</div>
				</div>
				<div class="form-group custom-control custom-switch">
					<input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" <?= $in['is_active'] ? 'checked' : '' ?> <?= $isSelf ? 'disabled' : '' ?>>
					<label class="custom-control-label" for="is_active">Aktif</label>
				</div>
				<button type="submit" class="btn btn-warning font-weight-bold"><i class="fas fa-save mr-1"></i> Simpan</button>
				<a href="<?= site_url('users') ?>" class="btn btn-light">Batal</a>
			</form>
		</div>
	</div>
</div>
