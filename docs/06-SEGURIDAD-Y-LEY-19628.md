# 06 — Seguridad y Ley 19.628

Este es el documento más importante de esta aplicación.

Datos de salud mental son **datos sensibles de salud** bajo la Ley 19.628. Y
este documento dice, sin adornos, qué hace el software, qué no hace, y qué
queda **pendiente de decisión del cliente**.

Si va a tomar una decisión sobre esta aplicación, lea esto primero.

---

## Aviso, primero y en serio

Tres cosas que hay que tener presentes antes que nada:

1. **Esta aplicación no redacta los documentos legales.** El texto del
   consentimiento informado, el documento de tratamiento de datos y la
   política de retención los escribe y revisa el consultorio con su asesoría
   legal. Lo que el software guarda es la **constancia** de que hubo un
   documento, con su versión.

2. **Los plazos de conservación no están puestos, y es a propósito.** Ver la
   sección de conservación más abajo. Un número escrito sin verificar sería
   peor que un hueco.

3. **No se ejecutó nada.** En la máquina donde se escribió esta aplicación no
   había PHP, ni Composer, ni Node, ni Docker, ni PostgreSQL. Cero
   migraciones aplicadas, cero pruebas corridas, cero imágenes construidas.
   Ver [10](10-PRUEBAS-Y-ESTADO.md).

---

## Ley 19.628

La ley chilena de protección de la vida privada regula el tratamiento de
datos personales. Dos cosas de ella importan acá.

### Datos personales

Cualquier dato que se refiere a una persona identificable: el nombre, el RUT,
el teléfono, la fecha de nacimiento. Son datos personales, y la tabla
`pacientes` los tiene.

### Datos sensibles

Datos personales que revelan información sobre la persona. La ley los trata
con un estándar más alto: requiere **consentimiento expreso**, y el
titular puede pedir información sobre el uso de sus datos.

Los datos de salud mental son sensibles por definición. La nota de una sesión
de terapia es el ejemplo más claro que hay.

### Lo que la ley NO dice, y esta aplicación no inventa

La ley obliga a que la conservación tenga una finalidad declarada. **No se pudo
verificar en el entorno donde se escribió esta aplicación qué plazo concreto
corresponde a una ficha de salud mental en Chile**, y no hay un plazo único que
sirva para todos los datos de un consultorio.

Por eso el plazo está en `null`, y `null` significa exactamente "no hay plazo
configurado".

---

## Consentimiento

### Lo que se guarda

| Campo | Para qué |
|---|---|
| `tipo` | Tratamiento, evaluación, datos sensibles, derivación |
| `version_documento` | **Obligatoria.** Sin versión, la constancia no prueba nada |
| `modalidad` | Escrita o verbal |
| `constancia` | Dónde está el documento firmado en papel |
| `firmado_en` | Cuándo firmó la persona |
| `anulado`, `motivo_anulacion` | Si dejó de estar vigente, y por qué |

Un consentimiento se **anula**, no se borra. Queda que existió.

### Por qué `version_documento` es obligatoria

Porque sin versión la constancia no prueba nada.

"Consentí" y "consentí esto" son cosas distintas cuando alguien reclama. La
versión es lo que permite demostrar, dos años después, cuál era el texto que
la persona firmó.

### Lo que NO se guarda, y por qué

El texto del consentimiento no está en la aplicación.

Un texto que el software da por bueno es un texto que el software no revisó
con un abogado. El software puede guardar la constancia de que hubo un
documento con una versión; **qué decía ese documento** es del consultorio.

### La modalidad no se califica

La aplicación registra si el consentimiento fue escrito o verbal. **No dice si
eso cumple o no.**

En Chile la ley admite ambas modalidades para ciertos tratamientos, y cuál
corresponde en cada caso es una calificación jurídica. La hace la asesoría del
consultorio. La aplicación solo la registra.

### El bloqueo

