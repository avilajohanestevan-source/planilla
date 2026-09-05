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

// Cierra el modal si se hace clic fuera de la caja
document.addEventListener('click', function (e) {
    const modal = document.getElementById('modal-nina');
    if (modal && e.target === modal) {
        cerrarModalNina();
    }
});

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

/* ------------------------------- Planilla: añadido plegable (+) ------------------------------ */
function inicializarExtras() {
    document.querySelectorAll('[data-extra-wrap]').forEach(wrap => {
        const boton = wrap.querySelector('[data-extra-toggle]');
        const input = wrap.querySelector('[data-extra]');
        const valor = wrap.querySelector('[data-extra-valor]');
        if (!boton || !input || !valor) return;

        function mostrarSegunValor() {
            const n = parseInt(input.value, 10) || 0;
            input.hidden = true;
            if (n !== 0) {
                valor.textContent = (n > 0 ? '+' : '') + n;
                valor.hidden = false;
                boton.hidden = true;
            } else {
                valor.hidden = true;
                boton.hidden = false;
            }
        }

        function abrirEdicion() {
            boton.hidden = true;
            valor.hidden = true;
            input.hidden = false;
            input.focus();
            input.select();
        }

        boton.addEventListener('dblclick', abrirEdicion);
        valor.addEventListener('dblclick', abrirEdicion);
        input.addEventListener('blur', function () {
            mostrarSegunValor();
            calcularTotalesPlanilla();
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                input.blur();
            }
        });

        mostrarSegunValor();
    });
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
