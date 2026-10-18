<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Lee los metadatos del emisor de identidad.
 *
 * QUÉ HACE
 * --------
 * Lee `<issuer>/.well-known/openid-configuration` y guarda el resultado un
 * tiempo corto, para no pegarle al proveedor de identidad en cada ingreso.
 *
 * POR QUÉ LA CACHÉ ES CORTA
 * -------------------------
 * Porque las llaves del proveedor pueden rotar, y una llave que rotó y que la
 * aplicación tiene cacheada produce un rechazo de ingreso que no dice nada
 * útil. Sesenta segundos es un equilibrio razonable, y es configurable en
 * `config/authentik.php`.
 *
 * NO SE ESCRIBE NADA EN DISCO
 * --------------------------
 * Solo en la caché. Un archivo con los metadatos del emisor sería una copia de
 * la configuración de autenticación en el disco, y no hace falta.
 */
class DescubridorOidc
{
    /**
     * La configuración del emisor, con la caché de por medio.
     *
     * @return array<string, mixed>
     */
    public function metadatos(): array
    {
        $issuer = rtrim((string) config('authentik.issuer'), '/');

        if ($issuer === '') {
            throw new RuntimeException(
                'Falta AUTHENTIK_ISSUER. No se puede discover sin saber a quién preguntar. '
                . 'Ver docs/05.'
            );
        }

        $clave = 'psicologia:oidc:metadatos:' . hash('sha256', $issuer);

        $metadatos = Cache::remember(
            $clave,
            (int) config('authentik.cache_metadata'),
            fn (): array => $this->pedir($issuer . '/.well-known/openid-configuration'),
        );

        return $metadatos;
    }

    /**
     * El authorization endpoint del emisor.
     */
    public function urlDeAutorizacion(): string
    {
        return (string) $this->metadatos()['authorization_endpoint'];
    }

    /**
     * El token endpoint del emisor.
     */
    public function urlDelToken(): string
    {
        return (string) $this->metadatos()['token_endpoint'];
    }

    /**
     * Las llaves públicas con las que se validan los tokens.
     *
     * @return array<int, array<string, mixed>>
     */
    public function llavesPublicas(): array
    {
        $issuer = rtrim((string) config('authentik.issuer'), '/');
        $jwksUri = (string) $this->metadatos()['jwks_uri'];

        $clave = 'psicologia:oidc:jwks:' . hash('sha256', $jwksUri);

        $respuesta = Cache::remember(
            $clave,
            (int) config('authentik.cache_jwks'),
            fn (): array => $this->pedir($jwksUri),
        );

        return $respuesta['keys'] ?? [];
    }

    /**
     * La llamada de red.
     *
     * @return array<string, mixed>
     */
    private function pedir(string $url): array
    {
        $respuesta = Http::timeout(10)
            ->acceptJson()
            ->get($url)
            ->throw();

        $cuerpo = $respuesta->json();

        if (! is_array($cuerpo)) {
            throw new RuntimeException(
                'El proveedor de identidad no devolvió una respuesta que se pueda leer. '
                . 'Ver docs/05.'
            );
        }

        return $cuerpo;
    }
}