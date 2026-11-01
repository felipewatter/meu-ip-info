# Histórico de alterações

## 2.2.0 — 2026-10-31

- Removida a exposição do token ipinfo.io em JavaScript e HTML público.
- Integração ipinfo.io migrada para chamada server-side protegida por TLS, URL fixa, sem redirecionamentos e com limites de tamanho/tempo.
- Novo endpoint REST público sem consulta por IP arbitrário; IP deriva da conexão e ignora X-Forwarded-For.
- Introduzido aviso de privacidade, solicitação explícita do visitante e ativação separada do mapa OpenStreetMap.
- Removidas dependências de scripts CDN (Leaflet, FontAwesome, Google Fonts); CSS local e JavaScript nativo.
- Suporte a múltiplos shortcodes na mesma página sem colisão de IDs.
- Resposta JSON com lista restrita de campos, saneamento e coordenadas validadas.
- Cache WordPress Transitório de 10 minutos, token do admin oculto com atualização/remoção explícitas.
- Testes PHP e Node.js + CI multiplataforma; documentação de riscos, uso e política de privacidade.
- Mantida a opção original 'meu_ip_info_token' para preservar configurações existentes.

## 2.1 (cabeçalho PHP original)

- Exibição inicial com token frontend e dependências externas, agora substituída.

## 1.0.0 (release histórica do GitHub)

- Primeira release publicada anteriormente no GitHub.
