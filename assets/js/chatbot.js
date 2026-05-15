/**
 * assets/js/chatbot.js
 *
 * Modulo do chatbot integrado com backend/chatbot_response.php
 * Faz parte da arquitetura MVC simplificada do projeto.
 *
 * Fluxo:
 *  1. Usuario digita mensagem
 *  2. JS envia via fetch POST para backend/chatbot_response.php
 *  3. PHP processa (manual ou IA) e retorna JSON
 *  4. JS renderiza a resposta na janela do chat
 */

const Reibot = (() => {
  // ── Configuracao ─────────────────────────────────────────
  const ENDPOINT = "backend/chatbot_response.php";
  const sessaoId = "sess_" + Math.random().toString(36).slice(2, 10);

  // Variacao de saudacao para dar personalidade
  const SAUDACOES = [
    "Boa escolha! Ja te ajudo. ",
    "Perfeito! ",
    "Ola! ",
    "Com prazer! ",
  ];

  let chatAberto = false;

  // ── Init ──────────────────────────────────────────────────
  function init() {
    document
      .getElementById("chatbotToggle")
      ?.addEventListener("click", _toggle);

    document.getElementById("chatSend")?.addEventListener("click", _enviar);

    document.getElementById("chatInput")?.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        _enviar();
      }
    });
  }

  // ── Toggle janela ─────────────────────────────────────────
  function _toggle() {
    chatAberto = !chatAberto;

    document
      .getElementById("chatbotWindow")
      ?.classList.toggle("open", chatAberto);

    // Alterna icones
    const iconOpen = document.getElementById("chatIconOpen");
    const iconClose = document.getElementById("chatIconClose");
    if (iconOpen) iconOpen.style.display = chatAberto ? "none" : "inline";
    if (iconClose) iconClose.style.display = chatAberto ? "inline" : "none";

    // Mensagem de boas-vindas na primeira abertura
    const msgs = document.getElementById("chatMessages");
    if (chatAberto && msgs && msgs.children.length === 0) {
      setTimeout(
        () =>
          _renderBotMsg({
            tipo: "bot",
            texto: "Ola! Bem-vindo ao *Rei da Esfirra*! Como posso te ajudar?",
            botoes: [
              "Cardápio",
              "Horários",
              "Localização",
              "Preços",
              "Delivery",
            ],
          }),
        350,
      );
    }
  }

  // ── Enviar mensagem ───────────────────────────────────────
  async function _enviar() {
    const input = document.getElementById("chatInput");
    const texto = input?.value.trim();
    if (!texto) return;
    input.value = "";

    // Renderiza mensagem do usuario
    _renderUserMsg(texto);
    _mostrarTyping();

    try {
      const res = await fetch(ENDPOINT, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ mensagem: texto, sessao_id: sessaoId }),
      });

      const data = await res.json();

      // Delay simulado para parecer mais humano
      const delay = 500 + Math.random() * 600;
      setTimeout(() => {
        _removerTyping();

        if (!data.sucesso) {
          _renderBotMsg({
            tipo: "bot",
            texto: data.texto || "Erro. Tente novamente.",
          });
          return;
        }

        // Adiciona variacao de saudacao (20% das vezes, apenas em respostas longas)
        if (
          data.tipo === "bot" &&
          data.texto.length > 40 &&
          Math.random() < 0.2
        ) {
          const saud = SAUDACOES[Math.floor(Math.random() * SAUDACOES.length)];
          data.texto = saud + data.texto;
        }

        _renderBotMsg(data);
      }, delay);
    } catch {
      setTimeout(() => {
        _removerTyping();
        _renderBotMsg({
          tipo: "bot",
          texto: "Problema de conexao. Verifique se o servidor esta rodando.",
        });
      }, 600);
    }
  }

  // Clique nos botoes de acao rapida
  window.chatClicar = function (texto) {
    const input = document.getElementById("chatInput");
    if (input) input.value = texto;
    _enviar();
  };

  // ── Renderizacao ──────────────────────────────────────────

  /** Renderiza mensagem do usuario */
  function _renderUserMsg(texto) {
    const el = document.createElement("div");
    el.className = "msg user";
    el.textContent = texto;
    _append(el);
  }

  /** Renderiza resposta do bot/IA */
  function _renderBotMsg(data) {
    // Indica visualmente se e resposta de IA
    const isAi = data.tipo === "ai";

    const el = document.createElement("div");
    el.className = "msg bot";
    if (isAi) el.style.borderLeft = "3px solid var(--secondary, #E67E22)";
    el.innerHTML = _mdLite(data.texto || "");
    _append(el);

    // Renderiza botoes de acao rapida
    if (Array.isArray(data.botoes) && data.botoes.length) {
      const wrap = document.createElement("div");
      wrap.className = "msg-botoes";

      data.botoes.forEach((b) => {
        const btn = document.createElement("button");
        btn.className = "msg-btn";
        btn.textContent = b;
        btn.addEventListener("click", () => window.chatClicar(b));
        wrap.appendChild(btn);
      });

      _append(wrap);
    }

    // Badge "IA" se resposta vier da inteligencia artificial
    if (isAi) {
      const badge = document.createElement("span");
      badge.style.cssText =
        "font-size:0.68rem;color:var(--secondary,#E67E22);margin-left:4px;opacity:0.8";
      badge.textContent = "✨ via IA";
      el.appendChild(badge);
    }
  }

  /** Indicador de "digitando..." */
  function _mostrarTyping() {
    const el = document.createElement("div");
    el.className = "typing-indicator";
    el.id = "chatTyping";
    for (let i = 0; i < 3; i++) {
      const d = document.createElement("div");
      d.className = "typing-dot";
      el.appendChild(d);
    }
    _append(el);
  }

  function _removerTyping() {
    document.getElementById("chatTyping")?.remove();
  }

  /** Adiciona elemento na lista de mensagens e faz scroll */
  function _append(el) {
    const container = document.getElementById("chatMessages");
    if (!container) return;
    container.appendChild(el);
    container.scrollTop = container.scrollHeight;
  }

  /**
   * Markdown minimo: *negrito* e quebras de linha.
   * Escapa HTML antes para evitar XSS.
   */
  function _mdLite(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\*(.*?)\*/g, "<strong>$1</strong>")
      .replace(/\n/g, "<br>");
  }

  // Expoe apenas o necessario
  return { init };
})();
