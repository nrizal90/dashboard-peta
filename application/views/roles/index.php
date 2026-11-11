<?php $csrf = '<input type="hidden" name="' . $this->security->get_csrf_token_name() . '" value="' . $this->security->get_csrf_hash() . '">'; ?>
<div class="container-fluid">
	<div class="d-sm-flex align-items-center justify-content-between mb-3">
		<h1 class="h3 mb-0 text-gray-800">Kelola Role</h1>
		<a href="<?= site_url('roles/form') ?>" class="btn btn-sm btn-warning font-weight-bold"><i class="fas fa-plus mr-1"></i> Tambah Role</a>
	</div>
	<?php $this->load->view('templates/flash'); ?>
	<div class="card shadow mb-4">
		<div class="card-body table-responsive">
			<table class="table table-sm table-hover mb-0">
				<thead><tr><th>Role</th><th>Deskripsi</th><th>Hak Akses</th><th>User</th><th class="text-right">Aksi</th></tr></thead>
				<tbody>
				<?php foreach ($roles as $r): ?>
					<tr>
						<td class="font-weight-bold"><?= html_escape($r['name']) ?><?= $r['is_system'] ? ' <span class="badge badge-dark">sistem</span>' : '' ?></td>
						<td class="small"><?= html_escape($r['description']) ?></td>
						<td class="small"><?= $r['perms'] !== NULL ? html_escape($r['perms']) : '<span class="text-muted">— (setara guest)</span>' ?></td>
						<td><?= (int) $r['user_count'] ?></td>
						<td class="text-right text-nowrap">
							<?php if ( ! $r['is_system']): ?>
							<a href="<?= site_url('roles/form/' . $r['id']) ?>" class="btn btn-sm btn-light" title="Ubah"><i class="fas fa-edit"></i></a>
							<form method="post" action="<?= site_url('roles/delete/' . $r['id']) ?>" class="d-inline" onsubmit="return confirm('Hapus role ini?')">
								<?= $csrf ?><button class="btn btn-sm btn-light text-danger" title="Hapus"><i class="fas fa-trash"></i></button>
							</form>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
