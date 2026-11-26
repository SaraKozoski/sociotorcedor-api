package com.wideias.sociotorcedor.viewmodel

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.wideias.sociotorcedor.data.api.*
import com.wideias.sociotorcedor.data.local.entity.SocioEntity
import com.wideias.sociotorcedor.ui.alimentacao.ItemCarrinho
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

/**
 * Estado do usuário logado, sincronizado com a sociotorcedor-api.
 *
 * Agora é um AndroidViewModel (precisa do Context para guardar o token).
 * Continua sendo criado com `val userViewModel: UserViewModel = viewModel()` no AppNavigation.
 */
class UserViewModel(application: Application) : AndroidViewModel(application) {

    private val api = RetrofitClient.api

    private val _usuario = MutableStateFlow<SocioEntity?>(null)
    val usuario: StateFlow<SocioEntity?> = _usuario.asStateFlow()

    private val _pontos = MutableStateFlow(0)
    val pontos: StateFlow<Int> = _pontos.asStateFlow()

    private val _saldoNumerico = MutableStateFlow(0.0)
    val saldoNumerico: StateFlow<Double> = _saldoNumerico.asStateFlow()

    private val _saldo = MutableStateFlow("R\$0,00")
    val saldo: StateFlow<String> = _saldo.asStateFlow()

    /** true enquanto tenta restaurar a sessão salva ao abrir o app. */
    private val _restaurandoSessao = MutableStateFlow(false)
    val restaurandoSessao: StateFlow<Boolean> = _restaurandoSessao.asStateFlow()

    private var _senhaSalva: String = ""
    private var _planoTipo: String? = null

    val estaLogado: Boolean get() = _usuario.value != null
    val temPlano:   Boolean get() = _planoTipo != null
    val planoTipo:  String? get() = _planoTipo

    init {
        SessionManager.init(application)
        if (SessionManager.token != null) {
            _restaurandoSessao.value = true
            viewModelScope.launch {
                apiCall { api.meuPerfil() }
                    .onSuccess { aplicarUsuario(it) }
                    .onFailure { if ((it as? ApiException)?.naoAutenticado == true) limparSessao() }
                _restaurandoSessao.value = false
            }
        }
    }

    // ───────────── Login / sessão ─────────────

    /** Chamado pelo LoginViewModel/CadastroViewModel depois de uma resposta de sucesso da API. */
    fun aplicarLogin(resposta: AuthResponse, senha: String) {
        SessionManager.salvarToken(getApplication(), resposta.token)
        _senhaSalva = senha
        aplicarUsuario(resposta.usuario)
    }

    /** Atualiza a tela com os dados vindos da API (saldo, pontos, plano...). */
    fun aplicarUsuario(dto: UsuarioDto) {
        _planoTipo = dto.planoTipo
        _usuario.value = SocioEntity(
            id             = dto.id.toString(),
            nome           = dto.nome,
            cpf            = dto.cpf ?: "",
            email          = dto.email,
            plano          = dto.planoNome ?: "",
            numeroCadastro = dto.numeroCadastro,
            fotoUrl        = null
        )
        _pontos.value = dto.pontos
        atualizarSaldo(dto.saldo)
    }

    /** Recarrega saldo/pontos/plano do servidor. */
    fun recarregarPerfil() {
        if (SessionManager.token == null) return
        viewModelScope.launch {
            apiCall { api.meuPerfil() }.onSuccess { aplicarUsuario(it) }
        }
    }

    fun verificarSenha(senha: String): Boolean = senha == _senhaSalva

    fun logout() {
        viewModelScope.launch { apiCall { api.logout() } }
        limparSessao()
    }

    private fun limparSessao() {
        SessionManager.salvarToken(getApplication(), null)
        _usuario.value = null
        _planoTipo     = null
        _senhaSalva    = ""
        _pontos.value  = 0
        atualizarSaldo(0.0)
    }

    // ───────────── Operações que agora vão para a API ─────────────

