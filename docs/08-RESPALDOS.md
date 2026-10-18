# 08 — Respaldos

Un respaldo de historia clínica de salud mental es lo más valioso que tiene un
consultorio, y también lo más difícil de recuperar si sale mal.

Este documento tiene tres partes: las reglas que no se pueden saltarse, los
scripts, y **lo que está sin resolver sobre el cifrado**.

---

## Las reglas, y por qué están

Cada una existe porque algo salió mal en un proyecto anterior.

### 1. Manifiesto con SHA-256 de cada archivo

Para saber que un respaldo sirve, hay que poder comprobar que ningún archivo se
cambió.

Sin el manifiesto, "se respaldó" no significa nada: uno tiene el archivo y una
lista al lado, y no hay forma de saber si corresponden.

`respaldo.sh` escribe un manifiesto con la suma del volcado, la fecha, la base y
si está cifrado.

### 2. Marcar el respaldo como completo SOLO al final

El respaldo se escribe en el orden:

1. Se vuelca la base a un archivo temporal.
2. Se verifica que el volcado se puede leer.
3. Se escribe el manifiesto con la suma.
4. **Y recién ahí** se escribe el archivo `COMPLETO`.

Un respaldo sin `COMPLETO` es un respaldo incompleto, y `restauracion.sh` lo
rechaza. Un volcado interrumpido deja un temporal con punto y comilla adelante,
no un archivo con nombre de bueno.

### 3. Validar rutas y espacio antes de escribir

Antes de copiar nada, el script comprueba:

- Que la ruta sea **absoluta**. Un respaldo en un directorio relativo depende de
  dónde se esté parado.
- Que esté en una carpeta pensada para respaldos (`/var/backups/...`,
  `/mnt/...`, `/srv/...`, `/home/...`, `/opt/...`). Un borrado accidental del
  respaldo no debería llevarse el sistema entero.
- Que haya espacio. Por omisión, al menos 512 MB libres, configurable con
  `PSICOLOGIA_ESPACIO_MINIMO_MB`.

Un respaldo a medias porque el disco se llenó es peor que no tener respaldo:
se pierde el tiempo y se cree que hay copia.

### 4. Rechazar destino ocupado, salvo `--force`

Un respaldo que sobrescribe a otro es un respaldo que borra la única copia que
quedaba.

Sin `--force`, el script se niega y dice por qué. Con `--force`, avisa qué va a
reemplazar antes de hacerlo, y borra solo un directorio cuyo nombre empieza con
`psicologia-` dentro de la carpeta de respaldos.

### 5. NUNCA DROP. NUNCA sobrescritura automática. `TRUNCATE` si hace falta

Esta regla es de la **restauración**, y es la razón por la que existe el
script aparte.

`DROP TABLE` se lleva la tabla con sus índices, sus restricciones, sus
**disparadores** y sus **políticas de Row Level Security**.

Una base restaurada con `DROP` queda sin el aislamiento puesto. Y eso no se
nota hasta que alguien lee una nota que no le correspondía.

`TRUNCATE` vacía la tabla y la deja existir: los disparadores y las políticas
siguen ahí. Por eso es la única operación permitida.

Y restaurar sobre una base con datos requiere `--truncate` explícito. Un
script que pisa lo que hay sin que nadie lo pida es un script que un día borra
historia clínica.

### 6. El respaldo no debe quedar legible para quien tenga el disco

Ver la sección siguiente, porque **esto está sin resolver**.

---

## El cifrado: lo que está y lo que no

### Qué hace el script

| `PSICOLOGIA_CIFRADO_CLAVE` | Qué pasa |
|---|---|
| Definida | El volcado va cifrado con `openssl enc -aes-256-cbc -pbkdf2`, y el manifiesto también |
| Vacía | El volcado queda **en claro**, y el manifiesto dice `cifrado: no` |

Con la clave definida, al terminar:

```bash
[respaldo] AVISO IMPORTANTE
[respaldo] Este respaldo NO está cifrado.
```

