<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Login/logout dashboard. Akun tetap (admin & guest) di config/auth.php —
 * tanpa DB. Satu-satunya controller yang dikecualikan Auth_guard.
 */
class Auth extends CI_Controller {

	public function __construct()
	{
		parent::__construct();
		$this->config->load('auth');
		$this->load->driver('cache', array('adapter' => 'file'));
	}

	/** GET: form login. POST: proses login. */
	public function login()
	{
		if ($this->session->userdata('role'))
		{
			redirect('');
		}

		$error = NULL;
		if ($this->input->method() === 'post')
		{
			$error = $this->attempt(
				(string) $this->input->post('username'),
				(string) $this->input->post('password')
			);
			if ($error === NULL)
			{
				$next = (string) $this->session->userdata('after_login');
				$this->session->unset_userdata('after_login');
				redirect($next !== '' ? $next : '');
			}
		}

		$this->load->view('auth/login', array('error' => $error));
	}

	/** POST: logout (POST + CSRF agar tidak bisa dipicu lewat link/gambar). */
	public function logout()
	{
		if ($this->input->method() === 'post')
		{
			$this->session->sess_destroy();
		}
		redirect('login');
	}

	/** @return string|null pesan error, NULL bila sukses */
	private function attempt($username, $password)
	{
		$lockKey  = 'login_fail_' . md5($this->input->ip_address());
		$fails    = (int) $this->cache->get($lockKey);
		$max      = (int) $this->config->item('auth_max_attempts');
		$lockout  = (int) $this->config->item('auth_lockout');

		if ($fails >= $max)
		{
			return 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . ceil($lockout / 60) . ' menit.';
		}

		$users = (array) $this->config->item('auth_users');
		$key   = strtolower(trim($username));
		$user  = isset($users[$key]) ? $users[$key] : NULL;
		// Tetap jalankan password_verify walau user tak ada → waktu respons seragam.
		$hash  = $user ? $user['hash'] : '$2y$12$8LzP7eRzoneVdiHK2a0.iuHQsys85qSeCr6jrI0LkfSDFSLQJSS9y';
		$ok    = password_verify($password, $hash) && $user !== NULL;

		if ( ! $ok)
		{
			$this->cache->save($lockKey, $fails + 1, $lockout);
			log_message('error', 'Login gagal untuk "' . substr($key, 0, 50) . '" dari ' . $this->input->ip_address());
			return 'Username atau password salah.';
		}

		$this->cache->delete($lockKey);
		$this->session->sess_regenerate(TRUE); // cegah session fixation
		$this->session->set_userdata(array('username' => $key, 'role' => $user['role']));
		return NULL;
	}
}
