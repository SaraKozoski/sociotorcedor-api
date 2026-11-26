# sociotorcedor-api

API em PHP + MySQL do app Sócio Torcedor (Android).

## Como rodar (XAMPP)

1. Copie esta pasta para `C:\xampp\htdocs\sociotorcedor-api`.
2. Importe `database/schema.sql` no phpMyAdmin (ou `mysql -u root < database/schema.sql`).
   **Atenção:** o script apaga e recria o banco `sociotorcedor`.
3. Copie `config/database.example.php` para `config/database.php` e coloque sua senha do MySQL.
4. Teste: <http://localhost/sociotorcedor-api/endpoints/planos.php>

Usuários de teste (senha `123456`): CPF `12345678901` (plano GOLD) e `98765432100` (sem plano).

Para integrar com o app Android veja [`android/INTEGRACAO.md`](android/INTEGRACAO.md).

## Autenticação

`login` e `cadastro` devolvem `{ "token": "...", "usuario": {...} }`.
Nas rotas marcadas com 🔒, envie o header `Authorization: Bearer <token>`.
Erros sempre vêm como `{ "error": "mensagem" }` com o status HTTP certo (400, 401, 404, 409...).

## Endpoints (`/endpoints/...`)

| Método | Rota | Corpo / parâmetros |
|---|---|---|
| POST | `auth.php?acao=login` | `{ cpf \| email, senha }` |
| POST | `auth.php?acao=cadastro` | `{ nome, email, cpf, senha, data_nascimento?, logradouro?, numero?, cep?, cidade?, uf? }` |
| POST 🔒 | `auth.php?acao=logout` | |
| GET 🔒 | `usuarios.php` | perfil: saldo, pontos, plano |
| PUT 🔒 | `usuarios.php` | `{ nome?, email? }` |
| PUT 🔒 | `usuarios.php?acao=senha` | `{ senha_atual, nova_senha }` |
| DELETE 🔒 | `usuarios.php` | exclui a conta |
| GET | `planos.php` / `planos.php?tipo=GOLD` | |
| POST 🔒 | `planos.php?acao=assinar` | `{ tipo }` ou `{ plano_id }` |
| POST 🔒 | `planos.php?acao=cancelar` | |
| GET | `jogos.php` | `?status=agendado\|ao_vivo\|encerrado`, `?clube_id=`, `?id=` |
| GET | `classificacao.php` | `?campeonato_id=1` |
| GET | `jogadores.php` | `?clube_id=1`, `?id=` |
| GET | `ingressos.php` | jogos à venda com setores; `?jogo_id=` |
| GET 🔒 | `ingressos.php?meus=1` | ingressos comprados (com QR code) |
| POST 🔒 | `ingressos.php` | `{ ingresso_jogo_id, quantidade?, pagar_com_saldo? }` |
| GET 🔒 | `beneficios.php` | benefícios do plano do usuário |
| GET | `produtos.php` | `?categoria_id=`, `?id=`, `?acao=categorias` |
| GET 🔒 | `pedidos.php` | pedidos do usuário |
| POST 🔒 | `pedidos.php` | `{ itens: [{ produto_id, quantidade, valor_adicionais? }], pagar_com_saldo }` (+1 ponto por real) |
| POST 🔒 | `pedidos.php?acao=resgatar` | `{ produto_id }`: usa `pontos_resgate` |
| GET 🔒 | `carteira.php` | saldo, pontos e extratos |
| POST 🔒 | `carteira.php` | `{ valor }`: recarga |
| GET 🔒 | `palpites.php` | `?jogo_id=` |
| POST 🔒 | `palpites.php` | `{ jogo_id, gols_mandante, gols_visitante, pontos_apostados? }` |

Preços, totais, saldo e pontos são sempre calculados no servidor (dentro de transações), nunca confiando no valor enviado pelo app.
