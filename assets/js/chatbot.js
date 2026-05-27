/**
 * assets/js/chatbot.js
 *
 * Módulo do chatbot integrado ao backend/chatbot_response.php.
 *
 * ┌─────────────────────────────────────────────────────────────┐
 * │ O QUE MUDOU                                                  │
 * │ Antes o front mandava apenas `sessao_id`, que o banco nem    │
 * │ tinha coluna para receber. Agora ele trabalha com            │
 * │ `conversation_id`: na 1ª mensagem manda null, o backend cria │
 * │ a conversa e devolve o id, e o front passa a reenviar esse   │
 * │ id nas próximas mensagens. É isso que fecha o fluxo          │
 * │ chat_conversations → chat_messages exigido no projeto.       │
 * └─────────────────────────────────────────────────────────────┘
 */

const Reibot = (() => {
  const ENDPOINT = "backend/chatbot_response.php";
  const STORAGE_KEY = "reibot_conversation_id"; // antes guardava a sessão

  /**
   * Recupera o conversation_id salvo nesta aba (se já houve conversa).
   * Pode ser null/NaN na primeira vez — tudo bem, o backend cria.
   */
  let conversationId = (() => {
    const salvo = sessionStorage.getItem(STORAGE_KEY);
    return salvo ? parseInt(salvo, 10) : null;
  })();

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

    // Mensagem de boas-vindas só na primeira abertura.
    if (chatAberto && msgs && msgs.children.length === 0) {
      setTimeout(
        () =>
          _renderBotMsg({
            tipo: "bot",
            texto: "Olá! Bem-vindo ao *Rey da Esfirra*! Como posso te ajudar?",
            botoes: ["Cardápio", "Horários", "Localização", "Preços", "Delivery"],
          }),
        350,
      );
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
        // 👇 enviamos o conversation_id atual (null na 1ª vez)
        body: JSON.stringify({
          mensagem: texto,
          conversation_id: conversationId,
        }),
      });

      const data = await res.json();

      // 👇 guardamos o id que o backend criou/confirmou, para reenviar depois
      if (data.conversation_id) {
        conversationId = data.conversation_id;
        sessionStorage.setItem(STORAGE_KEY, String(conversationId));
      }

      // Pequeno atraso para parecer "digitação" humana.
      setTimeout(() => {
        _removerTyping();

        if (!data.sucesso) {
          _renderBotMsg({
            tipo: "bot",
            texto: data.texto || "Erro. Tente novamente.",
          });
          return;
        }

        // De vez em quando, prefixa uma saudação em respostas longas.
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

  // Permite que os botões de ação rápida "digitem" por você.
  window.chatClicar = function (texto) {
    const input = document.getElementById("chatInput");
    if (input) input.value = texto;
    _enviar();
  };

  function _renderUserMsg(texto) {
    const el = document.createElement("div");
    el.className = "msg user";
    // textContent impede execução de HTML vindo do usuário (anti-XSS).
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

    // innerHTML é usado porque queremos markdown mínimo, mas o conteúdo
    // é escapado ANTES em _mdLite() — então não há injeção de HTML real.
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
   * 1. Escapa todo o HTML.
   * 2. Só depois aplica markdown controlado (*negrito* e quebras de linha).
   * 3. Nunca permite tags HTML reais vindas da IA/usuário.
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
