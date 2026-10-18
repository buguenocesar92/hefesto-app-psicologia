/**
 * Los tipos que viajan entre la interfaz y la API.
 *
 * POR QUE ESTOS TIPOS ESTAN ESCRITOS A MANO
 * -----------------------------------------
 * Porque la API no publica un esquema OpenAPI, y no hay nada de dónde
 * derivarlos automáticamente. Escribirlos a mano obliga a que alguien mire la
 * respuesta real de la API cuando cambie, en vez de que un tipo generado quede
 * viejo y nadie lo note.
 *
 * LA PARTE IMPORTANTE: `PerfilUsuario`
 * -----------------------------------
 * `ve_notas` y `acceso_al_contenido` vienen en la respuesta de
 * `GET /api/v1/auth/yo`. La interfaz los usa para explicarle a cada persona qué
 * ve y por qué.
 *
 * Es la respuesta que evita que un administrativo abra una ficha de un paciente
 * y encuentre un vacío sin explicación. Ver docs/03.
 */

export interface Consultorio {
    id: string
    nombre: string
    slug: string
}

/** Lo que la persona puede y no puede hacer. */
export type Rol = 'profesional' | 'administrativo' | 'administrador'

export interface PerfilUsuario {
    id: string
    nombre: string
    email: string
    rol: Rol | null
    rol_etiqueta: string | null
    es_tratante: boolean
    profesional_id: string | null
    consultorio: Consultorio | null
    /** Si esta persona puede abrir el contenido de las sesiones. */
    ve_notas: boolean
    /** La frase que explica el alcance del rol. Se muestra en la pantalla. */
    explicacion: string
    /** Por qué puede o no puede abrir el contenido de las sesiones. */
    acceso_al_contenido: string | null
}

export type EstadoCita = 'agendada' | 'realizada' | 'no_asistio' | 'cancelada' | 'reagendada'

export interface Cita {
    id: string
    estado: EstadoCita
    estado_etiqueta: string
    admite_cambio: boolean
    /** Si en esta cita debería existir una nota. No dice si existe. */
    admite_nota: boolean
    /** Si la nota ya existe. Es un sí o un no, nunca el contenido. */
    tiene_nota: boolean
    paciente: string | null
    paciente_id: string
    profesional: string | null
    profesional_id: string
    tipo_atencion: string | null
    fecha_inicio: string | null
    fecha_fin: string | null
    duracion_minutos: number
    motivo_consultacion: string | null
    nota_agenda: string | null
    reagendada_desde: string | null
}

export interface Agenda {
    fecha: string
    citas: Array<Cita & { hora: string | null, hora_fin: string | null, nota_abierta: boolean | null }>
    estados_disponibles: Array<{ valor: EstadoCita, etiqueta: string }>
}

export interface Paciente {
    id: string
    nombre_completo: string
    nombres: string
    apellidos: string
    apellido_materno: string | null
    rut: string | null
    fecha_nacimiento: string | null
    sexo: string | null
    telefono: string | null
    email: string | null
    domicilio: string | null
    prevision: string | null
    plan_prevision: string | null
    motivo_consulta: string | null
    contacto_emergencia: {
        nombre: string | null
        telefono: string | null
        parentesco: string | null
    }
    codigo_ficha: string | null
    activo: boolean
    motivo_desactivacion: string | null
}

export type EstadoNota = 'abierta' | 'cerrada'

/**
 * Una nota de sesión.
 *
 * `contenido` viene en `null` cuando la persona no tiene permiso para abrirla,
 * o cuando no hay nada escrito todavía. La interfaz no adivina cuál de las dos
 * es: usa `admite_edicion` y el estado.
 */
export interface NotaSesion {
    id: string
    estado: EstadoNota
    version: number
    contenido: string | null
    /** Si se puede guardar directamente o hay que registrar una corrección. */
    admite_edicion: boolean
    /** Qué acción ofrecer: guardar, o corregir con motivo. */
    siguiente_accion: 'editar_nota' | 'corregir_nota'
    siguiente_accion_etiqueta: string
    cerrada_en: string | null
    motivo_cierre: string | null
    tiene_correcciones: boolean
    actualizado_en: string | null
}

/** Una entrada del historial de correcciones. No trae los textos. */
export interface RevisionNota {
    id: string
    version_anterior: number
    version_nueva: number
    motivo: string
    registrado_en: string | null
}

/**
 * El estado del puntaje de corte de un instrumento.
 *
 * Los tres campos existen porque son tres situaciones distintas y la pantalla
 * tiene que distinguirlas. Un corte cargado y no verificado no es lo mismo que
 * uno verificado, y un pendiente no es lo mismo que ninguno.
 */
export interface Instrumento {
    id: string
    codigo: string
    nombre: string
    version_instrumento: string | null
    descripcion: string | null
    cantidad_items: number | null
    puntaje_maximo: number | null
    activo: boolean

