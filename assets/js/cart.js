/**
 * assets/js/cart.js
 * Gerencia o carrinho de compras.
 * Persiste no localStorage para sobreviver a recarregamentos.
 * Não tem dependência de outros módulos.
 */

const Cart = (() => {
  const STORAGE_KEY = 'rei_esfirra_cart';

  // ── Estado interno ──────────────────────────────
  let itens = _carregar();

  // ── Persistência ───────────────────────────────
  function _salvar() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(itens));
    } catch {
      console.warn('Cart: não foi possível salvar no localStorage.');
    }
  }

  function _carregar() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      return raw ? JSON.parse(raw) : [];
    } catch {
      return [];
    }
  }

  // ── Operações ───────────────────────────────────

  /**
   * Adiciona um produto ou incrementa a quantidade.
   * @param {{ produto_id, nome, preco, imagem }} produto
   * @param {number} quantidade
   */
  function adicionar(produto, quantidade = 1) {
    if (!produto.produto_id || produto.preco <= 0) return;

    const idx = itens.findIndex(i => i.produto_id === produto.produto_id);

    if (idx >= 0) {
      itens[idx].quantidade += quantidade;
    } else {
      itens.push({
        produto_id: produto.produto_id,
        nome:       produto.nome,
        preco:      produto.preco,
        imagem:     produto.imagem || '',
        quantidade,
      });
    }

    _salvar();
    _emitirAtualizacao();
  }

  /**
   * Altera a quantidade de um item.
   * Remove o item se a nova quantidade for <= 0.
   */
  function alterarQuantidade(produto_id, delta) {
    const idx = itens.findIndex(i => i.produto_id === produto_id);
    if (idx < 0) return;

    itens[idx].quantidade += delta;

    if (itens[idx].quantidade <= 0) {
      itens.splice(idx, 1);
    }

    _salvar();
    _emitirAtualizacao();
  }

  /** Remove um item do carrinho pelo produto_id. */
  function remover(produto_id) {
    itens = itens.filter(i => i.produto_id !== produto_id);
    _salvar();
    _emitirAtualizacao();
  }

  /** Esvazia o carrinho. */
  function limpar() {
    itens = [];
    _salvar();
    _emitirAtualizacao();
  }

  /** Retorna cópia dos itens (imutável externamente). */
  function getItens() {
    return [...itens];
  }

  /** Retorna o total somado de todos os itens. */
  function getTotal() {
    return itens.reduce((acc, i) => acc + i.preco * i.quantidade, 0);
  }

  /** Retorna a quantidade total de unidades no carrinho. */
  function getTotalUnidades() {
    return itens.reduce((acc, i) => acc + i.quantidade, 0);
  }

  // ── Evento customizado para notificar a UI ──────
  function _emitirAtualizacao() {
    window.dispatchEvent(new CustomEvent('cart:update', {
      detail: { itens: getItens(), total: getTotal(), unidades: getTotalUnidades() },
    }));
  }

  // Emite imediatamente ao carregar para sincronizar a UI
  document.addEventListener('DOMContentLoaded', _emitirAtualizacao);

  return { adicionar, alterarQuantidade, remover, limpar, getItens, getTotal, getTotalUnidades };
})();
