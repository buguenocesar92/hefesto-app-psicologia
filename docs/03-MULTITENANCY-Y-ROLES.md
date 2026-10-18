# 03 — Multi-tenancy y roles

Este documento tiene dos partes: cómo se aíslan los consultorios entre sí, y
qué puede ver cada persona dentro de un consultorio.

Las dos son distintas y se confunden seguido.

---

# Parte 1: Aislamiento entre consultorios

## El patrón

Es el mismo que el resto de las aplicaciones de Hefesto. No es un patrón
nuevo.

```
+------------------------------------------------------------------+
|                                                                  |
|  1. Una función que falla CERRADO                                |
|                                                                  |
|     CREATE FUNCTION tenant_actual() RETURNS uuid AS $$           |
|         SELECT NULLIF(current_setting('app.tenant_id', true), '') |
|     $$ LANGUAGE sql STABLE;                                      |
|                                                                  |
|     Sin app.tenant_id devuelve NULL.                             |
|     NULL no es igual a ninguna fila.                             |
|     Nunca devuelve true "por si acaso".                          |
|                                                                  |
+------------------------------------------------------------------+
                              |
                              v
+------------------------------------------------------------------+
|                                                                  |
|  2. Una politica por tabla, con USING y WITH CHECK               |
|                                                                  |
|     CREATE POLICY notas_sesion_aislamiento ON notas_sesion        |
|         USING      (tenant_id = tenant_actual())                 |
|         WITH CHECK (tenant_id = tenant_actual())                 |
|                                                                  |
|     USING      -> que filas se pueden LEER                       |
|     WITH CHECK -> que filas se pueden ESCRIBIR                   |
|                                                                  |
|     El WITH CHECK es lo que impide escribir una fila con el       |
|     tenant_id de otro consultorio.                               |
|                                                                  |
+------------------------------------------------------------------+
                              |
                              v
+------------------------------------------------------------------+
|                                                                  |
|  3. FORCE ROW LEVEL SECURITY en cada tabla                       |
|                                                                  |
|     Sin FORCE, el DUENO de la tabla no obedece la politica.      |
|     Y en este despliegue el dueno es el mismo usuario que         |
|     corre la aplicacion.                                         |
|                                                                  |
+------------------------------------------------------------------+
                              |
                              v
+------------------------------------------------------------------+
|                                                                  |
|  4. El contexto viaja con alcance de TRANSACCION                 |
|                                                                  |
|     SELECT set_config('app.tenant_id', ?, true)                  |
|                                                        ^^^^^    |
|     Ese `true` es lo que importa. Sin el, el valor sobrevive a    |
|     la transaccion y a la conexion: la peticion siguiente         |
|     heredaria el consultorio anterior.                           |
|                                                                  |
+------------------------------------------------------------------+
```

## Por qué NO un esquema por consultorio

Un esquema por consultorio parece más limpio hasta que se necesita respaldar,
restaurar o migrar: son N esquiems que repetir por cada tabla, y una consulta
que se equivoca de nombre de esquema lee los datos de otro consultorio.

Una columna `tenant_id` con Row Level Security es una sola estructura, y la
base la impone.

## Las dos capas

El filtro está en dos lugares a propósito.

| Capa | Qué hace | Si alguien la salta |
|---|---|---|
| Row Level Security (base) | Impide el acceso cruzado entre consultorios | Es la última línea |
| Alcance global de Eloquent | Evita mandar una consulta que cruza consultorios y recibir cero filas sin entender por qué |

Con el alcance global **quitado**, la base sigue filtrando. Ver
`app/Models/Concerns/PerteneceAlConsultorio.php`.

El alcance es un `addGlobalScope` y no un `newQuery()` sobreescrito, a
propósito: un alcance global se puede quitar por nombre con
`withoutGlobalScope('tenant')`, y eso lo necesita el ingreso. Sobreescribir
`newQuery()` pondría el filtro donde no se puede apagar sin saltarse el
método a mano.

## La tabla de consultorios NO lleva RLS

Porque en el ingreso todavía no hay consultorio. El que está entrando todavía
no dijo en qué consultorio está: elegirlo es el primer dato que escribe.

Si esa tabla tuviera RLS, la pantalla de ingreso no podría listar nada y la
aplicación no tendría por dónde empezar.

Es la excepción documentada, y su compensación es la vista siguiente.

