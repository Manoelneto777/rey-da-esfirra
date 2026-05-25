/**
 * assets/js/api.js
 * Camada de comunicação com o backend PHP.
 * Todas as chamadas fetch ficam aqui — nenhum outro
 * arquivo faz requisições diretamente (exceto o chatbot.js,
 * que tem o próprio endpoint relativo).
 *
 * Ajuste da revisão:
 *  - BASE agora é RELATIVO ("backend"), igual ao chatbot.js.
 *    Antes era absoluto (http://localhost/reydaesfirra/backend),
 *    o que quebrava ao abrir o site em outra porta/host ou ao publicar.
 *  - Removido o postChatbot morto (estava comentado e com bug de barra).
 */

const Api = (() => {
  /** Prefixo do backend, relativo à pasta do index.html. */
  const BASE = "backend";

  /**
   * Busca produtos, com filtro opcional de categoria.
   * @param {string} categoria  '' ou 'Todos' para todos
   * @returns {Promise<Array>}
   */
  async function getProdutos(categoria = "") {
    const params =
      categoria && categoria !== "Todos"
        ? `?categoria=${encodeURIComponent(categoria)}`
        : "";

    const res = await fetch(`${BASE}/produtos.php${params}`);

    if (!res.ok) throw new Error(`HTTP ${res.status}`);

    const json = await res.json();
    if (!json.sucesso) throw new Error(json.erro || "Erro ao buscar produtos.");

    return json.data; // array de produtos
  }

  /**
   * Envia um pedido completo para o backend.
   * O backend recalcula preço/total no servidor — aqui só enviamos
   * produto_id e quantidade (preço vai junto apenas como referência).
   * @param {Object} pedido
   * @returns {Promise<{ sucesso, pedido_id, mensagem }>}
   */
  async function postPedido(pedido) {
    const res = await fetch(`${BASE}/pedidos.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(pedido),
    });

    const json = await res.json();

    if (!res.ok || !json.sucesso) {
      const msg = json.erros
        ? json.erros.join("\n")
        : json.erro || "Erro desconhecido.";
      throw new Error(msg);
    }

    return json; // { sucesso, pedido_id, mensagem }
  }

  // Expõe apenas o necessário
  return { getProdutos, postPedido };
})();
