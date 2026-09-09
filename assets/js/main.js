/* =====================================================================
   Ministerio de Danza C.D · Celestial Dance
   JS de apoyo: modal de niñas, cálculo de edad, totales de planilla.
   ===================================================================== */

/* ------------------------------- Modal de niña ------------------------------ */
function abrirModalNina(id) {
    const modal = document.getElementById('modal-nina');
    const form = document.getElementById('form-nina');
    const titulo = document.getElementById('modal-nina-titulo');
    if (!modal || !form) return;

    form.reset();
    document.getElementById('edad-calculada').textContent = '';
    document.querySelectorAll('.chk-cargo').forEach(chk => chk.checked = false);

    if (id && typeof DATOS_NINAS !== 'undefined' && DATOS_NINAS[id]) {
        const nina = DATOS_NINAS[id];
        titulo.textContent = 'Editar niña';
        document.getElementById('nina_id').value = nina.id;
        document.getElementById('nina_nombres').value = nina.nombres;
        document.getElementById('nina_apellidos').value = nina.apellidos;
        document.getElementById('nina_fecha_nacimiento').value = nina.fecha_nacimiento || '';
        document.getElementById('nina_fecha_cumpleanos').value = nina.fecha_cumpleanos || '';
        document.getElementById('nina_puede_altar').checked = (nina.puede_altar !== 0);
        (nina.cargos || []).forEach(cargoId => {
            const chk = document.querySelector('.chk-cargo[value="' + cargoId + '"]');
            if (chk) chk.checked = true;
        });
        mostrarEdadCalculada();
    } else {
        titulo.textContent = 'Nueva niña';
        document.getElementById('nina_id').value = '';
    }

    modal.classList.add('abierto');
}

function cerrarModalNina() {
    const modal = document.getElementById('modal-nina');
    if (modal) modal.classList.remove('abierto');
}

// Cierra los modales si se hace clic fuera de la caja
document.addEventListener('click', function (e) {
    const modalNina = document.getElementById('modal-nina');
    if (modalNina && e.target === modalNina) {
        cerrarModalNina();
    }
    const modalExtra = document.getElementById('modal-extra');
    if (modalExtra && e.target === modalExtra) {
        cerrarModalExtra();
    }
    const modalCronograma = document.getElementById('modal-cronograma');
    if (modalCronograma && e.target === modalCronograma) {
        cerrarModalCronograma();
    }
});

/* ------------------------------- Modal de cronograma ------------------------------ */
function abrirModalCronograma(id) {
    const modal = document.getElementById('modal-cronograma');
    const form = document.getElementById('form-cronograma');
    const titulo = document.getElementById('modal-cronograma-titulo');
    if (!modal || !form || typeof DATOS_CRONOGRAMA === 'undefined' || !DATOS_CRONOGRAMA[id]) return;

    const fecha = DATOS_CRONOGRAMA[id];
    form.reset();
    document.querySelectorAll('.chk-danzora').forEach(chk => chk.checked = false);

    titulo.textContent = fecha.fecha_bonita;
    document.getElementById('cronograma_fecha_id').value = fecha.id;

    (fecha.ninas || []).forEach(ninaId => {
        const chk = document.querySelector('.chk-danzora[value="' + ninaId + '"]');
        if (chk) chk.checked = true;
    });

    const select = document.getElementById('cronograma_uniforme_id');
    if (select) select.value = fecha.uniforme_id || 0;
    actualizarSwatchUniforme();

    modal.classList.add('abierto');
}

function cerrarModalCronograma() {
    const modal = document.getElementById('modal-cronograma');
    if (modal) modal.classList.remove('abierto');
}

function actualizarSwatchUniforme() {
    const select = document.getElementById('cronograma_uniforme_id');
    const swatch = document.getElementById('uniforme-preview-swatch');
    if (!select || !swatch) return;
    const opcion = select.options[select.selectedIndex];
    swatch.style.background = opcion ? (opcion.dataset.color || 'transparent') : 'transparent';
}

function mostrarEdadCalculada() {
    const input = document.getElementById('nina_fecha_nacimiento');
    const salida = document.getElementById('edad-calculada');
    if (!input || !salida || !input.value) {
        if (salida) salida.textContent = '';
        return;
    }
    const nacimiento = new Date(input.value + 'T00:00:00');
    const hoy = new Date();
    let edad = hoy.getFullYear() - nacimiento.getFullYear();
    const m = hoy.getMonth() - nacimiento.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < nacimiento.getDate())) {
        edad--;
    }
    salida.textContent = edad >= 0 ? ('Edad actual: ' + edad + ' años') : '';
}

/* ------------------------------- Planilla: puntos extra vía modal ------------------------------ */
let extraContexto = null; // { grupo, input, badge, signo }

