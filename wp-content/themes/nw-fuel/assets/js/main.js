(function () {
  'use strict';

  const productsBase = (window.nwFuel && nwFuel.productsUrl)
    ? String(nwFuel.productsUrl).replace(/\/?$/, '')
    : '/products';

  function productUrl(slug) {
    return productsBase + '/' + encodeURIComponent(slug).replace(/%2F/g, '/') + '/';
  }

  function productsSearchUrl(query) {
    return productsBase + '/?q=' + encodeURIComponent(query);
  }

  function fetchProductSuggestions(query, limit) {
    const ajaxUrl = (window.nwFuel && nwFuel.ajaxUrl) ? String(nwFuel.ajaxUrl) : '/wp-admin/admin-ajax.php';
    const url = ajaxUrl + '?action=nw_fuel_product_search&q=' + encodeURIComponent(query) + '&limit=' + String(limit || 6);
    return fetch(url, { credentials: 'same-origin' })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        return (payload && payload.success && Array.isArray(payload.data)) ? payload.data : [];
      })
      .catch(function () { return []; });
  }

  function debounce(fn, wait) {
    let timer = null;
    return function () {
      const context = this;
      const args = arguments;
      window.clearTimeout(timer);
      timer = window.setTimeout(function () {
        fn.apply(context, args);
      }, wait);
    };
  }

  function reportAjax(params) {
    const ajaxUrl = (window.nwFuel && nwFuel.ajaxUrl) ? String(nwFuel.ajaxUrl) : '/wp-admin/admin-ajax.php';
    const query = Object.keys(params).map(function (key) {
      return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
    }).join('&');
    fetch(ajaxUrl + '?' + query, { credentials: 'same-origin', keepalive: true }).catch(function () {});
  }

  function digitsOnly(value) {
    return String(value || '').replace(/\D/g, '');
  }

  function alnumOnly(value) {
    return String(value || '').toLowerCase().replace(/[^a-z0-9]/g, '');
  }

  function levenshtein(a, b) {
    if (a === b) return 0;
    const m = a.length;
    const n = b.length;
    if (!m) return n;
    if (!n) return m;
    const row = new Array(n + 1);
    for (let j = 0; j <= n; j++) row[j] = j;
    for (let i = 1; i <= m; i++) {
      let prev = i - 1;
      row[0] = i;
      for (let j = 1; j <= n; j++) {
        const tmp = row[j];
        const cost = a.charAt(i - 1) === b.charAt(j - 1) ? 0 : 1;
        row[j] = Math.min(row[j] + 1, row[j - 1] + 1, prev + cost);
        prev = tmp;
      }
    }
    return row[n];
  }

  function partTokenScore(query, token) {
    const q = alnumOnly(query);
    const t = alnumOnly(token);
    if (!q || !t) return 99;

    if (t === q) return 0;

    const qd = digitsOnly(query);
    const td = digitsOnly(token);

    if (qd && td === qd) return 1;
    if (Math.min(q.length, t.length) >= 4 && (t.startsWith(q) || q.startsWith(t))) {
      return 2 + Math.min(4, Math.abs(t.length - q.length));
    }
    if (qd.length >= 4 && td.length >= 4 && (td.startsWith(qd) || qd.startsWith(td))) {
      return 3 + Math.min(4, Math.abs(td.length - qd.length));
    }
    if (qd.length >= 4 && td.includes(qd)) return 8;
    if (t.includes(q) || (q.length >= 4 && t.length >= 4 && q.includes(t))) return 9;

    if (qd.length >= 4 && td.length >= 4) {
      const dist = levenshtein(qd, td);
      if (dist <= 2) return 10 + dist;
    }

    if (q.length >= 4 && t.length >= 4) {
      const dist = levenshtein(q, t);
      const maxLen = Math.max(q.length, t.length);
      if (dist <= 3 && dist / maxLen <= 0.5) return 13 + dist;
    }

    return 99;
  }

  function partListScore(query, part, alts) {
    let best = partTokenScore(query, part || '');
    String(alts || '').split(/[\s,;|/]+/).forEach(function (alt) {
      if (!alt) return;
      const score = partTokenScore(query, alt);
      if (score < best) best = score;
    });
    return best;
  }

  function productMatchesQuery(product, query, queryCompact) {
    if (!query) return false;

    const name = (product.name || '').toLowerCase();
    const brand = (product.brand || '').toLowerCase();
    const code = (product.code || '').toLowerCase();
    const codeCompact = alnumOnly(product.code || '');

    if (name.includes(query) || brand.includes(query) || code.includes(query) || (codeCompact && codeCompact.includes(queryCompact))) {
      return true;
    }

    return partListScore(query, product.part, product.alts) < 99;
  }

  function matchingSearchAlt(product, query) {
    const partScore = partTokenScore(query, product.part || '');
    let bestAlt = '';
    let bestScore = 99;
    String(product.alts || '').split(/[\s,;|/]+/).forEach(function (alt) {
      if (!alt) return;
      const score = partTokenScore(query, alt);
      if (score < bestScore) {
        bestScore = score;
        bestAlt = alt;
      }
    });
    if (bestScore >= 99 || !bestAlt) return '';
    if (alnumOnly(bestAlt) === alnumOnly(product.part || '')) return '';
    if (partScore < 99 && partScore <= bestScore) return '';
    return bestAlt;
  }

  function suggestionMetaText(product, query) {
    const bits = [];
    if (product.brand) bits.push(String(product.brand));
    if (product.part) bits.push('Part # ' + String(product.part));
    const alt = matchingSearchAlt(product, query);
    if (alt) bits.push('Alt ' + alt);
    return bits.join(' · ');
  }

  function productQueryScore(product, query, queryCompact) {
    const partScore = partListScore(query, product.part, product.alts);
    if (partScore < 99) return partScore;

    const name = (product.name || '').toLowerCase();
    const brand = (product.brand || '').toLowerCase();
    const code = (product.code || '').toLowerCase();
    const codeCompact = alnumOnly(product.code || '');

    if (name.startsWith(query)) return 20;
    if (code.startsWith(query) || (codeCompact && codeCompact.startsWith(queryCompact))) return 21;
    if (name.includes(query)) return 22;
    if (brand.includes(query)) return 23;
    if (code.includes(query) || (codeCompact && codeCompact.includes(queryCompact))) return 24;
    return 99;
  }

  function lockBody(lock) {
    document.body.classList.toggle('is-locked', lock);
  }

  // Sticky header glass effect
  const header = document.getElementById('site-header');
  if (header) {
    window.addEventListener('scroll', function () {
      header.classList.toggle('is-scrolled', window.scrollY > 20);
    }, { passive: true });
  }

  // Mobile menu
  const toggle = document.getElementById('menu-toggle');
  const mobileMenu = document.getElementById('mobile-menu');

  function closeMobileMenu() {
    if (!mobileMenu || !toggle) return;
    mobileMenu.setAttribute('hidden', '');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Open menu');
    lockBody(false);
  }

  if (toggle && mobileMenu) {
    toggle.addEventListener('click', function () {
      const open = mobileMenu.hasAttribute('hidden');
      if (open) {
        mobileMenu.removeAttribute('hidden');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Close menu');
        lockBody(true);
      } else {
        closeMobileMenu();
      }
    });

    mobileMenu.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', closeMobileMenu);
    });
  }

  // Mobile submenu accordions
  document.querySelectorAll('[data-mobile-submenu]').forEach(function (group) {
    const btn = group.querySelector('.mobile-menu__toggle');
    const panel = group.querySelector('.mobile-menu__sub');
    if (!btn || !panel) return;

    btn.addEventListener('click', function () {
      const isOpen = group.classList.contains('is-open');
      document.querySelectorAll('[data-mobile-submenu]').forEach(function (other) {
        other.classList.remove('is-open');
        const otherBtn = other.querySelector('.mobile-menu__toggle');
        const otherPanel = other.querySelector('.mobile-menu__sub');
        if (otherBtn) otherBtn.setAttribute('aria-expanded', 'false');
        if (otherPanel) otherPanel.hidden = true;
      });

      if (!isOpen) {
        group.classList.add('is-open');
        panel.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
      }
    });
  });

  // FAQ accordion
  document.querySelectorAll('.faq').forEach(function (faq) {
    faq.querySelectorAll('.faq__question').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const item = btn.closest('.faq__item');
        const answer = item.querySelector('.faq__answer');
        const isOpen = item.classList.contains('is-open');

        faq.querySelectorAll('.faq__item').forEach(function (el) {
          el.classList.remove('is-open');
          el.querySelector('.faq__answer').hidden = true;
          el.querySelector('.faq__question').setAttribute('aria-expanded', 'false');
        });

        if (!isOpen) {
          item.classList.add('is-open');
          answer.hidden = false;
          btn.setAttribute('aria-expanded', 'true');
        }
      });
    });
  });

  // Product gallery
  document.querySelectorAll('[data-gallery]').forEach(function (gallery) {
    const main = gallery.querySelector('#gallery-main')
      || gallery.querySelector('.gallery__main img')
      || gallery.querySelector('.product-gallery__main img');
    const zoomLink = gallery.querySelector('.product-gallery__zoom');
    const thumbs = Array.from(gallery.querySelectorAll('.gallery__thumb, .product-gallery__thumb'));
    if (!main) return;

    let activeIndex = Math.max(0, thumbs.findIndex(function (t) {
      return t.classList.contains('is-active');
    }));

    function activateThumb(index) {
      if (!thumbs.length) return;
      const len = thumbs.length;
      activeIndex = ((index % len) + len) % len;
      const thumb = thumbs[activeIndex];

      thumbs.forEach(function (t) {
        t.classList.remove('is-active');
      });
      thumb.classList.add('is-active');

      const thumbImg = thumb.querySelector('img');
      const src = thumb.dataset.src || (thumbImg && thumbImg.src);
      if (src) {
        main.src = src;
        if (zoomLink) zoomLink.setAttribute('href', src);
      }
    }

    thumbs.forEach(function (thumb, index) {
      thumb.addEventListener('click', function () {
        activateThumb(index);
      });
    });

    const prevBtn = gallery.querySelector('[data-gallery-prev]');
    const nextBtn = gallery.querySelector('[data-gallery-next]');
    if (prevBtn) {
      prevBtn.addEventListener('click', function () {
        activateThumb(activeIndex - 1);
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        activateThumb(activeIndex + 1);
      });
    }

    // Click-to-zoom (desktop only — the toggle button is hidden on touch
    // devices via CSS): clicking the +/- icon engages the magnified view,
    // then mouse movement pans it to follow the cursor until toggled off.
    const mainWrap = gallery.querySelector('.product-gallery__main');
    const zoomToggle = gallery.querySelector('[data-gallery-zoom-toggle]');
    if (mainWrap && zoomToggle) {
      zoomToggle.addEventListener('click', function () {
        const isActive = mainWrap.classList.toggle('is-zoom-active');
        zoomToggle.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        zoomToggle.setAttribute('aria-label', isActive ? 'Zoom out' : 'Zoom in');
        if (!isActive) {
          mainWrap.style.removeProperty('--zoom-x');
          mainWrap.style.removeProperty('--zoom-y');
        }
      });

      mainWrap.addEventListener('mousemove', function (event) {
        if (!mainWrap.classList.contains('is-zoom-active')) return;
        const rect = mainWrap.getBoundingClientRect();
        const x = ((event.clientX - rect.left) / rect.width) * 100;
        const y = ((event.clientY - rect.top) / rect.height) * 100;
        mainWrap.style.setProperty('--zoom-x', Math.max(0, Math.min(100, x)) + '%');
        mainWrap.style.setProperty('--zoom-y', Math.max(0, Math.min(100, y)) + '%');
      });
    }
  });

  // Contact and quote forms
  function showFormSuccess(form, success) {
    if (!form || !success) return;
    form.hidden = true;
    success.hidden = false;
  }

  const contactForm = document.getElementById('contact-form');
  if (contactForm) {
    contactForm.addEventListener('submit', function (e) {
      const action = (contactForm.getAttribute('action') || '');
      if (action.indexOf('admin-post.php') !== -1 || contactForm.method.toLowerCase() === 'post' && contactForm.querySelector('input[name="action"]')) {
        return;
      }
      e.preventDefault();
      showFormSuccess(contactForm, document.getElementById('form-success'));
    });
  }

  document.querySelectorAll('[data-quote-toggle]').forEach(function (toggleBtn) {
    const target = document.getElementById(toggleBtn.getAttribute('aria-controls') || '');
    if (!target) return;

    toggleBtn.addEventListener('click', function () {
      const isOpening = target.hasAttribute('hidden');
      target.hidden = !isOpening;
      toggleBtn.setAttribute('aria-expanded', isOpening ? 'true' : 'false');

      if (isOpening) {
        const firstInput = target.querySelector('input:not([type="hidden"]), textarea');
        if (firstInput) firstInput.focus({ preventScroll: true });
        target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });
  });

  document.querySelectorAll('a[href="#service-quote-form-wrap"]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      const quoteBtn = document.querySelector('[data-quote-toggle][aria-controls="service-quote-form-wrap"]');
      const target = document.getElementById('service-quote-form-wrap');
      if (!quoteBtn || !target) return;

      e.preventDefault();
      if (target.hasAttribute('hidden')) {
        quoteBtn.click();
      }
      quoteBtn.scrollIntoView({ behavior: 'smooth', block: 'center' });
      const firstInput = target.querySelector('input:not([type="hidden"]), textarea');
      window.setTimeout(function () {
        if (firstInput) firstInput.focus({ preventScroll: true });
      }, 300);
    });
  });

  document.querySelectorAll('[data-quote-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      const action = (form.getAttribute('action') || '');
      if (action.indexOf('admin-post.php') !== -1 || form.querySelector('input[name="action"]')) {
        return;
      }
      e.preventDefault();
      const wrap = form.closest('.product-quote, .service-quote');
      const success = wrap?.querySelector('[data-quote-success]');
      showFormSuccess(form, success);
    });
  });

  // Service pricing table search
  function normalizeSearchText(value) {
    return (value || '')
      .toLowerCase()
      .replace(/&/g, ' and ')
      .replace(/[^a-z0-9.]+/g, ' ')
      .trim();
  }

  function levenshtein(a, b) {
    if (a === b) return 0;
    if (!a.length) return b.length;
    if (!b.length) return a.length;

    const prev = Array.from({ length: b.length + 1 }, function (_, i) { return i; });
    const curr = Array(b.length + 1);

    for (let i = 1; i <= a.length; i++) {
      curr[0] = i;
      for (let j = 1; j <= b.length; j++) {
        const cost = a[i - 1] === b[j - 1] ? 0 : 1;
        curr[j] = Math.min(curr[j - 1] + 1, prev[j] + 1, prev[j - 1] + cost);
      }
      for (let j = 0; j <= b.length; j++) prev[j] = curr[j];
    }

    return prev[b.length];
  }

  function tokenMatches(queryToken, rowTokens, rowText) {
    if (!queryToken) return true;
    if (rowText.includes(queryToken)) return true;
    if (queryToken.length <= 2) return rowTokens.some(function (token) { return token === queryToken; });

    return rowTokens.some(function (token) {
      if (token.includes(queryToken) || queryToken.includes(token)) return true;
      const maxDistance = queryToken.length <= 5 ? 1 : 2;
      return levenshtein(queryToken, token) <= maxDistance;
    });
  }

  document.querySelectorAll('[data-service-table-wrap]').forEach(function (wrap) {
    const panel = wrap.closest('.service-detail-panel');
    const search = panel?.querySelector('[data-service-table-search]');
    const status = panel?.querySelector('[data-service-table-status]');
    const rows = Array.from(wrap.querySelectorAll('tbody tr'));
    if (!search || !rows.length) return;

    const rowData = rows.map(function (row) {
      const text = normalizeSearchText(row.textContent);
      return {
        row: row,
        text: text,
        tokens: text.split(/\s+/).filter(Boolean)
      };
    });

    function applyTableSearch() {
      const query = normalizeSearchText(search.value);
      const queryTokens = query.split(/\s+/).filter(Boolean);
      let visible = 0;

      rowData.forEach(function (entry) {
        const match = !queryTokens.length || queryTokens.every(function (token) {
          return tokenMatches(token, entry.tokens, entry.text);
        });
        entry.row.hidden = !match;
        if (match) visible++;
      });

      if (status) {
        status.textContent = queryTokens.length ? visible + ' matching row' + (visible === 1 ? '' : 's') : '';
      }
    }

    search.addEventListener('input', applyTableSearch);
    applyTableSearch();
  });

  // Product catalog filters
  const catalog = document.getElementById('product-catalog');
  if (catalog) {
    const search = document.getElementById('filter-search');
    const heroSearch = document.getElementById('hero-search');
    const category = document.getElementById('filter-category');
    const brand = document.getElementById('filter-brand');
    const vehicle = document.getElementById('filter-vehicle');
    const engine = document.getElementById('filter-engine');
    const form = document.getElementById('catalog-filters');
    const filterToggle = document.getElementById('filter-toggle');
    const sidebar = document.getElementById('catalog-sidebar');
    const backdrop = document.getElementById('catalog-backdrop');
    const sidebarClose = document.getElementById('catalog-close');

    function setCatalogOpen(open) {
      if (!sidebar) return;
      sidebar.classList.toggle('is-open', open);
      if (backdrop) backdrop.classList.toggle('is-visible', open);
      if (filterToggle) filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      lockBody(open);
    }

    function submitCatalogFilters() {
      if (form) form.requestSubmit ? form.requestSubmit() : form.submit();
    }

    if (form) {
      form.addEventListener('submit', function () {
        Array.from(form.elements).forEach(function (el) {
          if (el.name && !String(el.value || '').trim()) {
            el.disabled = true;
          }
        });
      });
    }

    function syncHeroSearch() {
      if (heroSearch && search) heroSearch.value = search.value;
    }

    [category, brand, vehicle, engine].forEach(function (el) {
      if (el) el.addEventListener('change', submitCatalogFilters);
    });

    if (search) {
      const trackFilterSearch = debounce(function () {
        const query = search.value.trim();
        if (query.length < 2) return;
        fetchProductSuggestions(query, 6);
      }, 400);
      search.addEventListener('input', function () {
        syncHeroSearch();
        trackFilterSearch();
      });
    }

    if (heroSearch && search) {
      heroSearch.addEventListener('input', function () {
        search.value = heroSearch.value;
      });
    }

    if (filterToggle && sidebar) {
      filterToggle.addEventListener('click', function () {
        setCatalogOpen(!sidebar.classList.contains('is-open'));
      });
    }

    if (sidebarClose) {
      sidebarClose.addEventListener('click', function () {
        setCatalogOpen(false);
      });
    }

    if (backdrop) {
      backdrop.addEventListener('click', function () {
        setCatalogOpen(false);
      });
    }

    window.addEventListener('resize', function () {
      if (window.innerWidth >= 1024) setCatalogOpen(false);
    });

    const productParams = new URLSearchParams(window.location.search);
    const shouldScrollToCatalog =
      !window.location.hash &&
      (productParams.has('category') || productParams.has('q') || productParams.has('brand') || productParams.has('vehicle') || productParams.has('engine') || /\/page\/\d+/.test(window.location.pathname));

    if (shouldScrollToCatalog) {
      window.setTimeout(function () {
        catalog.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }, 100);
    }
  }

  if (window.location.hash === '#filter-search') {
    const filterSearch = document.getElementById('filter-search');
    if (filterSearch) {
      filterSearch.focus({ preventScroll: true });
      filterSearch.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  // Header product search
  document.querySelectorAll('[data-site-search]').forEach(function (form) {
    const openBtn = form.querySelector('.site-search__open');
    const input = form.querySelector('input[type="search"]');
    const closeBtn = form.querySelector('.site-search__close');
    const suggestionsEl = form.querySelector('.site-search__suggestions');
    let products = [];
    let activeIndex = -1;

    try {
      products = JSON.parse(form.dataset.products || '[]');
    } catch (e) {
      products = [];
    }

    const MIN_QUERY_LEN = 2;
    const MAX_SUGGESTIONS = 6;
    const optionPrefix = (input?.id || 'site-search') + '-option-';

    function escapeHtml(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/"/g, '&quot;');
    }

    function normalizeQuery(value) {
      return value.toLowerCase().trim().replace(/\s+/g, ' ');
    }

    function compactQuery(value) {
      return value.toLowerCase().replace(/\s/g, '');
    }

    function productScore(product, query, queryCompact) {
      return productQueryScore(product, query, queryCompact);
    }

    function matchProduct(product, query, queryCompact) {
      return productMatchesQuery(product, query, queryCompact);
    }

    function searchProducts(query) {
      const normalized = normalizeQuery(query);
      if (normalized.length < MIN_QUERY_LEN) return Promise.resolve([]);
      return fetchProductSuggestions(query, MAX_SUGGESTIONS);
    }

    function setExpanded(expanded) {
      form.classList.toggle('is-expanded', expanded);
      if (closeBtn) closeBtn.hidden = !expanded;
    }

    function hideSuggestions() {
      if (!suggestionsEl || !input) return;
      suggestionsEl.hidden = true;
      suggestionsEl.innerHTML = '';
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      activeIndex = -1;
    }

    function renderSuggestions(matches, query) {
      if (!suggestionsEl || !input) return;

      const trimmed = query.trim();
      if (trimmed.length < MIN_QUERY_LEN || !matches.length) {
        hideSuggestions();
        return;
      }

      const encodedQuery = encodeURIComponent(trimmed);
      let html = '';

      matches.forEach(function (product, index) {
        const optionId = optionPrefix + index;
        html += '<a href="' + productUrl(product.slug) + '" class="hero-search__option" id="' + optionId + '" role="option" data-index="' + index + '">';

        if (product.image) {
          html += '<img class="hero-search__option-thumb" src="' + escapeHtml(product.image) + '" alt="" loading="lazy">';
        }

        html += '<span class="hero-search__option-body">';
        html += '<span class="hero-search__option-name">' + escapeHtml(product.name) + '</span>';
        html += '<span class="hero-search__option-meta">' + escapeHtml(suggestionMetaText(product, trimmed)) + '</span>';
        html += '</span></a>';
      });

      html += '<a href="' + productsSearchUrl(trimmed) + '" class="hero-search__option hero-search__option--all" role="option">View all results</a>';

      suggestionsEl.innerHTML = html;
      suggestionsEl.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      activeIndex = -1;
    }

    function updateSuggestions() {
      if (!input) return;
      const value = input.value;
      if (value.trim().length < MIN_QUERY_LEN) {
        hideSuggestions();
        return;
      }
      searchProducts(value).then(function (matches) {
        if (input.value !== value) return;
        renderSuggestions(matches, value);
      });
    }

    function setActiveOption(options) {
      options.forEach(function (option, index) {
        option.classList.toggle('is-active', index === activeIndex);
        if (index === activeIndex && input) {
          input.setAttribute('aria-activedescendant', option.id || '');
        }
      });

      if (input && activeIndex < 0) {
        input.removeAttribute('aria-activedescendant');
      }
    }

    if (!input) return;

    if (input.value.trim()) setExpanded(true);

    if (openBtn) {
      openBtn.addEventListener('click', function () {
        setExpanded(true);
        input.focus();
      });
    }

    input.addEventListener('focus', function () {
      setExpanded(true);
      updateSuggestions();
    });

    const scheduleSuggestions = debounce(updateSuggestions, 400);

    input.addEventListener('input', function () {
      setExpanded(true);
      scheduleSuggestions();
    });

    input.addEventListener('blur', function () {
      window.setTimeout(function () {
        hideSuggestions();
        if (!input.value.trim()) setExpanded(false);
      }, 150);
    });

    input.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        hideSuggestions();
        input.value = '';
        setExpanded(false);
        input.blur();
        return;
      }

      if (!suggestionsEl || suggestionsEl.hidden) return;

      const options = Array.from(suggestionsEl.querySelectorAll('.hero-search__option'));
      if (!options.length) return;

      if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex = Math.min(activeIndex + 1, options.length - 1);
        setActiveOption(options);
        options[activeIndex].scrollIntoView({ block: 'nearest' });
      } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex = Math.max(activeIndex - 1, 0);
        setActiveOption(options);
        options[activeIndex].scrollIntoView({ block: 'nearest' });
      } else if (event.key === 'Enter' && activeIndex >= 0) {
        event.preventDefault();
        options[activeIndex].click();
      }
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        input.value = '';
        hideSuggestions();
        setExpanded(false);
        input.blur();
      });
    }

  });

  // Hero search typing placeholder + product suggestions
  const heroForm = document.querySelector('.hero-search[data-categories]');
  if (heroForm) {
    const input = heroForm.querySelector('#hero-search');
    const typedEl = document.getElementById('hero-typed');
    const spaceEl = document.getElementById('hero-space');
    const suggestionsEl = heroForm.querySelector('.hero-search__suggestions');
    let categories = [];
    let products = [];

    try {
      categories = JSON.parse(heroForm.dataset.categories || '[]');
    } catch (e) {
      categories = [];
    }

    try {
      products = JSON.parse(heroForm.dataset.products || '[]');
    } catch (e) {
      products = [];
    }

    let catIndex = 0;
    let charIndex = 0;
    let deleting = false;
    let timer = null;
    let activeIndex = -1;

    const TYPE_MS = 70;
    const DELETE_MS = 40;
    const PAUSE_TYPED_MS = 2200;
    const PAUSE_DELETED_MS = 400;
    const MIN_QUERY_LEN = 2;
    const MAX_SUGGESTIONS = 6;

    function escapeHtml(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/"/g, '&quot;');
    }

    function normalizeQuery(value) {
      return value.toLowerCase().trim().replace(/\s+/g, ' ');
    }

    function compactQuery(value) {
      return value.toLowerCase().replace(/\s/g, '');
    }

    function productScore(product, query, queryCompact) {
      return productQueryScore(product, query, queryCompact);
    }

    function matchProduct(product, query, queryCompact) {
      return productMatchesQuery(product, query, queryCompact);
    }

    function searchProducts(query) {
      const normalized = normalizeQuery(query);
      if (normalized.length < MIN_QUERY_LEN) return Promise.resolve([]);
      return fetchProductSuggestions(query, MAX_SUGGESTIONS);
    }

    function hideSuggestions() {
      if (!suggestionsEl || !input) return;
      suggestionsEl.hidden = true;
      suggestionsEl.innerHTML = '';
      input.setAttribute('aria-expanded', 'false');
      activeIndex = -1;
      heroForm.classList.remove('is-suggestions-open');
    }

    function setActiveOption(options) {
      options.forEach(function (option, index) {
        option.classList.toggle('is-active', index === activeIndex);
        if (index === activeIndex) {
          input.setAttribute('aria-activedescendant', option.id || '');
        }
      });
      if (activeIndex < 0) {
        input.removeAttribute('aria-activedescendant');
      }
    }

    function renderSuggestions(matches, query) {
      if (!suggestionsEl || !input) return;

      const trimmed = query.trim();
      if (trimmed.length < MIN_QUERY_LEN || !matches.length) {
        hideSuggestions();
        return;
      }

      const encodedQuery = encodeURIComponent(trimmed);
      let html = '';

      matches.forEach(function (product, index) {
        const optionId = 'hero-search-option-' + index;
        html += '<a href="' + productUrl(product.slug) + '" class="hero-search__option" id="' + optionId + '" role="option" data-index="' + index + '">';

        if (product.image) {
          html += '<img class="hero-search__option-thumb" src="' + escapeHtml(product.image) + '" alt="" loading="lazy">';
        }

        html += '<span class="hero-search__option-body">';
        html += '<span class="hero-search__option-name">' + escapeHtml(product.name) + '</span>';
        html += '<span class="hero-search__option-meta">' + escapeHtml(suggestionMetaText(product, trimmed)) + '</span>';
        html += '</span></a>';
      });

      html += '<a href="' + productsSearchUrl(trimmed) + '" class="hero-search__option hero-search__option--all" role="option">View all results</a>';

      suggestionsEl.innerHTML = html;
      suggestionsEl.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      heroForm.classList.add('is-suggestions-open');
      activeIndex = -1;
    }

    function updateSuggestions() {
      if (!input) return;
      const value = input.value;
      if (value.trim().length < MIN_QUERY_LEN) {
        hideSuggestions();
        return;
      }
      searchProducts(value).then(function (matches) {
        if (input.value !== value) return;
        renderSuggestions(matches, value);
      });
    }

    function isPaused() {
      return document.activeElement === input || input.value.trim() !== '';
    }

    function syncGhost() {
      heroForm.classList.toggle('is-active', isPaused());
    }

    function setTyped(partial) {
      if (!typedEl || !spaceEl) return;
      typedEl.textContent = partial;
      spaceEl.hidden = !partial;
    }

    function tick() {
      if (!typedEl || !spaceEl || !categories.length) return;

      if (isPaused()) {
        timer = window.setTimeout(tick, 200);
        return;
      }

      const word = categories[catIndex];
      if (!deleting) {
        charIndex += 1;
        setTyped(word.slice(0, charIndex));
        if (charIndex >= word.length) {
          deleting = true;
          timer = window.setTimeout(tick, PAUSE_TYPED_MS);
          return;
        }
        timer = window.setTimeout(tick, TYPE_MS);
      } else {
        charIndex -= 1;
        setTyped(word.slice(0, charIndex));
        if (charIndex <= 0) {
          deleting = false;
          catIndex = (catIndex + 1) % categories.length;
          timer = window.setTimeout(tick, PAUSE_DELETED_MS);
          return;
        }
        timer = window.setTimeout(tick, DELETE_MS);
      }
    }

    if (input) {
      input.addEventListener('focus', function () {
        syncGhost();
        updateSuggestions();
      });

      input.addEventListener('blur', function () {
        window.setTimeout(function () {
          hideSuggestions();
          syncGhost();
          if (!timer && categories.length) tick();
        }, 150);
      });

      const scheduleSuggestions = debounce(updateSuggestions, 400);

      input.addEventListener('input', function () {
        syncGhost();
        scheduleSuggestions();
      });

      input.addEventListener('keydown', function (event) {
        if (!suggestionsEl || suggestionsEl.hidden) return;

        const options = Array.from(suggestionsEl.querySelectorAll('.hero-search__option'));
        if (!options.length) return;

        if (event.key === 'ArrowDown') {
          event.preventDefault();
          activeIndex = Math.min(activeIndex + 1, options.length - 1);
          setActiveOption(options);
          options[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'ArrowUp') {
          event.preventDefault();
          activeIndex = Math.max(activeIndex - 1, 0);
          setActiveOption(options);
          options[activeIndex].scrollIntoView({ block: 'nearest' });
        } else if (event.key === 'Enter' && activeIndex >= 0) {
          event.preventDefault();
          options[activeIndex].click();
        } else if (event.key === 'Escape') {
          hideSuggestions();
        }
      });
    }

    if (input && typedEl && spaceEl && categories.length) {
      syncGhost();
      tick();
    }
  }

  const partnerModal = document.querySelector('[data-partner-login-modal]');
  const partnerOpeners = document.querySelectorAll('[data-partner-login-open]');
  const partnerAccount = document.querySelector('[data-partner-account]');

  function openPartnerLogin() {
    if (!partnerModal) return;
    partnerModal.hidden = false;
    partnerModal.classList.add('is-open');
    lockBody(true);
    const email = partnerModal.querySelector('#partner-login-email');
    if (email) email.focus();
  }

  function closePartnerLogin() {
    if (!partnerModal) return;
    partnerModal.hidden = true;
    partnerModal.classList.remove('is-open');
    lockBody(false);
  }

  partnerOpeners.forEach(function (btn) {
    btn.addEventListener('click', openPartnerLogin);
  });

  if (partnerModal) {
    partnerModal.querySelectorAll('[data-partner-login-close]').forEach(function (el) {
      el.addEventListener('click', closePartnerLogin);
    });
    if (partnerModal.classList.contains('is-open')) {
      lockBody(true);
    }
  }

  if (partnerAccount) {
    const accountToggle = partnerAccount.querySelector('.partner-account__toggle');
    const accountMenu = partnerAccount.querySelector('.partner-account__menu');
    if (accountToggle && accountMenu) {
      accountToggle.addEventListener('click', function () {
        const open = accountMenu.hidden;
        accountMenu.hidden = !open;
        accountToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      document.addEventListener('click', function (event) {
        if (!partnerAccount.contains(event.target)) {
          accountMenu.hidden = true;
          accountToggle.setAttribute('aria-expanded', 'false');
        }
      });
    }
  }

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    if (partnerModal && !partnerModal.hidden) {
      closePartnerLogin();
    }
    if (partnerAccount) {
      const accountToggle = partnerAccount.querySelector('.partner-account__toggle');
      const accountMenu = partnerAccount.querySelector('.partner-account__menu');
      if (accountToggle && accountMenu && !accountMenu.hidden) {
        accountMenu.hidden = true;
        accountToggle.setAttribute('aria-expanded', 'false');
      }
    }
  });

  // Gallery image lightbox
  const galleryLinks = document.querySelectorAll('[data-gallery-lightbox]');
  if (galleryLinks.length) {
    const overlay = document.createElement('div');
    overlay.className = 'gallery-lightbox';
    overlay.hidden = true;
    overlay.innerHTML = '<button type="button" class="gallery-lightbox__close" aria-label="Close">&times;</button><img alt="">';
    document.body.appendChild(overlay);
    const img = overlay.querySelector('img');
    const closeBtn = overlay.querySelector('.gallery-lightbox__close');

    // Touch zoom state: pinch-to-zoom, double-tap-to-zoom, and panning
    // while zoomed in, since a static enlarged image isn't a real "zoom"
    // on devices with no mouse to hover with.
    let scale = 1;
    let originX = 0;
    let originY = 0;
    let pinchStartDist = 0;
    let pinchStartScale = 1;
    let isPanning = false;
    let panStartX = 0;
    let panStartY = 0;
    let panOriginX = 0;
    let panOriginY = 0;
    let lastTapTime = 0;

    function applyTransform() {
      img.style.transform = 'translate(' + originX + 'px, ' + originY + 'px) scale(' + scale + ')';
      img.classList.toggle('is-zoomed', scale > 1);
    }

    function resetZoom() {
      scale = 1;
      originX = 0;
      originY = 0;
      applyTransform();
    }

    function touchDistance(touches) {
      const dx = touches[0].clientX - touches[1].clientX;
      const dy = touches[0].clientY - touches[1].clientY;
      return Math.hypot(dx, dy);
    }

    function closeLightbox() {
      overlay.hidden = true;
      if (img) img.removeAttribute('src');
      resetZoom();
      lockBody(false);
    }

    galleryLinks.forEach(function (link) {
      link.addEventListener('click', function (event) {
        event.preventDefault();
        if (!img) return;
        img.src = link.getAttribute('href') || '';
        img.alt = (link.querySelector('img') && link.querySelector('img').alt) || '';
        resetZoom();
        overlay.hidden = false;
        lockBody(true);
      });
    });

    overlay.addEventListener('click', function (event) {
      if (event.target === overlay || event.target === closeBtn) {
        closeLightbox();
      }
    });

    // Gestures are bound to the whole overlay, not just the img, because
    // object-fit: contain letterboxes the image inside it — a pinch that
    // starts a few pixels off the image would otherwise fall through to
    // the browser's native (whole-page) pinch-zoom instead of ours.
    overlay.addEventListener('touchstart', function (event) {
      if (event.touches.length === 2) {
        event.preventDefault();
        pinchStartDist = touchDistance(event.touches);
        pinchStartScale = scale;
        isPanning = false;
        return;
      }

      if (event.touches.length !== 1) return;

      if (event.target === img) {
        const now = Date.now();
        if (now - lastTapTime < 300) {
          event.preventDefault();
          scale = scale > 1 ? 1 : 2.5;
          originX = 0;
          originY = 0;
          applyTransform();
        }
        lastTapTime = now;
      }

      if (scale > 1) {
        isPanning = true;
        panStartX = event.touches[0].clientX;
        panStartY = event.touches[0].clientY;
        panOriginX = originX;
        panOriginY = originY;
      }
    }, { passive: false });

    overlay.addEventListener('touchmove', function (event) {
      if (event.touches.length === 2) {
        event.preventDefault();
        const dist = touchDistance(event.touches);
        scale = Math.max(1, Math.min(4, pinchStartScale * (dist / pinchStartDist)));
        applyTransform();
        return;
      }

      if (event.touches.length === 1 && isPanning) {
        event.preventDefault();
        originX = panOriginX + (event.touches[0].clientX - panStartX);
        originY = panOriginY + (event.touches[0].clientY - panStartY);
        applyTransform();
      }
    }, { passive: false });

    overlay.addEventListener('touchend', function (event) {
      if (event.touches.length === 0) {
        isPanning = false;
        if (scale <= 1) resetZoom();
      }
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !overlay.hidden) {
        closeLightbox();
      }
    });
  }

  const productView = document.querySelector('[data-nw-product-view]');
  if (productView) {
    const productId = productView.getAttribute('data-nw-product-view');
    if (productId) {
      reportAjax({ action: 'nw_fuel_product_view', product_id: productId });
    }
  }

  const searchHit = document.querySelector('[data-nw-search]');
  if (searchHit) {
    const term = (searchHit.getAttribute('data-nw-search') || '').trim();
    const ids = searchHit.getAttribute('data-nw-search-ids') || '';
    if (term.length >= 2) {
      reportAjax({ action: 'nw_fuel_product_search_hit', q: term, ids: ids });
    }
  }
})();
