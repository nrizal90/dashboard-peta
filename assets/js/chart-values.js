// Plugin global Chart.js 2.x: tampilkan angka di setiap chart tanpa hover.
//  - bar / horizontalBar / line : angka di ujung bar / di atas titik.
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
