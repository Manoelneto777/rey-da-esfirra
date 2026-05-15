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
  const ENDPOINT = "backend/chatbot_response.php";
  const STORAGE_KEY = "reibot_sessao_id";

  /**
   * Persistência de sessão:
   * mantém o mesmo ID após F5 durante a aba aberta.
   * Troque sessionStorage por localStorage se quiser manter entre abas/sessões.
   */
  let sessaoId = sessionStorage.getItem(STORAGE_KEY);

  if (!sessaoId) {
    sessaoId = "sess_" + crypto.randomUUID().replace(/-/g, "").slice(0, 16);
    sessionStorage.setItem(STORAGE_KEY, sessaoId);
  }

  const SAUDACOES = [
    "Boa escolha! Já te ajudo. ",
    "Perfeito! ",
    "Olá! ",
    "Com prazer! ",
  ];

  let chatAberto = false;

  function init() {
    document.getElementById("chatbotToggle")?.addEventListener("click", _toggle);
    document.getElementById("chatSend")?.addEventListener("click", _enviar);

    document.getElementById("chatInput")?.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        _enviar();
      }
    });
  }

  function _toggle() {
    chatAberto = !chatAberto;

    document.getElementById("chatbotWindow")?.classList.toggle("open", chatAberto);

    const iconOpen = document.getElementById("chatIconOpen");
    const iconClose = document.getElementById("chatIconClose");

    if (iconOpen) iconOpen.style.display = chatAberto ? "none" : "inline";
    if (iconClose) iconClose.style.display = chatAberto ? "inline" : "none";

    const msgs = document.getElementById("chatMessages");

    if (chatAberto && msgs && msgs.children.length === 0) {
      setTimeout(() => {
        _renderBotMsg({
          tipo: "bot",
          texto: "Olá! Bem-vindo ao *Rey da Esfirra*! Como posso te ajudar? 👑",
          botoes: ["Cardápio", "Horários", "Localização", "Preço", "Delivery"],
        });
      }, 350);
    }
  }

  async function _enviar() {
    const input = document.getElementById("chatInput");
    const texto = input?.value.trim();

    if (!texto) return;

    input.value = "";

    _renderUserMsg(texto);
    _mostrarTyping();

    try {
      const res = await fetch(ENDPOINT, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ mensagem: texto, sessao_id: sessaoId }),
      });

      const data = await res.json();

      setTimeout(() => {
        _removerTyping();

        if (!data.sucesso) {
          _renderBotMsg({
            tipo: "bot",
            texto: data.texto || "Erro. Tente novamente.",
          });
          return;
        }

        if (data.tipo === "bot" && data.texto?.length > 40 && Math.random() < 0.2) {
          const saud = SAUDACOES[Math.floor(Math.random() * SAUDACOES.length)];
          data.texto = saud + data.texto;
        }

        _renderBotMsg(data);
      }, 500 + Math.random() * 600);
    } catch (error) {
      console.error("[Reibot]", error);

      setTimeout(() => {
        _removerTyping();
        _renderBotMsg({
          tipo: "bot",
          texto: "Problema de conexão. Verifique se o servidor está rodando.",
        });
      }, 600);
    }
  }

  window.chatClicar = function (texto) {
    const input = document.getElementById("chatInput");
    if (input) input.value = texto;
    _enviar();
  };

  function _renderUserMsg(texto) {
    const el = document.createElement("div");
    el.className = "msg user";

    // textContent impede execução de HTML vindo do usuário.
    el.textContent = texto;

    _append(el);
  }

  function _renderBotMsg(data) {
    const isAi = data.tipo === "ai";

    const el = document.createElement("div");
    el.className = "msg bot";

    if (isAi) {
      el.style.borderLeft = "3px solid var(--secondary, #E67E22)";
    }

    /**
     * Ainda usamos innerHTML porque queremos permitir markdown mínimo,
     * mas o conteúdo é escapado antes em _mdLite().
     */
    el.innerHTML = _mdLite(data.texto || "");

    _append(el);

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

    if (isAi) {
      const badge = document.createElement("span");
      badge.style.cssText =
        "font-size:0.68rem;color:var(--secondary,#E67E22);margin-left:4px;opacity:0.8";
      badge.textContent = "✨ via IA";
      el.appendChild(badge);
    }
  }

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

  function _append(el) {
    const container = document.getElementById("chatMessages");
    if (!container) return;

    container.appendChild(el);
    container.scrollTop = container.scrollHeight;
  }

  /**
   * Sanitização leve contra XSS:
   * 1. Escapa HTML inteiro.
   * 2. Só depois aplica markdown controlado.
   * 3. Não permite tags HTML reais vindas da IA/usuário.
   */
  function _mdLite(s) {
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;")
      .replace(/\*(.*?)\*/g, "<strong>$1</strong>")
      .replace(/\n/g, "<br>");
  }

  return { init };
})();