    /** Recarga de créditos (CreditoScreen). */
    fun adicionarSaldo(valor: Double, onResultado: (Result<Unit>) -> Unit = {}) {
        viewModelScope.launch {
            val r = apiCall { api.recarregar(RecargaRequest(valor)) }
            r.onSuccess { resp -> resp.usuario?.let { aplicarUsuario(it) } }
            onResultado(r.map { })
        }
    }

    /** Finaliza o pedido da lanchonete (PagamentoScreen). Pontos e saldo são calculados no servidor. */
    fun finalizarPedido(
        itens: List<ItemCarrinho>,
        pagarComSaldo: Boolean,
        onResultado: (Result<PedidoResponse>) -> Unit
    ) {
        val request = PedidoRequest(
            itens = itens.map { item ->
                ItemPedidoRequest(
                    produtoId       = item.produto.id.toInt(),
                    quantidade      = 1,
                    valorAdicionais = (item.precoTotal - item.produto.preco).coerceAtLeast(0.0),
                    observacao      = item.observacao.ifBlank { null }
                )
            },
            pagarComSaldo = pagarComSaldo
        )
        viewModelScope.launch {
            val r = apiCall { api.fazerPedido(request) }
            r.onSuccess { aplicarUsuario(it.usuario) }
            onResultado(r)
        }
    }

    /** Troca pontos por um produto (ProdutosAlimentacaoScreen). */
    fun resgatarProduto(produtoId: String, onResultado: (Result<ResgateResponse>) -> Unit) {
        viewModelScope.launch {
            val r = apiCall { api.resgatar(ResgatarRequest(produtoId.toInt())) }
            r.onSuccess { aplicarUsuario(it.usuario) }
            onResultado(r)
        }
    }

    /** Assina um plano (RED, GOLD, BLACK). */
    fun assinarPlano(tipo: String, onResultado: (Result<Unit>) -> Unit = {}) {
        viewModelScope.launch {
            val r = apiCall { api.assinarPlano(AssinarPlanoRequest(tipo)) }
            r.onSuccess { resp -> resp.usuario?.let { aplicarUsuario(it) } }
            onResultado(r.map { })
        }
    }

    /** Compra de ingresso (ComprarIngressosScreen). */
    fun comprarIngresso(
        ingressoJogoId: Int,
        quantidade: Int = 1,
        pagarComSaldo: Boolean = false,
        onResultado: (Result<CompraIngressoResponse>) -> Unit
    ) {
        viewModelScope.launch {
            val r = apiCall { api.comprarIngresso(ComprarIngressoRequest(ingressoJogoId, quantidade, pagarComSaldo)) }
            r.onSuccess { aplicarUsuario(it.usuario) }
            onResultado(r)
        }
    }

    /** Palpite de placar (ApostasScreen). */
    fun palpitar(
        jogoId: Int, golsMandante: Int, golsVisitante: Int, pontosApostados: Int = 0,
        onResultado: (Result<PalpiteResponse>) -> Unit
    ) {
        viewModelScope.launch {
            val r = apiCall { api.palpitar(PalpiteRequest(jogoId, golsMandante, golsVisitante, pontosApostados)) }
            r.onSuccess { aplicarUsuario(it.usuario) }
            onResultado(r)
        }
    }

    // ───────────── Antigas (só mexiam na memória) ─────────────

    @Deprecated("Use finalizarPedido(), que debita no servidor", ReplaceWith("finalizarPedido(itens, true) {}"))
    fun debitarSaldo(valor: Double) = atualizarSaldo((_saldoNumerico.value - valor).coerceAtLeast(0.0))

    @Deprecated("Os pontos agora são somados pelo servidor em finalizarPedido()")
    fun adicionarPontos(reais: Double) { _pontos.value += reais.toInt() }

    @Deprecated("Use resgatarProduto(produtoId)", ReplaceWith("resgatarProduto(produtoId) {}"))
    fun resgatarPontos(quantidade: Int) { _pontos.value = (_pontos.value - quantidade).coerceAtLeast(0) }

    private fun atualizarSaldo(novoValor: Double) {
        _saldoNumerico.value = novoValor
        val inteiro  = novoValor.toLong()
        val centavos = Math.round((novoValor - inteiro) * 100)
        _saldo.value = "R\$%d,%02d".format(inteiro, centavos)
    }
}