Un paciente sin consentimiento de tratamiento vigente **no puede tener
citas**. `ServicioAgenda` lo rechaza con un mensaje que dice qué falta.

El control está en el servicio y no en el controlador, porque agendar se llama
desde la interfaz, desde la consola y desde la cola. Un control que vive en
un controlador solo protege el camino del controlador.

---

## Acceso escalonado por rol

### Los tres roles

| Rol | Ve | No ve |
|---|---|---|
| **Profesional** | Agenda, sus pacientes, notas de las sesiones que condujo, instrumentos que aplicó, derivaciones que hizo | Notas de otro profesional |
| **Administrativo** | Agenda, ficha de identificación, pagos, boletas, estado de las derivaciones | **Contenido de sesión.** Motivo de las derivaciones. Respuestas de instrumentos |
| **Administrador** | Todo lo del administrativo, más profesionales, tarifas, tipos de atención, usuarios, **bitácora** | **Contenido de sesión.** Tampoco |

### La línea que lo define

El **motivo** de una derivación es contenido de sesión, aunque la tabla se
llame `derivaciones` y la acción sea operativa.

El administrativo necesita ver que hay una derivación y si exige contacto el
mismo día, porque eso es lo que tiene que coordinar. No necesita saber por qué,
y por eso no lo ve.

Es la misma línea que separa la agenda del contenido, y se implementa con un
permiso aparte: `DerivacionPolicy::verMotivo`.

### Por qué 404 y no 403

Un 403 dice "esto existe pero no te lo muestro". Eso ya es filtrar: confirma
que hay una nota de esa persona.

El 404 no dice si existe o no. Ver [03](03-MULTITENANCY-Y-ROLES.md).

### Por qué la interfaz lo explica

Una pantalla que dice "no tiene permiso" sin decir por qué obliga a la persona
a preguntar.

Entonces la respuesta de `GET /api/v1/auth/yo` trae `explicacion` y
`acceso_al_contenido`, y la interfaz las muestra en el inicio, en la ficha de
un paciente, en la agenda y en la lista de derivaciones.

En la lista de derivaciones, la columna de motivo dice, para quien no lo ve:
**"No lo ve: es contenido clínico"**. Nunca un vacío, que haría pensar que no
hay motivo.

### Lo que la interfaz NO protege

Ocultar un enlace en el menú no esconde nada. Quien sepa escribir la URL la
escribe y recibe un 404 que no confirma nada.

El permiso está en la API. La interfaz es experiencia.

---

## Auditoría: quién abrió qué

### Qué se anota

Cada intento de abrir el **contenido de una sesión**, permitido o denegado.

Y también las denegaciones de otros recursos: abrir la bitácora, administrar
el consultorio.

| Campo | Para qué |
|---|---|
| `usuario_id`, `rol` | Quién, y con qué rol **en ese momento** |
| `recurso`, `recurso_id` | Qué intentó abrir |
| `accion` | Ver, crear, editar, cerrar, corregir |
| `resultado` | Permitido o denegado |
| `motivo` | **Por qué**, en palabras del dominio |
| `ruta`, `direccion_ip`, `agente_usuario` | Dónde |

`rol` es una **copia**, no una referencia. Si mañana la persona deja de ser
profesional, el registro de hoy sigue diciendo que era profesional cuando lo
intentó.

### Por qué los rechazos se anotan

Porque un intento denegado de abrir una sesión que no le corresponde es
información de seguridad, y es de las pocas que no se puede recuperar
después.

Sin los rechazos, la bitácora solo registra los accesos correctos, que es
justo lo contrario de lo que sirve para descubrir un acceso indebido.

### Por qué una conexión aparte

Una petición a la que se le deniega el permiso **termina revertida**.

Si el registro del intento se escribiera en la misma conexión y la misma
transacción, **desaparecería justo en el caso en que más importa**.

