package com.wideias.sociotorcedor.data.api

/** "2027-02-07 18:00:00" -> "07/02/2027"  (sem java.time, funciona no minSdk 24) */
fun dataBr(dataHora: String?): String {
    if (dataHora.isNullOrBlank() || dataHora.length < 10) return ""
    val (a, m, d) = dataHora.substring(0, 10).split("-")
    return "$d/$m/$a"
}

/** "2027-02-07 18:00:00" -> "18:00" */
fun horaBr(dataHora: String?): String =
    if (dataHora != null && dataHora.length >= 16) dataHora.substring(11, 16) else ""