## La vista pública, Y SE USA DE VERDAD

```
CREATE VIEW vw_consultorios_publicas AS
    SELECT id, nombre, slug
    FROM consultorios
    WHERE activo = true
```

Tres columnas. Con eso se arma la pantalla "elija su consultorio" y no sale
nada más.

Lo que **no** sale: RUT, correo, teléfono, dirección. Y filtra por `activo`, así
que un consultorio dado de baja desaparece del ingreso sin que haya que
borrarlo.

### La ruta que la consulta

```
GET /api/v1/consultorios-publicos
  -> ConsultoriosPublicosController
     -> Consultorio::publicos()
        -> DB::table('vw_consultorios_publicas')
           -> select(['id', 'nombre', 'slug'])
```

Y hay una prueba que lo comprueba:
`tests/Feature/Aislamiento/VistaPublicaSeUsaTest.php`.

### Por qué se insiste tanto

En siete aplicaciones anteriores de Hefesto se creó la vista
`vw_<entidad>s_publicas`, la documentación afirmó que el ingreso la usaba, y
**nunca se consultó**.

Eso es prometer una protección que no existe. Y lo peor no es la vista sin
usar: es que alguien leyó la documentación, creyó que el ingreso estaba
protegido, y no tomó otra precaución.

En esta aplicación la vista existe, la ruta la consulta, y hay una prueba que
lo verifica. Si alguien cambia el controlador para leer la tabla, la prueba
falla.

## Cómo se fija el contexto

Un solo lugar: `ResolverTenant`.

```
1. Buscar al usuario con el guard `web`
2. Leer su tenant_id
3. Abrir una transaccion en pgsql
4. ContextoTenant::establecer($tenantId, 'pgsql')
5. ContextoTenant::establecer($tenantId, 'pgsql_auditoria')
6. ... la peticion ...
7. Commit, o rollback si hubo excepción
8. ContextoTenant::olvidar()
```

El paso 1 tiene que ir **antes** que `auth`, y por eso este middleware
resuelve el usuario por su cuenta: la fila de `usuarios` está detrás de la
Row Level Security, y sin `app.tenant_id` fijado no se puede ni leer.

El paso 5 pone el contexto en las **dos** conexiones. Sin eso, la tabla de
accesos sería invisible para todos en la conexión de auditoría, porque también
tiene Row Level Security.

Y cambiar el consultorio dentro de una misma petición lanza una excepción:
sería una forma discreta de leer datos de otro.

## Verificación

Antes de recibir al primer paciente:

```bash
php artisan psicologia:verificar-aislamiento
```

El comando comprueba cinco cosas:

1. Que `tenant_actual()` exista y devuelva `null` sin consultorio.
2. Que las diecisiete tablas tengan RLS activada, **forzada**, y su política.
3. Que las tablas exentas no la tengan.
4. Que los cinco disparadores de la base estén.
5. **Que una consulta sin consultorio devuelva cero filas.** Esta es la que de
   verdad importa: las otras comprueban que la medida esté puesta, y esta
   comprueba que funciona.

El contenedor de la API lo corre en cada arranque. Si falla, el contenedor no
levanta. Ver [04](04-INSTALACION.md).

---

# Parte 2: Roles dentro del consultorio

## Los tres roles

| Rol | Qué hace | Qué **no** puede |
|---|---|---|
| **Profesional** | Atiende. Agenda, ve sus pacientes, escribe las notas de las sesiones que condujo, registra derivaciones | Ver las notas de otro profesional |
| **Administrativo** | Agenda, ve la ficha de identificación, cobra, registra pagos, coordina derivaciones | **Abrir el contenido de una sesión.** Nunca |
| **Administrador** | Administra: profesionales, tarifas, tipos de atención, usuarios, bitácora | **Abrir el contenido de una sesión.** Tampoco |

## La distinción que define el producto

Un administrativo **no** es un profesional con menos permisos.

Tiene su propio trabajo, que es real: sin agenda no atiende el teléfono, sin
ficha de identificación no sabe a quién hay que llamar, sin pagos no puede
cobrar. Y aun así, el contenido de una sesión no lo ve nunca.

Y el administrador del consultorio tampoco. Que sea el jefe no lo habilita para
leer lo que se dijo en una sesión de otro.

## De dónde sale el permiso de abrir notas

No del rol. De que exista una ficha de profesional asociada a la cuenta.

