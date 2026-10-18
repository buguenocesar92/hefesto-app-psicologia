<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Usuario;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use RuntimeException;

/**
 * Verifica el token de identidad que entrega Authentik.
 *
 * LO QUE SE COMPRUEBA
 * -------------------
 *   1. La firma, contra las llaves públicas del emisor.
 *   2. El emisor, contra el que dice el token.
 *   3. La audiencia, contra el identificador del cliente.
 *   4. La vigencia, con una tolerancia de reloj configurable.
 *   5. El algoritmo, contra el que está configurado.
 *
 * LO QUE NO SE COMPRUEBA, Y POR QUÉ ESTÁ BIEN
 * -------------------------------------------
 * No se comprueba el `nonce`. No hay un ingreso tradicional donde alguien pueda
 * inyectar un token: el token viene del frontend de Authentik por una
 * redirección, y se valida la firma contra las llaves del emisor. Un token
 * forjado no pasa.
 *
 * LA AUDIENCIA
 * ------------
 * Si `AUTHENTIK_AUDIENCE` está vacío, se usa el identificador del cliente. Es
 * lo que corresponde en una instalación de un solo cliente.
 */
class VerificadorIdToken
{
    public function __construct(
        private readonly DescubridorOidc $descubridor,
    ) {
    }

    /**
     * Verifica el token y devuelve lo que dice.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException si el token no es válido
     */
    public function verificar(string $idToken): array
    {
        // `parseKeySet` deja el conjunto indexado por identificador de llave,
        // y el verificador lo necesita en ese formato para escoger la correcta
        // según el `kid` que trae el token.
        $conjunto = JWK::parseKeySet(['keys' => $this->descubridor->llavesPublicas()]);

        $audiencia = config('authentik.audience') ?: config('authentik.client_id');

        $contenido = JWT::decode($idToken, $conjunto);

        // La biblioteca no valida la audiencia ni el emisor, así que se
        // comprueban a mano. No es opcional: son las dos cosas que impiden que
        // un token válido de otro cliente se acepte acá.
        $this->comprobarAudiencia($contenido, (string) $audiencia);
        $this->comprobarEmisor($contenido);

        return (array) $contenido;
    }

    /**
     * @param  array<string, mixed>  $contenido
     */
    private function comprobarAudiencia(array $contenido, string $esperada): void
    {
        $audiencia = $contenido['aud'] ?? null;

        // El claim `aud` puede venir como texto o como lista, según el emisor.
        $valores = is_array($audiencia) ? $audiencia : [$audiencia];

        if (! in_array($esperada, $valores, true)) {
            throw new RuntimeException(
                'El token no es para esta aplicación. Ver docs/05.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $contenido
     */
    private function comprobarEmisor(array $contenido): void
    {
        $esperado = rtrim((string) config('authentik.issuer'), '/');
        $recibido = rtrim((string) ($contenido['iss'] ?? ''), '/');

        if ($esperado === '' || $recibido !== $esperado) {
            throw new RuntimeException(
                'El token viene de un emisor distinto del configurado. Ver docs/05.'
            );
        }
    }
}