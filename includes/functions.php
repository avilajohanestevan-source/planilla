<?php
/**
 * Funciones de apoyo reutilizadas en varias páginas.
 */

/** Calcula la edad actual a partir de una fecha de nacimiento (YYYY-MM-DD). */
function calcularEdad(?string $fechaNacimiento): ?int
{
    if (!$fechaNacimiento) {
        return null;
    }
    try {
        $nacimiento = new DateTime($fechaNacimiento);
        $hoy = new DateTime('today');
        return $hoy->diff($nacimiento)->y;
    } catch (Exception $e) {
        return null;
    }
}

/** Devuelve una fecha en formato dd/mm/aaaa para mostrar en pantalla. */
function formatearFecha(?string $fecha): string
{
    if (!$fecha) {
        return '—';
    }
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d ? $d->format('d/m/Y') : $fecha;
}

/**
 * Sábado "por defecto" para abrir la planilla:
 * si hoy es sábado, usa hoy; si no, usa el sábado más reciente ya pasado.
 */
function sabadoPorDefecto(): string
{
    $hoy = new DateTime('today');
    $diaSemana = (int) $hoy->format('N'); // 1=lunes ... 6=sábado, 7=domingo
    if ($diaSemana === 6) {
        return $hoy->format('Y-m-d');
    }
    $diasHastaSabadoAnterior = $diaSemana === 7 ? 1 : $diaSemana + 1;
    $hoy->modify("-{$diasHastaSabadoAnterior} days");
    return $hoy->format('Y-m-d');
}

/** ¿La fecha dada cae en sábado? */
function esSabado(string $fecha): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $fecha);
    return $d && $d->format('N') === '6';
}

/** Devuelve el emoji correspondiente a un estado de carita. */
function emojiEstado(string $estado): string
{
    return match ($estado) {
        'feliz'   => '😊',
        'triste'  => '😞',
        default   => '😐',
    };
}

/** Puntos numéricos que aporta cada estado (sin contar el extra manual). */
function puntosEstado(string $estado): int
{
    return match ($estado) {
        'feliz'  => 1,
        'triste' => -1,
        default  => 0,
    };
}

/** Limpia y devuelve texto seguro para imprimir en HTML. */
function h(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

/* ---------------------------- Cronograma: fechas en español ---------------------------- */

/** Nombre del mes en español a partir de su número (1-12). */
function nombreMes(int $mes): string
{
    $meses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];
    return $meses[$mes] ?? '';
}

/** Nombre del día de la semana en español a partir de una fecha (YYYY-MM-DD). No depende del locale del servidor. */
function nombreDiaSemanaDesdeFecha(string $fechaYmd): string
{
    $dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
    $d = DateTime::createFromFormat('Y-m-d', $fechaYmd);
    if (!$d) {
        return '';
    }
    return $dias[(int) $d->format('N')] ?? '';
}

/** Formatea una fecha como "Domingo 04 de Febrero" para el Cronograma. */
function formatearFechaBonita(string $fechaYmd): string
{
    $d = DateTime::createFromFormat('Y-m-d', $fechaYmd);
    if (!$d) {
        return $fechaYmd;
    }
    return nombreDiaSemanaDesdeFecha($fechaYmd) . ' ' . $d->format('d') . ' de ' . nombreMes((int) $d->format('n'));
}

/** Nombre del mes+año en español a partir de "YYYY-MM", para el título del cronograma. */
function nombreMesAnio(string $mesYm): string
{
    $partes = explode('-', $mesYm);
    if (count($partes) !== 2) {
        return $mesYm;
    }
    return nombreMes((int) $partes[1]) . ' ' . $partes[0];
}

/** Formatea una fecha como "06 de Septiembre" (sin nombre del día), para las filas del Cronograma agrupadas por semana. */
function formatearFechaCorta(string $fechaYmd): string
{
    $d = DateTime::createFromFormat('Y-m-d', $fechaYmd);
    if (!$d) {
        return $fechaYmd;
    }
    return $d->format('d') . ' de ' . nombreMes((int) $d->format('n'));
}

/**
 * El Cronograma se maneja por semana (domingo a sábado): a partir del domingo
 * que abre la semana (YYYY-MM-DD), devuelve el jueves de esa misma semana
 * (domingo + 4 días). Ese jueves comparte danzoras y uniforme con su domingo.
 */
function juevesDeLaSemana(string $fechaDomingoYmd): string
{
    $d = DateTime::createFromFormat('Y-m-d', $fechaDomingoYmd);
    if (!$d) {
        return $fechaDomingoYmd;
    }
    $d->modify('+4 days');
    return $d->format('Y-m-d');
}

/**
 * Título corto para identificar una semana del Cronograma (modal de edición y
 * confirmaciones), ej. "Domingo 06 · Jueves 10 de Septiembre", o con los dos
 * meses si el domingo y el jueves de esa semana caen en meses distintos.
 */
function tituloSemana(string $fechaDomingoYmd): string
{
    $jueves = juevesDeLaSemana($fechaDomingoYmd);
    $dObj = DateTime::createFromFormat('Y-m-d', $fechaDomingoYmd);
    $jObj = DateTime::createFromFormat('Y-m-d', $jueves);
    if (!$dObj || !$jObj) {
        return '';
    }
    if ($dObj->format('n') === $jObj->format('n')) {
        return 'Domingo ' . $dObj->format('d') . ' · Jueves ' . $jObj->format('d') . ' de ' . nombreMes((int) $dObj->format('n'));
    }
    return 'Domingo ' . formatearFechaCorta($fechaDomingoYmd) . ' · Jueves ' . formatearFechaCorta($jueves);
}

