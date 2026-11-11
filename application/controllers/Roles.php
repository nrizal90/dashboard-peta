<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Kelola role & hak akses (permission 'role.manage'). Role sistem terkunci. */
class Roles extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		auth_require('role.manage');
	}

	public function index()
	{
		$roles = auth_db()->query(
			'SELECT r.*,
			        (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count,
			        (SELECT GROUP_CONCAT(rp.permission_code ORDER BY rp.permission_code SEPARATOR ", ")
			           FROM role_permissions rp WHERE rp.role_id = r.id) AS perms
			   FROM roles r ORDER BY r.is_system DESC, r.name'
		)->result_array();
		$this->render('roles/index', array('title' => 'Kelola Role', 'roles' => $roles));
	}

	/** GET: form tambah/ubah. POST: simpan role + permission. */
	public function form($id = NULL)
	{
		$db   = auth_db();
		$role = NULL;
		if ($id !== NULL)
		{
			$role = $db->get_where('roles', array('id' => (int) $id))->row_array();
			if ( ! $role) { show_404(); }
			if ($role['is_system']) { show_error('Role sistem tidak bisa diubah.', 403, 'Akses Ditolak'); }
		}

		// Anti-eskalasi: hanya boleh memberi hak akses yang dimiliki sendiri,
		// dan tak boleh mengubah role yang hak aksesnya melebihi hak sendiri.
		$mine     = auth_user()['perms'];
		$allPerms = $db->where_in('code', $mine)->order_by('code')->get('permissions')->result_array();
		$errors   = array();
		$in       = $role ?: array('name' => '', 'description' => '');
		$checked  = $role
			? array_column($db->get_where('role_permissions', array('role_id' => (int) $role['id']))->result_array(), 'permission_code')
			: array();
		if (array_diff($checked, $mine)) { show_error('Tidak boleh mengubah role dengan hak akses melebihi hak Anda.', 403, 'Akses Ditolak'); }

		if ($this->input->method() === 'post')
		{
			$in = array(
				'name'        => trim((string) $this->input->post('name')),
				'description' => trim((string) $this->input->post('description')),
			);
			// Hanya permission yang benar-benar ada di tabel.
			$checked = array_values(array_intersect(
				array_column($allPerms, 'code'),
				(array) $this->input->post('perms')
			));

			if ($in['name'] === '' || mb_strlen($in['name']) > 50) { $errors[] = 'Nama role wajib, maksimal 50 karakter.'; }
			elseif ($db->where('name', $in['name'])->where('id !=', $role ? (int) $role['id'] : 0)->count_all_results('roles'))
			{
				$errors[] = 'Nama role sudah dipakai.';
			}
			if (mb_strlen($in['description']) > 255) { $errors[] = 'Deskripsi maksimal 255 karakter.'; }

			if ( ! $errors)
			{
				$db->trans_start();
				if ($role) { $db->update('roles', $in, array('id' => (int) $role['id'])); $rid = (int) $role['id']; }
				else       { $db->insert('roles', $in); $rid = (int) $db->insert_id(); }
				$db->delete('role_permissions', array('role_id' => $rid));
				foreach ($checked as $code)
				{
					$db->insert('role_permissions', array('role_id' => $rid, 'permission_code' => $code));
				}
				$db->trans_complete();

				if ($db->trans_status())
				{
					$this->session->set_flashdata('ok', 'Role "' . $in['name'] . '" tersimpan.');
					redirect('roles');
				}
				$errors[] = 'Gagal menyimpan role.';
			}
		}

		$this->render('roles/form', array(
			'title'    => $role ? 'Ubah Role' : 'Tambah Role',
			'role'     => $role,
			'in'       => $in,
			'checked'  => $checked,
			'allPerms' => $allPerms,
			'errors'   => $errors,
		));
	}

	/** POST: hapus role (bukan role sistem & tidak sedang dipakai user). */
	public function delete($id)
	{
		if ($this->input->method() !== 'post') { show_404(); }
		$db   = auth_db();
		$role = $db->get_where('roles', array('id' => (int) $id))->row_array();

		if ( ! $role || $role['is_system'])
		{
			$this->session->set_flashdata('err', 'Role sistem tidak bisa dihapus.');
		}
		elseif ($db->where('role_id', (int) $id)->count_all_results('users'))
		{
			$this->session->set_flashdata('err', 'Role masih dipakai user — pindahkan user ke role lain dulu.');
		}
		else
		{
			$db->delete('roles', array('id' => (int) $id));
			$this->session->set_flashdata('ok', 'Role "' . $role['name'] . '" dihapus.');
		}
		redirect('roles');
	}

	private function render($view, array $data)
	{
		$data['active'] = 'roles';
		$this->load->view('templates/header', $data);
		$this->load->view('templates/sidebar', $data);
		$this->load->view('templates/topbar', $data);
		$this->load->view($view, $data);
		$this->load->view('templates/footer', $data);
	}
}
