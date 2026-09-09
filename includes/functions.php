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
