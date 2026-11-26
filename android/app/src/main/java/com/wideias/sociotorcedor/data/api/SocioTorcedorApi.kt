package com.wideias.sociotorcedor.data.api

import retrofit2.http.*

/** Todos os endpoints da sociotorcedor-api. O token é enviado automaticamente pelo RetrofitClient. */
interface SocioTorcedorApi {

    // ── Auth ──
    @POST("auth.php?acao=login")
    suspend fun login(@Body body: LoginRequest): AuthResponse

    @POST("auth.php?acao=cadastro")
    suspend fun cadastrar(@Body body: CadastroRequest): AuthResponse

    @POST("auth.php?acao=logout")
    suspend fun logout(): MensagemResponse

    // ── Usuário logado ──
    @GET("usuarios.php")
    suspend fun meuPerfil(): UsuarioDto

    @PUT("usuarios.php")
    suspend fun atualizarPerfil(@Body body: AtualizarPerfilRequest): UsuarioDto

    @PUT("usuarios.php?acao=senha")
    suspend fun alterarSenha(@Body body: AlterarSenhaRequest): MensagemResponse

    // ── Planos ──
    @GET("planos.php")
    suspend fun planos(): List<PlanoDto>

    @GET("planos.php")
    suspend fun plano(@Query("tipo") tipo: String): PlanoDto

    @POST("planos.php?acao=assinar")
    suspend fun assinarPlano(@Body body: AssinarPlanoRequest): OperacaoResponse

    @POST("planos.php?acao=cancelar")
    suspend fun cancelarPlano(): OperacaoResponse

    // ── Time ──
    @GET("jogos.php")
    suspend fun jogos(
        @Query("status") status: String? = null,
        @Query("clube_id") clubeId: Int? = null
    ): List<JogoDto>

    @GET("classificacao.php")
    suspend fun classificacao(@Query("campeonato_id") campeonatoId: Int = 1): List<ClassificacaoDto>

    @GET("jogadores.php")
    suspend fun jogadores(@Query("clube_id") clubeId: Int = 1): List<JogadorDto>

    @GET("jogadores.php")
    suspend fun jogador(@Query("id") id: Int): JogadorDto

    // ── Ingressos ──
    @GET("ingressos.php")
    suspend fun jogosComIngressos(): List<JogoIngressoDto>

    @POST("ingressos.php")
    suspend fun comprarIngresso(@Body body: ComprarIngressoRequest): CompraIngressoResponse

    @GET("ingressos.php?meus=1")
    suspend fun meusIngressos(): List<MeuIngressoDto>

    // ── Benefícios ──
    @GET("beneficios.php")
    suspend fun beneficios(): List<BeneficioDto>

    // ── Lanchonete ──
    @GET("produtos.php")
    suspend fun produtos(@Query("categoria_id") categoriaId: Int? = null): List<ProdutoDto>

    @POST("pedidos.php")
    suspend fun fazerPedido(@Body body: PedidoRequest): PedidoResponse

    @POST("pedidos.php?acao=resgatar")
    suspend fun resgatar(@Body body: ResgatarRequest): ResgateResponse

    // ── Carteira ──
    @GET("carteira.php")
    suspend fun carteira(): CarteiraDto

    @POST("carteira.php")
    suspend fun recarregar(@Body body: RecargaRequest): OperacaoResponse

    // ── Palpites ──
    @GET("palpites.php")
    suspend fun meusPalpites(): List<PalpiteDto>

    @POST("palpites.php")
    suspend fun palpitar(@Body body: PalpiteRequest): PalpiteResponse
}
