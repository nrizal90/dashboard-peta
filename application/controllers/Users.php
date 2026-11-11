<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Kelola user (permission 'user.manage'). */
class Users extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		auth_require('user.manage');
	}

	public function index()
	{
		$users = auth_db()->query(
			'SELECT u.*, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.username'
		)->result_array();
		$this->render('users/index', array('title' => 'Kelola User', 'users' => $users, 'grantable' => $this->grantableRoleIds()));
	}

	/** GET: form tambah/ubah. POST: simpan. */
	public function form($id = NULL)
	{
		$db   = auth_db();
		$user = NULL;
		if ($id !== NULL)
		{
			$user = $db->get_where('users', array('id' => (int) $id))->row_array();
			if ( ! $user) { show_404(); }
		}

		$isSelf    = $user && (int) $user['id'] === (int) auth_user()['id'];
		$grantable = $this->grantableRoleIds();
		if ($user && ! $isSelf && ! in_array((int) $user['role_id'], $grantable, TRUE))
		{
			show_error('Tidak boleh mengubah user dengan hak akses melebihi hak Anda.', 403, 'Akses Ditolak');
		}
		$errors = array();
		$in     = $user ?: array('username' => '', 'full_name' => '', 'role_id' => '', 'is_active' => 1);

		if ($this->input->method() === 'post')
		{
			$in = array(
				'username'  => strtolower(trim((string) $this->input->post('username'))),
				'full_name' => trim((string) $this->input->post('full_name')),
				'role_id'   => (int) $this->input->post('role_id'),
				'is_active' => $this->input->post('is_active') ? 1 : 0,
			);
			$pass  = (string) $this->input->post('password');
			$pass2 = (string) $this->input->post('password_confirm');

			// Cegah admin mengunci dirinya sendiri.
			if ($isSelf)
			{
				$in['role_id']   = (int) $user['role_id'];
				$in['is_active'] = 1;
			}

			if ( ! preg_match('/^[a-z0-9._-]{3,50}$/', $in['username']))
			{
				$errors[] = 'Username 3–50 karakter: huruf kecil, angka, titik, minus, garis bawah.';
			}
			elseif ($db->where('username', $in['username'])->where('id !=', $user ? (int) $user['id'] : 0)->count_all_results('users'))
			{
				$errors[] = 'Username sudah dipakai.';
			}
			if (mb_strlen($in['full_name']) > 100) { $errors[] = 'Nama lengkap maksimal 100 karakter.'; }
			if ( ! $isSelf && ! in_array($in['role_id'], $grantable, TRUE)) { $errors[] = 'Role tidak valid atau melebihi hak akses Anda.'; }
			if ($user === NULL && $pass === '') { $errors[] = 'Password wajib diisi.'; }
			if ($pass !== '' && strlen($pass) < 8) { $errors[] = 'Password minimal 8 karakter.'; }
			if ($pass !== $pass2) { $errors[] = 'Konfirmasi password tidak sama.'; }

			if ( ! $errors)
			{
				if ($pass !== '') { $in['password_hash'] = password_hash($pass, PASSWORD_DEFAULT); }
				$user ? $db->update('users', $in, array('id' => (int) $user['id'])) : $db->insert('users', $in);
				$this->session->set_flashdata('ok', 'User "' . $in['username'] . '" tersimpan.');
				redirect('users');
			}
		}

		$this->render('users/form', array(
			'title'  => $user ? 'Ubah User' : 'Tambah User',
			'user'   => $user,
			'in'     => $in,
			'isSelf' => $isSelf,
			'errors' => $errors,
			'roles'  => $db->where_in('id', $grantable ?: array(0))->order_by('name')->get('roles')->result_array(),
		));
	}

	/** POST: hapus user. */
	public function delete($id)
	{
		if ($this->input->method() !== 'post') { show_404(); }
		$id = (int) $id;
		if ($id === (int) auth_user()['id'])
		{
			$this->session->set_flashdata('err', 'Tidak bisa menghapus akun sendiri.');
		}
		elseif (($target = auth_db()->get_where('users', array('id' => $id))->row_array())
			&& ! in_array((int) $target['role_id'], $this->grantableRoleIds(), TRUE))
		{
			$this->session->set_flashdata('err', 'Tidak boleh menghapus user dengan hak akses melebihi hak Anda.');
		}
		else
		{
			auth_db()->delete('users', array('id' => $id));
			$this->session->set_flashdata('ok', 'User dihapus.');
		}
		redirect('users');
	}

	/**
	 * ID role yang hak aksesnya ⊆ hak akses user login. Hanya role ini yang boleh
	 * diberikan, dan hanya user ber-role ini yang boleh diubah/dihapus —
	 * cegah eskalasi (mis. pemegang user.manage mengangkat diri jadi Administrator).
	 */
	private function grantableRoleIds()
	{
		$mine  = auth_user()['perms'];
		$perms = array();
		foreach (auth_db()->get('role_permissions')->result_array() as $rp)
		{
			$perms[$rp['role_id']][] = $rp['permission_code'];
		}
		$ids = array();
		foreach (auth_db()->select('id')->get('roles')->result_array() as $r)
		{
			$p = isset($perms[$r['id']]) ? $perms[$r['id']] : array();
			if ( ! array_diff($p, $mine)) { $ids[] = (int) $r['id']; }
		}
		return $ids;
	}

	private function render($view, array $data)
	{
		$data['active'] = 'users';
		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/topbar', $data);
		$this->load->view($view, $data);
		$this->load->view('templates/footer', $data);
	}
}
