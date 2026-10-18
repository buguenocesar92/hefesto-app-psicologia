# Psicología

Aplicación de Hefesto Studio para **consultorios de psicología y salud mental**
en Chile.

Software original del estudio. No es una herramienta de terceros ni un
proyecto=white-label: el modelo de datos, los permisos, las reglas de la base
y la documentación son propios.

---

## Qué resuelve, y qué la hace distinta

Una clínica de salud general maneja historia clínica. Esto maneja **salud
mental**, y el problema técnico es el **secreto profesional**, que es más
estricto que el de una ficha clínica común.

Lo que la aplicación garantiza:

| Regla | Cómo se garantiza |
|---|---|
| Un administrativo **no** puede leer el contenido de una sesión | No es un permiso Hidden: es una `Policy` que responde **404**, y un motivo que dice por qué |
| Un profesional ve **sus** pacientes, no los del consultorio | La consulta lleva el filtro del profesional escrito, y la base filtra por `tenant_id` |
| Una nota cerrada **no** se edita | Un disparador de PostgreSQL lo rechaza, aunque alguien escriba por consola |
| Lo que se corrigió **queda escrito** | `revisiones_nota` guarda el texto anterior, el nuevo y el motivo |
| Los puntajes de corte **no se inventan** | Nacen en `null`, y la base rechaza declararlos verificados sin fuente |
| Una derivación por crisis **no se borra** | Sin ruta de borrado, disparador en la base, y sin permiso de `TRUNCATE` |
| Toda apertura de una sesión **queda anotada** | Bitácora en conexión aparte, para que sobreviva al rechazo |

Lo que **no** se hizo, y por qué, está en cada documento. Lo más importante:
**nada se ejecutó** (ver `docs/10-PRUEBAS-Y-ESTADO.md`).

---

## Índice

| Documento | De qué trata |
|---|---|
| [00](docs/00-INDICE.md) | Índice y orden de lectura |
| [01](docs/01-ARQUITECTURA.md) | Las dos aplicaciones, el proxy y por qué |
| [02](docs/02-MODELO-DE-DATOS.md) | Las 18 tablas y lo que cada una protege |
| [03](docs/03-MULTITENANCY-Y-ROLES.md) | Aislamiento, roles escalonados y el 404 |
| [04](docs/04-INSTALACION.md) | Cómo se levanta, de cero |
| [05](docs/05-CONFIGURACION.md) | Cada variable y qué pasa si falta |
| [06](docs/06-SEGURIDAD-Y-LEY-19628.md) | **El documento más importante.** Ley 19.628, consentimiento, auditoría, retención |
| [07](docs/07-PRIMERA-SESION.md) | Del arranque al primer paciente atendido |
| [08](docs/08-RESPALDOS.md) | Respaldo, restauración y cifrado |
| [09](docs/09-API.md) | Las rutas y qué devuelve cada una |
| [10](docs/10-PRUEBAS-Y-ESTADO.md) | **La verdad sobre lo que se ejecutó** |

---

## Estructura

```
apps/psicologia/
  api/          API de Laravel
  web/          Interfaz de Nuxt
  deploy/       Docker, proxy, respaldos
  docs/         Documentación
```

## Para empezar

1. [docs/04-INSTALACION.md](docs/04-INSTALACION.md) para levantarla.
2. [docs/07-PRIMERA-SESION.md](docs/07-PRIMERA-SESION.md) para el primer arranque
   real.
3. [docs/06-SEGURIDAD-Y-LEY-19628.md](docs/06-SEGURIDAD-Y-LEY-19628.md) **antes**
   de recibir un paciente. Está lleno de decisiones que son del cliente, y es
   más fácil tomarlas sin datos adentro.

## Licencia

Propiedad de Hefesto Studio.