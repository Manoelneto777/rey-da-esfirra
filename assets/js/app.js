/**
 * assets/js/app.js
 * Orquestrador principal da aplicação corrigido.
 */

// ── Namespace UI (utilitários compartilhados) ────
const UI = (() => {
  function toast(icon, mensagem, tipo = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    const el = document.createElement('div');
    el.className = `toast ${tipo}`;
    const iconEl = document.createElement('span');
    iconEl.className = 'toast-icon';
    iconEl.textContent = icon;
    const body = document.createElement('div');
    body.className = 'toast-body';
    const txt = document.createElement('span');
    txt.textContent = mensagem;
    body.appendChild(txt);
    el.appendChild(iconEl);
    el.appendChild(body);
    container.appendChild(el);
    setTimeout(() => {
      el.style.transition = 'opacity .3s, transform .3s';
      el.style.opacity = '0';
      el.style.transform = 'translateX(20px)';
      setTimeout(() => el.remove(), 320);
    }, 4200);
  }

  function initScrollReveal() {
    if (!('IntersectionObserver' in window)) return;
    const obs = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (!e.isIntersecting) return;
        e.target.style.opacity = '1';
        e.target.style.transform = 'translateY(0)';
        obs.unobserve(e.target);
      });
    }, { threshold: 0.1 });
    document.querySelectorAll('.feat-item, .info-item, .review-card, .sb').forEach((el, i) => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(20px)';
      el.style.transition = `opacity .5s ease ${i * .08}s, transform .5s ease ${i * .08}s`;
      obs.observe(el);
    });
  }
  return { toast, initScrollReveal };
})();

