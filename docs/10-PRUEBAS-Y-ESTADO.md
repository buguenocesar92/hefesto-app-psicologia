# 10 — Pruebas y estado

Este es el documento mas importante de la aplicación. Si va a tomar una
decisión sobre este sistema y no leyo este archivo, todavia no sabe lo que
tiene entre manos.

---

## La respuesta corta

**Nada se ejecuto.**

```
Migraciones aplicadas      0
Pruebas corridas           0
Imagenes de Docker         0
Rutas ejercitadas          0
Contenedores levantados    0
```

No hay una excepcion. Ni una migración, ni una prueba, ni una imagen, ni una
peticion a la API.

---

## Por que

En la maquina donde se escribio esta aplicación **no estan instalados PHP,
Composer, Node, Docker ni PostgreSQL**.

Eso no es un problema del entorno que se resolva esperando. Es el motivo por el
que los números de arriba son cero, y hay que leerlos como una afirmacion y no
como una modesty.

Lo que si se ejecuto, y conviene decirlo con la misma claridad, fueron dos
programas de Python que revisan **archivos**, no la aplicación:

| Herramienta | Que revisa | Que NO revisa |
|---|---|---|
| `tools/acentuar.py` | La ortografia de los archivos de texto | Si el código funciona |
| `tools/validar_apps.py` | Sintaxis del YAML, sintaxis de los scripts de shell, caracteres admitidos, secretos, presencia de `tenant_id` y de Row Level Security, ortografia, puertos publicados | Si el código compila, si arranca, si la base responde |

Los dos dieron `0 errores`, y eso esta bien, pero **ninguno de los dos ejecuta una
sola linea de esta aplicación**. Un archivo puede pasar los dos validadores y
estar roto de varias formas: un `use` mal escrito, un metodo que ya no existe, un
nombre de columna que no esta en la migración.

---

## Lo que esta escrito y nunca se ha corrido

Hay siete archivos de pruebas con 68 metodos. Están escritos, se leyeron, y
**no se ejecutaron ni una vez**.

| Archivo | Metodos | Que pretende comprobar |
|---|---|---|
| `tests/Unit/TransicionesDeEstadoTest.php` | 14 | Que las transiciones de cita, nota y derivacion sean las que deben |
| `tests/Feature/Aislamiento/AislamientoEntreConsultoriosTest.php` | 5 | Que un consultorio no vea, ni lea, ni escriba filas de otro |
| `tests/Feature/Aislamiento/VistaPublicaSeUsaTest.php` | 6 | Que la vista pública exista, exponga tres columnas y la use el ingreso |
| `tests/Feature/Permisos/AdministrativoNoLeeNotaDeSesionTest.php` | 10 | Que el administrativo no abra el contenido de una sesión y que reciba 404 |
| `tests/Feature/Permisos/NotaCerradaNoSeEditaTest.php` | 11 | Que una nota cerrada se bloquee, se corrija con motivo y no se borre |
| `tests/Feature/Permisos/DerivacionNoSeBorraTest.php` | 12 | Que una derivacion no se pueda borrar ni truncar desde la base |
| `tests/Feature/Permisos/InstrumentoCorteSinFuenteNoSeVerificaTest.php` | 10 | Que un corte sin fuente no se verifique y que el pendiente no compare |

Escritas estan bien. **Ejecutadas no estan.** Un metodo de prueba que nunca se
corrio no es una prueba: es una afirmacion sobre el código, escrita en un
lenguaje que nadie ejecuto.

Lo que algunas de estas pruebas comprueban, y que conviene tener presente
mientras no se corran:

- Que la fila de la base rechaza el borrado de una derivacion, y no solo la
  aplicación. Eso prueba el disparador, no el controlador.
- Que el intento denegado queda anotado en la bitacora. Eso prueba que el
  rechazo es registro, no simplemente una puerta cerrada.
- Que el listado de notas trae solo las del profesional que pregunta. Eso
  prueba que el filtro esta en la consulta.
- Que un corte pendiente **no** se usa para comparar. Eso prueba que el sistema
  no inventa el número que no tiene.

---

## Pendientes de verificación

Estas cosas **no** estan verificadas. Cada una con su motivo.

