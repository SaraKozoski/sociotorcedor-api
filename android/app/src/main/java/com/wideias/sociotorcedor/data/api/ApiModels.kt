package com.wideias.sociotorcedor.data.api

import com.google.gson.annotations.SerializedName

// ─────────────── Erro padrão da API: { "error": "mensagem" } ───────────────
data class ApiErro(val error: String?)

data class MensagemResponse(val message: String?)

// ─────────────── Usuário / Auth ───────────────
data class UsuarioDto(
    val id: Int,
    val nome: String,
    val email: String,
    val cpf: String?,
    val saldo: Double,
    val pontos: Int,
    @SerializedName("plano_id") val planoId: Int?,
    @SerializedName("plano_tipo") val planoTipo: String?,   // "RED" | "GOLD" | "BLACK" | null
    @SerializedName("plano_nome") val planoNome: String?,
    @SerializedName("numero_cadastro") val numeroCadastro: String,
    @SerializedName("criado_em") val criadoEm: String?
)

data class LoginRequest(
    val cpf: String? = null,
    val email: String? = null,
    val senha: String
)

data class CadastroRequest(
    val nome: String,
    val email: String,
    val cpf: String,
    val senha: String,
    @SerializedName("data_nascimento") val dataNascimento: String? = null, // "dd/mm/aaaa"
    val logradouro: String? = null,
    val numero: String? = null,
    val cep: String? = null,
    val cidade: String? = null,
    val uf: String? = null
)

data class AuthResponse(
    val token: String,
    val usuario: UsuarioDto
)

data class AtualizarPerfilRequest(val nome: String? = null, val email: String? = null)

data class AlterarSenhaRequest(
    @SerializedName("senha_atual") val senhaAtual: String,
    @SerializedName("nova_senha") val novaSenha: String
)

/** Várias operações devolvem o usuário atualizado (saldo/pontos/plano). */
data class OperacaoResponse(
    val message: String?,
    val usuario: UsuarioDto?
)

// ─────────────── Planos ───────────────
data class DiferencialDto(val titulo: String, val descricao: String?)

data class PlanoDto(
    val id: Int,
    val tipo: String,
    val nome: String,
    val cor: String?,
    val preco: String?,
    val parcelas: String?,
    val especificacoes: String?,
    val diferenciais: List<DiferencialDto>
)

data class AssinarPlanoRequest(val tipo: String)

// ─────────────── Jogos / Clubes / Classificação / Elenco ───────────────
data class ClubeDto(
    val id: Int,
    val nome: String,
    val sigla: String,
    @SerializedName("escudo_url") val escudoUrl: String?
)

data class CampeonatoDto(val id: Int, val nome: String, val temporada: Int)

data class JogoDto(
    val id: Int,
    val rodada: String?,
    @SerializedName("data_hora") val dataHora: String,   // "2027-02-07 18:00:00"
    val estadio: String?,
    val status: String,                                   // "agendado" | "ao_vivo" | "encerrado"
    @SerializedName("gols_mandante") val golsMandante: Int?,
    @SerializedName("gols_visitante") val golsVisitante: Int?,
    val campeonato: CampeonatoDto,
    val mandante: ClubeDto,
    val visitante: ClubeDto
)

data class ClassificacaoDto(
    val posicao: Int,
    val clube: ClubeDto,
    val pontos: Int,
    val jogos: Int,
    val vitorias: Int,
    val empates: Int,
    val derrotas: Int,
    @SerializedName("gols_pro") val golsPro: Int,
    @SerializedName("gols_contra") val golsContra: Int,
    @SerializedName("saldo_gols") val saldoGols: Int
)

data class JogadorDto(
    val id: Int,
    @SerializedName("clube_id") val clubeId: Int,
    val numero: Int?,
    val nome: String,
    val posicao: String,                                  // goleiro | defensor | meia | atacante
    @SerializedName("foto_url") val fotoUrl: String?,
    @SerializedName("data_nascimento") val dataNascimento: String?,
    @SerializedName("local_nascimento") val localNascimento: String?,
    val altura: Double?,
    val peso: Double?,
    val pe: String?,
    val historia: String?
)

// ─────────────── Ingressos ───────────────
data class SetorIngressoDto(
    @SerializedName("ingresso_jogo_id") val ingressoJogoId: Int,
    @SerializedName("setor_id") val setorId: Int,
    val setor: String,
    val preco: Double,
    @SerializedName("preco_original") val precoOriginal: Double?,
    val disponiveis: Int,
    val status: String                                    // em_breve | disponivel | esgotado
)

