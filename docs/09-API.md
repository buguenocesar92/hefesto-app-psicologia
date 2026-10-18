# 09 — API

Las rutas de la aplicación, qué devuelve cada una y qué código devuelve cuando
no hay permiso.

La lista sale de `api/routes/web.php`. Ese archivo es la verdad: si una ruta no
está ahí, no existe, por mucho que aparezca en un documento.

---

## Cómo se llama

Todo cuelga de `/api/v1` y del grupo `web` de Laravel. No hay token en el
navegador: la sesión viaja en una cookie y el token CSRF se reenvía como
cabecera `X-XSRF-TOKEN`. El proxy de la interfaz los pasa por debajo, así que
quien usa la pantalla no los manipula.

```
Navegador
   |
   |  cookie + X-XSRF-TOKEN
   v
Nuxt (server/api/[...ruta].ts)   proxy en el servidor
   |
   |  cookie + X-XSRF-TOKEN
   v
Laravel (grupo web)
   |
   +-- ResolverTenant      abre la transaccion y fija el consultorio
   +-- auth:web            exige sesion valida
   +-- nota.auditada       anota el intento y corta con 404
   +-- admin               exige administrador del consultorio
   +-- Policy              permiso del recurso, uno por uno
```

El orden importa. `ResolverTenant` corre **antes** que `auth`, porque la tabla
`usuarios` tiene Row Level Security y sin consultorio fijado no se puede ni
saber quién pregunta. Ver [03](03-MULTITENANCY-Y-ROLES.md).

---

## Los códigos que devuelve

| Código | Cuando | Que trae |
|---|---|---|
| 200 | La peticion se resolvio | El recurso, o `datos` con el listado |
| 201 | Se creo algo | El recurso creado |
| 400 | El ingreso llego sin token | Un mensaje para volver a entrar |
| 401 | El ingreso fallo, o la sesión no es valida | Un mensaje; no dice por que fallo el ingreso |
| 404 | **No hay permiso**, o no existe | `{"mensaje": "No encontrado."}` |
| 422 | El dominio rechazo la operación | El mensaje del dominio, o el error de validación |

### Por que 404 y no 403

Un 403 dice "esto existe y no te lo puedo mostrar". En el contenido de una
sesión eso confirma que la persona estuvo en terapia. El 404 no confirma nada:
no distingue entre "no existe" y "no te corresponde".

Lo que si se escribe es el motivo, en la bitácora `accesos`, con la ruta, el
identificador que se pidio y quien pregunto. El motivo queda en el registro
interno, no en la respuesta. Ver [06](06-SEGURIDAD-Y-LEY-19628.md).

Un rechazo por Policy produce:

```json
{ "mensaje": "No encontrado." }
```

Lo mismo que devuelve una nota que no existe. No hay forma de distinguirlos
desde afuera.

### El 422 también es una respuesta buena

Cuando alguien intenta editar una nota cerrada por la ruta equivocada, o poner
un puntaje de corte sin fuente, la respuesta es 422 con el motivo escrito. No es
un error del sistema: es el sistema haciendo lo que debe, y un 500 le haria
creer a la persona que la aplicación se cayo.

---

## Las rutas

### Públicas

Sin sesión. Son lo único que se puede pedir sin haber entrado.

| Ruta | Devuelve |
|---|---|
| `GET /api/v1/salud` | 200. `estado` y `servicio`. Es el chequeo de salud del contenedor |
| `GET /api/v1/consultorios-publicos` | 200. El listado de consultorios para la pantalla de ingreso |
| `GET /api/v1/auth/entrar` | 200. La URL a la que se redirige al proveedor de identidad |
| `GET /api/v1/auth/callback` | 200 si el ingreso se completa; 400 si no llego el token; 401 si el token no se pudo validar |
| `POST /api/v1/auth/salir` | 200. Cierra la sesión |

`GET /api/v1/consultorios-publicos` lee la vista `vw_consultorios_publicas`, que
expone solo tres columnas: identificador, nombre y el nombre corto que se usa en
la direccion web. No expone nada mas, y la vista **se usa de verdad** en el
ingreso. En otras aplicaciones de Hefesto se creo esa vista y nunca se consulto,
mientras la documentacion afirmaba que si. Ver
[03](03-MULTITENANCY-Y-ROLES.md).

El ingreso no es un formulario con usuario y clave. La API devuelve una URL, el
navegador va al proveedor de identidad, vuelve a `auth/callback`, y la API
valida el token firmado. El rol sale de los equipos del proveedor de identidad y
se relee en cada ingreso, no queda pegado en un campo de la base. Ver
[03](03-MULTITENANCY-Y-ROLES.md).

