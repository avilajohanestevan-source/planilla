# Ministerio de Danza C.D — Celestial Dance

Aplicativo local en PHP + MySQL para llevar la planilla semanal de las niñas del ministerio (versículo, devocional, audio devocional, puntualidad, apuntes, calendario, comportamiento y fallas) y administrar su información y cargos.

## Instalación con XAMPP

1. **Copia la carpeta** `planilla_celestial_dance` completa dentro de `htdocs`:
   - Windows: `C:\xampp\htdocs\planilla_celestial_dance`
   - Mac: `/Applications/XAMPP/htdocs/planilla_celestial_dance`

2. **Abre el panel de XAMPP** y enciende los módulos **Apache** y **MySQL**.

3. **Crea la base de datos**: abre `http://localhost/phpmyadmin`, ve a la pestaña **Importar**, elige el archivo `database.sql` de esta carpeta y dale a **Continuar**. Esto crea la base `planilla_celestial_dance` con sus tablas y las 8 categorías de la planilla ya cargadas.

4. **Revisa `config.php`** si tu MySQL no usa el usuario `root` sin clave (configuración por defecto de XAMPP). Si es distinto, ajusta `DB_USER` y `DB_PASS`.

5. **Abre el aplicativo** en tu navegador:
   `http://localhost/planilla_celestial_dance/`

## Cómo está organizado

```
planilla_celestial_dance/
├── index.php              → Inicio, con los dos accesos principales
├── planilla.php           → Llenado de la planilla del ensayo (sábados)
├── ninas.php               → Registro/edición de niñas y administración de cargos
├── config.php              → Datos de conexión a la base de datos
├── database.sql            → Estructura de la base de datos (para importar en phpMyAdmin)
├── includes/
│   ├── db.php               → Conexión PDO a MySQL
│   ├── functions.php        → Cálculo de edad, sábado por defecto, etc.
│   ├── header.php / footer.php → Plantilla común (menú, logo, pie de página)
├── actions/
│   ├── guardar_planilla.php → Guarda los resultados de un ensayo
│   ├── guardar_nina.php     → Crea/edita una niña y sus cargos
│   ├── eliminar_nina.php    → Desactiva/reactiva una niña
│   ├── guardar_cargo.php    → Crea un cargo nuevo
│   └── eliminar_cargo.php   → Elimina un cargo
└── assets/
    ├── css/style.css        → Estilos (paleta tomada del logo)
    ├── js/main.js            → Modal de niñas, cálculo de edad en vivo, totales de la planilla
    └── img/logo.png          → Logo en mejor calidad
```

## Cómo funciona por ahora

- **Inicio**: accesos directos a "Llenar Planilla" y "Niñas y Cargos".
- **Planilla**: eliges la fecha del sábado (por defecto te muestra el sábado más reciente), y aparece una tabla con todas las niñas activas y las 8 categorías. Cada celda tiene las tres caritas (😊 feliz = +1, 😐 neutral = 0, 😞 triste = −1) y un campo pequeño para un puntaje extra que tú escribes libremente (por ejemplo, +20 si una niña completó los 5 días del devocional). El total del día se calcula solo mientras vas marcando.
- **Niñas y Cargos**: registras nombres, apellidos, fecha de nacimiento (la edad se calcula sola) y fecha de cumpleaños. En la misma pantalla creas los cargos del ministerio y se los asignas a cada niña desde su ficha. "Desactivar" no borra el historial de una niña, solo la saca de la planilla activa (por si se retira temporalmente); se puede reactivar cuando quieras.

Toda la información se guarda quedando lista para las siguientes fases que me vayas indicando (por ejemplo el conteo/ranking de caritas para la premiación, o el calendario más grande).

## Nota sobre los datos

La base de datos ya trae cargadas las 8 categorías de tu planilla original. Las niñas y los cargos los registras tú desde el aplicativo la primera vez que lo uses, ya que no tenía sus apellidos ni fechas de nacimiento completas.