    /** El corte que vale. En `null` cuando no hay, y eso no es un error. */
    puntaje_corte: string | null
    corte_verificado: boolean
    fuente_del_corte: string | null
    referencia_verificacion: string | null
    /** Un número conocido pero todavía sin respaldo. No se usa para comparar. */
    puntaje_corte_pendiente: string | null
    direccion_puntaje: string | null

    tiene_corte_verificado: boolean
    tiene_corte_pendiente: boolean
    /** La frase que explica la situación. Se muestra al lado del número. */
    estado_del_corte: string
}

export interface ComparacionConCorte {
    corte: number | null
    direccion: string | null
    resultado: string
}

export interface AplicacionInstrumento {
    id: string
    instrumento: string | null
    instrumento_id: string
    fecha_aplicacion: string | null
    puntaje: string | null
    comparacion_con_corte: ComparacionConCorte
    /** Si esta persona puede ver las respuestas y la interpretación. */
    tiene_contenido: boolean
    interpretacion: string | null
    observaciones: string | null
    respuestas: Record<string, unknown> | null
}

export type NaturalezaDerivacion = 'urgencia' | 'atencion_primaria' | 'especialidad' | 'red_salud_mental'
export type EstadoDerivacion = 'vigente' | 'atendida' | 'resuelta'

export interface Derivacion {
    id: string
    paciente: string | null
    paciente_id: string
    profesional: string | null
    naturaleza: NaturalezaDerivacion
    naturaleza_etiqueta: string
    destino: string
    estado: EstadoDerivacion
    estado_etiqueta: string
    exige_contacto_inmediato: boolean
    registrada_en: string | null
    atendida_en: string | null
    resuelta_en: string | null
    /** Si esta persona puede ver el motivo. */
    ve_motivo: boolean
    motivo: string | null
}

export interface Consentimiento {
    id: string
    tipo: string
    tipo_etiqueta: string
    version_documento: string
    modalidad: string
    constancia: string | null
    firmado_en: string | null
    anulado: boolean
    motivo_anulacion: string | null
    registrado_en: string | null
}

export interface Pago {
    id: string
    paciente: string | null
    paciente_id: string
    cita_id: string | null
    boleta_id: string | null
    monto_clp: number
    fecha_pago: string | null
    metodo: string
    pago_parcial: boolean
    monto_esperado_clp: number | null
    observacion: string | null
    anulado: boolean
    motivo_anulacion: string | null
}

export interface Boleta {
    id: string
    paciente: string | null
    paciente_id: string
    numero: string | null
    folio: string | null
    rut_emisor: string | null
    fecha_emision: string | null
    monto_total_clp: number
    detalle: unknown
    estado: string
    anulada: boolean
    motivo_anulacion: string | null
}

export interface Tarifa {
    id: string
    nombre: string
    tipo_atencion: string | null
    profesional: string | null
    /** En `null` cuando el consultorio todavía no la fijó. No es un cero. */
    precio_clp: number | null
    tiene_precio: boolean
    vigente_desde: string | null
    activo: boolean
}

export interface TipoAtencion {
    id: string
    codigo: string
    nombre: string
    descripcion: string | null
    duracion_minutos: number
    grupal: boolean
    activo: boolean
}

export interface Profesional {
    id: string
    nombre_completo: string
    nombres: string
    apellidos: string
    nombre_agenda: string
    rut: string | null
    registro_profesional: string | null
    registro_profesional_verificado: boolean
    registro_verificado_en: string | null
    especialidad: string | null
    email: string | null
    telefono: string | null
    activo: boolean
}

export interface Usuario {
    id: string
    nombre: string
    email: string
    rol: Rol | null
    rol_etiqueta: string | null
    es_tratante: boolean
    profesional: string | null
    activo: boolean
    ultimo_ingreso_en: string | null
    rol_actualizado_en: string | null
    acceso_al_contenido: string | null
}

export type ResultadoAcceso = 'permitido' | 'denegado'

export interface Acceso {
    id: string
    usuario_id: string | null
    rol: string | null
    recurso: string
    recurso_id: string | null
    accion: string
    resultado: ResultadoAcceso
    resultado_etiqueta: string
    /** Por qué se denegó, en palabras del dominio. */
    motivo: string | null
    ruta: string | null
    direccion_ip: string | null
    ocurrido_en: string | null
}

export interface ResumenAuditoria {
    fecha: string
    accion: string
    accion_etiqueta: string
    resultado: ResultadoAcceso
    total: number
    consolidado_en: string | null
}

export interface Panel {
    rol: Rol | null
    rol_etiqueta: string | null
    es_tratante: boolean
    explicacion: string
    acceso_al_contenido: string | null
    agenda: {
        citas_hoy: number
        realizadas_hoy: number
        no_asistio_hoy: number
    }
    derivaciones: {
        abiertas: number
        exigen_contacto_inmediato: number
    }
    pagos: {
        total_clp_hoy: number
    }
    /** Solo viene si la persona atiende. No es un cero: es una llave que no está. */
    notas?: {
        abiertas: number
        cerradas_por_mi: number
        con_correcciones: number
        sesiones_sin_nota: number
    }
}