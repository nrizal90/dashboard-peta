<?php $csrf = '<input type="hidden" name="' . $this->security->get_csrf_token_name() . '" value="' . $this->security->get_csrf_hash() . '">'; ?>
<div class="container-fluid">
	<div class="d-sm-flex align-items-center justify-content-between mb-3">
		<h1 class="h3 mb-0 text-gray-800">Kelola User</h1>
		<a href="<?= site_url('users/form') ?>" class="btn btn-sm btn-warning font-weight-bold"><i class="fas fa-user-plus mr-1"></i> Tambah User</a>
	</div>
	<?php $this->load->view('templates/flash'); ?>
	<div class="card shadow mb-4">
		<div class="card-body table-responsive">
			<table class="table table-sm table-hover mb-0">
				<thead><tr><th>Username</th><th>Nama</th><th>Role</th><th>Status</th><th>Login Terakhir</th><th class="text-right">Aksi</th></tr></thead>
				<tbody>
				<?php foreach ($users as $u): ?>
					<tr>
						<td class="font-weight-bold"><?= html_escape($u['username']) ?></td>
						<td><?= html_escape($u['full_name']) ?></td>
						<td><?= html_escape($u['role_name']) ?></td>
						<td><?= $u['is_active'] ? '<span class="badge badge-success">Aktif</span>' : '<span class="badge badge-secondary">Nonaktif</span>' ?></td>
						<td class="small"><?= $u['last_login_at'] ? html_escape($u['last_login_at']) : '-' ?></td>
						<td class="text-right text-nowrap">
							<?php $isMe = (int) $u['id'] === (int) auth_user()['id']; ?>
							<?php if ($isMe || in_array((int) $u['role_id'], $grantable, TRUE)): ?>
							<a href="<?= site_url('users/form/' . $u['id']) ?>" class="btn btn-sm btn-light" title="Ubah"><i class="fas fa-edit"></i></a>
							<?php endif; ?>
							<?php if ( ! $isMe && in_array((int) $u['role_id'], $grantable, TRUE)): ?>
							<form method="post" action="<?= site_url('users/delete/' . $u['id']) ?>" class="d-inline" onsubmit="return confirm('Hapus user ini?')">
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
