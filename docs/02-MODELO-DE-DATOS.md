# 02 — Modelo de datos

Dieciocho tablas. La mayoría tiene la misma forma: `tenant_id` y una regla que
la protege.

## El mapa

```
consultorios  (sin RLS: se consulta en el ingreso)
     |
     +--> profesionales  <--+  usuarios
     |         |               (profesional_id)
     |         |
     |         +--> citas --> notas_sesion --> revisiones_nota
     |         |       |            |
     |         |       +------------+
     |         |            |
     |         +--> derivaciones
     |
     +--> pacientes --> consentimientos
     |         |
     |         +--> aplicaciones_instrumento --> instrumentos
     |         |
     |         +--> pagos --> boletas
     |
     +--> tipos_atencion --> tarifas
     |
     +--> accesos, resumenes_auditoria, sesiones
```

## Las dieciocho

### Sin Row Level Security

| Tabla | Por qué no la tiene |
|---|---|
| `consultorios` | En el ingreso todavía no hay consultorio. Sin `app.tenant_id` no se puede filtrar nada, y sin filtrar no se puede mostrar el selector. Su compensación es la vista `vw_consultorios_publicas`. Ver [03](03-MULTITENANCY-Y-ROLES.md). |

### Con Row Level Security

| Tabla | Qué guarda | Qué tan sensible |
|---|---|---|
| `profesionales` | Ficha del tratante | Baja |
| `tipos_atencion` | Catálogo de duración de sesión | Baja |
| `tarifas` | Precios del consultorio | Baja |
| `usuarios` | Cuentas, rol y vínculo con el profesional | Media |
| `pacientes` | Nombre, RUT, contacto, motivo de consulta | **Media** |
| `consentimientos` | Constancia de consentimiento | **Alta** |
| `citas` | Agenda | Baja |
| `notas_sesion` | **El contenido de la sesión** | **Muy alta** |
| `revisiones_nota` | Historial de correcciones | **Muy alta** |
| `instrumentos` | Catálogo con puntajes de corte | Media |
| `aplicaciones_instrumento` | Evaluaciones de un paciente | **Alta** |
| `derivaciones` | Decisiones de derivar | **Alta** |
| `boletas` | Documentos de cobro | Media |
| `pagos` | Cobros | Media |
| `accesos` | Quién abrió qué | **Alta** |
| `resumenes_auditoria` | Conteos diarios | Baja |
| `sesiones` | Sesiones web de Laravel | Baja |

## La separación que define el producto

`pacientes` y `notas_sesion` son tablas distintas, y esa separación es el
producto.

Si identificación y contenido estuvieran en la misma tabla, no se podría dar
acceso escalonado: habría que dar acceso a la fila entera. Separadas, un
administrativo entra a `pacientes` y no puede ni acercarse a `notas_sesion`.

Lo mismo pasa con `derivaciones`: el administrativo ve **que hay** una
derivación, cuándo, de qué tipo y si exige contacto el mismo día. El **motivo**
no lo ve, porque el motivo es la razón clínica por la que se decidió derivar.

## Las tablas con reglas que no dependen del código

### `notas_sesion`

Una nota por cita, con restricción de unicidad sobre `cita_id`. "La nota de esa
sesión" es una sola cosa.

`estado` va de `abierta` a `cerrada` y no tiene vuelta atrás. `version` sube
con cada corrección.

**El contenido está en `$hidden` en el modelo.** Eso no es protección: quien
tenga permiso lo ve. Lo que hace es que un `toArray()` descuidado no arrastre
el contenido de todas las notas de un listado a un registro del sistema.

### `revisiones_nota`

Solo crece. No hay ruta que la modifique ni que la borre, y hay disparadores
que rechazan las dos cosas.

`contenido_anterior` **duplica** el dato sensible dentro de la misma tabla
sensible. Es a propósito: sin el texto anterior, una corrección sería
indistinguible de una edición en silencio.

### `instrumentos`

