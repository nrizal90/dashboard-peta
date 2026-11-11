<div class="container-fluid">
	<h1 class="h3 mb-3 text-gray-800"><?= html_escape($title) ?></h1>
	<?php if ($errors): ?>
	<div class="alert alert-danger small"><?= implode('<br>', array_map('html_escape', $errors)) ?></div>
	<?php endif; ?>
	<div class="card shadow mb-4" style="max-width: 640px">
		<div class="card-body">
			<form method="post" action="<?= site_url('roles/form' . ($role ? '/' . $role['id'] : '')) ?>">
				<input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
				<div class="form-group">
					<label class="small font-weight-bold" for="name">Nama Role</label>
					<input type="text" class="form-control" id="name" name="name" required maxlength="50" value="<?= html_escape($in['name']) ?>">
				</div>
				<div class="form-group">
					<label class="small font-weight-bold" for="description">Deskripsi</label>
					<input type="text" class="form-control" id="description" name="description" maxlength="255" value="<?= html_escape($in['description']) ?>">
				</div>
				<div class="form-group">
					<label class="small font-weight-bold d-block">Hak Akses</label>
					<?php foreach ($allPerms as $p): ?>
					<div class="custom-control custom-checkbox">
						<input type="checkbox" class="custom-control-input" id="p_<?= html_escape($p['code']) ?>" name="perms[]" value="<?= html_escape($p['code']) ?>" <?= in_array($p['code'], $checked, TRUE) ? 'checked' : '' ?>>
						<label class="custom-control-label" for="p_<?= html_escape($p['code']) ?>">
							<code><?= html_escape($p['code']) ?></code> — <?= html_escape($p['description']) ?>
						</label>
					</div>
					<?php endforeach; ?>
				</div>
				<button type="submit" class="btn btn-warning font-weight-bold"><i class="fas fa-save mr-1"></i> Simpan</button>
				<a href="<?= site_url('roles') ?>" class="btn btn-light">Batal</a>
			</form>
		</div>
	</div>
</div>
