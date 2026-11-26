package com.wideias.sociotorcedor.ui.cadastro

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.wideias.sociotorcedor.data.api.CadastroRequest
import com.wideias.sociotorcedor.data.api.RetrofitClient
import com.wideias.sociotorcedor.data.api.apiCall
import com.wideias.sociotorcedor.viewmodel.UserViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

sealed class CadastroState {
    object Idle       : CadastroState()
    object Carregando : CadastroState()
    object Sucesso    : CadastroState()
    data class Erro(val mensagem: String) : CadastroState()
}

class CadastroViewModel(
    private val userViewModel: UserViewModel
) : ViewModel() {

    private val _cadastroState = MutableStateFlow<CadastroState>(CadastroState.Idle)
    val cadastroState: StateFlow<CadastroState> = _cadastroState

    fun cadastrar(
        nome: String,
        email: String,
        dataNasc: String,
        cpf: String,
        logradouro: String,
        cep: String,
        cidade: String,
        uf: String,
        numero: String,
        senha: String
    ) {
        if (nome.isBlank() || email.isBlank() || cpf.isBlank() || senha.isBlank()) {
            _cadastroState.value = CadastroState.Erro("Preencha todos os campos obrigatórios.")
            return
        }
        if (cpf.length != 11) {
            _cadastroState.value = CadastroState.Erro("CPF deve conter 11 dígitos.")
            return
        }
        if (senha.length < 6) {
            _cadastroState.value = CadastroState.Erro("A senha deve ter no mínimo 6 caracteres.")
            return
        }

        val request = CadastroRequest(
            nome           = nome.trim(),
            email          = email.trim(),
            cpf            = cpf,
            senha          = senha,
            dataNascimento = dataNasc.ifBlank { null },
            logradouro     = logradouro.ifBlank { null },
            numero         = numero.ifBlank { null },
            cep            = cep.ifBlank { null },
            cidade         = cidade.ifBlank { null },
            uf             = uf.ifBlank { null }
        )

        _cadastroState.value = CadastroState.Carregando
        viewModelScope.launch {
            apiCall { RetrofitClient.api.cadastrar(request) }
                .onSuccess { resposta ->
                    // Já deixa o usuário logado depois do cadastro.
                    userViewModel.aplicarLogin(resposta, senha)
                    _cadastroState.value = CadastroState.Sucesso
                }
                .onFailure { e ->
                    _cadastroState.value = CadastroState.Erro(e.message ?: "Erro ao cadastrar.")
                }
        }
    }

    fun resetState() {
        _cadastroState.value = CadastroState.Idle
    }
}

class CadastroViewModelFactory(
    private val userViewModel: UserViewModel
) : ViewModelProvider.Factory {
    @Suppress("UNCHECKED_CAST")
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(CadastroViewModel::class.java)) {
            return CadastroViewModel(userViewModel) as T
        }
        throw IllegalArgumentException("ViewModel desconhecido: ${modelClass.name}")
    }
}