Por eso la bitácora usa la conexión `pgsql_auditoria`, que está fuera de la
transacción de la petición. Ver [01](01-ARQUITECTURA.md).

### El motivo va en palabras

```
"Es administrativo y los administrativos no abren el contenido de las sesiones"
```

no

```
"403"
```

Dos años después, el primero se entiende; el segundo obliga a adivinar.

### Lo que NO guarda

**No guarda un volcado de la respuesta.** Solo el identificador del recurso y
de la acción.

Duplicar el contenido de una sesión en el registro de auditoría sería la peor
decisión posible: es la misma cosa que esta aplicación existe para evitar.

### La bitácora no se borra

No hay ruta que la modifique ni que la borre. No hay `updated_at`.

Un registro de acceso que se pudiera editar dejaría de ser un registro.

El resumen diario (`resumenes_auditoria`) es una cuenta agregada al lado, no un
reemplazo. `accesos` sigue siendo la fuente.

---

## Las notas de sesión

### El contenido es lo más sensible

Lo que se escribió en una sesión de terapia es el dato más valioso y más
delicado de esta aplicación.

### Editable mientras está abierta, bloqueada después

Una nota está `abierta` mientras el profesional la escribe, y pasa a `cerrada`
cuando la da por terminada. Cerrada significa cerrada.

La regla está en **dos** lugares:

| Dónde | Qué |
|---|---|
| `ServicioNotas` | Devuelve un mensaje que la persona entiende |
| Disparador `notas_sesion_proteger_contenido_cerrado` | Rechaza el cambio |

El disparador no mira solo el estado: **permite cambiar el contenido de una
nota cerrada únicamente si ya existe la fila de historial que documenta ese
cambio**.

Eso es lo que separa una corrección de una edición en silencio, y está en la
base, no en una convención del código. Si alguien escribe el contenido sin
haber insertado la corrección, la base lo rechaza.

### La corrección, no la edición

Si el profesional se equivocó, no edita: **corrige**.

La corrección escribe tres cosas: el texto que había, el texto que queda, y
por qué cambió. Después sube la versión.

La diferencia entre corregir y editar no es de forma: es de registro. Editar en
silencio permite que una nota de una sesión de riesgo termine siendo otra cosa
sin que nadie lo sepa. En salud mental eso no es un problema de auditoría: es
el problema entero.

### El motivo es obligatorio y con largo mínimo

Quince caracteres. Sin un motivo escrito, la corrección no explica por qué el
registro de una sesión cambió, y no sirve para nada.

### El texto anterior se guarda

`revisiones_nota.contenido_anterior` duplica el dato sensible dentro de la misma
tabla sensible.

Es a propósito, y es el precio de que una corrección no pueda usarse para
reescribir una sesión sin dejar rastro. Es una decisión que el cliente debería
conocer.

### No se borran, en ningún estado

No hay ruta de borrado. No hay método en el servicio. Y hay un disparador que
rechaza el `DELETE`.

El único motivo por el que alguien borraría una nota de sesión es que moleste.

---

## Instrumentos de evaluación

### La regla

Un puntaje de corte es un dato clínico: dice, sobre una persona, "a partir de
acá hay algo".

Si el sistema pone un número sin fuente, está decidiendo sobre la salud mental
de alguien con un dato que nadie verificó. Un corte equivocado cambia un
diagnóstico o, peor, tranquiliza a quien debería haber sido derivado.

### Por eso nace vacío

| Columna | Por omisión |
|---|---|
| `puntaje_corte` | `null` |
| `corte_verificado` | `false` |
| `fuente_del_corte` | `null` |
| `referencia_verificacion` | `null` |
| `puntaje_corte_pendiente` | `null` |
| `puntaje_maximo` | `null` |
| `cantidad_items` | `null` |
| `direccion_puntaje` | `null` |

No hay ni un solo número de corte en las migraciones, en el seeder, en las
fábricas ni en las pruebas.

### Las dos columnas de corte

