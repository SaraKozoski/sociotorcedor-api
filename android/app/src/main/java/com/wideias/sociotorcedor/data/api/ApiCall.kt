package com.wideias.sociotorcedor.data.api

import com.google.gson.Gson
import retrofit2.HttpException
import java.io.IOException

/**
 * Executa uma chamada da API e devolve Result, com uma mensagem amigável no erro.
 *
 *   val r = apiCall { RetrofitClient.api.planos() }
 *   r.onSuccess { planos -> ... }.onFailure { e -> mostrar(e.message) }
 */
suspend fun <T> apiCall(bloco: suspend () -> T): Result<T> = try {
    Result.success(bloco())
} catch (e: HttpException) {
    val corpo = e.response()?.errorBody()?.string()
    val mensagem = runCatching { Gson().fromJson(corpo, ApiErro::class.java)?.error }.getOrNull()
    Result.failure(ApiException(e.code(), mensagem ?: "Erro ${e.code()} no servidor"))
} catch (e: IOException) {
    Result.failure(ApiException(0, "Sem conexão com o servidor. Verifique a internet e o endereço da API."))
} catch (e: Exception) {
    Result.failure(ApiException(-1, e.message ?: "Erro inesperado"))
}

class ApiException(val codigo: Int, mensagem: String) : Exception(mensagem) {
    val naoAutenticado: Boolean get() = codigo == 401
}