### Sesión

| Ruta | Devuelve |
|---|---|
| `GET /api/v1/auth/yo` | 200 con la persona, su rol y su consultorio; 401 si la sesión no vale |

Es la ruta que la interfaz consulta al abrirse. Si responde 401, la pantalla
vuelve al ingreso.

### Pacientes

| Ruta | Devuelve |
|---|---|
| `GET /api/v1/pacientes` | 200 con el listado |
| `POST /api/v1/pacientes` | 201 con el paciente creado |
| `GET /api/v1/pacientes/{id}` | 200 con el paciente; 404 si no le corresponde |
| `PATCH /api/v1/pacientes/{id}` | 200 con el paciente actualizado |
| `POST /api/v1/pacientes/{id}/desactivar` | 200 con el paciente ya desactivado |

**No hay ruta de borrado.** Un paciente se desactiva, con su motivo y la fecha.
La ficha de alguien que estuvo en terapia no es un registro que se pueda borrar
porque un dia se enfade.

Lo que se ve aca es identificacion: nombre, contacto, obra social. No es contenido
clinico. Por eso un administrativo puede atender el telefono sin ver nada de lo
que se dijo en las sesiones.

### Consentimientos

| Ruta | Devuelve |
|---|---|
| `GET /api/v1/pacientes/{id}/consentimientos` | 200 con el historial de consentimientos |
| `POST /api/v1/pacientes/{id}/consentimientos` | 201 con el consentimiento registrado |
| `POST /api/v1/consentimientos/{id}/anular` | 200 con el consentimiento anulado |

Consentimiento vigente es condicion para registrar una sesión. El bloqueo esta
en el servicio de agenda, no en el controlador: un control que depende de que
cada pantalla se acuerde de hacerlo no es un control.

### Agenda

| Ruta | Devuelve |
|---|---|
| `GET /api/v1/agenda` | 200 con las citas del rango pedido |
| `GET /api/v1/agenda/disponibilidad` | 200 con los horarios libres de un profesional y un dia |
| `POST /api/v1/citas` | 201 con la cita creada |
| `GET /api/v1/citas/{id}` | 200 con la cita; 404 si no le corresponde |
| `PATCH /api/v1/citas/{id}` | 200 con la cita actualizada |
| `POST /api/v1/citas/{id}/reagendar` | 201 con la cita nueva |

La agenda no es el dato sensible. Un administrativo la ve completa porque sin
ella no puede atender el telefono ni cobrar. Un profesional ve la suya.

Los estados son `agendada`, `realizada`, `no_asistio`, `cancelada` y
`reagendada`. La base impide que dos citas del mismo profesional se pisen en el
tiempo, con una restriccion de exclusion.

Reagendar **crea** una cita nueva y marca la anterior como reagendada. No
reescribe la anterior: queda el registro de que existio una cita a esa hora.

### Notas de sesión

Todas estas rutas pasan por `nota.auditada`, que anota cada intento en la
bitacora y corta con 404 a quien no tiene permiso.

| Ruta | Devuelve |
|---|---|
| `GET /api/v1/notas` | 200 con el listado **sin contenido** |
| `POST /api/v1/notas` | 201 con la nota creada |
| `GET /api/v1/notas/{id}` | 200 con la nota, incluido el contenido |
| `PUT /api/v1/notas/{id}` | 200 con la nota guardada |
| `POST /api/v1/notas/{id}/cerrar` | 200 con la nota cerrada |
| `POST /api/v1/notas/{id}/correcciones` | 201 con el identificador de la revision y la nueva versión |
| `GET /api/v1/notas/{id}/revisiones` | 200 con el historial de correcciones |

El listado **no** trae el contenido. Trae el estado, la versión, el paciente, la
fecha de la sesión y si hay contenido escrito. Alcanza para saber que hay
abierto y que hay cerrado, que es lo que una pantalla de trabajo necesita.

El contenido viaja solo en `GET /api/v1/notas/{id}`, que es la única ruta de
todo el sistema que lo entrega.

Quien puede abrir una nota es quien **atiende** esa sesión, no quien tenga un
rol. Un administrativo y un administrador del consultorio no abren notas, nunca.
Aun así el sistema no dice "no te corresponde": responde 404.

`PUT` solo funciona con la nota abierta. `POST .../cerrar` la bloquea. Y `POST
.../correcciones` es otra cosa: escribe el texto anterior, el motivo y la
versión nueva. Nunca es una edicion silenciosa, y la base la exige: un
disparador **rechaza** el cambio de contenido de una nota cerrada si no existe
la fila del historial que lo autoriza.

