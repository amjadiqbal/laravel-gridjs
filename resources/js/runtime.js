(function () {
  if (window.LaravelGridJS) return;

  var ENTITIES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

  function esc(value) {
    return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (c) {
      return ENTITIES[c];
    });
  }

  function safeUrl(url) {
    url = String(url === null || url === undefined ? '' : url);
    return /^\s*(javascript|data|vbscript):/i.test(url) ? '#' : url;
  }

  function anchor(href, label, cls, target) {
    var rel = target === '_blank' ? ' rel="noopener noreferrer"' : '';
    return '<a href="' + esc(safeUrl(href)) + '"' + (cls ? ' class="' + esc(cls) + '"' : '') +
      (target ? ' target="' + esc(target) + '"' : '') + rel + '>' + esc(label) + '</a>';
  }

  function empty(cell) {
    return cell === null || cell === undefined || cell === '';
  }

  // Built-in formats, chosen per column in PHP with 'format' => ['type' => ...].
  function format(f) {
    switch (f.type) {
      case 'date':
      case 'datetime':
        return function (cell) {
          if (empty(cell)) return '';
          var d = new Date(cell);
          if (isNaN(d.getTime())) return cell;
          return f.type === 'date' ? d.toLocaleDateString(f.locale || undefined, f.options || undefined)
            : d.toLocaleString(f.locale || undefined, f.options || undefined);
        };
      case 'currency':
        return function (cell) {
          if (empty(cell)) return '';
          return new Intl.NumberFormat(f.locale || undefined, { style: 'currency', currency: f.currency || 'USD' }).format(Number(cell));
        };
      case 'number':
        return function (cell) {
          if (empty(cell)) return '';
          return new Intl.NumberFormat(f.locale || undefined, f.options || undefined).format(Number(cell));
        };
      case 'boolean':
        return function (cell) {
          return cell === true || cell === 1 || cell === '1' ? (f.true || 'Yes') : (f.false || 'No');
        };
      case 'link':
        return function (cell) {
          if (empty(cell)) return '';
          var href = f.url ? f.url.split('{value}').join(encodeURIComponent(cell)) : cell;
          return gridjs.html(anchor(href, cell, f.class, f.target));
        };
    }
    return undefined;
  }

  function actions(buttons, keyIndex) {
    return function (_cell, row) {
      var key = row.cells[keyIndex] ? row.cells[keyIndex].data : '';
      return gridjs.html(buttons.map(function (b) {
        return anchor(String(b.url).split('{id}').join(encodeURIComponent(key)), b.label, b.class, b.target);
      }).join(' '));
    };
  }

  function param(url, name, value) {
    return url + (url.indexOf('?') === -1 ? '?' : '&') + name + '=' + encodeURIComponent(value);
  }

  function mount(id, spec, formatters) {
    var el = document.getElementById(id);
    if (!el || el.getAttribute('data-gridjs-mounted')) return;
    if (!window.gridjs || !window.gridjs.Grid) {
      console.error('[laravel-gridjs] Grid.js is not loaded. Enable gridjs.assets.inject, add @gridjsAssets, or load gridjs yourself.');
      return;
    }
    el.setAttribute('data-gridjs-mounted', '1');
    formatters = formatters || {};

    var fields = spec.columns.map(function (c) { return c.field; });
    var columns = spec.columns.map(function (c, i) {
      var col = { id: 'c' + i, name: c.name };
      if (c.width) col.width = c.width;
      if (c.hidden) col.hidden = true;
      if (!c.sortable) col.sort = false;
      var formatter = formatters[i] || (c.format && format(c.format)) || (c.actions && actions(c.actions, c.keyIndex));
      if (formatter) col.formatter = formatter;
      return col;
    });

    // Grid.js re-runs its pipeline as each plugin registers, which fires the same request several
    // times on load. Requests for a URL that is already in flight share one fetch.
    var inflight = {};
    function load(opts) {
      if (!inflight[opts.url]) {
        inflight[opts.url] = fetch(opts.url, {
          credentials: 'same-origin',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
          if (!res.ok) throw new Error('[laravel-gridjs] ' + res.status + ' ' + res.statusText);
          return res.json();
        }).then(function (res) {
          return { data: res.data, total: res.total };
        }).finally(function () {
          delete inflight[opts.url];
        });
      }
      return inflight[opts.url];
    }

    var config = Object.assign({}, spec.options, {
      columns: columns,
      server: { url: spec.url, data: load }
    });

    if (spec.search) {
      config.search = {
        debounceTimeout: spec.search.debounce,
        server: { url: function (prev, keyword) { return keyword ? param(prev, 'search', keyword) : prev; } }
      };
    }

    if (spec.sort) {
      config.sort = {
        multiColumn: false,
        server: {
          url: function (prev, cols) {
            if (!cols.length || !fields[cols[0].index]) return prev;
            return param(param(prev, 'sort', fields[cols[0].index]), 'direction', cols[0].direction === 1 ? 'asc' : 'desc');
          }
        }
      };
    }

    if (spec.pagination) {
      config.pagination = Object.assign({}, spec.pagination, {
        server: { url: function (prev, page) { return param(prev, 'page', page + 1); } }
      });
    }

    var grid = new gridjs.Grid(config).render(el);
    el.gridjs = grid;
    el.dispatchEvent(new CustomEvent('gridjs:ready', { bubbles: true, detail: { grid: grid } }));
  }

  window.LaravelGridJS = { mount: mount, escape: esc };
})();
