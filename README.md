# Meu IP Info — Plugin WordPress

[![CI](https://github.com/felipewatter/meu-ip-info/actions/workflows/ci.yml/badge.svg)](https://github.com/felipewatter/meu-ip-info/actions/workflows/ci.yml)
[![MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

Plugin WordPress em português que permite ao visitante consultar seu **IP público**, provedor e localização **aproximada**, utilizando a API ipinfo.io. Inclui um mapa incorporado do OpenStreetMap que é carregado **somente após autorização adicional**.

## Segurança e privacidade — versão 2.2.1

**O token ipinfo.io não é exposto ao visitante.** O WordPress realiza a consulta no servidor, em URL fixa, usando header Authorization. A página não contém esse token em HTML nem JavaScript, mesmo com o código-fonte aberto.

A consulta é opcional: o visitante clica **Consultar meu IP**; a localização aproximada é mostrada com consentimento de interface. O visitante pode então clicar **Carregar mapa** para gerar uma requisição a OpenStreetMap. Nenhuma biblioteca externa é carregada no shortcode antes dessa opção.

**Transients:** informações de IP são armazenadas temporariamente por até 10 minutos no cache do WordPress (banco de dados ou cache externo); não há histórico próprio permanente. As solicitações ao provedor ipinfo.io e ao mapa são cobertas também pelas práticas desses terceiros. Configure sua política de privacidade e avalie as exigências da LGPD.

## Instalação e atualização

**Requisitos:** WordPress com REST API ativa, PHP 7.4 ou superior e conexão HTTPS de saída para ipinfo.io. O mapa requer acesso ao OpenStreetMap após o clique. Não há dependências npm ou Composer.

1. Copie os arquivos do projeto para a pasta `wp-content/plugins/meu-ip-info/` (incluindo `meu-ip-info.php`, `meu-ip-info.js` e `meu-ip-info.css`).
2. No WordPress, acesse **Plugins** e ative **Meu IP Info**.
3. Em **Configurações → Meu IP Info**, digite um token válido da API ipinfo.io. Para substituir, informe o novo token e salve. Para manter o atual, deixe o campo em branco; para remover, selecione a opção correspondente.
4. Insira o shortcode `[meu_ip_info]` em páginas ou posts, inclusive múltiplas vezes.
5. Confira o fluxo completo como visitante. Em uma instância com proxy reverso, certifique-se de que REMOTE_ADDR representa o IP público real do visitante, configurado **somente** em proxy confiável.

A versão atual do cabeçalho WordPress é **2.2.1**, sincronizada à tag `v2.2.1` e à release oficial. A evolução histórica das versões anteriores está documentada no changelog.

## Funcionalidades

- IP público, provedor, país, estado e cidade (se disponíveis no provedor).
- Avisos claros sobre localização aproximada.
- Botão de cópia do IP com fallback visual.
- Mapa sob demanda, sem Leaflet nem CDNs de scripts externos.
- Layout responsivo, estilos locais, múltiplas instâncias e status acessível.
- Tratamento de falhas de rede, dados incompletos e IPs privados/indisponíveis.

## Como funciona

1. O shortcode carrega JS/CSS locais e não faz requisição ao ipinfo.io ao abrir a página.
2. O clique solicita `GET /wp-json/meu-ip-info/v1/lookup` na instalação WordPress.
3. O servidor obtém o IP de `REMOTE_ADDR`, bloqueia IPs privados/reservados e consulta `https://ipinfo.io/<IP>/json` por TLS, com o token armazenado como opção WordPress.
4. O JSON devolve apenas campos esperados; dados são exibidos por `textContent`. O cache de 10 minutos reduz chamadas repetidas.
5. Se existirem coordenadas válidas, o visitante pode ativar um iframe com mapa aproximado.

## Limites operacionais

O endpoint REST é **público e não exige login**, mas só aceita IP da conexão atual: **não recebe IP por query string**. A interface exige um clique, porém chamadas diretas à rota não são bloqueadas; use WAF/rate limiting para defesa contra automação e contenção da cota. Cookies, logs de servidor e cache de terceiros podem reter dados fora do controle do plugin.

O endereço real atrás de proxies não confiáveis **não** é inferido de cabeçalhos de encaminhamento. GeoIP não representa GPS nem localização exata. Certos IPs (localhost, privados e reservados) não podem ser consultados — comportamento seguro esperado em laboratórios.

## Testes

```bash
php -l meu-ip-info.php
php tests/plugin.php
node --check meu-ip-info.js
node --test tests/frontend.test.cjs
```

O GitHub Actions testa PHP 7.4/8.1/8.3/8.4 no Ubuntu e JavaScript Node 22/24 no Ubuntu, Windows e macOS. Os testes WordPress usam stubs isolados e chamadas simuladas, **não substituem** testes E2E em uma instalação real de WordPress.

Para reportar vulnerabilidades, consulte [SECURITY.md](SECURITY.md). Licença MIT.