El lugar donde la aplicación se niega a inventar.

| Columna | Por omisión | Por qué |
|---|---|---|
| `puntaje_corte` | `null` | Un corte sin fuente es un dato clínico inventado |
| `corte_verificado` | `false` | Verificar la hace el consultorio, no el software |
| `fuente_del_corte` | `null` | Sin fuente no hay verificación |
| `referencia_verificacion` | `null` | Una fuente sin referencia es un nombre, no un respaldo |
| `puntaje_corte_pendiente` | `null` | Un número conocido y todavía sin respaldo, que **no se usa para comparar** |
| `puntaje_maximo` | `null` | El sistema no conoce el rango de ningún instrumento |

Ver [06](06-SEGURIDAD-Y-LEY-19628.md).

### `derivaciones`

Sin estado "anulada". Sin bandera de borrado. Sin ruta de borrado.

Dos disparadores: uno de fila para el `DELETE` y otro de evento para el
`TRUNCATE`, porque un `TRUNCATE` no dispara los de fila.

### `accesos`

Sin columna `updated_at`. Un registro de acceso que se pudiera editar dejaría
de ser un registro.

Guarda `recurso` y `recurso_id`, no un volcado de la respuesta. Duplicar el
contenido de una sesión en el registro de auditoría sería la peor decisión
posible: sería la misma cosa que esta aplicación existe para evitar.

## Las restricciones que no son de seguridad

| Restricción | Qué hace |
|---|---|
| `citas_sin_solapamiento` | `EXCLUDE USING gist`: dos citas del mismo profesional no se pisan en el tiempo |
| `usuarios_tenant_rut_unico` | Un paciente no puede tener dos RUT en el mismo consultorio |
| `instrumentos_tenant_codigo_unico` | Un código de instrumento no se repite |
| `tipos_atencion_tenant_codigo_unico` | Un tipo de atención no se repite |
| `resumenes_auditoria_unico` | Permite volver a consolidar sin duplicar |
| `notas_sesion_cita_unica` | Una nota por sesión |

La de solapamiento merece una nota: se comprueba también en
`ServicioAgenda`, pero para dar un mensaje que se entienda. La restricción es
la que impide de verdad, porque se aplica aunque nadie pase por el servicio.

## Las columnas que no existen, y por qué

| No existe | Por qué |
|---|---|
| `password` en `usuarios` | La aplicación no tiene contraseñas. El ingreso es con el proveedor de identidad |
| `nivel_riesgo` en `derivaciones` | Sería un criterio clínico que esta aplicación no puede verificar. Ver [06](06-SEGURIDAD-Y-LEY-19628.md) |
| `fecha_vencimiento` en `consentimientos` | Cuándo deja de valer un consentimiento es una decisión jurídica del consultorio |
| `fecha_eliminacion` en cualquier tabla | No hay eliminaciones. Se desactivan, se anulan, se cierran |
| `plazo_conservacion` | El plazo de retención **no se escribe con un número inventado**. Ver [06](06-SEGURIDAD-Y-LEY-19628.md) |
| `resultado_calculado` en `aplicaciones_instrumento` | Calcularlo requiere la clave de corrección del instrumento, que es parte del manual publicado |

## Sobre el RUT

No se valida el dígito verificador en ninguna parte. Hacerlo bien requiere
revisar la norma vigente, y no se verificó en el entorno donde se escribió esta
aplicación. Ver [10](10-PRUEBAS-Y-ESTADO.md).

Un RUT mal escrito no rompe el sistema: es un dato de contacto que el
consultorio puede corregir desde la ficha.

## Sobre el `sexo` y la `previsión`

Ambos son texto libre.

`sexo` es el que declara el consultorio en su ficha de ingreso; esta
aplicación no conoce la lista de valores que usa cada ficha y no la va a
imponer.

`prevision` es texto porque la lista con que el consultorio trabaje cambia,
y porque el dato que importa es el plan, que lo da la persona.