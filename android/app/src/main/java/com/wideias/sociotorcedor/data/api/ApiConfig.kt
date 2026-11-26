package com.wideias.sociotorcedor.data.api

object ApiConfig {
    /**
     * Endereço da sociotorcedor-api (sempre terminando com "/").
     *
     * - Emulador do Android Studio + XAMPP no seu PC: http://10.0.2.2/sociotorcedor-api/endpoints/
     *   (10.0.2.2 é o "localhost" do computador visto de dentro do emulador)
     * - Celular físico na mesma rede Wi-Fi: http://<IP do seu PC>/sociotorcedor-api/endpoints/
     *   (descubra o IP com `ipconfig` no Windows, ex.: http://192.168.0.15/sociotorcedor-api/endpoints/)
     * - Servidor PHP embutido (php -S 0.0.0.0:8080 na pasta da API): http://10.0.2.2:8080/endpoints/
     */
    const val BASE_URL = "http://10.0.2.2/sociotorcedor-api/endpoints/"
}