### Lo que NO está resuelto, y hay que saberlo

**La clave se pasa por una variable de entorno, no por un archivo.**

Eso significa que:

- La clave tiene que estar en el entorno del proceso que corre el respaldo: el
  `cron`, el `systemd`, o la sesión de shell.
- **No hay un almacén de claves.** Si se pierde, los respaldos cifrados **no se
  pueden recuperar**.
- Si se filtra, cualquier persona con acceso de lectura al disco abre los
  respaldos.
- En un despliegue con varios respaldos, todos usan la misma clave.

Esa última es la debilidad más seria: **no hay rotación de claves**, y por lo
tanto no hay forma de rotar una clave filtrada sin dejar atrás todos los
respaldos viejos.

### Qué haría falta para resolverlo

Ninguna de estas está implementada, y ninguna se implementó a medias:

1. **Rotación de claves.** Cada respaldo con su clave, y la clave registrada en
   algún lado. Es un problema de gestión de secretos, no de codificación.
2. **Un almacén de claves** (una bóveda, un KMS, un gestor de secretos), en vez
   de una variable de entorno.
3. **Cifrado con clave pública**, que resuelve la rotación: se cifra con la
   clave pública del consultorio y solo el dueño de la privada puede abrirlo.
4. **Integración con el respaldo del sistema de archivos**, si el disco ya está
   cifrado con LUKS. En ese caso el respaldo hereda la protección y la clave no
   tiene que estar en el despliegue.

### Qué se decidió

Implementar la opción de clave por variable de entorno, **avisar claramente
cuando no hay**, y dejar el resto anotado como pendiente.

La razón de no inventar un almacén de claves: sería una integración con algo
que no existe en la instalación, y un respaldo que no funciona porque el
almacén no está configurado es peor que un respaldo en claro que por lo menos
avisa.

**Mientras tanto, la respuesta honesta es esta:** si no define
`PSICOLOGIA_CIFRADO_CLAVE`, quien tenga acceso de lectura a
`/var/backups/psicologia` puede abrir las notas de sesión.

---

## Cómo se respalda

### El comando

```bash
./deploy/respaldos/respaldo.sh --salida /var/backups/psicologia
```

Con etiqueta:

```bash
./deploy/respaldos/respaldo.sh --salida /var/backups/psicologia \
    --etiqueta antes-de-migrar
```

Reemplazando, a mano:

```bash
./deploy/respaldos/respaldo.sh --salida /var/backups/psicologia --force
```

### Lo que produce

```
/var/backups/psicologia/psicologia-20261018-023000/
  psicologia.dump              (o .cifrado)
  manifiesto.sha256            (o .cifrado)
  COMPLETO
```

El directorio queda con nombre `psicologia-<fecha>-<etiqueta>`, y `COMPLETO`
solo aparece al final.

### Variables que necesita

| Variable | Para qué |
|---|---|
| `DB_DATABASE`, `DB_USERNAME` | Para el volcado |
| `PSICOLOGIA_COMPOSE` | Para encontrar los contenedores. Por omisión `/opt/psicologia/deploy/docker-compose.prod.yml` |
| `PSICOLOGIA_CIFRADO_CLAVE` | Para cifrar. **Vacía = en claro** |
| `PSICOLOGIA_ESPACIO_MINIMO_MB` | Espacio libre mínimo |

---

## Cómo se restaura

### El comando

```bash
./deploy/respaldos/restauracion.sh \
    --respaldo /var/backups/psicologia/psicologia-20261018-023000 \
    --truncate
```

Con respaldo cifrado, además:

```bash
export PSICOLOGIA_CIFRADO_CLAVE='...'

./deploy/respaldos/restauracion.sh \
    --respaldo /var/backups/psicologia/psicologia-20261018-023000 \
    --truncate --clave-de-cifrado
```

### Lo que hace, en orden

1. **Exige el archivo `COMPLETO`.** Sin él, se niega. Un respaldo a medias no
   se restaura.
