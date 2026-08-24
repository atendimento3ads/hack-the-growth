# Hack The Growth — Landing Page

Landing page de materiais da 3ADS, com captura de leads por PHP, envio por e-mail, backup em CSV e integração opcional com Google Sheets via Apps Script.

## Deploy pelo cPanel

O arquivo `.cpanel.yml` publica a aplicação em:

```text
/home1/carlo589/hackthegrowth.3ads.com.br
```

No cPanel, clone este repositório em uma pasta separada, por exemplo:

```text
/home1/carlo589/repositories/hack-the-growth
```

Depois use **Git Version Control → Manage → Pull or Deploy → Update from Remote → Deploy HEAD Commit**.

## Configuração privada do Apps Script

Copie `lead-config.example.php` para:

```text
/home1/carlo589/secure/lead-config.php
```

Preencha a URL `/exec` e a mesma chave configurada em `google-sheets-webhook.example.gs`. O arquivo real de configuração não pode ser enviado ao GitHub nem colocado na pasta pública.
