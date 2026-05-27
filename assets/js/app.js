/**
 * assets/js/app.js
 * Orquestrador principal da aplicação.
 *
 * Ajustes da revisão:
 *  - esc(): escapa dados antes de ir para innerHTML (anti-XSS no cardápio/carrinho)
 *  - carrinho: botões via delegação de eventos (sem onclick inline)
 *  - removido o bloco morto do #heroAddBtn (o hero agora usa um link <a>)
 */

// ── Utilitário anti-XSS ──────────────────────────
// Escapa caracteres perigosos antes de inserir via innerHTML.
function esc(s) {
  return String(s ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

// ── Namespace UI (utilitários compartilhados) ────
const UI = (() => {
  function toast(icon, mensagem, tipo = "info") {
    const container = document.getElementById("toastContainer");
    if (!container) return;
    const el = document.createElement("div");
    el.className = `toast ${tipo}`;
    const iconEl = document.createElement("span");
    iconEl.className = "toast-icon";
    iconEl.textContent = icon;
    const body = document.createElement("div");
    body.className = "toast-body";
    const txt = document.createElement("span");
    txt.textContent = mensagem;
    body.appendChild(txt);
    el.appendChild(iconEl);
    el.appendChild(body);
    container.appendChild(el);
    setTimeout(() => {
      el.style.transition = "opacity .3s, transform .3s";
      el.style.opacity = "0";
      el.style.transform = "translateX(20px)";
      setTimeout(() => el.remove(), 320);
    }, 4200);
  }

  function initScrollReveal() {
    if (!("IntersectionObserver" in window)) return;
    const obs = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (!e.isIntersecting) return;
          e.target.style.opacity = "1";
          e.target.style.transform = "translateY(0)";
          obs.unobserve(e.target);
        });
      },
      { threshold: 0.1 },
    );
    document
      .querySelectorAll(".feat-item, .info-item, .review-card, .sb")
      .forEach((el, i) => {
        el.style.opacity = "0";
        el.style.transform = "translateY(20px)";
        el.style.transition = `opacity .5s ease ${i * 0.08}s, transform .5s ease ${i * 0.08}s`;
        obs.observe(el);
      });
  }
  return { toast, initScrollReveal };
})();

// ── Cardápio ────────────────────────────────────
const Cardapio = (() => {
  const RATINGS = [
    "4.9", "4.8", "4.7", "4.9", "4.8", "5.0", "4.7",
    "4.6", "4.8", "4.9", "5.0", "4.7", "4.8",
  ];
  let todosProdutos = [];

  async function carregar() {
    try {
      todosProdutos = await Api.getProdutos();
      renderizar(todosProdutos);
    } catch (err) {
      const grid = document.getElementById("produtosGrid");
      if (!grid) return;
      grid.innerHTML = `<div class="loading-state" style="color:#ff6b4a">⚠️ Erro ao carregar o cardápio.</div>`;
    }
  }

  function renderizar(produtos) {
    const grid = document.getElementById("produtosGrid");
    const countEl = document.getElementById("cardapioCount");
    if (!grid) return;
    const catAtiva =
      document.querySelector(".cat-pill.active")?.dataset.cat || "Todos";
    if (countEl) {
      countEl.textContent = `${produtos.length} ${produtos.length === 1 ? "item" : "itens"}${catAtiva !== "Todos" ? ` em ${catAtiva}` : " disponíveis"}`;
    }
    grid.innerHTML = "";
    if (!produtos.length) {
      grid.innerHTML = `<div class="loading-state">🔍 Nenhum item nessa categoria.</div>`;
      return;
    }
    produtos.forEach((p, i) => {
      const preco = parseFloat(p.preco).toFixed(2).replace(".", ",");
      const rating = RATINGS[i % RATINGS.length];
      const card = document.createElement("div");
      card.className = "produto-card";
      card.style.animationDelay = `${i * 0.04}s`;
      // Valores do banco são escapados com esc() antes de entrar no HTML.
      const nome = esc(p.nome);
      const desc = esc(p.descricao || "");
      const img = esc(p.imagem || "carne.webp");
      card.innerHTML = `
        <div class="produto-img-wrap">
          <img src="assets/images/${img}" alt="${nome}" loading="lazy" onerror="this.onerror=null; this.src='assets/images/carne.webp';">
          <div class="produto-img-overlay"></div>
          <div class="produto-preco-overlay">R$ ${preco}</div>
        </div>
        <div class="produto-body">
          <div class="produto-nome">${nome}</div>
          <div class="produto-desc">${desc}</div>
          <div class="produto-footer">
            <span class="produto-rating">★ ${rating}</span>
            <button class="btn-add-cart">+ Adicionar</button>
          </div>
        </div>
      `;
      const btnAdd = card.querySelector(".btn-add-cart");
      btnAdd.addEventListener("click", () => {
        Cart.adicionar({
          produto_id: parseInt(p.id),
          nome: p.nome,
          preco: parseFloat(p.preco),
          imagem: p.imagem,
        });
        const originalText = btnAdd.textContent;
        btnAdd.textContent = "✓ Adicionado";
        btnAdd.style.background = "#28a745";
        btnAdd.style.color = "#fff";
        setTimeout(() => {
          btnAdd.textContent = originalText;
          btnAdd.style.background = "";
          btnAdd.style.color = "";
        }, 1500);
      });
      grid.appendChild(card);
    });
  }

  function initFiltros() {
    document
      .getElementById("categoriasInner")
      ?.addEventListener("click", async (e) => {
        const btn = e.target.closest(".cat-pill");
        if (!btn) return;
        document
          .querySelectorAll(".cat-pill")
          .forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        const cat = btn.dataset.cat;
        const filtrados =
          cat === "Todos"
            ? todosProdutos
            : todosProdutos.filter((p) => p.categoria === cat);
        renderizar(filtrados);
        document
          .getElementById("cardapio")
          ?.scrollIntoView({ behavior: "smooth", block: "start" });
      });
  }
  return { carregar, initFiltros };
})();

