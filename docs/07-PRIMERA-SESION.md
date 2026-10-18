# 07 — Primera sesión

Del arranque al primer paciente atendido. En orden.

> **Nada de esto se ejecutó.** Los comandos están escritos y son los que
> corresponde correr, pero en la máquina donde se escribió esta aplicación no
> había PHP, ni Docker, ni PostgreSQL. Ver [10](10-PRUEBAS-Y-ESTADO.md).

---

## Antes de levantar

### 1. Authentik tiene que existir

La aplicación no tiene contraseñas propias. **Sin el proveedor de identidad
configurado, no hay manera de entrar.**

Necesitas:

- Una aplicación OIDC en Authentik con `response_type=id_token`.
- El identificador y el secreto del cliente.
- Una URL de retorno exactamente igual a `AUTHENTIK_REDIRECT_URI`.

El `response_mode` es `fragment`: el token no viaja en la URL, sino después del
`#`. Así no queda en los registros del servidor web ni en el historial del
proxy.

### 2. Las variables

```bash
cp api/.env.example api/.env
```

Como mínimo:

```
DB_PASSWORD=algo-real
AUTHENTIK_ISSUER=https://identidad.ejemplo.cl
AUTHENTIK_CLIENT_ID=psicologia
AUTHENTIK_CLIENT_SECRET=...
AUTHENTIK_REDIRECT_URI=https://psicologia.ejemplo.cl/auth/callback
APP_SESSION_SECURE_COOKIE=true
PSICOLOGIA_CIFRADO_CLAVE=una-frase-larga-y-aleatoria
```

Las de conservación **se dejan vacías**. Ver [06](06-SEGURIDAD-Y-LEY-19628.md).

---

## Levantar

```bash
cd deploy
docker compose -f docker-compose.prod.yml up -d --build
```

El contenedor de la API, en orden:

1. Comprueba que no falten variables.
2. Genera `APP_KEY` si no vino.
3. Limpia la caché de configuración y rutas.
4. Espera a la base.
5. Corre las migraciones.
6. **Corre `psicologia:verificar-aislamiento`.**
7. Si algo falló, **no levanta**.

### Si no levanta

La última línea del registro dice qué falló. Casi siempre es el aislamiento, y
el comando dice exactamente qué tabla o qué función está mal.

```bash
docker compose -f docker-compose.prod.yml logs api | tail -50
```

---

## El primer consultorio

```bash
docker compose -f deploy/docker-compose.prod.yml exec api \
    php artisan psicologia:crear-consultorio "Consultorio de Prueba"
```

Devuelve los nombres exactos de los equipos que hay que crear en Authentik.

### Los equipos en Authentik

Por cada consultorio, cuatro:

| Equipo | Para quién |
|---|---|
| `consultorio-<slug>` | Todas las personas del consultorio |
| `Profesionales de Psicologia` | Los tratantes |
| `Administrativos de Psicologia` | Administración |
| `Administradores de Psicologia` | Dirección |

El `slug` es el que devolvió el comando. Si no se pasó con `--slug`, sale del
nombre, en minúsculas y sin tildes.

**El equipo `consultorio-<slug>` es el que declara a qué consultorio pertenece
la persona.** Sin él no hay ingreso, y el mensaje es:

> Su cuenta no está asociada a ningún consultorio. Pídale a la dirección del
> consultorio que agregue su equipo con el prefijo configurado.

Que es un mensaje útil, y no un error genérico, a propósito.

### Orden de las personas

Cree **primero** al administrador del consultorio y átenlo a los dos equipos:
`consultorio-<slug>` y `Administradores de Psicologia`.

Con esa cuenta entra por primera vez y puede crear a los demás.

---

## El primer ingreso

1. Abrir la aplicación.
2. La pantalla muestra los consultorios **que salen de la vista
   `vw_consultorios_publicas`**: identificador, nombre y slug. Nada más.
3. Elegir uno y entrar.
4. Authentik autentica. Si es la primera vez, pide crear la cuenta.
5. Vuelve a la aplicación con la sesión abierta.

La pantalla de inicio muestra, arriba, el rol y qué puede ver esa persona.

---

## Poner el consultorio en marcha

### 1. Un profesional

Administración > Profesionales > Crear.

Se cargan nombre, cómo se lo llama en la agenda, especialidad y el registro
profesional si lo tiene.

`registro_profesional_verificado` **nace en `false`**. Verificarlo es un acto
del consultorio con su colegio profesional, no algo que el software haga.

### 2. Vincular la cuenta

Administración > Usuarios.

La cuenta tiene que apuntar a una ficha de profesional para poder atender.

**Sin ese vínculo, esa persona no abre el contenido de ninguna sesión.** No es
una configuración: es la regla del producto.

Y desde esta pantalla **no se cambia el rol**. El rol sale de los equipos de
Authentik y se relee en el próximo ingreso. Si se cambiara desde acá, al
siguiente ingreso volvería al de Authentik sin avisar.

### 3. Los tipos de atención