// ── Cardápio ────────────────────────────────────
const Cardapio = (() => {
  const IMG_MAP = {
    'carne':'carne', 'frango':'frango', 'queijo':'queijo', 'palmito':'palmito',
    'banana':'banana', 'chocolate':'chocolate',
    'combo':'combo',
    'coca':'coca',
    'guaraná':'guarana', 'guarana':'guarana',
    'lata':'refri', 'refrigerante':'refri',
    'jarra':'jarra',
    'suco':'suco', 'copo':'suco'
  };
  const CAT_EMOJI = {
    Tradicionais:'🥙', Especiais:'🌿', Premium:'👑',
    Doces:'🍫', Combos:'📦', Bebidas:'🥤',
  };
  const RATINGS = ['4.9','4.8','4.7','4.9','4.8','5.0','4.7','4.6','4.8','4.9','5.0','4.7','4.8'];
  let todosProdutos = [];

  function _imgKey(nome) {
    const n = nome.toLowerCase();
    for (const [k, v] of Object.entries(IMG_MAP)) {
      if (n.includes(k)) return v;
    }
    return 'carne';
  }

  async function carregar() {
    function renderizar(produtos) {
      const grid = document.getElementById('produtosGrid');
      const countEl = document.getElementById('cardapioCount');

      if (!grid) return;

      if (countEl) {
      countEl.textContent = `${produtos.length} itens disponíveis`;
      }

      grid.innerHTML = '';

      if (!produtos.length) {
      grid.innerHTML = `<div class="loading-state">🔍 Nenhum item encontrado.</div>`;
      return;
    }

      produtos.forEach((p, i) => {
      const preco = parseFloat(p.preco).toFixed(2).replace('.', ',');

      const card = document.createElement('div');
      card.className = 'produto-card';

      card.innerHTML = `
        <div class="produto-img-wrap">
          <img src="assets/images/${p.imagem}" alt="${p.nome}">
            <div class="produto-preco-overlay">R$ ${preco}</div>
          </div><div class="produto-body">
            <div class="produto-nome">${p.nome}</div>
            <div class="produto-desc">${p.descricao || ''}</div>

            <div class="produto-footer">
              <button class="btn-add-cart">+ Adicionar</button>
            </div>
          </div>
      `;

    card.querySelector('.btn-add-cart').addEventListener('click', () => {
      Cart.adicionar({
        produto_id: parseInt(p.id),
        nome: p.nome,
        preco: parseFloat(p.preco),
        imagem: p.imagem
      });
    });

    grid.appendChild(card);
  });
}
    try {
      todosProdutos = await Api.getProdutos();
      renderizar(todosProdutos);
    } catch (err) {
      const grid = document.getElementById('produtosGrid');
      if (!grid) return;
      grid.innerHTML = `<div class="loading-state" style="color:#ff6b4a">⚠️ Erro ao carregar o cardápio. Verifique se o XAMPP está rodando.</div>`;
    }
  }

  function _renderizar({ itens, total, unidades }) {
  const badge = document.getElementById('cartBadge');
  if (badge) badge.textContent = unidades;

  const sub = document.getElementById('cartHeaderSub');
  if (sub) sub.textContent = unidades
    ? `${unidades} ${unidades === 1 ? 'item' : 'itens'}`
    : 'Vazio';

  const itemsEl = document.getElementById('cartItems');
  const footerEl = document.getElementById('cartFooter');

  if (!itemsEl) return;
  itemsEl.innerHTML = '';

  // 🟡 Carrinho vazio
  if (!itens.length) {
    itemsEl.innerHTML = `
      <div class="cart-empty">
        <p>Seu pedido está vazio</p>
      </div>
    `;
    if (footerEl) footerEl.style.display = 'none';
    return;
  }

  // 🟢 Renderizar itens
  itens.forEach(item => {
    const el = document.createElement('div');
    el.className = 'cart-item';

    el.innerHTML = `
      <div class="ci-img">
        <img src="assets/images/${item.imagem || 'carne'}.webp" alt="${item.nome}">
      </div>
      <div class="ci-info">
        <div class="ci-nome">${item.nome}</div>
        <div class="ci-preco">
          R$ ${(item.preco * item.quantidade).toFixed(2).replace('.', ',')}
        </div>
      </div>
      <div class="ci-controls">
        <button class="ci-btn" onclick="Cart.alterarQuantidade(${item.produto_id}, -1)">−</button>
        <span class="ci-qty">${item.quantidade}</span>
        <button class="ci-btn" onclick="Cart.alterarQuantidade(${item.produto_id}, 1)">+</button>
        <button class="ci-del" onclick="Cart.remover(${item.produto_id})">✕</button>
      </div>
    `;

    itemsEl.appendChild(el);
  });

  // 🟢 Atualizar totais
  if (footerEl) {
    footerEl.style.display = 'block';

    const taxa = total >= 60 ? 0 : 5;

    document.getElementById('subtotalValue').textContent =
      `R$ ${total.toFixed(2).replace('.', ',')}`;

    document.getElementById('entregaValue').innerHTML =
      taxa === 0
        ? '<span style="color:var(--neon);font-weight:700">Grátis 🎉</span>'
        : `R$ ${taxa.toFixed(2).replace('.', ',')}`;

    document.getElementById('totalValue').textContent =
      `R$ ${(total + taxa).toFixed(2).replace('.', ',')}`;
  }
}
  function initFiltros() {
    document.getElementById('categoriasInner')?.addEventListener('click', async e => {
      const btn = e.target.closest('.cat-pill');
      if (!btn) return;
      document.querySelectorAll('.cat-pill').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const cat = btn.dataset.cat;
      const filtrados = cat === 'Todos' ? todosProdutos : todosProdutos.filter(p => p.categoria === cat);
      renderizar(filtrados);
      document.getElementById('cardapio')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }
  return { carregar, initFiltros };
})();

// ── Sidebar do carrinho ─────────────────────────
const CartUI = (() => {
  function init() {
    document.getElementById('cartToggle')?.addEventListener('click', abrir);
    document.getElementById('cartClose')?.addEventListener('click', fechar);
    document.getElementById('cartOverlay')?.addEventListener('click', fechar);
    window.addEventListener('cart:update', e => _renderizar(e.detail));
  }

  function abrir() {
    document.getElementById('cartSidebar')?.classList.add('open');
    document.getElementById('cartOverlay')?.classList.add('open');
  }

  function fechar() {
    document.getElementById('cartSidebar')?.classList.remove('open');
    document.getElementById('cartOverlay')?.classList.remove('open');
  }

  function _renderizar({ itens, total, unidades }) {
  const badge = document.getElementById('cartBadge');
  if (badge) badge.textContent = unidades;

  const sub = document.getElementById('cartHeaderSub');
  if (sub) sub.textContent = unidades ? `${unidades} ${unidades === 1 ? 'item' : 'itens'}` : 'Vazio';

  const itemsEl = document.getElementById('cartItems');
  const footerEl = document.getElementById('cartFooter');
  if (!itemsEl) return;

  itemsEl.innerHTML = '';

  if (!itens.length) {
    itemsEl.innerHTML = `<div class="cart-empty"><p>Seu pedido está vazio</p></div>`;
    if (footerEl) footerEl.style.display = 'none';
    return;
  }

  itens.forEach(item => {
    const el = document.createElement('div');
    el.className = 'cart-item';
    el.innerHTML = `
      <div class="ci-img"><img src="assets/images/${item.imagem || 'carne'}.webp"></div>
      <div class="ci-info">
        <div class="ci-nome">${item.nome}</div>
        <div class="ci-preco">R$ ${(item.preco * item.quantidade).toFixed(2).replace('.', ',')}</div>
      </div>
      <div class="ci-controls">
        <button onclick="Cart.alterarQuantidade(${item.produto_id}, -1)">−</button>
        <span>${item.quantidade}</span>
        <button onclick="Cart.alterarQuantidade(${item.produto_id}, 1)">+</button>
        <button onclick="Cart.remover(${item.produto_id})">✕</button>
      </div>
    `;
    itemsEl.appendChild(el);
  });

  if (footerEl) {
    footerEl.style.display = 'block';
    const taxa = total >= 60 ? 0 : 5;

    document.getElementById('subtotalValue').textContent =
      `R$ ${total.toFixed(2).replace('.', ',')}`;

    document.getElementById('entregaValue').innerHTML =
      taxa === 0 ? 'Grátis 🎉' : `R$ ${taxa.toFixed(2).replace('.', ',')}`;

    document.getElementById('totalValue').textContent =
      `R$ ${(total + taxa).toFixed(2).replace('.', ',')}`;
  }
}
  return { init, abrir, fechar };
})();
// ── Navbar ──────────────────────────────────────
const Navbar = (() => {
  function init() {
    const navbar = document.getElementById('navbar');
    const hamburger = document.getElementById('hamburger');
    const navLinks = document.getElementById('navLinks');
    window.addEventListener('scroll', () => navbar?.classList.toggle('scrolled', window.scrollY > 20), { passive: true });
    hamburger?.addEventListener('click', () => {
      const open = navLinks?.classList.toggle('nav-open');
      if (hamburger) hamburger.textContent = open ? '✕' : '☰';
    });
    navLinks?.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => {
        navLinks.classList.remove('nav-open');
        if (hamburger) hamburger.textContent = '☰';
      });
    });
    document.querySelectorAll('a[href^="#"]').forEach(link => {
      link.addEventListener('click', e => {
        const id = link.getAttribute('href').slice(1);
        const el = document.getElementById(id);
        if (el) { e.preventDefault(); el.scrollIntoView({ behavior: 'smooth' }); }
      });
    });
  }
  return { init };
})();

