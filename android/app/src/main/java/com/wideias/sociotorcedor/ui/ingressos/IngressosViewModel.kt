package com.wideias.sociotorcedor.ui.ingressos

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.wideias.sociotorcedor.data.api.*
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

/**
 * Carrega da API os jogos com ingressos à venda e converte para JogoDisponivel (o modelo que a tela usa).
 *
 * Na ComprarIngressosScreen troque:
 *     val jogosDisponiveis = remember { jogosMock.filter { ... } }
 * por:
 *     val vm: IngressosViewModel = viewModel()
 *     val jogos by vm.jogos.collectAsState()
 *     val jogosDisponiveis = jogos.filter { it.status != StatusIngresso.EM_BREVE }
 *     val jogosEmBreve     = jogos.filter { it.status == StatusIngresso.EM_BREVE }
 */
class IngressosViewModel : ViewModel() {

    private val api = RetrofitClient.api

    private val _jogos = MutableStateFlow<List<JogoDisponivel>>(emptyList())
    val jogos: StateFlow<List<JogoDisponivel>> = _jogos

    private val _carregando = MutableStateFlow(true)
    val carregando: StateFlow<Boolean> = _carregando

    private val _erro = MutableStateFlow<String?>(null)
    val erro: StateFlow<String?> = _erro

    /** Setores de cada jogo (id do jogo -> setores), para escolher qual ingresso comprar. */
    private val _setores = MutableStateFlow<Map<Int, List<SetorIngressoDto>>>(emptyMap())
    val setores: StateFlow<Map<Int, List<SetorIngressoDto>>> = _setores

    init {
        carregar()
    }

    fun carregar() {
        viewModelScope.launch {
            _carregando.value = true
            apiCall { api.jogosComIngressos() }
                .onSuccess { lista ->
                    _jogos.value = lista.map { it.toJogoDisponivel() }
                    _setores.value = lista.associate { it.id to it.setores }
                    _erro.value = null
                }
                .onFailure { _erro.value = it.message }
            _carregando.value = false
        }
    }

    /** Setor mais barato ainda disponível de um jogo (para um botão "Comprar" simples). */
    fun setorMaisBarato(jogoId: Int): SetorIngressoDto? =
        _setores.value[jogoId]?.filter { it.status == "disponivel" }?.minByOrNull { it.preco }
}

private fun JogoIngressoDto.toJogoDisponivel() = JogoDisponivel(
    id            = id,
    mandante      = mandante.nome,
    visitante     = visitante.nome,
    competicao    = listOfNotNull(campeonato.nome, rodada).joinToString(" · "),
    data          = dataBr(dataHora),
    hora          = horaBr(dataHora),
    estadio       = estadio ?: "",
    precoMinimo   = precoMinimo,
    precoOriginal = precoOriginal,
    status        = when (statusIngresso) {
        "disponivel" -> StatusIngresso.DISPONIVEL
        "esgotado"   -> StatusIngresso.ESGOTADO
        else         -> StatusIngresso.EM_BREVE
    }
)