### 1. Las versiones de las dependencias

`composer.json` declara PHP `^8.3`, Laravel `^13.37`, `predis` `^3.7`,
`firebase/php-jwt` `^7.2`, PHPUnit `^13.0.3` y Faker `^1.24`.

Se heredaron de las aplicaciones de referencia del taller. **No se comprobaron
contra el registro de paquetes.** Sin PHP ni Composer en la maquina no se pudo
ni resolver el arbol de dependencias.

Por que importa: si una de esas versiones no existe, o no es compatible con las
demas, `composer install` falla y la aplicación no arranca. Es lo primero que
hay que revisar en una maquina con PHP.

### 2. Que el nucleo de PostgreSQL soporte lo que se le pide

La aplicación depende de:

- Row Level Security con `FORCE ROW LEVEL SECURITY`
- Políticas con `USING` y `WITH CHECK`
- `set_config` con alcance de transacción
- `EXCLUDE USING gist` para que las citas no se pisen
- Disparadores de fila y de evento

**Ninguno de esos se probo contra un servidor.** Se escribieron siguiendo la
documentacion de PostgreSQL, pero la documentacion y un servidor real no son lo
mismo.

Por que importa: el `EXCLUDE USING gist` sobre las citas y los disparadores de
correccion de notas son lo mas delicado de escribir sin poder probar. Si algo
falla, falla al migrar, que es el peor momento para descubrirlo.

### 3. El middleware `ResolverTenant` no esta ejercitado

Las pruebas fijan el contexto del consultorio **a mano**, con un metodo de
ayuda. Ninguna prueba pasa por el middleware real.

Por que importa: `ResolverTenant` es el que abre la transacción, resuelve el
usuario y fija el consultorio en las dos conexiones. Es el primer eslabon de
todo el aislamiento, y es justamente el que las pruebas se saltan. Si tiene un
error, **todas** las pruebas de aislamiento pasarían igual, porque no pasan por
ahi.

Este es el pendiente mas serio de la lista. Lo primero que hay que escribir
despues es una prueba que entre por HTTP con una sesión real y compruebe que el
contexto queda fijado.

### 4. Los plazos de conservacion

En la configuración, los plazos estan en `null` y **no hay purga
automática**.

Por que esta decisión: el plazo legal de conservacion de datos sensibles de
salud en Chile depende de la normativa vigente y de la respuesta que de el
servicio juridico del consultorio. Poner un número sin fuente seria inventar un
plazo legal, y un plazo legal inventado es un error con consecuencias.

Por que sigue siendo un pendiente: mientras el plazo sea `null`, los datos se
conservan para siempre. Eso puede estar bien o estar mal según la situacion del
consultorio, y **es una decisión del cliente, no del software**. Queda anotada
en [05](05-CONFIGURACION.md) y en [06](06-SEGURIDAD-Y-LEY-19628.md).

### 5. La emision de boletas electronicas

La aplicación **no emite boletas electronicas**. Guarda el número y el folio que
emitio el sistema que use el consultorio.

Por que: los requisitos de emision electrónica en Chile cambian, y dependen de a
quien se le vende. Integrarlo sin saber el caso real seria adivinar.

Por que sigue siendo un pendiente: si el consultorio factura, hoy tiene que
duplicar el registro. Hay que decidir si se integra con el sistema que ya usa, o
si se deja como esta.

### 6. El digito verificador del RUT

La aplicación **no valida** el digito verificador del RUT.

Por que: se puede agregar, y es un calculo corto, pero no se escribio porque no
se probo. Un RUT mal escrito se acepta y queda guardado.

Por que importa mas de lo que parece: el RUT es la referencia que une una ficha
con una persona real. Un digito verificador que no se revisa deja pasar
identidades que no existen.

### 7. El cifrado de los respaldos

El respaldo se cifra con `openssl` cuando hay clave en el entorno. **Sin clave,
el respaldo queda en claro y el script lo avisa.**

Lo que no esta resuelto: la rotacion de claves, donde se guarda la llave, y si
la llave pública del consultorio se puede compartir con quien administrate los
respaldos. Ver [08](08-RESPALDOS.md).