// ── Chatbot ─────────────────────────────────────
const Chatbot = (() => {
  const sessaoId = 'sess_' + Math.random().toString(36).slice(2, 10);
  let aberto = false;

  function init() {
    const toggleBtn = document.getElementById('chatbotToggle');
    // Agora usamos apenas o botão da coroa para detectar o mouse
    let hoverTimeout;

    // Abrir automaticamente ao passar o mouse APENAS no botão (Coroa)
    toggleBtn?.addEventListener('mouseenter', () => {
      hoverTimeout = setTimeout(() => {
        if (!aberto) _toggle();
      }, 200);
    });

    // Cancela se o mouse sair do botão
    toggleBtn?.addEventListener('mouseleave', () => {
      clearTimeout(hoverTimeout);
    });

    // Mantém o clique original
    toggleBtn?.addEventListener('click', _toggle);
    
    document.getElementById('chatSend')?.addEventListener('click', _enviar);
    document.getElementById('chatInput')?.addEventListener('keydown', e => {
      if (e.key === 'Enter') { e.preventDefault(); _enviar(); }
    });
  }

 function _toggle() {
    aberto = !aberto;
    const win = document.getElementById('chatbotWindow');
    const label = document.querySelector('.chat-label'); // Seleciona o balão de ajuda
    
    // Abre/fecha a janela do chat
    win?.classList.toggle('open', aberto);
    
    // Esconde o balão "Posso ajudar?" se o chat estiver aberto
    if (label) {
      label.style.display = aberto ? 'none' : 'block';
    }

    // Gerencia os ícones da coroa e do fechar (X)
    const iconOpen = document.getElementById('chatIconOpen');
    const iconClose = document.getElementById('chatIconClose');
    if (iconOpen) iconOpen.style.display = aberto ? 'none' : 'inline';
    if (iconClose) iconClose.style.display = aberto ? 'inline' : 'none';

    // Dispara a mensagem de boas-vindas na primeira abertura
    const msgs = document.getElementById('chatMessages');
    if (aberto && msgs && msgs.children.length === 0) {
      setTimeout(() => _addBotMsg({
        tipo: 'botoes',
        texto: '👋 Olá! Bem-vindo ao *Rei da Esfirra*! 🥙\nComo posso te ajudar?',
        botoes: ['📋 Cardápio', '⏰ Horários', '📍 Localização', '💰 Preços', '🛵 Delivery'],
      }), 350);
    }
  }

  async function _enviar() {
    const input = document.getElementById('chatInput');
    const texto = input?.value.trim();
    if (!texto) return;
    input.value = '';
    _addUserMsg(texto);
    _addTyping();
    try {
      const resp = await Api.postChatbot(texto, sessaoId);
      setTimeout(() => { _removeTyping(); _addBotMsg(resp); }, 600);
    } catch {
      setTimeout(() => { _removeTyping(); _addBotMsg({ tipo:'texto', texto:'😔 Erro de conexão.' }); }, 600);
    }
  }

  window.chatClicar = (t) => { const i = document.getElementById('chatInput'); if(i) i.value = t; _enviar(); };

  function _addUserMsg(t) { const el = document.createElement('div'); el.className = 'msg user'; el.textContent = t; _append(el); }
  function _addBotMsg(d) {
    const el = document.createElement('div'); el.className = 'msg bot'; el.innerHTML = _mdLite(d.texto || ''); _append(el);
    if (Array.isArray(d.botoes) && d.botoes.length) {
      const w = document.createElement('div'); w.className = 'msg-botoes';
      d.botoes.forEach(b => {
        const btn = document.createElement('button'); btn.className = 'msg-btn'; btn.textContent = b;
        btn.addEventListener('click', () => window.chatClicar(b));
        w.appendChild(btn);
      });
      _append(w);
    }
  }
  function _addTyping() { const el = document.createElement('div'); el.className = 'typing-indicator'; el.id = 'chatTyping'; for(let i=0;i<3;i++){ const d=document.createElement('div'); d.className='typing-dot'; el.appendChild(d); } _append(el); }
  function _removeTyping() { document.getElementById('chatTyping')?.remove(); }
  function _append(el) { const c = document.getElementById('chatMessages'); if(c) { c.appendChild(el); c.scrollTop = c.scrollHeight; } }
  function _mdLite(s) { return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\*(.*?)\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>'); }

  return { init };
})();

// ── Bootstrap ────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  Navbar.init();
  CartUI.init();
  Chatbot.init();
  Pedido.init();
  Cardapio.initFiltros();
  Cardapio.carregar();
  UI.initScrollReveal();

  const heroBtn = document.getElementById('heroAddBtn');
  if (heroBtn) {
    heroBtn.addEventListener('click', () => {
      Cart.adicionar({ produto_id: 1, nome: 'Esfirra de Carne', preco: 7.50, imagem: 'carne' });
      heroBtn.textContent = '✓ Adicionado';
      heroBtn.style.background = '#28a745';
      heroBtn.style.color = '#fff';
      setTimeout(() => CartUI.abrir(), 400);
      setTimeout(() => { heroBtn.textContent = '+ Adicionar'; heroBtn.style.background = ''; heroBtn.style.color = ''; }, 2000);
    });
  }

  document.getElementById('btnVoltarCompras')?.addEventListener('click', () => CartUI.fechar());

  // Mapa — abrir no Google Maps ao clicar
  document.querySelector('.mapa-card')?.addEventListener('click', () => {
    window.open('https://maps.app.goo.gl/j2e2DSYPWfaVFi7N9', '_blank', 'noopener');
  });
}); 