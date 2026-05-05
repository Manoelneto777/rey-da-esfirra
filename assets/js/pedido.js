/**
 * assets/js/pedido.js
 * Responsável por:
 *  - Renderizar o resumo do pedido no modal
 *  - Validar o formulário no frontend
 *  - Enviar o pedido ao backend via Api.postPedido()
 *  - Gerar e abrir link do WhatsApp após salvar
 */

const Pedido = (() => {
  // Número do WhatsApp (só dígitos, com DDI)
  const WHATSAPP_NUM = '5575999990000';

  // ── Inicialização ───────────────────────────────
  function init() {
    const btnAbrir  = document.getElementById('abrirModal');
    const btnFechar = document.getElementById('fecharModal');
    const btnEnviar = document.getElementById('btnEnviarPedido');
    const overlay   = document.getElementById('modalOverlay');

    btnAbrir?.addEventListener('click', _abrirModal);
    btnFechar?.addEventListener('click', _fecharModal);
    btnEnviar?.addEventListener('click', _enviar);

    // Fechar clicando fora do modal
    overlay?.addEventListener('click', e => {
      if (e.target === overlay) _fecharModal();
    });

    // Fechar com ESC
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') _fecharModal();
    });

    // Máscara de telefone
    document.getElementById('pedidoTelefone')
      ?.addEventListener('input', _mascaraTelefone);
  }

  // ── Modal ───────────────────────────────────────
  function _abrirModal() {
    const itens = Cart.getItens();
    if (itens.length === 0) {
      UI.toast('⚠️', 'Adicione itens ao carrinho primeiro.', 'error');
      return;
    }
    _renderizarResumo(itens);
    document.getElementById('modalOverlay')?.classList.add('open');
    document.getElementById('pedidoNome')?.focus();
  }

  function _fecharModal() {
    document.getElementById('modalOverlay')?.classList.remove('open');
  }

  function _renderizarResumo(itens) {
    const container = document.getElementById('resumoPedido');
    if (!container) return;

    const total = Cart.getTotal();
    const taxa  = total >= 60 ? 0 : 5;

    // Limpa conteúdo anterior de forma segura
    container.innerHTML = '';

    itens.forEach(item => {
      const row = document.createElement('div');
      row.className = 'resumo-item';

      const desc = document.createElement('span');
      desc.textContent = `${item.nome} ×${item.quantidade}`;

      const valor = document.createElement('strong');
      valor.textContent = `R$ ${(item.preco * item.quantidade).toFixed(2).replace('.', ',')}`;

      row.appendChild(desc);
      row.appendChild(valor);
      container.appendChild(row);
    });

    // Linha de taxa de entrega
    const rowTaxa = document.createElement('div');
    rowTaxa.className = 'resumo-item';
    const lblTaxa = document.createElement('span');
    lblTaxa.textContent = 'Taxa de entrega';
    const valTaxa = document.createElement('span');
    valTaxa.style.color = taxa === 0 ? 'var(--neon)' : '';
    valTaxa.textContent = taxa === 0 ? 'Grátis 🎉' : `R$ ${taxa.toFixed(2).replace('.', ',')}`;
    rowTaxa.appendChild(lblTaxa);
    rowTaxa.appendChild(valTaxa);
    container.appendChild(rowTaxa);

    // Divisor
    const hr = document.createElement('hr');
    hr.style.cssText = 'border:none;border-top:1px solid var(--border-soft);margin:8px 0';
    container.appendChild(hr);

    // Total
    const rowTotal = document.createElement('div');
    rowTotal.className = 'resumo-total';
    const lblTotal = document.createElement('span');
    lblTotal.textContent = 'Total do pedido';
    const valTotal = document.createElement('span');
    valTotal.textContent = `R$ ${(total + taxa).toFixed(2).replace('.', ',')}`;
    rowTotal.appendChild(lblTotal);
    rowTotal.appendChild(valTotal);
    container.appendChild(rowTotal);
  }

  // ── Envio ───────────────────────────────────────
  async function _enviar() {
    const nome       = document.getElementById('pedidoNome')?.value.trim()     ?? '';
    const telefone   = document.getElementById('pedidoTelefone')?.value.trim() ?? '';
    const endereco   = document.getElementById('pedidoEndereco')?.value.trim() ?? '';
    const observacoes = document.getElementById('pedidoObs')?.value.trim()     ?? '';

    // Validação frontend
    const erros = _validar({ nome, telefone, endereco });
    if (erros.length) {
      UI.toast('⚠️', erros[0], 'error');
      return;
    }

    const itens = Cart.getItens();
    if (!itens.length) {
      UI.toast('⚠️', 'Carrinho está vazio.', 'error');
      return;
    }

    const subtotal = Cart.getTotal();
    const taxa     = subtotal >= 60 ? 0 : 5;
    const total    = subtotal + taxa;

    const payload = {
      nome_cliente: nome,
      telefone,
      endereco,
      observacoes,
      total,
      itens: itens.map(i => ({
        produto_id: i.produto_id,
        quantidade: i.quantidade,
        preco:      i.preco,
      })),
    };

    const btn = document.getElementById('btnEnviarPedido');
    _setBtnLoading(btn, true);

    try {
      const resp = await Api.postPedido(payload);

      // Pedido salvo — limpar carrinho e fechar modal
      Cart.limpar();
      _fecharModal();
      _limparFormulario();

      UI.toast('🎉', `Pedido #${resp.pedido_id} confirmado!`, 'success');

      // Abrir WhatsApp após breve delay
      setTimeout(() => _abrirWhatsApp({ nome, telefone, endereco, observacoes, itens, total }), 800);

    } catch (erro) {
      UI.toast('❌', erro.message || 'Erro ao enviar pedido.', 'error');
    } finally {
      _setBtnLoading(btn, false);
    }
  }

  // ── WhatsApp ────────────────────────────────────
  function _abrirWhatsApp({ nome, telefone, endereco, observacoes, itens, total }) {
    const linhasItens = itens
      .map(i => `• ${i.nome} ×${i.quantidade} — R$ ${(i.preco * i.quantidade).toFixed(2).replace('.', ',')}`)
      .join('\n');

    const msg = [
      '🥙 *NOVO PEDIDO — Rei da Esfirra*',
      '',
      `👤 *Cliente:* ${nome}`,
      `📞 *Telefone:* ${telefone}`,
      `📍 *Endereço:* ${endereco}`,
      observacoes ? `📝 *Obs:* ${observacoes}` : '',
      '',
      '🛒 *Itens:*',
      linhasItens,
      '',
      `💰 *Total:* R$ ${total.toFixed(2).replace('.', ',')}`,
    ].filter(l => l !== null && l !== undefined && !(l === '' && !observacoes)).join('\n');

    const url = `https://wa.me/${WHATSAPP_NUM}?text=${encodeURIComponent(msg)}`;
    window.open(url, '_blank', 'noopener,noreferrer');
  }

  // ── Helpers ─────────────────────────────────────
  function _validar({ nome, telefone, endereco }) {
    const erros = [];
    if (!nome)     erros.push('Informe seu nome completo.');
    if (!telefone) erros.push('Informe seu WhatsApp.');
    if (!endereco) erros.push('Informe o endereço de entrega.');
    if (telefone && telefone.replace(/\D/g, '').length < 10)
      erros.push('Telefone inválido.');
    return erros;
  }

  function _mascaraTelefone(e) {
    let v = e.target.value.replace(/\D/g, '').slice(0, 11);
    if (v.length > 6)      v = `(${v.slice(0,2)}) ${v.slice(2,7)}-${v.slice(7)}`;
    else if (v.length > 2) v = `(${v.slice(0,2)}) ${v.slice(2)}`;
    else if (v.length)     v = `(${v}`;
    e.target.value = v;
  }

  function _limparFormulario() {
    ['pedidoNome', 'pedidoTelefone', 'pedidoEndereco', 'pedidoObs'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.value = '';
    });
  }

  function _setBtnLoading(btn, loading) {
    if (!btn) return;
    btn.disabled     = loading;
    btn.textContent  = loading ? 'Enviando...' : '🚀 Confirmar Pedido';
  }

  return { init };
})();
