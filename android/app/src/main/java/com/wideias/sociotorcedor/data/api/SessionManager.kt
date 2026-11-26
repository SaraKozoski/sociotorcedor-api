package com.wideias.sociotorcedor.data.api

import android.content.Context

/** Guarda o token de login no aparelho para o usuário continuar logado ao reabrir o app. */
object SessionManager {
    private const val PREFS = "sociotorcedor_sessao"
    private const val KEY_TOKEN = "token"

    @Volatile
    var token: String? = null
        private set

    fun init(context: Context) {
        token = context.applicationContext
            .getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .getString(KEY_TOKEN, null)
    }

    fun salvarToken(context: Context, novoToken: String?) {
        token = novoToken
        context.applicationContext
            .getSharedPreferences(PREFS, Context.MODE_PRIVATE)
            .edit()
            .apply { if (novoToken == null) remove(KEY_TOKEN) else putString(KEY_TOKEN, novoToken) }
            .apply()
    }
}
