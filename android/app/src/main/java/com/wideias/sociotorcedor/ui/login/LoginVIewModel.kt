package com.wideias.sociotorcedor.ui.login

import androidx.lifecycle.ViewModel
import androidx.lifecycle.ViewModelProvider
import androidx.lifecycle.viewModelScope
import com.wideias.sociotorcedor.data.api.LoginRequest
import com.wideias.sociotorcedor.data.api.RetrofitClient
import com.wideias.sociotorcedor.data.api.apiCall
import com.wideias.sociotorcedor.viewmodel.UserViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.launch

class LoginViewModel(
    private val userViewModel: UserViewModel
) : ViewModel() {

    private val _loginState = MutableStateFlow<LoginState>(LoginState.Idle)
    val loginState: StateFlow<LoginState> = _loginState

    fun loginComCpf(cpf: String, senha: String) {
        if (cpf.length != 11) {
            _loginState.value = LoginState.Erro("CPF inválido")
            return
        }
        if (senha.length < 6) {
            _loginState.value = LoginState.Erro("Senha deve ter pelo menos 6 caracteres")
            return
        }

        _loginState.value = LoginState.Carregando
        viewModelScope.launch {
            apiCall { RetrofitClient.api.login(LoginRequest(cpf = cpf, senha = senha)) }
                .onSuccess { resposta ->
                    userViewModel.aplicarLogin(resposta, senha)
                    _loginState.value = LoginState.Sucesso
                }
                .onFailure { e ->
                    _loginState.value = LoginState.Erro(e.message ?: "Erro ao entrar")
                }
        }
    }

    fun loginComGoogle(idToken: String) {
        // O login com Google ainda não existe na API (precisa validar o idToken no servidor).
        _loginState.value = LoginState.Erro("Login com Google ainda não disponível")
    }

    fun resetState() {
        _loginState.value = LoginState.Idle
    }
}

class LoginViewModelFactory(
    private val userViewModel: UserViewModel
) : ViewModelProvider.Factory {
    override fun <T : ViewModel> create(modelClass: Class<T>): T {
        if (modelClass.isAssignableFrom(LoginViewModel::class.java)) {
            @Suppress("UNCHECKED_CAST")
            return LoginViewModel(userViewModel) as T
        }
        throw IllegalArgumentException("ViewModel desconhecido")
    }
}

sealed class LoginState {
    object Idle       : LoginState()
    object Carregando : LoginState()
    object Sucesso    : LoginState()
    data class Erro(val mensagem: String) : LoginState()
}