Por que importa: un respaldo sin cifrar, en un disco al que se tenga acceso,
es una copia de todas las fichas clinicas en texto plano.

### 8. El ingreso por el proveedor de identidad

El ingreso reenvia a Authentik, valida el token firmado, y lee el rol de los
equipos de ese proveedor.

**No se probo contra un Authentik real.** No se verifico la forma exacta del
token, ni los nombres de los equipos, ni el comportamiento cuando el proveedor
devuelve algo que no se espera.

Por que importa: es la puerta de entrada. Si falla, nadie entra; si acepta de
mas, entra quien no deberia.

---

## Que falta para poder decir que esta verificada

En este orden, porque cada paso depende del anterior:

1. **Una maquina con PHP, Composer, Node, Docker y PostgreSQL.**
2. Resolver el arbol de dependencias y **corregir las versiones** del punto 1 de
   la lista de arriba, si alguna no existe.
3. Levantar PostgreSQL y correr las migraciones. Aca es donde se descubre si el
   `EXCLUDE USING gist` y los disparadores funcionan de verdad.
4. Correr `composer test`. Los 68 metodos. Anotar cuantos pasan y cuantos no.
5. **No aceptar los fallos**: arreglarlos y volver a correr.
6. Escribir la prueba que falta, la que entra por HTTP con sesión real y
   comprueba que `ResolverTenant` fija el contexto.
7. Levantar el Docker Compose de desarrollo y hacer el recorrido completo de
   [07](07-PRIMERA-SESION.md): entrar, cargar un paciente, agendar, atender,
   escribir la nota, corregirla, derivar, cobrar.
8. Recien ahi, levantar producción y hacer el recorrido otra vez, con el proxy y
   los certificados de por medio.
9. Probar el respaldo **y la restauración**, en una base de prueba, y medir
   cuanto tarda.

Hasta que los nueve esten hechos, este documento sigue diciendo lo que dice:
cero de todo.

---

## Lo que no falta

Para que no se lea este documento como una lista de problemas sin fin, esto si
esta hecho:

- La estructura de la aplicación esta completa: no hay archivos a medio escribir
  ni marcadores de tarea.
- Los once documentos existen y se referencian entre si.
- Los dos validadores del taller pasan sin errores.
- No hay ningun dato clinico inventado: ningun puntaje de corte, ningun plazo
  legal, ningun precio, ninguna versión de manual.
- Las reglas que no se pueden saltar estan puestas **dos veces**: una en el
  código, que explica con mensajes, y otra en la base, que impide. Las dos
  hacen falta, porque una se rompe con un refactor y la otra no.

---

## Los dos roles de PostgreSQL: escritos, no ejecutados

La separación entre el rol de arranque (`psicologia_admin`) y el rol de la
aplicación (`psicologia`) está escrita en el `compose`, en
`deploy/postgres/inicializar-rol.sh` y en
[03-MULTITENANCY-Y-ROLES.md](03-MULTITENANCY-Y-ROLES.md). **No se ha ejecutado
ninguna vez.**

En el equipo donde se escribió este cambio no había PostgreSQL ni Docker. Queda
pendiente:

1. Levantar el `compose` con el volumen de PostgreSQL vacío y confirmar que el
   rol `psicologia` se crea sin error.
2. Confirmar con `SELECT rolname, rolsuper, rolbypassrls FROM pg_roles` que el
   rol de la conexión no es superusuario ni salta la RLS. Este paso **no está
   automatizado** en el comando verificador de esta aplicación, hay que hacerlo a
   mano.
3. Correr `php artisan psicologia:verificar-aislamiento` y confirmar que sale
   con código 0.
4. Correrlo **con datos dentro**. El comando detecta un rol equivocado por
   efecto colateral: si el conteo sin consultorio devuelve cero porque la tabla
   está vacía, no detecta nada. Contra una base recién instalada pasaría.
5. Probar el escenario que motiva todo el cambio: un consultorio intentando leer
   las notas de sesión de otro.

Lo único verificado es estático: `bash -n` pasa sobre el script y el `compose`
es YAML válido, ambas cosas comprobadas por `tools/validar_apps.py`. El
razonamiento se apoya en la documentación oficial de PostgreSQL 18, no en una
ejecución.