// ── Sidebar do carrinho ─────────────────────────
const CartUI = (() => {
  function init() {
  document.getElementById("cartToggle")?.addEventListener("click", abrir);
  document.getElementById("cartClose")?.addEventListener("click", fechar);
  document.getElementById("cartOverlay")?.addEventListener("click", fechar);
  window.addEventListener("cart:update", (e) => _renderizar(e.detail));

  document
    .getElementById("cartItems")
    ?.addEventListener("click", _onItemClick);

  document.getElementById("cartFooter")?.addEventListener("click", (e) => {
    const btnFinalizar = e.target.closest("#btnFinalizarPedido, #abrirModal");
    if (btnFinalizar) {
      e.preventDefault();
      Pedido.abrirModal();
      return;
    }

    const btnVoltar = e.target.closest("#btnVoltarCompras");
    if (btnVoltar) {
      e.preventDefault();
      fechar();
    }
  });
}

  // Lê data-acao / data-id do botão clicado e chama o Cart.
  function _onItemClick(e) {
    const btn = e.target.closest("[data-acao]");
    if (!btn) return;
    const id = Number(btn.dataset.id);
    switch (btn.dataset.acao) {
      case "inc": Cart.alterarQuantidade(id, 1); break;
      case "dec": Cart.alterarQuantidade(id, -1); break;
      case "del": Cart.remover(id); break;
    }
  }

  function abrir() {
    document.getElementById("cartSidebar")?.classList.add("open");
    document.getElementById("cartOverlay")?.classList.add("open");
  }

  function fechar() {
    document.getElementById("cartSidebar")?.classList.remove("open");
    document.getElementById("cartOverlay")?.classList.remove("open");
  }

  function _renderizar({ itens, total, unidades }) {
    const badge = document.getElementById("cartBadge");
    if (badge) badge.textContent = unidades;

    // 1. O Subtítulo do topo (Logo abaixo do título principal)
    const sub = document.getElementById("cartHeaderSub");
    if (sub) {
      sub.textContent = unidades
        ? `${unidades} ${unidades === 1 ? "item" : "itens"}`
        : "Nenhuma esfiha por aqui ainda..."; 
    }

    const itemsEl = document.getElementById("cartItems");
    const footerEl = document.getElementById("cartFooter");
    if (!itemsEl) return;

    itemsEl.innerHTML = "";

    // 2. O miolo do carrinho quando está totalmente zerado
    if (!itens.length) {
      itemsEl.innerHTML = `
        <div class="cart-empty" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; text-align: center;">
          <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#cccccc" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 16px;">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <path d="M16 10a4 4 0 0 1-8 0"></path>
          </svg>
          <p style="color: var(--text-main); font-weight: 800; font-size: 1.2rem; margin-bottom: 4px;">Bateu a fome? 🍕</p>
          <small style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 16px;">Escolha suas esfirras favoritas para começarmos!</small>
          <button type="button" class="btn-voltar-compras" id="btnVoltarVazio">&larr; Ver Cardápio</button>
        </div>`;

      document
        .getElementById("btnVoltarVazio")
        ?.addEventListener("click", fechar);

      if (footerEl) footerEl.style.display = "none";
      return;
    }

    itens.forEach((item) => {
      const el = document.createElement("div");
      el.className = "cart-item";
      const nome = esc(item.nome);
      const img = esc(item.imagem || "carne.webp");
      // Botões usam data-acao/data-id; o clique é tratado por delegação.
      el.innerHTML = `
        <div class="ci-img">
          <img src="assets/images/${img}" onerror="this.onerror=null; this.src='assets/images/carne.webp'" alt="${nome}">
        </div>
        <div class="ci-info">
          <div class="ci-nome">${nome}</div>
          <div class="ci-preco">R$ ${(item.preco * item.quantidade).toFixed(2).replace(".", ",")}</div>
        </div>
        <div class="ci-controls">
          <button class="ci-btn" data-acao="dec" data-id="${item.produto_id}" aria-label="Diminuir quantidade">−</button>
          <span class="ci-qty">${item.quantidade}</span>
          <button class="ci-btn" data-acao="inc" data-id="${item.produto_id}" aria-label="Aumentar quantidade">+</button>
          <button class="ci-del" data-acao="del" data-id="${item.produto_id}" aria-label="Remover item">✕</button>
        </div>`;

      itemsEl.appendChild(el);
    });

 if (footerEl) {
      footerEl.style.display = "block";

      const taxa = total >= 60 ? 0 : 5;

      // Monta a estrutura em linhas organizadas usando flexbox inline
      footerEl.innerHTML = `
        <div class="cart-resumo-container" style="padding: 10px 0; border-bottom: 1px dashed #e5e5e5; margin-bottom: 15px;">
          
          <div class="cart-frete-alerta" style="font-size: 0.85rem; color: #666; text-align: left; margin-bottom: 12px; font-style: italic;">
            ${total >= 60 ? '🎉 Parabéns! Você ganhou <strong>Frete Grátis</strong>!' : '💡 <em>Frete grátis em pedidos acima de R$ 60!</em>'}
          </div>

          <div class="resumo-item-linha" style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.95rem;">
            <span style="color: #555;">Subtotal</span>
            <strong style="color: #000; font-weight: 700;">R$ ${total.toFixed(2).replace(".", ",")}</strong>
          </div>
          
          <div class="resumo-item-linha" style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 0.95rem;">
            <span style="color: #555;">Taxa de Entrega</span>
            <span id="entregaValue">
              ${taxa === 0 
                ? '<strong style="color: var(--neon, #28a745); font-weight: 800 !important;">Grátis 🎉</strong>' 
                : `<strong>R$ ${taxa.toFixed(2).replace(".", ",")}</strong>`}
            </span>
          </div>

        </div>

        <div class="cart-total-row" style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; margin-bottom: 15px;">
          <span style="font-weight: 800; font-size: 1.1rem; color: var(--text-main, #000);">Total</span>
          <span id="totalValue" style="font-weight: 900; font-size: 1.4rem; color: #e4002b;">R$ ${(total + taxa).toFixed(2).replace(".", ",")}</span>
        </div>

        <button class="btn-finalizar" id="btnFinalizarPedido" style="display: block; width: 100%; padding: 14px; background: #e4002b; color: #fff; border: none; border-radius: 8px; font-weight: 700; font-size: 1rem; cursor: pointer; margin-bottom: 12px;">
          FINALIZAR PEDIDO &rarr;
        </button>

        <button type="button" class="btn-voltar-compras" id="btnVoltarCompras" style="display: block; width: 100%; text-align: center;">
          &larr; Continuar Comprando
        </button>
      `;

      // Garante que as ações dos botões recriados continuem funcionando
    }
  }

  // ── O fechamento correto e o retorno do módulo ──
  return { init, abrir, fechar };
})();

