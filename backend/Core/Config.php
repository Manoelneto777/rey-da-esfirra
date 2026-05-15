<?php
/**
 * backend/Core/Config.php
 * Configuracoes centralizadas do sistema.
 * Edite apenas este arquivo para mudar banco, chaves de API, etc.
 */

namespace Core;

class Config
{
    // Banco de Dados
    const DB_HOST    = 'localhost';
    const DB_NAME    = 'chatbot'; // nome do banco criado no MySQL
    const DB_USER    = 'root';   // padrao XAMPP
    const DB_PASS    = '';       // padrao XAMPP (sem senha)
    const DB_PORT    = '3307'; //está certo a porta do meu xampp é 3307
    const DB_CHARSET = 'utf8mb4';

    // OpenRouter (IA) — obtenha sua chave em: https://openrouter.ai/keys
    const OPENROUTER_API_KEY  = 'sk-or-v1-SUA_CHAVE_AQUI';
    const OPENROUTER_API_URL  = 'https://openrouter.ai/api/v1/chat/completions';
    const OPENROUTER_MODEL    = 'mistralai/mistral-7b-instruct:free';

    // Chatbot
    // true  = usa IA quando nao encontra resposta manual
    // false = responde so com o banco (modo offline / sem chave)
    const CHATBOT_USE_AI     = false;
    const CHATBOT_MAX_TOKENS = 500;

    // Prompt de sistema enviado para a IA
    const CHATBOT_SYSTEM_PROMPT = 'Voce e o Rei Bot, assistente virtual da lanchonete Rei da Esfirra em Capoeirucu - BA. Responda de forma simpatica, curta e objetiva em portugues. Horario: Seg-Dom 13h-22h30. Endereco: Capoeirucu - BA. Tel: (75) 99999-0000. Delivery 30-50min, taxa R$5, gratis acima R$60. Pagamento: PIX, Dinheiro, Debito, Credito. Seja breve (max 3 linhas) e use emojis ocasionalmente.';

    // Aplicacao
    const APP_DEBUG = true; // false em producao
}