```
Usuario::esTratante()  =>  $this->profesional_id !== null
```

Un administrativo tiene `profesional_id` en `null`, y por eso no tiene "sus
pacientes": no atiende. La regla dice "un profesional abre el contenido de las
sesiones de **sus** pacientes", y un administrativo no tiene de cuáles.

Un administrador de consultorio que también atiende tiene ficha, y entonces sí
abre las notas de los pacientes que atiende — no las de los demás.

## El acceso a las notas de otro profesional

No se aparta.

Hay consultorios donde los profesionales se cubren, y ahí lo que corresponde es
que la dirección registre la derivación interna con su motivo. Acortar esto
"por comodidad" sería abrir la puerta a que cualquiera lea lo que se dijo en
una sesión.

## Por qué 404 y no 403

Un 403 dice "esto existe pero no te lo muestro".

En una clínica de salud mental, decir eso ya es filtrar: confirma que hay una
nota de esa persona. El 404 no dice si existe o no, y con eso no hay nada que
filtrar.

Está configurado en `bootstrap/app.php`, en el manejador de
`AuthorizationException`: cualquier rechazo dentro de la API responde 404.

Y hay una prueba que lo comprueba:
`tests/Feature/Permisos/AdministrativoNoLeeNotaDeSesionTest.php`.

## La bitácora y el motivo

Cada intento de abrir el contenido de una sesión se anota, permitido o
denegado.

Los rechazos son la parte importante: un intento denegado de abrir una sesión
que no le corresponde es información de seguridad, y es de las pocas que no
se puede recuperar después.

Por eso se escribe en una conexión aparte. Una petición denegada termina
revertida, y si el registro del intento fuera en la misma transacción,
desaparecería justo en el caso en que más importa.

El motivo va **en palabras del dominio**, no en código:

```
"Esta nota es de otro profesional. Un profesional abre el contenido de las
 sesiones de los pacientes que atiende, no las de los demás."
```

pero no este:

```
"403"
```

Dos años después, el primero se entiende; el segundo obliga a adivinar.

## Por qué la interfaz explica

Una pantalla que dice "no tiene permiso" sin decir por qué obliga a la persona
a preguntar. Y en este caso la pregunta es siempre la misma.

Entonces la respuesta de `GET /api/v1/auth/yo` trae `explicacion` y
`acceso_al_contenido`, escritas por el dominio, y la interfaz las muestra. En
la ficha de un paciente, en la agenda, en el inicio.

Y en la lista de derivaciones, la columna de motivo dice, para quien no lo ve:
"No lo ve: es contenido clínico". No un vacío, que haría pensar que no hay
motivo.

## Dónde se aplica cada permiso

| Recurso | Quién ve | Quién escribe | Notas |
|---|---|---|---|
| Agenda | Todos | Todos | No es contenido de sesión |
| Ficha del paciente | Todos | Todos | Identificación, no clínica |
| Consentimientos | Todos | Todos | Se anulan, no se borran |
| **Nota de sesión** | **Solo el tratante** | **Solo el tratante** | 404 para el resto |
| Historial de correcciones | Solo el tratante | Solo el tratante | Solo crece |
| Instrumentos | Todos ven | Administrador | Verificar corte: administrador |
| Aplicaciones | Todos ven el puntaje | Tratante | Respuestas: solo tratante |
| Derivaciones | Todos ven el estado | Tratante | Motivo: solo tratante |
| Pagos y boletas | Todos | Todos | Se anulan con motivo |
| **Bitácora** | **Solo administrador** | Solo el sistema | |
| Profesionales, tarifas, tipos | Todos ven | Administrador | |

## Lo que la interfaz hace y lo que no

**Hace**: esconder el enlace de "Notas de sesión" a quien no atiende, para no
ofrecer una pantalla que va a dar 404.

**No hace**: proteger nada. Quien sepa escribir la URL la escribe y recibe un
404 que no confirma nada.

Un `if` en la interfaz es experiencia. El permiso está en la API.

---

## Los dos roles de PostgreSQL

La base de esta aplicación corre con **dos** roles, no con uno. La separación es
deliberada: es lo que hace que el aislamiento entre consultorios funcione en vez
de quedar como decoración.

### Qué es cada rol