| Columna | Qué es | ¿Se usa para comparar? |
|---|---|---|
| `puntaje_corte` | El corte que vale, con su fuente | **Sí**, si `corte_verificado` es `true` |
| `puntaje_corte_pendiente` | Un número que el consultorio conoce pero no puede respaldar | **Nunca** |

La diferencia entre las dos es la diferencia entre "esto vale" y "esto se cree
que vale".

La interfaz muestra las dos con marcas distintas, y la respuesta del listado
trae `estado_del_corte` con una frase, para que la pantalla no tenga que
saber la diferencia.

### La base lo impide

El disparador `instrumentos_corte_verificado_exige_fuente` rechaza poner
`corte_verificado = true` si falta el número, la fuente o la referencia.

Funciona aunque alguien escriba por consola con una llave.

### El sistema no calcula el puntaje

`aplicaciones_instrumento.puntaje` lo escribe el profesional.

Calcularlo requiere la clave de corrección del instrumento: cuántos puntos vale
cada ítem, cómo se invierten los que están al revés, qué se hace con los que
no se contestaron. Esa clave es parte del manual publicado, y esta aplicación
no trae ningún instrumento.

Las `respuestas` se guardan crudas para que alguien con el manual pueda
repetir el cálculo.

### La única comparación que hace el sistema

Si el instrumento tiene un corte **verificado**, la interfaz puede mostrar "el
puntaje está por sobre el corte de este instrumento".

Eso es una resta entre dos números que la base garantiza que tienen fuente. No
es un diagnóstico, y la interfaz no lo escribe como tal.

### Cómo se agrega un instrumento

1. El administrador lo carga por su cuenta, con nombre, versión y cantidad de
   ítems.
2. Los puntajes de corte quedan en `null`.
3. Para poner un corte hay que llamar a `verificar-corte` con el número, **la
   fuente y la referencia de dónde se comprobó esa fuente**.
4. La base rechaza el paso 3 si falta alguna.

Para ver cuáles faltan:

```bash
php artisan psicologia:verificar-cortes
```

### Nombres de instrumentos

Esta aplicación **no trae ningún instrumento precargado**. Ni de ejemplo, ni
"típicos", ni de prueba.

El seeder no crea ninguno, y las fábricas tampoco. Si hubiera un corte de
ejemplo en el seeder, alguien lo iba a leer como si fuera real y lo iba a
copiar a una instalación sin mirar la fuente.

Los instrumentos que el consultorio use, sus nombres y sus versiones, los
confirma el cliente.

---

## Derivaciones por crisis

### Es una obligación, no una función opcional

Si un profesional decide que una persona necesita ser derivada, esa decisión
tiene que quedar.

Si después esa derivación desaparece del registro, el sistema dejó de dar fe
de la decisión. Eso no es un problema de auditoría: es un problema de
seguridad de la persona atendida.

### No se borran. Nunca.

Tres barreras:

1. **No hay ruta de borrado** en `routes/web.php`.
2. **No hay método de borrado** en `ServicioDerivaciones`.
3. **Hay dos disparadores en la base**: uno para el `DELETE` y otro para el
   `TRUNCATE`, porque un `TRUNCATE` no dispara los de fila.

Y una cuarta: el rol de la aplicación **no tiene permiso de `TRUNCATE`** sobre
la tabla. Se quita en la migración `2026_10_18_000034_permisos_de_base`.

### Lo que sí se puede

Cambiar el estado: `vigente`, `atendida`, `resuelta`. Con su fecha, su
responsable y su motivo.

El registro de que se decidió derivar queda siempre.

### No hay estado "anulada"

Una derivación que se anula es una derivación que se quitó del medio, que es
justo el caso que tiene que quedar a la vista.

### Por qué no hay nivel de riesgo

Sería fácil agregar `leve`, `moderado` y `alto`, y al lado habría que poner un
número.