La respuesta de `GET /api/v1/notas/{id}` trae `siguiente_accion`, que es
`editar_nota` o `corregir_nota`. La pantalla no decide que offer: eso lo dice la
nota.

### Instrumentos de evaluacion

| Ruta | Devuelve |
|---|---|
| `GET /api/v1/instrumentos` | 200 con el catalogo |
| `POST /api/v1/instrumentos` | 201 con el instrumento creado |
| `GET /api/v1/instrumentos/{id}` | 200 con el instrumento |
| `PATCH /api/v1/instrumentos/{id}` | 200 con el instrumento actualizado |
| `POST /api/v1/instrumentos/{id}/verificar-corte` | 200 con el instrumento y el corte verificado |
| `GET /api/v1/instrumentos/{id}/aplicaciones` | 200 con las aplicaciones de ese instrumento |

**`POST /api/v1/instrumentos` no acepta `puntaje_corte`.** El catalogo se carga
con el nombre y la versión del manual, y nada mas.

El puntaje de corte se carga **unicamente** por
`POST /api/v1/instrumentos/{id}/verificar-corte`, y exige tres cosas juntas: un
número, una fuente y una referencia. Un corte sin fuente no se verifica: un
disparador de la base lo rechaza.

Si no se ha verificado, `puntaje_corte` sigue en `null` y la interfaz lo dice,
en vez de comparar contra un número inventado. `puntaje_corte_pendiente` es un
número conocido pero todavia sin respaldo: se guarda, se muestra, y **no se usa
para comparar** contra nada.

La aplicación **no calcula puntajes**. Calcularlos exige la clave de correccion
del manual, que el consultorio no entrega a un sistema web. Ver
[06](06-SEGURIDAD-Y-LEY-19628.md).

### Aplicaciones de instrumentos

| Ruta | Devuelve |
|---|---|
| `GET /api/v1/pacientes/{id}/instrumentos` | 200 con lo que se le aplico a ese paciente |
| `POST /api/v1/pacientes/{id}/instrumentos` | 201 con la aplicación registrada |
| `GET /api/v1/aplicaciones/{id}` | 200 con la aplicación |
| `PATCH /api/v1/aplicaciones/{id}` | 200 con la aplicación actualizada |

Una aplicación **no** guarda las respuestas crudas del paciente: guarda el
puntaje que la persona ya habia calculado con la clave del manual. Las
respuestas item a item son contenido clinico con la misma protección que una
nota de sesión.

### Derivaciones por crisis

| Ruta | Devuelve |
|---|---|
| `GET /api/v1/derivaciones` | 200 con el listado, **sin motivo** si quien pregunta no atiende |
| `POST /api/v1/derivaciones` | 201 con la derivacion registrada |
| `GET /api/v1/derivaciones/{id}` | 200; el motivo solo si quien pregunta atiende |
| `PATCH /api/v1/derivaciones/{id}` | 200 con el estado nuevo |
| `GET /api/v1/derivaciones/{id}/historial` | 200 con los cambios de estado |

**No hay ruta de borrado, y no es un olvido.** No hay `destroy` en el
controlador, no hay ruta en el archivo de rutas, en la base hay un disparador que
rechaza el `DELETE` y otro que rechaza el `TRUNCATE`, y al rol de la aplicación
se le revoca el permiso de `TRUNCATE`. Quedan cuatro barreras y estan las
cuatro a propósito.

Lo que si se puede es cambiar el estado a atendida o resuelta, con su fecha y su
motivo. Resolverlo exige que la persona atienda: el cierre clinico es del
tratante. Atenderlo lo puede hacer también el administrativo, porque coordinar
una derivacion es su trabajo.

El motivo es contenido clinico, como la nota. Por eso el listado **nunca** lo
trae y el detalle lo trae solo para quien atiende. Para el resto va `null`
explicito, no la llave ausente: la pantalla tiene que poder decir "no lo ve" y
no "no hay".

Lo que si ve todo el consultorio es que hay una derivacion, de quien, cuando, de
que naturaleza y si exige contacto inmediato. Eso es operativo: sin eso no se
puede coordinar.

### Pagos y boletas

| Ruta | Devuelve |
|---|---|
| `GET /api/v1/pagos` | 200 con el listado y el total del periodo |
| `POST /api/v1/pagos` | 201 con el pago registrado |
| `GET /api/v1/pagos/{id}` | 200 con el pago; 404 si no le corresponde |
| `POST /api/v1/pagos/{id}/anular` | 200 con el pago anulado |
| `GET /api/v1/boletas` | 200 con el listado y el total del periodo |
| `POST /api/v1/boletas` | 201 con la boleta registrada |
| `GET /api/v1/boletas/{id}` | 200 con la boleta; 404 si no le corresponde |
| `POST /api/v1/boletas/{id}/anular` | 200 con la boleta anulada |

