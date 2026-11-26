# Integração do app Android com a sociotorcedor-api

Esta pasta tem os arquivos Kotlin que conectam o app (`com.wideias.sociotorcedor`) à API.
A estrutura de pastas é a mesma do projeto do Android Studio, então é só copiar por cima.

## 1. Copiar os arquivos

Copie `android/app/src/main/...` para a pasta `app/src/main/...` do projeto Android:

| Arquivo | O que faz |
|---|---|
| `data/api/ApiConfig.kt` | **Endereço da API**: ajuste aqui |
| `data/api/RetrofitClient.kt` | Retrofit + OkHttp, envia o token automaticamente |
| `data/api/SessionManager.kt` | Salva o token, então o usuário continua logado ao reabrir o app |
| `data/api/SocioTorcedorApi.kt` | Todos os endpoints |
| `data/api/ApiModels.kt` | Modelos (DTOs) do JSON |
| `data/api/ApiCall.kt` | `apiCall { }`: transforma erro HTTP/sem internet em mensagem amigável |
| `data/api/Formatos.kt` | Converte `2027-02-07 18:00:00` → `07/02/2027` e `18:00` |
| `data/repository/TimeRepository.kt` | **substitui** o antigo: jogos, elenco e classificação vêm da sua API |
| `ui/login/UserViewModel.kt` | **substitui**: saldo, pontos, plano e sessão vêm da API |
| `ui/login/LoginVIewModel.kt` | **substitui**: login real por CPF + senha (sem mocks) |
| `ui/cadastro/CadastroViewModel.kt` | **substitui**: cadastro real (já deixa logado) |
| `ui/meueespaco/MeuEspacoViewModel.kt` | **substitui**: ingressos e benefícios reais |
| `ui/ingressos/IngressosViewModel.kt` | novo: lista de jogos com ingressos à venda |
| `res/xml/network_security_config.xml` | novo: libera HTTP para o servidor local |

> Não precisa adicionar nenhuma dependência: Retrofit, Gson, OkHttp e coroutines já estão no `build.gradle.kts`.

## 2. AndroidManifest.xml

Na tag `<application>` adicione a linha `networkSecurityConfig` (a permissão INTERNET já existe):

```xml
<application
    android:networkSecurityConfig="@xml/network_security_config"
    android:allowBackup="true"
    ... >
```

Sem isso o Android bloqueia `http://` e aparece o erro *"CLEARTEXT communication not permitted"*.

## 3. Endereço da API (`ApiConfig.kt`)

| Onde o app roda | BASE_URL |
|---|---|
| Emulador + XAMPP (`htdocs/sociotorcedor-api`) | `http://10.0.2.2/sociotorcedor-api/endpoints/` (padrão) |
| Celular físico no mesmo Wi-Fi | `http://192.168.x.x/sociotorcedor-api/endpoints/`, com o IP do PC (`ipconfig`) e esse IP também no `network_security_config.xml` |

Teste no navegador do PC: `http://localhost/sociotorcedor-api/endpoints/planos.php` precisa mostrar o JSON dos planos.

Usuários de teste (do `schema.sql`), ambos com senha **123456**:
- CPF `12345678901`: João, plano GOLD, R$ 100 de saldo e 50 pontos
- CPF `98765432100`: Maria, sem plano

## 4. Ajustes pequenos nas telas

As telas abaixo chamavam funções que só mexiam na memória. Essas funções continuam existindo (marcadas
como `@Deprecated`) para o projeto compilar, mas o ideal é trocar pelos trechos abaixo.

### PagamentoScreen.kt: finalizar pedido no servidor

```kotlin
onClick = {
    userViewModel.finalizarPedido(
        itens = carrinhoViewModel.itens.value,
        pagarComSaldo = formaPagamento == "saldo"
    ) { resultado ->
        scope.launch {
            resultado
                .onSuccess {
                    carrinhoViewModel.limparCarrinho()
                    snackbarHostState.showSnackbar("Pedido realizado! +${it.pontosGanhos} pontos")
                    navController.popBackStack()
                    navController.popBackStack()
                }
                .onFailure { snackbarHostState.showSnackbar(it.message ?: "Erro no pedido") }
        }
    }
},
```

### ProdutosAlimentacaoScreen.kt: resgate com pontos

```kotlin
onResgatar = {
    if (temPontos) {
        userViewModel.resgatarProduto(produto.id) { resultado ->
            resultado.onSuccess { r ->
                resgateAtivo = ResgateInfo(
                    codigo = r.qrCode,
                    nomeProduto = produto.nome,
                    pontos = pontosProduto
                )
            }
        }
    }
},
```

Os ids dos produtos do mock (`"1"` a `"5"`) são os mesmos da tabela `produtos`, por isso o pedido e o resgate já funcionam
mantendo as imagens locais (`R.drawable...`). Se quiser a lista vinda da API, use `RetrofitClient.api.produtos()`.

### CreditoScreen.kt: recarga
Não precisa mudar nada: `userViewModel.adicionarSaldo(valor)` agora grava no servidor. Para mostrar erro:

```kotlin
userViewModel.adicionarSaldo(valorNumerico) { r ->
    r.onSuccess { mostrarConfirmado = true }
}
```

### ComprarIngressosScreen.kt: jogos reais

```kotlin
val vm: IngressosViewModel = viewModel()
val jogos by vm.jogos.collectAsState()
val jogosDisponiveis = jogos.filter { it.status != StatusIngresso.EM_BREVE }
val jogosEmBreve     = jogos.filter { it.status == StatusIngresso.EM_BREVE }
// ...
onComprar = {
    vm.setorMaisBarato(jogo.id)?.let { setor ->
        userViewModel.comprarIngresso(setor.ingressoJogoId, pagarComSaldo = false) { r ->
            r.onSuccess { vm.carregar() }   // atualiza disponibilidade
        }
    }
},
```

Para isso a `ComprarIngressosScreen` precisa receber o `userViewModel` (passe no `AppNavigation`, como nas outras telas).
**Atenção:** no repositório existem duas `data class JogoDisponivel` (em `JogosModels.kt` e em `ComprarIngressosScreen.kt`).
Deixe só uma (e coloque `package com.wideias.sociotorcedor.ui.ingressos` no topo do `JogosModels.kt`).

### PlanosDetalheScreen.kt: assinar plano
(passe o `userViewModel` para a tela no `AppNavigation`)

```kotlin
userViewModel.assinarPlano(tipoPlano) { r ->
    r.onSuccess { navController.navigate("meueespaco") }
}
```

### ApostasScreen.kt: palpite

```kotlin
userViewModel.palpitar(jogoId = 3, golsMandante = 2, golsVisitante = 1, pontosApostados = 10) { r -> ... }
```

### Perfil: sair
`userViewModel.logout()` já apaga o token no servidor e no aparelho.

## 5. Como depurar

- No **Logcat** filtre por `OkHttp`: aparece cada requisição e a resposta JSON.
- `Sem conexão com o servidor`: o XAMPP está desligado, a `BASE_URL` está errada ou o celular está em outra rede.
- `CLEARTEXT communication not permitted`: faltou o passo 2.
- `Não autenticado` / `Sessão expirada`: faça login de novo (o token dura 30 dias).