| Rol | Nombre | De dónde sale | Para qué sirve | ¿Lo usa la aplicación? |
|---|---|---|---|---|
| **Arranque** | `psicologia_admin` | `POSTGRES_USER` | Crear la base `psicologia` y las tareas administrativas del despliegue | **Nunca** |
| **Aplicación** | `psicologia` | `DB_USERNAME`, creado por `deploy/postgres/inicializar-rol.sh` | Todo el tráfico de la API | **Sí, este y solo este** |

El rol de aplicación **no** lleva sufijo `_app`: se llama `psicologia`, igual que
la base.

El rol de arranque no es una convención del estudio: la imagen oficial de
PostgreSQL ejecuta `initdb --username="$POSTGRES_USER"`, así que ese rol nace
**superusuario**.

### Por qué con un solo rol no servía

La documentación de PostgreSQL 18 lo dice de forma textual:

> *«Superusers and roles with the `BYPASSRLS` attribute always bypass the row
> security system when accessing a table. Table owners normally bypass row
> security as well, though a table owner can choose to be subject to row
> security with `ALTER TABLE ... FORCE ROW LEVEL SECURITY`.*

La segunda frase es la clave. `FORCE ROW LEVEL SECURITY` sujeta al **dueño** de
la tabla, pero **no al superusuario**. Mientras la aplicación conectara con el
rol de `POSTGRES_USER`, ninguna política de este documento se iba a aplicar: un
consultorio habría visto las fichas de los pacientes y las notas de sesión de
los demás, sin error y sin aviso. Con datos de salud confidencial, eso no es
un detalle aceptable.

### Los atributos van todos escritos

`deploy/postgres/inicializar-rol.sh`, montado en
`/docker-entrypoint-initdb.d/`, crea el rol con `NOSUPERUSER`, `NOCREATEDB`,
`NOCREATEROLE`, `NOINHERIT` y `NOBYPASSRLS` **explícitos todos**, aunque
`NOBYPASSRLS` ya sea el valor por omisión.

Se escriben igual a propósito: un rol verificado no debería depender de un valor
por omisión que una versión nueva puede cambiar. El script también falla
ruidosamente si falta `POSTGRES_DB`, `APP_DB_ROLE` o `APP_DB_PASSWORD`.

### Por qué el rol de aplicación es dueño de la base

Justo después de crearlo, el script corre:

```sql
ALTER DATABASE psicologia OWNER TO psicologia;
```

Las migraciones de Laravel se ejecutan con el rol de la aplicación y necesitan
**crear tablas**. La única forma de darles ese permiso sin convertirlos en
superusuario es que ese rol sea el dueño de la base. Desde PostgreSQL 15 el
esquema `public` pertenece al dueño de la base, así que con esto el rol ya
puede crear tablas ahí.

Y este es el punto exacto del diseño: al ser el dueño, **las tablas quedan a su
nombre**, y por eso el `FORCE ROW LEVEL SECURITY` que usan las tablas sí lo
sujeto. Ser dueño no es saltarse la política; es lo que hace que la política lo
alcance. Un superusuario la saltearía siempre.

### Cómo se comprueba

```
php artisan psicologia:verificar-aislamiento
php artisan psicologia:verificar-aislamiento --consultorio=<id>
```

La opción `--consultorio` limita la comprobación de datos a un consultorio
concreto.

**Una diferencia que hay que conocer:** este comando **no** consulta `pg_roles`
para revisar los atributos del rol, como sí hacen varias de las otras
aplicaciones de Hefesto. Lo que hace es comprobar que `tenant_actual()` falla
cerrada, que cada tabla tenga RLS activada y forzada con su política
`<tabla>_aislamiento`, que los disparadores estén, y que una consulta sin
consultorio fijado devuelva **cero filas**.

Eso detecta un rol equivocado de forma indirecta: si la conexión fuera
superusuario, el conteo sin consultorio devolvería filas y el comando fallaría.
Pero la detección **depende de que haya datos**: contra una base recién
instalada y vacía, el conteo sería cero y el comando pasaría sin haber detectado
nada. Por eso conviene correrlo también con la consulta a `pg_roles` a mano,
que es el control que aquí no está automatizado.

**Este comando no se ha ejecutado.** En el equipo donde se escribió este cambio
no había PostgreSQL ni Docker. El diseño es correcto y está sostenido en la
documentación oficial, pero nadie lo ha corrido. Ver
[10-PRUEBAS-Y-ESTADO.md](10-PRUEBAS-Y-ESTADO.md).