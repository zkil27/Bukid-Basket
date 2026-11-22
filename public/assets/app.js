(() => {
  'use strict';

  const BASKET_KEY = 'bb_basket_v1';
  const FREE_DELIVERY_MIN = 50000; // ₱500.00 in centavos
  const DELIVERY_FEE = 5000;       // ₱50.00
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') || '' : '';
  }

  function esc(str) {
    return String(str ?? '').replace(/[&<>"']/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
  }

  function peso(centavos) {
    const sign = centavos < 0 ? '-' : '';
    const abs = Math.abs(Math.trunc(centavos));
    const whole = String(Math.trunc(abs / 100)).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    const cents = abs % 100;
    return cents === 0 ? `${sign}₱${whole}` : `${sign}₱${whole}.${String(cents).padStart(2, '0')}`;
  }

  function toast(msg, type = 'info') {
    let host = document.getElementById('toast-host');
    if (!host) {
      host = document.createElement('div');
      host.id = 'toast-host';
      host.className = 'toast-host';
      host.setAttribute('role', 'status');
      host.setAttribute('aria-live', 'polite');
      document.body.appendChild(host);
    }
    const el = document.createElement('div');
    el.className = `toast ${type === 'err' ? 'toast-err' : type === 'ok' ? 'toast-ok' : ''}`;
    el.textContent = msg;
    host.appendChild(el);
    setTimeout(() => el.remove(), type === 'err' ? 5500 : 3600);
  }

  // Server flash messages (PHP flash_set) are handed over as a hidden element and shown as a toast.
  function showFlash() {
    const node = document.getElementById('flash-data');
    if (!node) return;
    const { type, msg } = node.dataset;
    node.remove();
    if (msg) toast(msg, type === 'err' || type === 'ok' ? type : 'info');
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', showFlash);
  else showFlash();

  async function post(url, body = {}) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken(),
        'Accept': 'application/json',
      },
      body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({ ok: false, error: 'The server sent an unexpected reply. Please try again.' }));
    if (!res.ok || !data.ok) {
      throw new Error(data.error || `Request failed (${res.status}). Please try again.`);
    }
    return data.data;
  }

  // Replays a CSS animation class on an element.
  function replay(el, cls) {
    if (!el || reduceMotion) return;
    el.classList.remove(cls);
    void el.offsetWidth;
    el.classList.add(cls);
  }

  // ── Basket (browser storage; the server re-checks stock at checkout) ─────
  function readBasket() {
    try {
      const parsed = JSON.parse(localStorage.getItem(BASKET_KEY) || '[]');
      return Array.isArray(parsed) ? parsed : [];
    } catch {
      return [];
    }
  }

  function writeBasket(items) {
    try {
      localStorage.setItem(BASKET_KEY, JSON.stringify(items));
    } catch {
      toast("This browser won't let us save your basket. Try turning off private browsing.", 'err');
    }
    renderBasketUI();
  }

  function addToBasket(item, qty = 1) {
    const items = readBasket();
    const existing = items.find(i => i.variantId === item.variantId);
    const cap = Math.min(50, Number(item.maxStock || 50));
    let added = qty;
    if (existing) {
      const before = existing.qty;
      existing.qty = Math.min(cap, existing.qty + qty);
      existing.price = item.price;
      existing.maxStock = cap;
      added = existing.qty - before;
    } else {
      items.push({ ...item, qty: Math.min(cap, Math.max(1, qty)) });
    }
    if (added <= 0) {
      toast(`You already have all ${cap} we have of ${item.name} (${item.variant}).`, 'err');
      return false;
    }
    writeBasket(items);
    const btn = document.querySelector('.basket-btn');
    replay(btn, 'thump');
    replay(document.querySelector('[data-basket-count]'), 'tick');
    toast(`${item.name} (${item.variant}) is in your basket`, 'ok');
    return true;
  }

  function setBasketQty(variantId, delta) {
    const items = readBasket();
    const idx = items.findIndex(i => i.variantId === variantId);
    if (idx === -1) return;
    const next = items[idx].qty + delta;
    if (next <= 0) {
      items.splice(idx, 1);
    } else {
      items[idx].qty = Math.min(Math.min(50, Number(items[idx].maxStock || 50)), next);
    }
    writeBasket(items);
  }

  function clearBasket() {
    writeBasket([]);
  }

  function totals(items) {
    const subtotal = items.reduce((s, i) => s + i.price * i.qty, 0);
    const fee = items.length ? (subtotal >= FREE_DELIVERY_MIN ? 0 : DELIVERY_FEE) : 0;
    return { subtotal, fee, units: items.reduce((s, i) => s + i.qty, 0) };
  }

  function renderBasketUI() {
    const items = readBasket();
    const { subtotal, fee, units } = totals(items);

    document.querySelectorAll('[data-basket-count]').forEach(el => { el.textContent = String(units); });

    const listEl = document.getElementById('basket-items');
    const summaryEl = document.getElementById('basket-summary');
    if (listEl && summaryEl) {
      if (!items.length) {
        listEl.innerHTML = `
          <div class="empty-note">
            <strong>Your basket is empty.</strong><br>
            <span class="small">Add something from today's harvest and it will show up here.</span>
          </div>`;
        summaryEl.innerHTML = '<a href="/#catalog" class="btn btn-block" data-basket-close>See today\'s harvest</a>';
      } else {
        listEl.innerHTML = items.map(i => `
          <div class="basket-line">
            ${i.image ? `<img src="${esc(i.image)}" alt="">` : '<span class="ph"></span>'}
            <div>
              <strong>${esc(i.name)}</strong>
              <div class="small muted tabular">${esc(i.variant)} · ${peso(i.price)}</div>
            </div>
            <div class="qty-ctl" role="group" aria-label="Quantity of ${esc(i.name)}">
              <button type="button" data-basket-delta="-1" data-vid="${esc(i.variantId)}" aria-label="One less">−</button>
              <span>${i.qty}</span>
              <button type="button" data-basket-delta="1" data-vid="${esc(i.variantId)}" aria-label="One more">+</button>
            </div>
          </div>
        `).join('');
        const needFree = Math.max(0, FREE_DELIVERY_MIN - subtotal);
        const pct = Math.min(100, Math.round((subtotal / FREE_DELIVERY_MIN) * 100));
        summaryEl.innerHTML = `
          <div class="stack-sm">
            <div class="small" style="font-weight:700;">${needFree === 0 ? 'Free delivery unlocked.' : `Add ${peso(needFree)} more for free delivery.`}</div>
            <div class="free-meter" aria-hidden="true"><i style="--fill:${pct / 100}"></i></div>
            <div class="row-between"><span>Subtotal</span><strong class="tabular">${peso(subtotal)}</strong></div>
            <div class="row-between"><span>Delivery</span><strong class="tabular">${fee === 0 ? 'Free' : peso(fee)}</strong></div>
            <div class="row-between" style="font-size:19px;"><strong>Total</strong><strong class="tabular">${peso(subtotal + fee)}</strong></div>
            <a href="/checkout" class="btn btn-accent btn-lg btn-block">Check out</a>
          </div>
        `;
      }
    }

    if (document.getElementById('checkout-items-preview')) renderCheckoutSummary(items, subtotal, fee);
  }

  function renderCheckoutSummary(items, subtotal, fee) {
    const host = document.getElementById('checkout-items-preview');
    const placeBtn = document.getElementById('checkout-submit-btn');
    if (!items.length) {
      host.innerHTML = '<div class="empty-note">Your basket is empty. <a href="/#catalog">See today\'s harvest</a>.</div>';
      if (placeBtn) placeBtn.disabled = true;
      return;
    }
    if (placeBtn && !placeBtn.dataset.busy) placeBtn.disabled = false;
    host.innerHTML = `
      <div class="stack-sm">
        ${items.map(i => `
          <div class="row-between" style="align-items:flex-start;flex-wrap:nowrap;">
            <span><strong class="tabular">${i.qty}×</strong> ${esc(i.name)} <span class="muted">· ${esc(i.variant)}</span></span>
            <span class="tabular">${peso(i.price * i.qty)}</span>
          </div>
        `).join('')}
        <hr>
        <div class="row-between"><span>Subtotal</span><span class="tabular">${peso(subtotal)}</span></div>
        <div class="row-between"><span>Delivery</span><span class="tabular">${fee === 0 ? 'Free' : peso(fee)}</span></div>
        <div class="row-between" style="font-size:20px;"><strong>Total</strong><strong class="tabular">${peso(subtotal + fee)}</strong></div>
      </div>
    `;
  }

  // ── Drawer ──────────────────────────────────────────────────────────────
  let lastFocus = null;
  function openDrawer() {
    const d = document.getElementById('basket-drawer');
    if (!d) return;
    lastFocus = document.activeElement;
    d.classList.remove('hidden');
    const closeBtn = d.querySelector('.drawer-head [data-basket-close]');
    if (closeBtn) closeBtn.focus();
  }
  function closeDrawer() {
    const d = document.getElementById('basket-drawer');
    if (!d || d.classList.contains('hidden')) return;
    d.classList.add('hidden');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  // ── The lot stamp: lands on the product photo when something is added ───
  function stampLot(card) {
    const photo = card && card.querySelector('.sack-photo');
    if (!photo) return;
    let stamp = photo.querySelector('.lot-stamp');
    if (!stamp) {
      stamp = document.createElement('span');
      stamp.className = 'lot-stamp';
      stamp.setAttribute('aria-hidden', 'true');
      photo.appendChild(stamp);
    }
    // One pack, one lot: a stable 4-digit number derived from the pack's id.
    const vid = (card.querySelector('[data-add-basket]') || {}).dataset?.vid || '';
    let h = 0;
    for (let i = 0; i < vid.length; i++) h = (h * 31 + vid.charCodeAt(i)) >>> 0;
    const lot = String(h % 10000).padStart(4, '0');
    stamp.innerHTML = `<small>In basket</small>Lot ${lot}`;
    replay(stamp, 'slam');
  }

  // Big green "Confirmed" stamp used by the admin after a confirm.
  function stampConfirm(text = 'Confirmed') {
    const el = document.createElement('div');
    el.className = 'confirm-stamp';
    el.setAttribute('aria-hidden', 'true');
    el.textContent = text;
    document.body.appendChild(el);
    return new Promise(resolve => setTimeout(() => { el.remove(); resolve(); }, reduceMotion ? 300 : 700));
  }

  function stockText(stock) {
    if (stock <= 0) return ['Sold out', 'chip-gray'];
    if (stock <= 10) return [`Only ${stock} left`, 'chip-yellow'];
    return [`${stock} in stock`, 'chip-green'];
  }

  // ── Delegated handlers ──────────────────────────────────────────────────
  document.addEventListener('click', async ev => {
    const target = ev.target.closest('[data-basket-open], [data-basket-close], [data-basket-delta], [data-add-basket], [data-variant-pick], [data-copy], [data-print]');
    if (!target) return;

    if (target.hasAttribute('data-basket-open')) { openDrawer(); return; }
    if (target.hasAttribute('data-basket-close')) {
      if (ev.target.closest('[data-drawer-panel]') && !ev.target.closest('.drawer-panel [data-basket-close]')) return;
      closeDrawer();
      return;
    }
    if (target.hasAttribute('data-basket-delta')) {
      setBasketQty(target.dataset.vid, Number(target.dataset.basketDelta));
      return;
    }
    if (target.hasAttribute('data-variant-pick')) {
      const card = target.closest('[data-product-card]');
      if (!card) return;
      card.querySelectorAll('[data-variant-pick]').forEach(b => {
        b.classList.toggle('active', b === target);
        b.setAttribute('aria-pressed', b === target ? 'true' : 'false');
      });
      const price = Number(target.dataset.price || 0);
      const stock = Number(target.dataset.stock || 0);
      const priceEl = card.querySelector('[data-price-display]');
      const unitEl = card.querySelector('[data-unit-display]');
      const stockEl = card.querySelector('[data-stock-display]');
      const addBtn = card.querySelector('[data-add-basket]');
      if (priceEl) priceEl.textContent = peso(price);
      if (unitEl) unitEl.textContent = `/ ${target.dataset.vname}`;
      if (stockEl) {
        const [txt, cls] = stockText(stock);
        stockEl.textContent = txt;
        stockEl.className = `chip ${cls}`;
      }
      if (addBtn) {
        const offline = addBtn.dataset.offline === '1';
        addBtn.dataset.vid = target.dataset.vid;
        addBtn.dataset.variant = target.dataset.vname;
        addBtn.dataset.price = String(price);
        addBtn.dataset.stock = String(stock);
        addBtn.disabled = stock <= 0 || offline;
        addBtn.textContent = offline ? 'Ordering paused' : (stock <= 0 ? 'Sold out' : 'Add to basket');
      }
      return;
    }
    if (target.hasAttribute('data-add-basket')) {
      const qtyInput = document.getElementById('product-qty');
      const qty = qtyInput ? Math.min(50, Math.max(1, Math.trunc(Number(qtyInput.value || 1)))) : 1;
      const ok = addToBasket({
        variantId: target.dataset.vid,
        productId: target.dataset.pid,
        name: target.dataset.pname,
        variant: target.dataset.variant,
        price: Number(target.dataset.price),
        image: target.dataset.image || '',
        maxStock: Number(target.dataset.stock || 50),
      }, qty);
      if (ok) stampLot(target.closest('[data-product-card]'));
      return;
    }
    if (target.hasAttribute('data-copy')) {
      const url = new URL(target.dataset.copy || '', window.location.origin).href;
      try {
        await navigator.clipboard.writeText(url);
        toast('Tracking link copied.', 'ok');
      } catch {
        toast("Couldn't copy. Copy the link from the address bar instead.", 'err');
      }
      return;
    }
    if (target.hasAttribute('data-print')) {
      window.print();
    }
  });

  document.addEventListener('keydown', ev => {
    if (ev.key === 'Escape') closeDrawer();
  });

  document.addEventListener('submit', ev => {
    const form = ev.target;
    if (form.dataset && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
      ev.preventDefault();
    }
  });

  window.addEventListener('storage', ev => {
    if (ev.key === BASKET_KEY) renderBasketUI();
  });

  document.addEventListener('DOMContentLoaded', () => {
    renderBasketUI();

    // Storefront search + dish filter
    const searchInput = document.getElementById('catalog-search');
    const dishPills = document.querySelectorAll('[data-dish-filter]');
    const emptyNote = document.getElementById('catalog-empty');
    let activeDish = 'all';
    function filterCatalog() {
      const q = (searchInput ? searchInput.value : '').trim().toLowerCase();
      let shown = 0;
      document.querySelectorAll('[data-cat-section]').forEach(section => {
        let inSection = 0;
        section.querySelectorAll('[data-product-card]').forEach(card => {
          const matchQ = !q || (card.dataset.search || '').includes(q);
          const matchD = activeDish === 'all' || (card.dataset.dishes || '').split(',').includes(activeDish);
          const show = matchQ && matchD;
          card.classList.toggle('hidden', !show);
          if (show) inSection++;
        });
        section.classList.toggle('hidden', inSection === 0);
        shown += inSection;
      });
      if (emptyNote) emptyNote.classList.toggle('hidden', shown > 0);
    }
    if (searchInput) searchInput.addEventListener('input', filterCatalog);
    dishPills.forEach(btn => btn.addEventListener('click', () => {
      dishPills.forEach(b => {
        b.classList.toggle('active', b === btn);
        b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
      });
      activeDish = btn.dataset.dishFilter || 'all';
      filterCatalog();
    }));

    // Checkout
    const checkoutForm = document.getElementById('checkout-form');
    if (checkoutForm) {
      const gcashNote = document.getElementById('gcash-note');
      const codNote = document.getElementById('cod-note');
      checkoutForm.querySelectorAll('input[name="payment"]').forEach(r => r.addEventListener('change', () => {
        const v = checkoutForm.querySelector('input[name="payment"]:checked')?.value;
        if (gcashNote) gcashNote.classList.toggle('hidden', v !== 'GCASH');
        if (codNote) codNote.classList.toggle('hidden', v !== 'COD');
      }));

      checkoutForm.addEventListener('submit', async ev => {
        ev.preventDefault();
        const items = readBasket().map(i => ({ variantId: i.variantId, qty: i.qty }));
        if (!items.length) {
          toast('Your basket is empty. Add something first.', 'err');
          return;
        }
        const fd = new FormData(checkoutForm);
        if (!fd.get('slot')) {
          toast('Choose a delivery slot.', 'err');
          return;
        }
        const btn = document.getElementById('checkout-submit-btn');
        if (btn) { btn.disabled = true; btn.dataset.busy = '1'; btn.textContent = 'Placing your order…'; }
        try {
          const res = await post('/api/checkout', {
            items,
            delivery: {
              name: fd.get('name'),
              phone: fd.get('phone'),
              address: fd.get('address'),
              barangay: fd.get('barangay'),
              slot: fd.get('slot'),
            },
            payment: fd.get('payment'),
            saveDetails: fd.get('saveDetails') === 'on',
          });
          clearBasket();
          window.location.href = `/track?code=${encodeURIComponent(res.code)}&placed=1`;
        } catch (err) {
          toast(err.message, 'err');
          if (btn) { btn.disabled = false; delete btn.dataset.busy; btn.textContent = 'Place order'; }
        }
      });
    }
  });

  window.BB = { post, toast, peso, esc, addToBasket, clearBasket, openDrawer, closeDrawer, stampConfirm };
})();