Un administrativo ve cuanto se cobro. No ve en que se empleo la sesión: el pago
se registra contra la cita, no contra la nota.

**La aplicación no emite boletas electronicas.** Guarda el número y el folio que
emitio el sistema que use el consultorio, si usa alguno. Tampoco trae precios:
las tarifas se cargan a mano y pueden quedar sin precio, que es lo que la
interfaz avisa. Ver [06](06-SEGURIDAD-Y-LEY-19628.md).

### Bitacora de accesos

Solo para la direccion del consultorio. Un profesional no puede leerla completa.

| Ruta | Devuelve |
|---|---|
| `GET /api/v1/auditoria/accesos` | 200 con los accesos, con su resultado permitido o denegado |
| `GET /api/v1/auditoria/resumenes` | 200 con el resumen diario |

### Administración del consultorio

El prefijo exige ser administrador del consultorio. Cada recurso vuelve a
preguntar a su Policy igual.

| Ruta | Devuelve |
|---|---|
| `GET /api/v1/administracion/profesionales` | 200 con el listado |
| `POST /api/v1/administracion/profesionales` | 201 con el profesional creado |
| `GET /api/v1/administracion/profesionales/{id}` | 200 con el profesional |
| `PATCH /api/v1/administracion/profesionales/{id}` | 200 con el profesional actualizado |
| `GET /api/v1/administracion/tipos-atencion` | 200 con el listado |
| `POST /api/v1/administracion/tipos-atencion` | 201 con el tipo creado |
| `PATCH /api/v1/administracion/tipos-atencion/{id}` | 200 con el tipo actualizado |
| `GET /api/v1/administracion/tarifas` | 200 con el listado |
| `POST /api/v1/administracion/tarifas` | 201 con la tarifa creada |
| `PATCH /api/v1/administracion/tarifas/{id}` | 200 con la tarifa actualizada |
| `GET /api/v1/administracion/usuarios` | 200 con las cuentas del consultorio |
| `PATCH /api/v1/administracion/usuarios/{id}` | 200 con la cuenta actualizada, **con un aviso** |

La última ruta tiene un detalle importante: acepta cambiar si la cuenta esta
activa y si su profesional esta bien, pero **no cambia el rol**. El rol sale de
los equipos del proveedor de identidad. Por eso la respuesta trae un `mensaje`
que lo dice, aunque el cambio de estado si se guarde.

### Panel

| Ruta | Devuelve |
|---|---|
| `GET /api/v1/panel` | 200 con lo que ese rol puede ver |

Es lo primero que carga la pantalla de inicio. Trae el rol, una explicacion de
que puede ver esa persona, los conteos de agenda del dia, las derivaciones
abiertas y el total pagado hoy.

La llave `notas` esta **solo** si la persona atiende. Un administrativo no la
recibe: no es que reciba ceros, es que no la recibe. Una pantalla que muestra
"0 notas" esta mintiendo sobre una cosa que ese rol no tiene derecho a saber.

---

## Lo que no hay

Estas rutas **no existen**. Que no aparezcan es parte del diseno.

| Ruta que no existe | Por que |
|---|---|
| `DELETE /api/v1/notas/{id}` | Una nota no se borra, ni abierta ni cerrada |
| `DELETE /api/v1/derivaciones/{id}` | Una derivacion no se borra, en ningun estado |
| `DELETE /api/v1/pacientes/{id}` | Un paciente se desactiva, no se elimina |
| `PUT /api/v1/notas/{id}` con la nota cerrada | Se corrige por `POST .../correcciones`, con motivo |
| `POST /api/v1/instrumentos` con `puntaje_corte` | El corte se verifica por otra ruta, con fuente |
| Cualquier ruta que devuelva notas de otro profesional | El filtro esta en la consulta, no en un `if` |
| Cualquier ruta que calcule un puntaje de instrumento | Exige la clave del manual |

La ausencia de la ruta es la primera barrera. No la única: debajo hay disparadores
en la base. Una barrera que depende de que nadie escriba la ruta se cae con un
refactor. Un disparador, no.

---

## Lo que no se ha probado

Ninguna de estas rutas se ejecuto nunca. No hay PHP, Composer, Node, Docker ni
PostgreSQL en la maquina donde se escribio esta aplicación, y por lo tanto:

- 0 rutas ejecutadas
- 0 migraciones aplicadas
- 0 pruebas corridas
- 0 imagenes construidas

Los códigos de esta pagina se leyeron en el código, no se observaron en una
respuesta real. Ver [10](10-PRUEBAS-Y-ESTADO.md).