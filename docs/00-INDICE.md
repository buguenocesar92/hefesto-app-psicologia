# 00 — Índice

Los once documentos de esta aplicación, en el orden en que conviene leerlos.

---

## Para quien va a desplegar

1. [04 — Instalación](04-INSTALACION.md)
2. [05 — Configuración](05-CONFIGURACION.md)
3. [08 — Respaldos](08-RESPALDOS.md)
4. [10 — Pruebas y estado](10-PRUEBAS-Y-ESTADO.md)

## Para quien va a entender el sistema

1. [01 — Arquitectura](01-ARQUITECTURA.md)
2. [02 — Modelo de datos](02-MODELO-DE-DATOS.md)
3. [03 — Multi-tenancy y roles](03-MULTITENANCY-Y-ROLES.md)
4. [09 — API](09-API.md)

## Para quien tiene que responder por los datos

1. [06 — Seguridad y Ley 19.628](06-SEGURIDAD-Y-LEY-19628.md)
2. [07 — Primera sesión](07-PRIMERA-SESION.md)

---

## Qué documento resuelve qué duda

| Duda | Documento |
|---|---|
| ¿Por qué el administrativo no ve las notas? | [03](03-MULTITENANCY-Y-ROLES.md) |
| ¿Por qué devuelve 404 y no 403? | [03](03-MULTITENANCY-Y-ROLES.md) |
| ¿Dónde están los puntajes de corte? | [06](06-SEGURIDAD-Y-LEY-19628.md) |
| ¿Cuánto tiempo se guardan los datos? | [06](06-SEGURIDAD-Y-LEY-19628.md) |
| ¿Cómo se borra una derivación? | No se puede. Ver [06](06-SEGURIDAD-Y-LEY-19628.md) |
| ¿El respaldo está cifrado? | [08](08-RESPALDOS.md) |
| ¿Ya se probó algo? | [10](10-PRUEBAS-Y-ESTADO.md). Respuesta: no |
| ¿Cómo se agrega un instrumento? | [06](06-SEGURIDAD-Y-LEY-19628.md) y [09](09-API.md) |
| ¿Qué pasa si se cae la base? | [08](08-RESPALDOS.md) |

---

## Documentos

### 00 — Índice

Este documento.

### 01 — Arquitectura

Las dos aplicaciones, el proxy, la red y por qué están separadas. Qué corre
dónde y qué habla con qué.

### 02 — Modelo de datos

Las dieciocho tablas, qué protege cada una y cuáles son sensibles. Incluye el
diagrama de las relaciones entre las que importan.

### 03 — Multi-tenancy y roles

El patrón de aislamiento entre consultorios, los tres roles, y por qué el
rechazo se responde con 404 y no con 403.

### 04 — Instalación

Cómo se levanta de cero, en desarrollo y en producción. Lo que hay que tener
antes de empezar.

### 05 — Configuración

Cada variable de entorno, qué hace y qué pasa si falta. Incluye la lista de
lo que está vacío a propósito y hay que decidir.

### 06 — Seguridad y Ley 19.628

El documento más importante de esta aplicación. Consentimiento, acceso
escalonado, auditoría de quién abrió qué, conservación de datos y las
decisiones que quedan pendientes del cliente.

### 07 — Primera sesión

Del arranque al primer paciente atendido, con los comandos en el orden en que
se necesitan.

### 08 — Respaldos

Cómo se respalda, cómo se restaura y las reglas que no se pueden saltarse.
Incluye lo que está sin resolver sobre el cifrado.

### 09 — API

Las rutas, qué devuelven y qué código devuelven cuando no hay permiso.

### 10 — Pruebas y estado

Qué se ejecutó y qué no. **Nada se ejecutó.** Ver ese documento antes que
ninguno otro si va a tomar una decisión sobre esta aplicación.