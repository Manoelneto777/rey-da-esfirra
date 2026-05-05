/**
 * assets/js/api.js
 * Camada de comunicação com o backend PHP.
 * Todas as chamadas fetch ficam aqui — nenhum outro
 * arquivo faz requisições diretamente.
 */

const Api = (() => {
  /** Prefixo do backend — ajuste se mudar o host */
  const BASE = "http://localhost/reydaesfirra-chatbot/backend";

  /**
   * Busca produtos, com filtro opcional de categoria.
   * @param {string} categoria  '' ou 'Todos' para todos
   * @returns {Promise<Array>}
   */
  async function getProdutos(categoria = '') {
    const params = categoria && categoria !== 'Todos'
      ? `?categoria=${encodeURIComponent(categoria)}`
      : '';

    const res = await fetch(`${BASE}/produtos.php${params}`);

    if (!res.ok) throw new Error(`HTTP ${res.status}`);

    const json = await res.json();
    if (!json.sucesso) throw new Error(json.erro || 'Erro ao buscar produtos.');

    return json.data; // array de produtos
  }

  /**
   * Envia um pedido completo para o backend.
   * @param {Object} pedido
   * @param {string} pedido.nome_cliente
   * @param {string} pedido.telefone
   * @param {string} pedido.endereco
   * @param {string} pedido.observacoes
   * @param {number} pedido.total
   * @param {Array}  pedido.itens   [{ produto_id, quantidade, preco, nome }]
   * @returns {Promise<{ sucesso, pedido_id, mensagem }>}
   */
  async function postPedido(pedido) {
    const res = await fetch(`${BASE}/pedidos.php`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(pedido),
    });

    const json = await res.json();

    if (!res.ok || !json.sucesso) {
      const msg = json.erros ? json.erros.join('\n') : (json.erro || 'Erro desconhecido.');
      throw new Error(msg);
    }

    return json; // { sucesso, pedido_id, mensagem }
  }

  /**
   * Envia mensagem para o chatbot.
   * @param {string} mensagem
   * @param {string} sessaoId
   * @returns {Promise<{ tipo, texto, botoes? }>}
   */
  async function postChatbot(mensagem, sessaoId) {
    const res = await fetch(`${BASE}/chatbot.php`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ mensagem, sessao_id: sessaoId }),
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  // Expõe apenas o necessário
  return { getProdutos, postPedido, postChatbot };
})();
