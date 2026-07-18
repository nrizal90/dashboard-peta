/**
 * vokasi-api.js — pengganti backend PHP untuk versi statis (GitHub Pages).
 *
 * Memuat file JSON di data/vokasi/ lalu meniru endpoint /api/* yang dulu
 * dilayani application/controllers/Api.php + libraries/Vokasi_repo.php.
 * Semua logika filter & agregat dijalankan di browser.
 *
 * Cara pakai: muat script ini SEBELUM dashboard.js / wall.js. Ia meng-override
 * window.fetch hanya untuk URL yang mengarah ke "api/..." (relatif); request
 * lain (tile peta, dsb.) diteruskan ke fetch asli.
 *
 * Aturan penting yang dipertahankan dari Vokasi_repo (DASHBOARD_SPEC bag. 2):
 *  - 2.1 Filter sektor/jabatan diresolusi ke set lembaga_id unik dulu (dedup).
 *  - 2.2 Semua agregat default hanya lembaga is_primary = true.
 */
(function () {
	'use strict';

	var realFetch = window.fetch ? window.fetch.bind(window) : null;
	var BASE = 'data/vokasi/';
	var FILES = [
		'lembaga', 'lembaga_sektor', 'summary', 'agg_provinsi',
		'agg_provinsi_sektor', 'ref_provinsi', 'ref_kota', 'ref_sektor', 'ref_jabatan'
	];

	var cache = {};      // hasil json_decode per file
	var derived = {};    // subset turunan (primary, index by id)
	var ready = null;    // promise pemuatan

	function init() {
		if (ready) { return ready; }
		ready = Promise.all(FILES.map(function (n) {
			return realFetch(BASE + n + '.json')
				.then(function (r) { return r.json(); })
				.then(function (d) { cache[n] = Array.isArray(d) || (d && typeof d === 'object') ? d : []; });
		}));
		return ready;
	}

	function get(n) { return cache[n] || []; }

	// ------------------------------------------------------------------
	// Subset & index turunan
	// ------------------------------------------------------------------
	function primary() {
		if (derived.primary) { return derived.primary; }
		derived.primary = get('lembaga').filter(function (r) { return !!r.is_primary; });
		return derived.primary;
	}

	function indexById() {
		if (derived.byId) { return derived.byId; }
		var idx = {};
		get('lembaga').forEach(function (r) { idx[r.id] = r; });
		derived.byId = idx;
		return idx;
	}

	function find(id) { return indexById()[+id] || null; }

	function sektorOf(id) {
		id = +id;
		return get('lembaga_sektor').filter(function (rel) { return +rel.lembaga_id === id; });
	}

	function dupGroupOf(row) {
		if (!row || !row.dup_group) { return []; }
		var out = [];
		get('lembaga').forEach(function (r) {
			if (r.dup_group === row.dup_group && r.id !== row.id) {
				out.push({ id: r.id, uid: r.uid, email: r.email, is_primary: r.is_primary });
			}
		});
		return out;
	}

	// ------------------------------------------------------------------
	// Referensi (dropdown filter)
	// ------------------------------------------------------------------
	function pulauList() {
		var acc = {};
		primary().forEach(function (r) {
			var p = r.pulau;
			if (p == null || p === '') { return; }
			acc[p] = (acc[p] || 0) + 1;
		});
		var out = Object.keys(acc).map(function (nama) {
			return { nama: nama, jumlah_lembaga: acc[nama] };
		});
		out.sort(function (a, b) { return b.jumlah_lembaga - a.jumlah_lembaga; });
		return out;
	}

	function refs() {
		return {
			provinsi: get('ref_provinsi'),
			kota: get('ref_kota'),
			sektor: get('ref_sektor'),
			jabatan: get('ref_jabatan'),
			pulau: pulauList()
		};
	}

	// ------------------------------------------------------------------
	// Filter inti
	// ------------------------------------------------------------------
	function asList(p, key) {
		if (p[key] == null || p[key] === '') { return []; }
		var v = p[key];
		if (!Array.isArray(v)) { v = String(v).split(','); }
		return v.map(function (x) { return String(x).trim(); })
			.filter(function (x) { return x !== ''; });
	}

	function norm(s) { return String(s == null ? '' : s).trim().toLowerCase(); }

	function toSet(arr) {
		var s = {};
		arr.forEach(function (x) { s[x] = true; });
		return s;
	}

	// Aturan 2.1: resolusi relasi sektor/jabatan -> set lembaga_id unik.
	function idsBySektorJabatan(sektorSlugs, jabatanSlugs) {
		if (!sektorSlugs.length && !jabatanSlugs.length) { return null; }
		var sSet = sektorSlugs.length ? toSet(sektorSlugs) : null;
		var jSet = jabatanSlugs.length ? toSet(jabatanSlugs) : null;
		var ids = {};
		get('lembaga_sektor').forEach(function (rel) {
			if (sSet && !sSet[rel.sektor_slug]) { return; }
			if (jSet && !jSet[rel.jabatan_slug]) { return; }
			ids[+rel.lembaga_id] = true;
		});
		return ids;
	}

	function filter(p, primaryOnly) {
		var rows = primaryOnly ? primary() : get('lembaga');

		var provinsi = asList(p, 'provinsi');
		var sektor = asList(p, 'sektor_slug');
		var jabatan = asList(p, 'jabatan_slug');
		var idSet = idsBySektorJabatan(sektor, jabatan);

		function val(k) { return (p[k] != null && p[k] !== '') ? p[k] : null; }
		var kota = val('kota_slug'), pulau = val('pulau'), owner = val('ownership'),
			jenis = val('jenis'), legal = val('status_legalitas'), coord = val('coord_source');
		var kmin = val('kapasitas_min') != null ? parseInt(p.kapasitas_min, 10) : null;
		var kmax = val('kapasitas_max') != null ? parseInt(p.kapasitas_max, 10) : null;
		var q = val('q') != null ? norm(p.q) : null;
		var onlyReal = !!p.only_original && p.only_original !== '0' && p.only_original !== 'false';

		var out = [];
		rows.forEach(function (r) {
			if (idSet && !idSet[+r.id]) { return; }
			if (provinsi.length && provinsi.indexOf(String(r.provinsi_kode)) < 0) { return; }
			if (kota !== null && r.kota_slug !== kota) { return; }
			if (pulau !== null && r.pulau !== pulau) { return; }
			if (owner !== null && r.ownership !== owner) { return; }
			if (jenis !== null && r.jenis !== jenis) { return; }
			if (legal !== null && r.status_legalitas !== legal) { return; }
			if (coord !== null && r.coord_source !== coord) { return; }
			if (onlyReal && r.coord_source !== 'original') { return; }

			var kap = r.kapasitas == null ? 0 : parseInt(r.kapasitas, 10);
			if (kmin !== null && kap < kmin) { return; }
			if (kmax !== null && kap > kmax) { return; }
			if (q !== null && norm(r.nama).indexOf(q) < 0) { return; }

			out.push(r);
		});
		return out;
	}

	function points(p) {
		return filter(p, true).map(function (r) {
			return {
				id: r.id,
				n: r.nama,
				lat: r.lat,
				lng: r.lng,
				p: r.provinsi_kode,
				o: r.ownership === 'Pemerintah' ? 'P' : 'N',
				k: r.kapasitas == null ? 0 : parseInt(r.kapasitas, 10),
				cs: r.coord_source === 'original' ? 'o' : 'c'
			};
		});
	}

	function topN(map, n) {
		return Object.keys(map)
			.map(function (label) { return { label: label, value: map[label] }; })
			.sort(function (a, b) { return b.value - a.value; })
			.slice(0, n);
	}

	function stats(p) {
		var rows = filter(p, true);
		var idSet = {}, prov = {}, totalKapasitas = 0;
		var ownership = { 'Pemerintah': 0, 'Non Pemerintah': 0 };
		var fun = {
			legalitas: { accepted: 0, rejected: 0, pending: 0, not_submitted: 0, revised: 0 },
			fasilitas: { accepted: 0, rejected: 0, pending: 0, not_submitted: 0, revised: 0 },
			program: { accepted: 0, rejected: 0, pending: 0, not_submitted: 0, revised: 0 }
		};
		var fieldOf = { legalitas: 'status_legalitas', fasilitas: 'status_fasilitas', program: 'status_program' };

		rows.forEach(function (r) {
			idSet[+r.id] = true;
			totalKapasitas += (r.kapasitas == null ? 0 : parseInt(r.kapasitas, 10));
			if (ownership[r.ownership] != null) { ownership[r.ownership]++; }
			prov[r.provinsi] = (prov[r.provinsi] || 0) + 1;
			Object.keys(fieldOf).forEach(function (tahap) {
				var v = r[fieldOf[tahap]] != null ? r[fieldOf[tahap]] : 'not_submitted';
				if (fun[tahap][v] == null) { fun[tahap][v] = 0; }
				fun[tahap][v]++;
			});
		});

		var sektor = {}, jabatan = {};
		get('lembaga_sektor').forEach(function (rel) {
			if (!idSet[+rel.lembaga_id]) { return; }
			sektor[rel.sektor] = (sektor[rel.sektor] || 0) + 1;
			jabatan[rel.jabatan] = (jabatan[rel.jabatan] || 0) + 1;
		});

		return {
			jumlah_lembaga: rows.length,
			total_kapasitas: totalKapasitas,
			ownership: ownership,
			top_sektor: topN(sektor, 10),
			top_jabatan: topN(jabatan, 10),
			top_provinsi: topN(prov, 10),
			verifikasi: fun
		};
	}

	function lembaga(id) {
		if (id == null || !/^\d+$/.test(String(id))) { return { error: 'ID tidak valid' }; }
		var row = find(id);
		if (!row) { return { error: 'Lembaga tidak ditemukan' }; }
		return { lembaga: row, sektor: sektorOf(id), duplikat: dupGroupOf(row) };
	}

	function choropleth(metric) {
		var allowed = { jumlah: 1, kapasitas: 1, kepadatan_sektor: 1 };
		if (!allowed[metric]) { metric = 'jumlah'; }
		return get('agg_provinsi').map(function (a) {
			var val;
			if (metric === 'kapasitas') { val = a.total_kapasitas || 0; }
			else if (metric === 'kepadatan_sektor') { val = a.jumlah_sektor || 0; }
			else { val = a.jumlah_lembaga || 0; }
			return { provinsi_kode: a.provinsi_kode, provinsi: a.provinsi, value: val };
		});
	}

	var api = {
		init: init,
		summary: function () { return get('summary'); },
		refs: refs,
		points: points,
		stats: stats,
		lembaga: lembaga,
		choropleth: choropleth,
		gap: function () { return get('agg_provinsi_sektor'); }
	};
	window.VokasiApi = api;

	// ------------------------------------------------------------------
	// Router: petakan URL "api/..." ke method di atas.
	// ------------------------------------------------------------------
	function route(url) {
		var qi = url.indexOf('?');
		var path = qi >= 0 ? url.slice(0, qi) : url;
		var params = {};
		if (qi >= 0) {
			new URLSearchParams(url.slice(qi + 1)).forEach(function (v, k) { params[k] = v; });
		}
		var m = path.match(/api\/?(.*)$/);
		var r = m ? m[1].replace(/\/+$/, '') : '';

		if (r === 'points') { return points(params); }
		if (r === 'stats') { return stats(params); }
		if (r === 'refs') { return refs(); }
		if (r === 'summary') { return api.summary(); }
		if (r === 'choropleth') { return choropleth(params.metric); }
		if (r === 'gap') { return api.gap(); }
		if (r.indexOf('lembaga/') === 0) { return lembaga(r.split('/')[1]); }
		return null;
	}

	function isApiUrl(url) {
		return typeof url === 'string' && !/^https?:\/\//i.test(url) && /(^|\/)api(\/|$|\?)/.test(url);
	}

	// Override fetch hanya untuk URL API relatif; sisanya diteruskan.
	window.fetch = function (input, opts) {
		var url = (typeof input === 'string') ? input : (input && input.url);
		if (isApiUrl(url)) {
			return init().then(function () {
				var data = route(url);
				return {
					ok: true,
					status: 200,
					json: function () { return Promise.resolve(data); },
					text: function () { return Promise.resolve(JSON.stringify(data)); }
				};
			});
		}
		return realFetch ? realFetch(input, opts) : Promise.reject(new Error('fetch tidak tersedia'));
	};
})();
