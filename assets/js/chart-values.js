// Plugin global Chart.js 2.x: tampilkan angka di setiap chart tanpa hover.
//  - bar / horizontalBar / line : angka di ujung bar / di atas titik.
//  - bar bertumpuk (stacked)     : angka di tengah tiap segmen (bila muat) + total tebal di ujung tumpukan.
//  - doughnut / pie             : angka + persen ditaruh di legend (slice kecil tidak muat diberi label).
(function () {
	if (typeof Chart === 'undefined') { return; }
	var fmt = function (v) { return Number(v).toLocaleString('id-ID'); };

	function donutLegend(chart) {
		var d = chart.data.datasets[0] || { data: [], backgroundColor: [] };
		var sum = d.data.reduce(function (a, b) { return a + (Number(b) || 0); }, 0);
		return chart.data.labels.map(function (lb, i) {
			var v = Number(d.data[i]) || 0;
			var pct = sum ? (v / sum * 100).toLocaleString('id-ID', { maximumFractionDigits: 1 }) : '0';
			var col = Array.isArray(d.backgroundColor) ? d.backgroundColor[i] : d.backgroundColor;
			var meta = chart.getDatasetMeta(0);
			return { text: lb + ' · ' + fmt(v) + ' (' + pct + '%)',
				fillStyle: col, strokeStyle: col, lineWidth: 0, index: i,
				hidden: !!(meta.data[i] && meta.data[i].hidden) };
		});
	}

	function isStacked(chart) {
		var s = chart.options.scales || {};
		return [].concat(s.xAxes || [], s.yAxes || []).some(function (a) { return a && a.stacked; });
	}

	// Teks putih di atas warna gelap, abu tua di atas warna terang.
	function textOn(col) {
		var m = String(col).match(/\d+/g), r, g, b;
		if (/^#/.test(col)) {
			var h = col.slice(1); if (h.length === 3) { h = h.replace(/./g, '$&$&'); }
			r = parseInt(h.substr(0, 2), 16); g = parseInt(h.substr(2, 2), 16); b = parseInt(h.substr(4, 2), 16);
		} else if (m) { r = +m[0]; g = +m[1]; b = +m[2]; } else { return '#2b2b33'; }
		return (r * 299 + g * 587 + b * 114) / 1000 < 150 ? '#ffffff' : '#2b2b33';
	}

	function drawStacked(chart, horiz) {
		var ctx = chart.ctx, totals = [], ends = [];
		ctx.save();
		ctx.font = '700 10px Nunito, sans-serif';
		ctx.textAlign = 'center';
		ctx.textBaseline = 'middle';
		chart.data.datasets.forEach(function (ds, i) {
			var meta = chart.getDatasetMeta(i);
			if (meta.hidden) { return; }
			meta.data.forEach(function (el, j) {
				var v = Number(ds.data[j]) || 0, m = el._model;
				totals[j] = (totals[j] || 0) + v;
				ends[j] = horiz ? Math.max(ends[j] || -Infinity, m.x) : Math.min(ends[j] || Infinity, m.y);
				if (!v) { return; }
				var txt = fmt(v), size = horiz ? Math.abs(m.x - m.base) : Math.abs(m.base - m.y);
				if (size < ctx.measureText(txt).width + 6) { return; } // segmen terlalu sempit
				ctx.fillStyle = textOn(Array.isArray(ds.backgroundColor) ? ds.backgroundColor[j] : ds.backgroundColor);
				ctx.fillText(txt, horiz ? (m.x + m.base) / 2 : m.x, horiz ? m.y : (m.y + m.base) / 2);
			});
		});
		// Total per tumpukan di ujungnya.
		var meta0 = chart.getDatasetMeta(0);
		ctx.font = '800 11px Nunito, sans-serif';
		ctx.fillStyle = '#2b2b33';
		ctx.textAlign = horiz ? 'left' : 'center';
		ctx.textBaseline = horiz ? 'middle' : 'bottom';
		totals.forEach(function (tot, j) {
			var m = meta0.data[j] && meta0.data[j]._model;
			if (!m) { return; }
			ctx.fillText(fmt(tot), horiz ? ends[j] + 4 : m.x, horiz ? m.y : ends[j] - 3);
		});
		ctx.restore();
	}

	Chart.plugins.register({
		id: 'dgValues',
		beforeInit: function (chart) {
			var t = chart.config.type, o = chart.options;
			if (t === 'doughnut' || t === 'pie') {
				o.legend = o.legend || {}; o.legend.labels = o.legend.labels || {};
				// Chart.js selalu mengisi generateLabels bawaan; timpa hanya jika halaman tidak menyetel sendiri.
				var g = o.legend.labels.generateLabels, td = Chart.defaults[t] || {};
				var isDefault = !g || g === Chart.defaults.global.legend.labels.generateLabels ||
					(td.legend && td.legend.labels && g === td.legend.labels.generateLabels);
				if (isDefault) { o.legend.labels.generateLabels = donutLegend; }
			} else if (t === 'horizontalBar' || t === 'bar' || t === 'line') {
				o.layout = o.layout || {}; o.layout.padding = o.layout.padding || {};
				if (typeof o.layout.padding === 'object') {
					if (t === 'horizontalBar') { o.layout.padding.right = Math.max(o.layout.padding.right || 0, 40); }
					else { o.layout.padding.top = Math.max(o.layout.padding.top || 0, 18); }
				}
			}
		},
		afterDatasetsDraw: function (chart) {
			var t = chart.config.type;
			if (t !== 'bar' && t !== 'horizontalBar' && t !== 'line') { return; }
			var ctx = chart.ctx, horiz = t === 'horizontalBar';
			if (t !== 'line' && isStacked(chart)) { return drawStacked(chart, horiz); }
			ctx.save();
			ctx.font = '600 11px Nunito, sans-serif';
			ctx.fillStyle = '#5a5a66';
			ctx.textAlign = horiz ? 'left' : 'center';
			ctx.textBaseline = horiz ? 'middle' : 'bottom';
			chart.data.datasets.forEach(function (ds, i) {
				var meta = chart.getDatasetMeta(i);
				if (meta.hidden) { return; }
				meta.data.forEach(function (el, j) {
					var v = ds.data[j];
					if (v === null || v === undefined || el.hidden) { return; }
					var m = el._model;
					ctx.fillText(fmt(v), horiz ? m.x + 4 : m.x, horiz ? m.y : m.y - (t === 'line' ? 8 : 3));
				});
			});
			ctx.restore();
		}
	});
})();