/* ---------------------------- Puntaje: ranking, ajustes y cierres ---------------------------- */

/**
 * Arma el ranking de niñas activas combinando dos fuentes:
 *  - los registros reales de la planilla (una carita = un registro), y
 *  - los ajustes manuales (totales que ya se tenían en papel, cargados de una
 *    sola vez por niña+categoría, sin una fecha de ensayo real detrás).
 * Ambas fuentes se filtran por el mismo rango de fechas (y, si se pide, la
 * misma categoría) antes de sumarlas, para que un ajuste manual cuente igual
 * que un registro real dentro de un periodo o de un cierre.
 *
 * Las niñas activas sin nada en el rango elegido aparecen igual, con todo en
 * cero, para no "esconder" a nadie del ranking.
 *
 * @param int|null    $categoriaId Filtra a una sola categoría, o null para el ranking general (todas juntas).
 * @param string|null $desde       Fecha mínima (YYYY-MM-DD) o null/'' para no limitar por abajo.
 * @param string|null $hasta       Fecha máxima (YYYY-MM-DD) o null/'' para no limitar por arriba.
 */
function armarRanking(PDO $pdo, ?int $categoriaId, ?string $desde, ?string $hasta): array
{
    $condRegistros = [];
    $paramRegistros = [];
    if ($categoriaId) {
        $condRegistros[] = 'r.categoria_id = ?';
        $paramRegistros[] = $categoriaId;
    }
    if ($desde) {
        $condRegistros[] = 'e.fecha >= ?';
        $paramRegistros[] = $desde;
    }
    if ($hasta) {
        $condRegistros[] = 'e.fecha <= ?';
        $paramRegistros[] = $hasta;
    }
    $whereRegistros = $condRegistros ? ('WHERE ' . implode(' AND ', $condRegistros)) : '';

    $condAjustes = [];
    $paramAjustes = [];
    if ($categoriaId) {
        $condAjustes[] = 'a.categoria_id = ?';
        $paramAjustes[] = $categoriaId;
    }
    if ($desde) {
        $condAjustes[] = 'a.fecha >= ?';
        $paramAjustes[] = $desde;
    }
    if ($hasta) {
        $condAjustes[] = 'a.fecha <= ?';
        $paramAjustes[] = $hasta;
    }
    $whereAjustes = $condAjustes ? ('WHERE ' . implode(' AND ', $condAjustes)) : '';

    $sql = "
        SELECT
            n.id,
            n.nombres,
            n.apellidos,
            COALESCE(SUM(x.feliz), 0) AS conteo_feliz,
            COALESCE(SUM(x.neutral), 0) AS conteo_neutral,
            COALESCE(SUM(x.triste), 0) AS conteo_triste,
            COALESCE(SUM(x.extra), 0) AS puntos_extra,
            COALESCE(SUM(x.feliz - x.triste + x.extra), 0) AS puntaje_total
        FROM ninas n
        LEFT JOIN (
            SELECT
                r.nina_id,
                CASE WHEN r.estado = 'feliz' THEN 1 ELSE 0 END AS feliz,
                CASE WHEN r.estado = 'neutral' THEN 1 ELSE 0 END AS neutral,
                CASE WHEN r.estado = 'triste' THEN 1 ELSE 0 END AS triste,
                r.puntos_extra AS extra
            FROM registros r
            JOIN ensayos e ON e.id = r.ensayo_id
            $whereRegistros
            UNION ALL
            SELECT
                a.nina_id,
                a.conteo_feliz AS feliz,
                a.conteo_neutral AS neutral,
                a.conteo_triste AS triste,
                a.puntos_extra AS extra
            FROM ajustes_manuales a
            $whereAjustes
        ) x ON x.nina_id = n.id
        WHERE n.activa = 1
        GROUP BY n.id, n.nombres, n.apellidos
        ORDER BY puntaje_total DESC, n.nombres ASC, n.apellidos ASC
    ";
    // Nota: MySQL/MariaDB no permite reutilizar dos alias agregados dentro de
    // una expresión en ORDER BY (error 1247, 'reference to group function');
    // por eso el total se calcula una sola vez como su propio alias
    // (puntaje_total) y el ORDER BY lo referencia directo, sin combinarlo.
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($paramRegistros, $paramAjustes));
    $filas = $stmt->fetchAll();
    foreach ($filas as &$fila) {
        $fila['puntaje_total'] = (int) $fila['puntaje_total'];
    }
    unset($fila);
    return $filas;
}

/** Fecha (YYYY-MM-DD) hasta donde llegó el último cierre de periodo, o null si nunca se ha cerrado uno. */
function ultimoCierreHasta(PDO $pdo): ?string
{
    $valor = $pdo->query('SELECT MAX(fecha_hasta) FROM puntaje_cierres')->fetchColumn();
    return $valor ?: null;
}

/** El día siguiente a una fecha (YYYY-MM-DD). */
function diaSiguiente(string $fechaYmd): string
{
    $d = DateTime::createFromFormat('Y-m-d', $fechaYmd);
    if (!$d) {
        return $fechaYmd;
    }
    $d->modify('+1 day');
    return $d->format('Y-m-d');
}
