/**
 * Product Report charts — dependency-free inline SVG (donut + line).
 */
(function () {
  'use strict';

  var SVG_NS = 'http://www.w3.org/2000/svg';
  var SERIES_VARS = ['--nw-report-series-1', '--nw-report-series-2', '--nw-report-series-3', '--nw-report-series-4', '--nw-report-series-5', '--nw-report-series-6', '--nw-report-series-7'];

  function cssVar(root, name) {
    return getComputedStyle(root).getPropertyValue(name).trim();
  }

  function el(tag, attrs) {
    var node = document.createElementNS(SVG_NS, tag);
    for (var key in attrs) {
      if (Object.prototype.hasOwnProperty.call(attrs, key)) {
        node.setAttribute(key, attrs[key]);
      }
    }
    return node;
  }

  function createTooltip(container) {
    var tip = document.createElement('div');
    tip.className = 'nw-report-tooltip';
    container.appendChild(tip);
    return tip;
  }

  function showTooltip(tip, container, x, y, html) {
    tip.innerHTML = html;
    var rect = container.getBoundingClientRect();
    tip.style.left = Math.max(40, Math.min(rect.width - 40, x)) + 'px';
    tip.style.top = Math.max(10, y - 10) + 'px';
    tip.classList.add('is-visible');
  }

  function hideTooltip(tip) {
    tip.classList.remove('is-visible');
  }

  function renderDonut(container, slices, root) {
    if (!slices.length) {
      container.innerHTML = '<p class="nw-report-chart__empty">No activity yet.</p>';
      return;
    }

    var total = slices.reduce(function (sum, s) { return sum + s.value; }, 0);
    if (total <= 0) {
      container.innerHTML = '<p class="nw-report-chart__empty">No activity yet.</p>';
      return;
    }

    var size = 220;
    var cx = size / 2;
    var cy = size / 2;
    var r = 82;
    var thickness = 34;
    var circumference = 2 * Math.PI * r;
    var gapPx = 3;

    var wrap = document.createElement('div');
    wrap.className = 'nw-report-chart-body';

    var svg = el('svg', { viewBox: '0 0 ' + size + ' ' + size, width: size, height: size });
    var group = el('g', { transform: 'rotate(-90 ' + cx + ' ' + cy + ')' });

    var cumulative = 0;
    var tooltip = createTooltip(container);
    var otherColor = cssVar(root, '--nw-report-other');

    slices.forEach(function (slice, i) {
      var fraction = slice.value / total;
      var segLen = Math.max(0, fraction * circumference - gapPx);
      var offset = -(cumulative * circumference);
      var color = slice.isOther ? otherColor : cssVar(root, SERIES_VARS[i % SERIES_VARS.length]);

      var circle = el('circle', {
        cx: cx,
        cy: cy,
        r: r,
        fill: 'none',
        stroke: color,
        'stroke-width': thickness,
        'stroke-dasharray': segLen + ' ' + (circumference - segLen),
        'stroke-dashoffset': offset,
        class: slice.url ? 'nw-report-donut-slice nw-report-donut-slice--link' : 'nw-report-donut-slice',
      });

      circle.addEventListener('mousemove', function (evt) {
        var rect = container.getBoundingClientRect();
        var pct = Math.round(fraction * 1000) / 10;
        var html = '<strong>' + escapeHtml(slice.label) + '</strong><br>' +
          pct + '% of activity<br>' +
          'Searches: ' + slice.searches + ' &middot; Views: ' + slice.views + ' &middot; Quotes: ' + slice.orders;
        if (slice.url) {
          html += '<br>Click to open this product';
        }
        showTooltip(tooltip, container, evt.clientX - rect.left, evt.clientY - rect.top, html);
      });
      circle.addEventListener('mouseleave', function () {
        hideTooltip(tooltip);
      });

      if (slice.url) {
        var sliceLink = el('a', { href: slice.url });
        sliceLink.appendChild(circle);
        group.appendChild(sliceLink);
      } else {
        group.appendChild(circle);
      }
      cumulative += fraction;
    });

    svg.appendChild(group);

    var totalLabel = el('text', { x: cx, y: cy - 4, 'text-anchor': 'middle', class: 'nw-report-donut-total' });
    totalLabel.textContent = total.toLocaleString();
    var subLabel = el('text', { x: cx, y: cy + 14, 'text-anchor': 'middle', class: 'nw-report-donut-total-label' });
    subLabel.textContent = 'activity';
    svg.appendChild(totalLabel);
    svg.appendChild(subLabel);

    wrap.appendChild(svg);

    var legend = document.createElement('ul');
    legend.className = 'nw-report-legend';
    slices.forEach(function (slice, i) {
      var color = slice.isOther ? otherColor : cssVar(root, SERIES_VARS[i % SERIES_VARS.length]);
      var item = document.createElement('li');
      var swatch = document.createElement('span');
      swatch.className = 'nw-report-legend__swatch';
      swatch.style.background = color;
      item.appendChild(swatch);
      var pct = Math.round((slice.value / total) * 1000) / 10;
      var name = slice.label + ' (' + pct + '%)';
      if (slice.url) {
        var nameLink = document.createElement('a');
        nameLink.href = slice.url;
        nameLink.textContent = name;
        item.appendChild(nameLink);
      } else {
        item.appendChild(document.createTextNode(name));
      }
      legend.appendChild(item);
    });
    wrap.appendChild(legend);

    container.innerHTML = '';
    container.appendChild(wrap);
  }

  function niceMax(value) {
    if (value <= 0) {
      return 4;
    }
    var magnitude = Math.pow(10, Math.floor(Math.log(value) / Math.LN10));
    var residual = value / magnitude;
    var step;
    if (residual > 5) {
      step = 10;
    } else if (residual > 2) {
      step = 5;
    } else if (residual > 1) {
      step = 2;
    } else {
      step = 1;
    }
    return step * magnitude;
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function renderTimeseries(container, points, labels, root) {
    if (!points.length) {
      container.innerHTML = '<p class="nw-report-chart__empty">No activity yet.</p>';
      return;
    }

    var width = 760;
    var height = 260;
    var marginLeft = 40;
    var marginRight = 12;
    var marginTop = 14;
    var marginBottom = 30;
    var plotW = width - marginLeft - marginRight;
    var plotH = height - marginTop - marginBottom;

    var seriesKeys = ['searches', 'views', 'orders'];
    var seriesColors = [cssVar(root, '--nw-report-series-1'), cssVar(root, '--nw-report-series-2'), cssVar(root, '--nw-report-series-3')];
    var seriesLabels = [labels.searches, labels.views, labels.orders];

    var maxVal = 0;
    points.forEach(function (p) {
      seriesKeys.forEach(function (key) {
        if (p[key] > maxVal) {
          maxVal = p[key];
        }
      });
    });
    var axisMax = niceMax(maxVal);

    function xAt(i) {
      if (points.length === 1) {
        return marginLeft + plotW / 2;
      }
      return marginLeft + (plotW * i) / (points.length - 1);
    }
    function yAt(v) {
      return marginTop + plotH * (1 - v / axisMax);
    }

    var svg = el('svg', { viewBox: '0 0 ' + width + ' ' + height, width: '100%', height: height });

    var ticks = 4;
    for (var t = 0; t <= ticks; t++) {
      var val = (axisMax / ticks) * t;
      var y = yAt(val);
      svg.appendChild(el('line', { x1: marginLeft, x2: width - marginRight, y1: y, y2: y, class: 'nw-report-chart-gridline' }));
      var label = el('text', { x: marginLeft - 6, y: y + 3, 'text-anchor': 'end', class: 'nw-report-chart-axis-label' });
      label.textContent = Math.round(val).toLocaleString();
      svg.appendChild(label);
    }

    var labelStep = Math.max(1, Math.ceil(points.length / 8));
    points.forEach(function (p, i) {
      if (i % labelStep !== 0 && i !== points.length - 1) {
        return;
      }
      var label = el('text', { x: xAt(i), y: height - 8, 'text-anchor': 'middle', class: 'nw-report-chart-axis-label' });
      label.textContent = p.label;
      svg.appendChild(label);
    });

    seriesKeys.forEach(function (key, s) {
      var d = points.map(function (p, i) {
        return (i === 0 ? 'M' : 'L') + xAt(i) + ' ' + yAt(p[key]);
      }).join(' ');
      svg.appendChild(el('path', { d: d, stroke: seriesColors[s], class: 'nw-report-chart-line' }));

      points.forEach(function (p, i) {
        svg.appendChild(el('circle', { cx: xAt(i), cy: yAt(p[key]), r: 3, fill: seriesColors[s], class: 'nw-report-chart-dot' }));
      });
    });

    var hoverLine = el('line', {
      x1: marginLeft, x2: marginLeft, y1: marginTop, y2: marginTop + plotH,
      stroke: cssVar(root, '--nw-report-baseline'), 'stroke-width': 1, opacity: 0,
    });
    svg.appendChild(hoverLine);

    var overlay = el('rect', {
      x: marginLeft, y: marginTop, width: plotW, height: plotH, fill: 'transparent',
    });
    svg.appendChild(overlay);

    var container_div = document.createElement('div');
    container_div.className = 'nw-report-chart-body';
    container_div.style.display = 'block';
    container_div.appendChild(svg);

    var tooltip = createTooltip(container);

    overlay.addEventListener('mousemove', function (evt) {
      var rect = svg.getBoundingClientRect();
      var scaleX = width / rect.width;
      var relX = (evt.clientX - rect.left) * scaleX;
      var index = points.length === 1 ? 0 : Math.round(((relX - marginLeft) / plotW) * (points.length - 1));
      index = Math.max(0, Math.min(points.length - 1, index));
      var p = points[index];

      hoverLine.setAttribute('x1', xAt(index));
      hoverLine.setAttribute('x2', xAt(index));
      hoverLine.setAttribute('opacity', 1);

      var containerRect = container.getBoundingClientRect();
      var px = (xAt(index) / width) * rect.width + (rect.left - containerRect.left);
      var py = (evt.clientY - containerRect.top);

      var html = '<strong>' + escapeHtml(p.label) + '</strong><br>' +
        seriesLabels[0] + ': ' + p.searches + '<br>' +
        seriesLabels[1] + ': ' + p.views + '<br>' +
        seriesLabels[2] + ': ' + p.orders;
      showTooltip(tooltip, container, px, py, html);
    });
    overlay.addEventListener('mouseleave', function () {
      hoverLine.setAttribute('opacity', 0);
      hideTooltip(tooltip);
    });

    var legend = document.createElement('ul');
    legend.className = 'nw-report-legend';
    seriesLabels.forEach(function (label, i) {
      var item = document.createElement('li');
      var swatch = document.createElement('span');
      swatch.className = 'nw-report-legend__swatch';
      swatch.style.background = seriesColors[i];
      item.appendChild(swatch);
      item.appendChild(document.createTextNode(label));
      legend.appendChild(item);
    });

    container.innerHTML = '';
    container.appendChild(container_div);
    container.appendChild(legend);
    container.appendChild(tooltip);
  }

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('.nw-report');
    var dataNode = document.getElementById('nw-report-data');
    var donutEl = document.getElementById('nw-report-donut');
    var timeEl = document.getElementById('nw-report-timeseries');

    if (!root || !dataNode || !donutEl || !timeEl) {
      return;
    }

    var data;
    try {
      data = JSON.parse(dataNode.textContent);
    } catch (e) {
      return;
    }

    renderDonut(donutEl, data.donut || [], root);
    renderTimeseries(timeEl, data.timeseries || [], data.labels || { searches: 'Searches', views: 'Views', orders: 'Orders' }, root);

    var monthSelect = document.getElementById('nw-report-month');
    if (monthSelect) {
      monthSelect.addEventListener('change', function () {
        var startInput = document.getElementById('nw-report-start');
        var endInput = document.getElementById('nw-report-end');
        var disable = monthSelect.value !== '';
        if (startInput) {
          startInput.disabled = disable;
        }
        if (endInput) {
          endInput.disabled = disable;
        }
      });
      monthSelect.dispatchEvent(new Event('change'));
    }
  });
})();