No está, y la razón es que un nivel de riesgo es un **juicio clínico** con
consecuencias: define a quién se llama primero y qué pasa si la llamada no se
hace. Poner esos umbrales en el software es inventar exactamente el tipo de
dato que esta aplicación no inventa.

Lo que hay es `motivo`, escrito por quien deriva, y `naturaleza`, que dice a
dónde se derivó.

El nivel de riesgo, si el consultorio lo necesita, lo escribe la persona que
deriva, en el motivo, con sus palabras y bajo su responsabilidad profesional.

### El motivo tiene largo mínimo

Quince caracteres. Una derivación sin motivo escrito no sostiene la decisión.

### El contacto inmediato no es un puntaje

`exige_contacto_inmediato` sale de la **naturaleza** elegida: urgencia significa
urgencia, el resto espera su turno. El sistema no lo deduce de un puntaje ni de
una palabra del motivo.

---

## Conservación de datos

### Los plazos NO están puestos, y es una decisión

```
PSICOLOGIA_RETENCION_NOTAS_DIAS=
PSICOLOGIA_RETENCION_ACCESOS_DIAS=
PSICOLOGIA_RETENCION_DERIVACIONES_DIAS=
```

Vacíos. Por omisión.

**Por qué:**

No se pudo verificar en el entorno donde se escribió esta aplicación qué plazo
de conservación corresponde a una ficha de salud mental en Chile.

Y aunque se hubiera verificado, no hay un plazo único que sirva para todos los
datos de un consultorio: las notas, los accesos y las derivaciones no tienen
el mismo ciclo de vida.

**Qué significa `null`:**

Exactamente "no hay plazo configurado".

**Qué hace la aplicación:**

No borra nada por su cuenta. No hay tarea programada de purga.

**Por qué esa es la consecuencia correcta:**

Borrar historia clínica de salud mental porque pasó un número inventado sería
peor que no borrar nunca.

**Qué tiene que hacer el cliente:**

Revisar con su asesoría legal qué plazo corresponde, escribirlo en el documento
de tratamiento de datos de **ese** consultorio, y si quiere que el sistema lo
aplique, configurarlo por instalación.

Mientras tanto, la decisión es del consultorio y está documentada como
pendiente.

---

## Sesión y autenticación

### No hay contraseñas

La aplicación no tiene usuarios con contraseñas propias. Quien entra llega
desde Authentik.

Eso elimina de un plumazo el manejo de contraseñas: no hay tabla de hashes, no
hay restablecimiento, no hay que inventar una política de contraseñas. Es la
razón principal por la que los datos no se pueden filtrar por ahí.

### El rol se relee en cada ingreso

El rol sale de los **equipos de Authentik**, y se vuelve a escribir en la base en
cada ingreso.

Si el rol quedara congelado en la base el día del primer ingreso, quitar un
equipo en Authentik no haría nada hasta que alguien corriera un comando. Y
mientras tanto, esa persona conservaría el acceso.

Con el modelo actual, quitar el equipo surte efecto en la sesión siguiente.

### La cookie

| Atributo | Valor | Por qué |
|---|---|---|
| `httpOnly` | `true` | La cookie no se lee desde JavaScript |
| `secure` | `true` en producción | Sin eso, viaja en claro |
| `sameSite` | `Lax` | El robo de cookie deja de servir desde otro sitio |
| Duración | 30 minutos | Una sesión abierta mucho rato es una sesión abierta al contenido de las sesiones de los pacientes |

Y el token CSRF va en la cabecera `X-XSRF-TOKEN`, reenviado por el servidor de
la interfaz.

### No hay token en el navegador

No hay clave de API ni token de sesión en el código de la interfaz. La cookie
viaja del navegador al servidor de Nuxt y de ahí a la API.

---

## Registro del sistema

### Sin datos personales

Los registros van a `stderr`, que es lo que recoge el orquestador de
contenedores. **No se escribe en archivo dentro del contenedor**: un archivo de
registro es una segunda copia de datos clínicos en un disco que nadie está
mirando.

