package com.wideias.sociotorcedor.data.repository

import com.wideias.sociotorcedor.data.api.*
import com.wideias.sociotorcedor.data.remote.*

/**
 * Agora lê jogos, elenco e classificação da sociotorcedor-api (tabelas jogos, jogadores, classificacao)
 * e converte para os mesmos modelos (ApiPartida, ApiJogador, ApiClassificacao) que as telas já usam.
 */
class TimeRepository {

    private val api = RetrofitClient.api

    /** id do clube da casa no banco (tabela clubes). */
    val CLUBE_ID = 1

    /** As telas (UltimosJogosScreen/ClassificacaoScreen) destacam o time com id 134. */
    private val ID_TIME_CASA_NAS_TELAS = 134

    suspend fun getPartidas(): Result<List<ApiPartida>> =
        apiCall { api.jogos(clubeId = CLUBE_ID).map { it.toApiPartida() } }

    suspend fun getElenco(): Result<List<ApiJogador>> =
        apiCall { api.jogadores(CLUBE_ID).map { it.toApiJogador() } }

    suspend fun getClassificacao(): Result<List<ApiClassificacao>> =
        apiCall { api.classificacao(1).map { it.toApiClassificacao() } }

    private fun ClubeDto.toApiTime() = ApiTime(
        time_id      = if (id == CLUBE_ID) ID_TIME_CASA_NAS_TELAS else id,
        nome_popular = nome,
        sigla        = sigla,
        escudo       = escudoUrl ?: ""
    )

    private fun JogoDto.toApiPartida() = ApiPartida(
        partida_id       = id,
        data_realizacao  = dataBr(dataHora),
        hora_realizacao  = horaBr(dataHora),
        placar           = if (golsMandante != null && golsVisitante != null) "$golsMandante x $golsVisitante" else null,
        status           = status,
        time_mandante    = mandante.toApiTime(),
        time_visitante   = visitante.toApiTime(),
        placar_mandante  = golsMandante,
        placar_visitante = golsVisitante,
        campeonato       = ApiCampeonato(
            campeonato_id = campeonato.id,
            nome          = campeonato.nome,
            slug          = campeonato.nome.lowercase().replace(" ", "-")
        )
    )

    private fun JogadorDto.toApiJogador() = ApiJogador(
        atleta_id        = id,
        nome_popular     = nome,
        apelido          = nome,
        // as telas traduzem os nomes em inglês (goalkeeper, defender...)
        posicao          = when (posicao) {
            "goleiro"  -> "Goalkeeper"
            "defensor" -> "Defender"
            "meia"     -> "Midfielder"
            "atacante" -> "Attacker"
            else       -> posicao
        },
        camisa           = numero,
        foto             = fotoUrl,
        data_nascimento  = dataNascimento?.let { dataBr(it) },
        local_nascimento = localNascimento,
        altura           = altura,
        peso             = peso,
        pe               = pe,
        historia         = historia
    )

    private fun ClassificacaoDto.toApiClassificacao() = ApiClassificacao(
        posicao     = posicao,
        time        = clube.toApiTime(),
        pontos      = pontos,
        jogos       = jogos,
        vitorias    = vitorias,
        empates     = empates,
        derrotas    = derrotas,
        gols_pro    = golsPro,
        gols_contra = golsContra,
        saldo_gols  = saldoGols
    )
}