2. **Verifica el SHA-256.** Si el archivo cambió desde que se hizo, se niega.
3. Encuentra el contenedor de la base.
4. **Respalda el estado actual**, por si la restauración sale mal.
5. **Detiene la API**, para que nadie entre mientras restaura.
6. **Vacia las tablas con `TRUNCATE`**, nunca con `DROP`.
7. Restaura.
8. **Comprueba que el aislamiento sigue puesto.**
9. **Deja el servicio detenido.**

### Por qué exige `--truncate`

Restaurar encima de datos que ya están descarta esos datos.

El script no lo hace por omisión. Si no se pasa `--truncate`, se niega y
explica por qué:

```
Se necesita --truncate para restaurar.

El vaciado se hace con TRUNCATE y NUNCA con DROP:

    DROP TABLE se lleva la tabla con sus índices, sus
    restricciones, sus disparadores y sus políticas de Row
    Level Security. Una base restaurada con DROP queda sin el
    aislamiento puesto, y eso no se nota hasta que alguien lee
    una nota que no le correspondía.

    TRUNCATE vacía la tabla y la deja existir: los
    disparadores y las políticas siguen ahí.
```

### Por qué deja el servicio detenido

Una API corriendo mientras se restaura es una API que responde con datos a
medio restaurar.

Al terminar, el script **no la levanta**. Hay que hacerlo a mano, después de
comprobar:

```bash
docker compose -f /opt/psicologia/deploy/docker-compose.prod.yml run --rm \
    api php artisan psicologia:verificar-aislamiento

docker compose -f /opt/psicologia/deploy/docker-compose.prod.yml up -d
```

### La comprobación del paso 8

Después de restaurar, el script cuenta las tablas sin Row Level Security.

**Más de una** significa que la base no quedó protegida: el script falla y avisa
que no se toque nada más.

La única que puede estar sin RLS es `consultorios`, que se consulta en el
ingreso. Ver [03](03-MULTITENANCY-Y-ROLES.md).

---

## Programación

```cron
0 3 * * * /opt/psicologia/deploy/respaldos/respaldo.sh --salida /var/backups/psicologia
```

Cada noche a las 3.

Y hay que verificar que el respaldo terminó bien:

```bash
ls -la /var/backups/psicologia/
```

Un respaldo sin `COMPLETO` es un respaldo que no se terminó. Y uno de ayer que
no está es un respaldo que dejó de correr, que es peor que uno que falla con
ruido.

### La rotación

Los respaldos viejos no se borran solos. Eso es a propósito: el script nunca
borra nada sin que alguien lo pida.

La rotación es del despliegue, y debería acompañarse de una copia **fuera** de
la máquina. Un respaldo en el mismo disco que protege no es un respaldo: es
una copia que se pierde con el disco.

---

## Qué verificar antes de producción

1. Hay un respaldo hecho.
2. **Se restauró en un entorno de pruebas** y la base quedó bien.
3. La carpeta de respaldos está cifrada, o hay una decisión escrita de por qué
   no lo está.
4. La clave de cifrado está a salvo y en más de un lado.
5. Hay una copia fuera de la máquina.
6. El `cron` corre y se verifica que el respaldo terminó.

El punto 2 es el que más se saltea, y es el que más duele: una copia de la que
nunca se restauró no es un respaldo, es un archivo con una lista al lado.

---

## Resumen de lo que no se resolvió

| Pendiente | Por qué |
|---|---|
| Rotación de claves | Requiere un almacén de claves que no existe en la instalación |
| Llaves públicas | Sería la solución correcta, requiere una infraestructura que no se implementó |
| Verificación automática de la restauración | Requeriría un entorno de pruebas con datos de mentira |
| Comprobación de integridad periódica | Se puede hacer con `sha256sum -c`, pero no se programó |
| Copia fuera de la máquina | Es del despliegue, no del software |

Ninguna de estas se implementó a medias. Ver [10](10-PRUEBAS-Y-ESTADO.md).