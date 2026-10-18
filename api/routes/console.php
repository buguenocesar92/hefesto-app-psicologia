<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Rutas de consola
|------------------------------------------------------------------------------
|
| Los comandos de Psicología se descubren automáticamente desde
| app/Console/Commands. Se dejan declarados aquí para que quede explícito qué
| hay y qué no.
|
| EL QUE HAY QUE CORRER ANTES DE RECIBIR AL PRIMER PACIENTE
| --------------------------------------------------------
| `psicologia:verificar-aislamiento` comprueba, desde el servidor y sin pasar por
| la aplicación, que las dieciséis tablas de negocio tienen RLS forzado y que
| una consulta sin consultorio falla. Una instalación en la que este comando
| no se ha corrido no está verificada. Ver docs/03 y docs/04.
|
| LOS QUE SÍ IMPORTA CORRER
| --------------------------
| `psicologia:verificar-cortes` lista los instrumentos cuyo puntaje de corte
| sigue en `null` o sin verificar. La lista es el recordatorio de que esa
| decisión clínica es del consultorio y no del software. Ver docs/06.
|
| LO QUE NO HAY
| -------------
| No hay ninguna tarea programada que borre historia clínica. La configuración
| contempla plazos de conservación, y por omisión están en `null`: sin plazo
| configurado no hay purga. Ver docs/06 y docs/08.
|
*/