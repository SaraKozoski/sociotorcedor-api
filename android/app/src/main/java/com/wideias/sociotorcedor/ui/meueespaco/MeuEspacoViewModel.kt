package com.wideias.sociotorcedor.ui.meueespaco

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.wideias.sociotorcedor.data.api.*
import com.wideias.sociotorcedor.viewmodel.UserViewModel
import kotlinx.coroutines.async
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

sealed class MeuEspacoState {
    object Carregando : MeuEspacoState()
    data class Sucesso(
        val ingressos: List<Ingresso>,
        val beneficios: List<Beneficio>
    ) : MeuEspacoState()
    data class Erro(val mensagem: String) : MeuEspacoState()
}

class MeuEspacoViewModel(
    private val userViewModel: UserViewModel
) : ViewModel() {

    private val api = RetrofitClient.api

    private val _state = MutableStateFlow<MeuEspacoState>(MeuEspacoState.Carregando)
    val state: StateFlow<MeuEspacoState> = _state

    init {
        carregarDados()
    }

    private fun carregarDados() {
        viewModelScope.launch {
            if (userViewModel.usuario.value == null) {
                _state.value = MeuEspacoState.Erro("Faça login para ver seu espaço")
                return@launch
            }

            val ingressos  = async { apiCall { api.meusIngressos() } }
            val beneficios = async { apiCall { api.beneficios() } }
            val rIngressos  = ingressos.await()
            val rBeneficios = beneficios.await()

            val erro = rIngressos.exceptionOrNull() ?: rBeneficios.exceptionOrNull()
            if (erro != null) {
                _state.value = MeuEspacoState.Erro("Erro ao carregar dados: ${erro.message}")
                return@launch
            }

            _state.value = MeuEspacoState.Sucesso(
                ingressos  = rIngressos.getOrThrow()
                    .filter { it.status != "cancelado" }
                    .map { it.toIngresso() },
                beneficios = rBeneficios.getOrThrow().map { it.toBeneficio() }
            )
        }
    }

    fun recarregar() {
        _state.value = MeuEspacoState.Carregando
        carregarDados()
    }
}

private fun MeuIngressoDto.toIngresso() = Ingresso(
    id         = id.toString(),
    jogo       = "${jogo.mandante.nome} x ${jogo.visitante.nome}",
    adversario = jogo.visitante.nome,
    data       = dataBr(jogo.dataHora),
    hora       = horaBr(jogo.dataHora),
    setor      = setor,
    assento    = "Fila ${fila ?: "-"} - Cadeira ${cadeira ?: "-"}",
    confirmado = status == "confirmado",
    qrCodeData = qrCode ?: "INGRESSO:$id"
)

private fun BeneficioDto.toBeneficio() = Beneficio(
    id        = id.toString(),
    titulo    = titulo,
    descricao = descricao ?: "",
    desconto  = "$descontoPercentual% OFF",
    codigo    = codigo,
    validade  = dataBr(validoAte)
)

class MeuEspacoViewModelFactory(
    private val userViewModel: UserViewModel
) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(MeuEspacoViewModel::class.java)) {
            return MeuEspacoViewModel(userViewModel) as T
        }
        throw IllegalArgumentException("ViewModel desconhecido")
    }
}