// ── Navbar ──────────────────────────────────────
const Navbar = (() => {
  function init() {
    const navbar = document.getElementById("navbar");
    const hamburger = document.getElementById("hamburger");
    const navLinks = document.getElementById("navLinks");
    window.addEventListener(
      "scroll",
      () => navbar?.classList.toggle("scrolled", window.scrollY > 20),
      { passive: true },
    );
    hamburger?.addEventListener("click", () => {
      const open = navLinks?.classList.toggle("nav-open");
      if (hamburger) hamburger.textContent = open ? "✕" : "☰";
    });
    navLinks?.querySelectorAll(".nav-link").forEach((link) => {
      link.addEventListener("click", () => {
        navLinks.classList.remove("nav-open");
        if (hamburger) hamburger.textContent = "☰";
      });
    });
    document.querySelectorAll('a[href^="#"]').forEach((link) => {
      link.addEventListener("click", (e) => {
        const id = link.getAttribute("href").slice(1);
        const el = document.getElementById(id);
        if (el) {
          e.preventDefault();
          el.scrollIntoView({ behavior: "smooth" });
        }
      });
    });
  }
  return { init };
})();

// ── Bootstrap ────────────────────────────────────
document.addEventListener("DOMContentLoaded", () => {
  Navbar.init();
  CartUI.init();

  Pedido.init();
  Cardapio.initFiltros();
  Cardapio.carregar();
  UI.initScrollReveal();

  document
    .getElementById("btnVoltarCompras")
    ?.addEventListener("click", () => CartUI.fechar());

  document
    .querySelector(".mapa-card")
    ?.addEventListener("click", () =>
      window.open("https://www.google.com/maps", "_blank", "noopener"),
    );
});
