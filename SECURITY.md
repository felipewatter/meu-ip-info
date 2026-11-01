# Política de segurança — Meu IP Info

## Divulgação responsável

Relate possíveis vulnerabilidades pelo GitHub Security Advisories (quando habilitado), sem publicar credenciais ipinfo.io, endereços reais de visitantes, capturas com dados pessoais ou dados de infraestrutura em issues públicas.

## Modelo de privacidade

- A página **não inclui o token ipinfo.io em HTML, JavaScript ou URLs públicas**; o servidor transmite o segredo ao provedor exclusivamente no header HTTP Authorization.
- A consulta **não é realizada automaticamente** ao abrir a página. O visitante deve selecionar "Consultar meu IP".
- O IP observado pelo servidor WordPress (REMOTE_ADDR) é enviado ao ipinfo.io somente após a consulta. O plugin **não confia em X-Forwarded-For**, que pode ser falsificado. Em instalações atrás de proxy reverso, configure o IP real de forma segura na camada HTTP (somente proxies de confiança) ou a consulta pode falhar.
- A resposta com IP e localização aproximada fica temporariamente no sistema de transients do WordPress por até **10 minutos**, identificada por hash com segredo WordPress, para reduzir consultas e limitar custo. Conforme a configuração, transients podem estar no banco de dados ou cache externo. O plugin **não mantém histórico próprio**.
- A integração OpenStreetMap só é carregada após um **segundo clique** do visitante. Esse fornecedor pode receber IP e dados de navegação do visitante. O iframe usa referrerPolicy=no-referrer.
- Serviços externos, servidor web e mecanismos de cache/log da hospedagem possuem suas **próprias** políticas de retenção. Inclua a operação na política de privacidade do seu site e examine sua base legal (LGPD).
- Os dados derivados de IP são aproximados e **não** correspondem a localização exata/GPS.

## Controles técnicos

- Rota pública REST não recebe parâmetro de IP; consulta somente REMOTE_ADDR. IPs locais, privados e reservados não são enviados ao provedor.
- Conexão de saída usa wp_safe_remote_get, TLS validado, redirecionamentos desativados, timeout curto, corpo de resposta limitado a 8192 bytes e URL fixa do provedor.
- A resposta enviada ao navegador tem uma lista fixa de campos normalizados; resultados de terceiros não são inseridos como HTML (usa textContent).
- Configurações seguem WordPress Settings API, permissões de administrador, nonce nativo e saneamento do token. O campo é do tipo password e nunca retorna o valor salvo.
- Assets somente no shortcode, com classes isoladas e suporte a múltiplas instâncias.

## Limitações

- O endpoint público pode receber requisições não originadas da interface visual (sem consentimento demonstrado). **O botão de consentimento é um mecanismo de UX, não uma barreira criptográfica ou de controle de acesso.** Para política de consentimento exigida por lei, utilize um mecanismo server-side adequado ou gateway de API.
- Uma grande quantidade de IPs distintos pode aumentar custos/consumo da cota do ipinfo.io. Proteja a rota com WAF, rate limiting no reverse proxy e quotas do provedor.
- O plugin não autentica visitantes, não identifica a origem verdadeira atrás de proxies arbitrários e não assegura anonimato contra terceiros.
- Evite inserir o token em logs de depuração de requisições HTTP e controle backups/opções do banco WordPress.