El canal `sterilized` borra los valores personales del registro antes de
escribirlo, con una lista explícita de claves.

Un error de aplicación debería decir "no se pudo guardar la nota de la cita
1234", no lo que dice la nota.

### Sin correo saliente

No hay correo implementado. No hay alertas automáticas.

Los umbrales existen en la configuración para que, cuando se implemente, no haya
que inventarlos.

---

## Transporte y cabeceras

| Capa | Qué hace |
|---|---|
| Caddy | HTTPS con certificado válido, HSTS |
| nginx | Oculta los directorios del proyecto, no muestra errores de PHP |
| PHP | `expose_php=Off`, `display_errors=Off` |
| Cabeceras | `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy` |

Ninguna ruta sirve `/storage`, `/bootstrap`, `/app`, `/config`, `/database`,
`/vendor` ni archivos `.env`.

En producción, el único puerto publicado es el del proxy. Ver
[04](04-INSTALACION.md).

---

## Superficie expuesta

Lo que se puede consultar sin tener sesión:

- `GET /api/v1/salud` — dice que la aplicación está viva. No toca la base.
- `GET /api/v1/consultorios-publicos` — tres columnas, de la vista.

Nada más.

---

## Lo que NO está hecho

Todo esto está declarado a propósito, no por olvido:

| No está | Por qué |
|---|---|
| Cifrado de los respaldos por omisión | Depende de que la instalación defina `PSICOLOGIA_CIFRADO_CLAVE`. Ver [08](08-RESPALDOS.md) |
| Emisión de boletas electrónicas | Emitir un documento tributario sin verificar los requisitos vigentes sería afirmar que la aplicación cumple algo que no se comprobó |
| Validación del dígito verificador del RUT | Requiere revisar la norma vigente, y no se verificó |
| Correo saliente y alertas automáticas | Ver [10](10-PRUEBAS-Y-ESTADO.md) |
| Cálculo automático de puntajes de instrumentos | Requiere la clave de corrección de cada instrumento, que es parte del manual publicado |
| Un nivel de riesgo en las derivaciones | Es un juicio clínico, no un dato de software |
| Texto del consentimiento informado | Lo redacta el consultorio con su asesoría legal |
| Plazos de conservación | Decisión del cliente |
| Exportación de la bitácora | Un volcado es un volcado de quién leyó qué. Si hace falta, se conversa antes |
| Carga de documentos por la interfaz | `file_uploads` está en `Off` |

---

## Qué hacer antes de producción

1. `php artisan psicologia:verificar-aislamiento` sale limpio.
2. El texto del consentimiento está redactado y revisado por el cliente.
3. Hay un documento de tratamiento de datos, y en él está la decisión sobre
   conservación.
4. Los plazos quedaron decididos y escritos, o la decisión de no fijarlos está
   documentada por el cliente.
5. La clave de cifrado del respaldo está definida y a salvo.
6. Hay un respaldo **restaurado** en un entorno de pruebas.
7. Los instrumentos que el consultorio use están cargados **con su fuente**.
8. `php artisan psicologia:verificar-cortes` muestra lo que falta, y es
   aceptable.

---

## Revisiones de la bitácora

La pantalla de accesos es de solo lectura y es de la dirección.

Para una revisión trimestral:

```sql
SELECT DATE(ocurrido_en) AS dia, resultado, COUNT(*) AS total
FROM accesos
WHERE recurso = 'nota_sesion'
  AND resultado = 'denegado'
  AND ocurrido_en >= now() - interval '90 days'
GROUP BY 1, 2
ORDER BY 1 DESC;
```

Un total alto y sostenido de denegados no es necesariamente un problema: en un
consultorio donde los profesionales se cruzan de pacientes, es normal. Lo que
interesa es el **motivo**, que la aplicación escribe en palabras y está en la
misma pantalla.