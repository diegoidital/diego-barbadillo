(() => {
  const D = window.BROCHURE, S = D.slides;
  const $ = (q, el = document) => el.querySelector(q);
  const deck = $('#deck');
  const idx = id => S.findIndex(s => s.id === id);
  const esc = t => String(t).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  /* ---------- Bloques ---------- */
  const blocks = {
    texto: b => `${b.subtitulo ? `<h3>${esc(b.subtitulo)}</h3>` : ''}${b.html}`,
    iconos: b => b.items.map(i => `<div class="icon-item"><span class="ic" style="background:${i.color}">${i.icono}</span>
        <div><h4 style="color:${i.color}">${esc(i.titulo)}</h4><p>${esc(i.texto)}</p></div></div>`).join(''),
    desplegable: b => `<details class="acc"><summary>${esc(b.titulo)}</summary><div class="acc-body">${b.html}</div></details>`,
    descargas: b => `<div class="dl">${b.items.map(i => `<a href="${i.url}" download data-track="descarga">${esc(i.texto)}</a>`).join('')}</div>`,
    datos: b => `<div class="facts">${b.items.map(([ic, k, v]) => `<div><span>${ic}</span><span>${esc(k)}<b>${esc(v)}</b></span></div>`).join('')}</div>`,
    enlace: b => `<button class="cta-arrow" data-goto="${b.destino}">${esc(b.texto)} →</button>`,
    popup: b => `<button class="pop-btn" data-popup>${esc(b.boton)} +</button>
        <template><h3 style="color:var(--red);font-size:28px;margin-bottom:16px">${esc(b.titulo)}</h3>${b.html}</template>`
  };
  const renderBlocks = bs => (bs || []).map(b => `<div class="blk">${blocks[b.tipo](b)}</div>`).join('');

  /* ---------- Media ---------- */
  const media = m => {
    if (!m) return '';
    if (m.tipo === 'carrusel') return `<div class="carousel"><div class="c-track">${m.items.map(i =>
        `<div class="c-item" style="background:${i.fondo}">${esc(i.pie)}</div>`).join('')}</div>
        <button class="c-btn p">‹</button><button class="c-btn n">›</button>
        <div class="c-dots">${m.items.map((_, k) => `<button data-k="${k}" class="${k ? '' : 'on'}"></button>`).join('')}</div></div>`;
    if (m.tipo === 'video-popup') return `<div class="vposter" style="background:${m.fondo}"><button class="play" data-video="${m.url}">▷</button></div>`;
    if (m.tipo === 'video') return `<iframe class="vframe" data-src="${m.url}" allow="autoplay; fullscreen" title="Vídeo"></iframe>`;
  };

  /* ---------- Layouts ---------- */
  const layouts = {
    hero: s => `<section class="slide hero" style="background:${s.fondo}">
        <span class="deco" style="top:18%;right:12%"></span><span class="deco" style="bottom:16%;left:14%;animation-delay:-3s"></span>
        <div><span class="tag">${esc(s.etiqueta)}</span><h1>${esc(s.titulo)}</h1><p>${esc(s.subtitulo)}</p></div></section>`,
    split: s => `<section class="slide split ${s.invertido ? 'inv' : ''}"><div class="slide-inner">
        <div class="media">${media(s.media)}</div>
        <div><h2>${esc(s.titulo)}</h2>${renderBlocks(s.bloques)}</div></div></section>`,
    'fondo-tarjeta': s => `<section class="slide card-layout" style="background:${s.fondo}"><div class="slide-inner">
        <div class="card">${renderBlocks(s.bloques)}</div>
        <div class="links">${s.enlaces.map(e => `<button class="link-btn" data-goto="${e.destino}">${esc(e.texto)}<i>→</i></button>`).join('')}</div>
        </div></section>`
  };

  /* ---------- Render ---------- */
  $('#marca').textContent = D.marca.nombre; $('#marca-sub').textContent = D.marca.sub;
  $('#menu').innerHTML = D.secciones.map(sec =>
    `<a href="#page=${S.findIndex(s => s.seccion === sec.id) + 1}" data-sec="${sec.id}">${esc(sec.titulo)}</a>`).join('');
  deck.innerHTML = S.map(s => layouts[s.layout](s)).join('');

  /* ---------- Navegación ---------- */
  let cur = 0;
  const mobile = () => matchMedia('(max-width:900px)').matches;
  const go = (n, push = true) => {
    cur = Math.max(0, Math.min(S.length - 1, n));
    if (mobile()) deck.children[cur].scrollIntoView({ behavior: 'smooth' });
    else deck.style.transform = `translateX(${-cur * 100}vw)`;
    $('#counter').textContent = `${cur + 1} / ${S.length}`;
    document.querySelectorAll('#menu a').forEach(a => a.classList.toggle('active', a.dataset.sec === S[cur].seccion));
    $('.nav-arrows.left').toggleAttribute('data-off', cur === 0);
    $('.nav-arrows.right').toggleAttribute('data-off', cur === S.length - 1);
    // Carga diferida de vídeos: solo el de la diapositiva actual
    document.querySelectorAll('iframe[data-src]').forEach(f => {
      const here = deck.children[cur].contains(f);
      if (here && !f.src) f.src = f.dataset.src;
      if (!here && f.src) f.removeAttribute('src');
    });
    if (push) history.replaceState(null, '', `#page=${cur + 1}`);
    track('slide_view', S[cur].id);
  };
  const fromHash = () => go((parseInt(location.hash.split('=')[1]) || 1) - 1, false);
  window.addEventListener('hashchange', fromHash);

  $('#prev').onclick = () => go(cur - 1); $('#next').onclick = () => go(cur + 1);
  $('#first').onclick = () => go(0); $('#last').onclick = () => go(S.length - 1);
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') return closeAll();
    if (e.target.matches('input')) return;
    if (e.key === 'ArrowRight' || e.key === 'PageDown') go(cur + 1);
    if (e.key === 'ArrowLeft' || e.key === 'PageUp') go(cur - 1);
  });
  let tx = null;
  deck.addEventListener('touchstart', e => tx = e.touches[0].clientX, { passive: true });
  deck.addEventListener('touchend', e => {
    if (tx === null || mobile()) return; const dx = e.changedTouches[0].clientX - tx;
    if (Math.abs(dx) > 60) go(cur + (dx < 0 ? 1 : -1)); tx = null;
  });

  /* ---------- Modal / drawer ---------- */
  const modal = $('#modal'), box = $('.modal-box'), body = $('#modal-body'), bd = $('#backdrop'), drawer = $('#drawer');
  const openModal = (html, cls = '') => { closeAll(); body.innerHTML = html; box.className = 'modal-box ' + cls; modal.hidden = bd.hidden = false; };
  const closeAll = () => { modal.hidden = drawer.hidden = bd.hidden = true; body.innerHTML = ''; };
  bd.onclick = closeAll;

  const panels = {
    indice: () => openModal(`<h3 style="margin-bottom:20px">Todas las páginas</h3><div class="thumbs">${S.map((s, k) =>
        `<button class="thumb ${k === cur ? 'on' : ''}" data-goto="${s.id}" style="background:${s.fondo || s.media?.fondo || s.media?.items?.[0].fondo || '#333'}">${k + 1}. ${esc(s.titulo || s.id)}</button>`).join('')}</div>`),
    form: () => openModal(`<h3 style="margin-bottom:20px">Solicita información</h3><form class="f" onsubmit="event.preventDefault();this.innerHTML='<p>¡Gracias! Te contactaremos pronto.</p>'">
        <input required placeholder="Nombre"><input required type="email" placeholder="Email"><input placeholder="Teléfono">
        <select>${S.filter(s => s.id.startsWith('grado')).map(s => `<option>${esc(s.titulo)}</option>`).join('')}</select>
        <button class="btn red">Enviar</button></form>`),
    contacto: () => openModal(`<h3>Contacto</h3><p style="margin-top:12px">Teléfono, email y dirección del campus (desde el CMS).</p>`),
    drawer: () => { closeAll(); drawer.hidden = bd.hidden = false; }
  };

  document.addEventListener('click', e => {
    const t = e.target.closest('button,a'); if (!t) return;
    if (t.dataset.open) return panels[t.dataset.open]();
    if (t.hasAttribute('data-close')) return closeAll();
    if (t.dataset.goto) { closeAll(); return go(idx(t.dataset.goto)); }
    if (t.dataset.video) { track('video_open', t.dataset.video);
      return openModal(`<iframe class="vframe" src="${t.dataset.video}?autoplay=1" allow="autoplay; fullscreen" allowfullscreen title="Vídeo"></iframe>`, 'video'); }
    if (t.hasAttribute('data-popup')) return openModal(t.nextElementSibling.innerHTML);
    if (t.dataset.panel) { const p = $('#panel-' + t.dataset.panel); p.hidden = !p.hidden; if (!p.hidden) p.querySelector('input')?.focus(); }
    if (t.dataset.track) track(t.dataset.track, t.textContent);
  });

  /* ---------- Hamburguesa: buscador, compartir, pantalla completa ---------- */
  const SKIP = ['destino', 'url', 'fondo', 'tipo', 'layout', 'id', 'seccion', 'color', 'icono'];
  const texts = o => typeof o === 'string' ? [o] : Array.isArray(o) ? o.flatMap(texts)
    : o && typeof o === 'object' ? Object.entries(o).filter(([k]) => !SKIP.includes(k)).flatMap(([, v]) => texts(v)) : [];
  const plain = s => texts(s).join(' ').replace(/<[^>]+>/g, ' ');
  const index = S.map(plain);
  $('#search').addEventListener('input', e => {
    const q = e.target.value.trim().toLowerCase();
    $('#results').innerHTML = q.length < 2 ? '' : S.map((s, k) => ({ s, k })).filter(({ k }) => index[k].toLowerCase().includes(q))
      .map(({ s, k }) => `<li data-k="${k}"><b>${k + 1}</b> · ${esc(s.titulo || s.id)}</li>`).join('') || '<li>Sin resultados</li>';
  });
  $('#results').addEventListener('click', e => { const li = e.target.closest('li[data-k]'); if (li) { closeAll(); go(+li.dataset.k); } });
  $('#share').onclick = async () => {
    const url = location.href;
    if (navigator.share) navigator.share({ url }).catch(() => {});
    else { await navigator.clipboard?.writeText(url); $('#share').textContent = '¡Enlace copiado!'; }
  };
  $('#fs').onclick = () => document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen();

  /* ---------- Carrusel interno ---------- */
  document.querySelectorAll('.carousel').forEach(c => {
    const tr = $('.c-track', c), n = tr.children.length; let k = 0;
    const set = v => { k = (v + n) % n; tr.style.transform = `translateX(${-k * 100}%)`;
      c.querySelectorAll('.c-dots button').forEach((d, j) => d.classList.toggle('on', j === k)); };
    $('.p', c).onclick = () => set(k - 1); $('.n', c).onclick = () => set(k + 1);
    c.querySelectorAll('.c-dots button').forEach(d => d.onclick = () => set(+d.dataset.k));
  });

  /* ---------- Analítica (stub: conectar a GA4 / dataLayer) ---------- */
  function track(evento, valor) { (window.dataLayer = window.dataLayer || []).push({ event: evento, valor }); }

  fromHash();
})();