Administración > Tipos de atención.

Aquí va la duración de cada tipo de sesión: individual, pareja, familiar,
primera entrevista.

Es el dato que usa la agenda para saber cuánto ocupa cada bloque. La duración
está en el catálogo porque **es del consultorio**: una sesión de devolución de
treinta minutos es una decisión del consultorio, no del software.

### 4. Las tarifas

Administración > Tarifas.

`precio_clp` **nace en `null`**.

La aplicación no trae ni un solo precio: un precio en el software sería una
afirmación sobre lo que cuesta una sesión, y esa afirmación cambia por tipo,
por profesional, por región y por año.

Si no se ha fijado, el sistema lo dice. Un precio en `null` mostrado como cero
sería peor: el consultorio cobraría la diferencia.

### 5. Los instrumentos

Administración > Instrumentos, o directamente por la API.

**La aplicación no trae ninguno precargado.** Ni de ejemplo, ni de prueba.

Se cargan nombre, versión y cantidad de ítems. Los puntajes de corte quedan en
`null`.

Para poner un corte hay que usar `POST /api/v1/instrumentos/{id}/verificar-corte`
con el número, **la fuente y la referencia de dónde se comprobó esa fuente**.
Sin las tres, la base lo rechaza.

Para ver cuáles faltan:

```bash
docker compose -f docker-compose.prod.yml exec api \
    php artisan psicologia:verificar-cortes
```

---

## El primer paciente

### 1. La ficha

Pacientes > Crear.

Nombre, y lo que haya. El RUT y la fecha de nacimiento **admiten vacío**: una
persona puede empezar a atenderse sin haberlos traído, y bloquear el ingreso por
eso empuja a que la ficha quede en papel, que es peor.

### 2. El consentimiento

Este paso **no es opcional** para atender.

Sin consentimiento de tratamiento vigente, el servicio de agenda rechaza la
cita con un mensaje que lo dice.

El registro tiene dos campos que importan:

- **`version_documento`**: obligatoria. Sin versión, la constancia no prueba
  nada. Dos años después no hay forma de demostrar cuál era el texto que la
  persona firmó.
- **`constancia`**: dónde está el documento firmado en papel.

El **texto** del consentimiento no lo pone la aplicación. Lo redacta el
consultorio con su asesoría legal.

### 3. Agendar

Agenda > Agendar.

El paciente, el profesional, el tipo de atención, la fecha.

El servicio comprueba: consentimiento vigente, tipo de atención activo y
dentro del máximo de duración, que no sea en el pasado, y que el profesional no
tenga otra cita a esa hora.

El último también está en la base, con una restricción `EXCLUDE USING gist`.
La comprobación del código es para el mensaje; la restricción es la que
impide.

---

## La primera sesión

### 1. Verificada

Cuando la sesión ocurrió, en Agenda > Cita > Estado > **Realizada**.

Solo una cita realizada admite nota.

### 2. La nota

Notas de sesión > Abrir.

Mientras esté **abierta** se edita y se guarda cuantas veces haga falta.

Al cerrarla ya no se puede editar. Si más tarde el profesional se equivoca, no
edita: registra una **corrección** con su motivo, y el texto anterior queda
guardado.

Esa es la diferencia que sostiene todo: editar en silencio permitiría que una
nota de una sesión de riesgo terminara siendo otra cosa sin que nadie lo supiera.

### 3. Y queda anotado

Cada vez que alguien abre el contenido de una sesión — **y cada vez que no le
dejan** — queda en la bitácora.

Administración > Accesos.

---

## La primera derivación

Derivaciones > Registrar una derivación.

Solo quien atiende.

El motivo tiene un mínimo de quince caracteres, y sin un motivo escrito la
derivación no deja constancia de por qué se decidió.

**No hay ningún botón para borrarla.** No hay ruta, no hay método, y hay un
disparador en la base que rechaza el `DELETE` y el `TRUNCATE`.

Después se puede cambiar el estado a `atendida` o `resuelta`, con su motivo. El
registro de que se decidió derivar queda siempre.

---

## La primera boleta

Pagos > Registrar un pago.

Y, si el consultorio emite boletas por algún sistema, guardar el número y el
folio en la boleta correspondiente.

**La aplicación no emite boletas electrónicas.** Emitir un documento tributario
sin haber verificado los requisitos vigentes sería afirmar que la aplicación
cumple algo que no se comprobó.

---

## El primer respaldo

Antes de que haya un solo dato real.

```bash
./deploy/respaldos/respaldo.sh --salida /var/backups/psicologia
```

Y después, **restaurarlo en un entorno de pruebas**. Una copia de la que nunca
se restauró no es un respaldo.

Ver [08](08-RESPALDOS.md).

---

## Comprobación final

```bash
docker compose -f docker-compose.prod.yml exec api \
    php artisan psicologia:verificar-aislamiento
```

Tiene que salir limpio. Y conviene correrlo después de cada actualización, no
solo la primera vez: es el comando que dice si el aislamiento sigue en su
lugar.