function inicializarExtras() {
    document.querySelectorAll('[data-celda][data-tiene-extra="1"]').forEach(celda => {
        const grupo = celda.querySelector('[data-grupo-carita]');
        const input = celda.querySelector('[data-extra]');
        const badge = celda.querySelector('[data-extra-valor]');
        if (!grupo || !input || !badge) return;

        const labelFeliz  = grupo.querySelector('label[data-extra-doble="feliz"]');
        const labelTriste = grupo.querySelector('label[data-extra-doble="triste"]');
        const contexto = { grupo, input, badge };

        if (labelFeliz) {
            labelFeliz.addEventListener('dblclick', function (e) {
                e.preventDefault();
                abrirModalExtra(Object.assign({ signo: 'positivo' }, contexto));
            });
        }
        if (labelTriste) {
            labelTriste.addEventListener('dblclick', function (e) {
                e.preventDefault();
                abrirModalExtra(Object.assign({ signo: 'negativo' }, contexto));
            });
        }
        badge.addEventListener('dblclick', function () {
            const actual = parseInt(input.value, 10) || 0;
            abrirModalExtra(Object.assign({ signo: actual < 0 ? 'negativo' : 'positivo' }, contexto));
        });
    });
}

function abrirModalExtra(contexto) {
    extraContexto = contexto;
    const modal = document.getElementById('modal-extra');
    const titulo = document.getElementById('modal-extra-titulo');
    const ayuda = document.getElementById('modal-extra-ayuda');
    const campoValor = document.getElementById('modal-extra-valor');
    const btnQuitar = document.getElementById('modal-extra-quitar');
    if (!modal || !campoValor) return;

    const actual = parseInt(contexto.input.value, 10) || 0;

    if (contexto.signo === 'positivo') {
        titulo.textContent = 'Puntos extra · Carita feliz';
        ayuda.textContent = 'Se suman al total de esta categoría (ej. 10, 15).';
    } else {
        titulo.textContent = 'Puntos extra · Carita triste';
        ayuda.textContent = 'Se restan del total de esta categoría (ej. 10, 15).';
    }

    campoValor.value = actual !== 0 ? Math.abs(actual) : '';
    if (btnQuitar) btnQuitar.hidden = (actual === 0);

    modal.classList.add('abierto');
    setTimeout(function () {
        campoValor.focus();
        campoValor.select();
    }, 0);
}

function cerrarModalExtra() {
    const modal = document.getElementById('modal-extra');
    if (modal) modal.classList.remove('abierto');
    extraContexto = null;
}

function aplicarValorExtra(valor) {
    if (!extraContexto) return;
    const { input, badge, grupo } = extraContexto;
    input.value = valor;
    badge.classList.remove('extra-positivo', 'extra-negativo');
    if (valor !== 0) {
        badge.textContent = (valor > 0 ? '+' : '') + valor;
        badge.classList.add(valor > 0 ? 'extra-positivo' : 'extra-negativo');
        badge.hidden = false;
        grupo.hidden = true;
    } else {
        badge.hidden = true;
        badge.textContent = '';
        grupo.hidden = false;
    }
    calcularTotalesPlanilla();
}

function guardarExtraModal() {
    if (!extraContexto) return;
    const campoValor = document.getElementById('modal-extra-valor');
    const magnitud = Math.abs(parseInt(campoValor.value, 10) || 0);
    if (magnitud <= 0) {
        campoValor.focus();
        return;
    }
    const valor = extraContexto.signo === 'positivo' ? magnitud : -magnitud;

    // La carita seleccionada debe ser coherente con el signo del extra.
    const estadoDeseado = extraContexto.signo === 'positivo' ? 'feliz' : 'triste';
    const radio = extraContexto.grupo.querySelector('input[type=radio][value="' + estadoDeseado + '"]');
    if (radio) radio.checked = true;

    aplicarValorExtra(valor);
    cerrarModalExtra();
}

function quitarExtraModal() {
    aplicarValorExtra(0);
    cerrarModalExtra();
}

/* ------------------------------- Planilla: totales en vivo ------------------------------ */
function calcularTotalesPlanilla() {
    document.querySelectorAll('[data-fila-nina]').forEach(fila => {
        let total = 0;
        fila.querySelectorAll('[data-grupo-carita] input[type=radio]:checked').forEach(r => {
            total += parseInt(r.dataset.puntos || '0', 10);
        });
        fila.querySelectorAll('input[data-extra]').forEach(inp => {
            const v = parseInt(inp.value, 10);
            if (!isNaN(v)) total += v;
        });
        const celda = fila.querySelector('[data-total-nina]');
        if (celda) celda.textContent = total;
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const tabla = document.getElementById('tabla-planilla');
    if (tabla) {
        inicializarExtras();
        tabla.addEventListener('change', calcularTotalesPlanilla);
        tabla.addEventListener('input', calcularTotalesPlanilla);
        calcularTotalesPlanilla();
    }

    const campoExtra = document.getElementById('modal-extra-valor');
    if (campoExtra) {
        campoExtra.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                guardarExtraModal();
            } else if (e.key === 'Escape') {
                cerrarModalExtra();
            }
        });
    }

    // Aviso (no bloqueante) si la fecha elegida en la planilla no es sábado
    const inputFecha = document.getElementById('fecha');
    if (inputFecha) {
        const revisar = function () {
            const aviso = document.getElementById('aviso-sabado');
            if (!aviso) return;
            const d = new Date(inputFecha.value + 'T00:00:00');
            aviso.style.display = (d.getDay() === 6) ? 'none' : 'block';
        };
        inputFecha.addEventListener('change', revisar);
        revisar();
    }
});