data class JogoIngressoDto(
    val id: Int,
    val rodada: String?,
    @SerializedName("data_hora") val dataHora: String,
    val estadio: String?,
    val status: String,
    val campeonato: CampeonatoDto,
    val mandante: ClubeDto,
    val visitante: ClubeDto,
    @SerializedName("status_ingresso") val statusIngresso: String,
    @SerializedName("preco_minimo") val precoMinimo: Double?,
    @SerializedName("preco_original") val precoOriginal: Double?,
    val setores: List<SetorIngressoDto>
)

data class ComprarIngressoRequest(
    @SerializedName("ingresso_jogo_id") val ingressoJogoId: Int,
    val quantidade: Int = 1,
    @SerializedName("pagar_com_saldo") val pagarComSaldo: Boolean = false
)

data class CompraIngressoResponse(
    val message: String?,
    val ingressos: List<Int>,
    val total: Double,
    val usuario: UsuarioDto
)

data class MeuIngressoDto(
    val id: Int,
    val setor: String,
    val fila: String?,
    val cadeira: String?,
    val status: String,                                   // pendente | confirmado | cancelado | utilizado
    @SerializedName("qr_code") val qrCode: String?,
    val preco: Double,
    val jogo: JogoDto
)

// ─────────────── Benefícios ───────────────
data class BeneficioDto(
    val id: Int,
    val titulo: String,
    val descricao: String?,
    @SerializedName("desconto_percentual") val descontoPercentual: Int,
    val codigo: String,
    @SerializedName("valido_ate") val validoAte: String?
)

// ─────────────── Lanchonete ───────────────
data class ProdutoDto(
    val id: Int,
    val nome: String,
    val descricao: String?,
    val preco: Double,
    @SerializedName("pontos_resgate") val pontosResgate: Int?,
    @SerializedName("imagem_url") val imagemUrl: String?,
    @SerializedName("categoria_id") val categoriaId: Int,
    val categoria: String
)

data class ItemPedidoRequest(
    @SerializedName("produto_id") val produtoId: Int,
    val quantidade: Int = 1,
    @SerializedName("valor_adicionais") val valorAdicionais: Double = 0.0,
    val observacao: String? = null
)

data class PedidoRequest(
    val itens: List<ItemPedidoRequest>,
    @SerializedName("pagar_com_saldo") val pagarComSaldo: Boolean
)

data class PedidoResponse(
    val message: String?,
    @SerializedName("pedido_id") val pedidoId: Int,
    val total: Double,
    @SerializedName("pontos_ganhos") val pontosGanhos: Int,
    @SerializedName("qr_code") val qrCode: String,
    val usuario: UsuarioDto
)

data class ResgatarRequest(@SerializedName("produto_id") val produtoId: Int)

data class ResgateResponse(
    val message: String?,
    @SerializedName("resgate_id") val resgateId: Int,
    @SerializedName("qr_code") val qrCode: String,
    val usuario: UsuarioDto
)

// ─────────────── Carteira ───────────────
data class TransacaoDto(
    val id: Int,
    val tipo: String,                                     // credito | debito
    val valor: Double,
    val descricao: String?,
    @SerializedName("criado_em") val criadoEm: String
)

data class TransacaoPontosDto(
    val id: Int,
    val pontos: Int,
    val motivo: String?,
    @SerializedName("criado_em") val criadoEm: String
)

data class CarteiraDto(
    val saldo: Double,
    val pontos: Int,
    val transacoes: List<TransacaoDto>,
    @SerializedName("pontos_transacoes") val pontosTransacoes: List<TransacaoPontosDto>
)

data class RecargaRequest(val valor: Double, val descricao: String? = null)

// ─────────────── Palpites ───────────────
data class PalpiteRequest(
    @SerializedName("jogo_id") val jogoId: Int,
    @SerializedName("gols_mandante") val golsMandante: Int,
    @SerializedName("gols_visitante") val golsVisitante: Int,
    @SerializedName("pontos_apostados") val pontosApostados: Int = 0
)

data class PalpiteDto(
    val id: Int,
    @SerializedName("jogo_id") val jogoId: Int,
    @SerializedName("gols_mandante") val golsMandante: Int,
    @SerializedName("gols_visitante") val golsVisitante: Int,
    @SerializedName("pontos_apostados") val pontosApostados: Int,
    val jogo: JogoDto? = null
)

data class PalpiteResponse(
    val message: String?,
    val palpite: PalpiteDto,
    val usuario: UsuarioDto
)
