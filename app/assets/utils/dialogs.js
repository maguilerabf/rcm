import Swal from 'sweetalert2';

/**
 * Reemplazo de `window.confirm()` con SweetAlert2.
 * Uso:
 *   if (!(await confirmDialog({ text: '¿Borrar?', danger: true }))) return;
 *
 * @param {object} opts
 * @param {string} [opts.title]        Encabezado del diálogo.
 * @param {string}  opts.text          Mensaje principal.
 * @param {boolean} [opts.danger]      Si true → tono rojo (acciones destructivas).
 * @param {string}  [opts.confirmText] Texto del botón confirmar.
 * @param {string}  [opts.cancelText]  Texto del botón cancelar.
 * @returns {Promise<boolean>}
 */
export async function confirmDialog({ title, text, danger = false, confirmText, cancelText = 'Cancelar' } = {}) {
    const result = await Swal.fire({
        title: title ?? (danger ? '¿Estás seguro?' : '¿Confirmas?'),
        text,
        icon: danger ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonText: confirmText ?? (danger ? 'Sí, continuar' : 'Sí'),
        cancelButtonText: cancelText,
        confirmButtonColor: danger ? '#dc2626' : '#1d4ed8',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
        focusCancel: danger,
    });
    return result.isConfirmed;
}

/** Toast no bloqueante para feedback rápido tras una acción. */
export function toast(message, icon = 'success') {
    return Swal.fire({
        toast: true,
        position: 'top-end',
        icon,
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
    });
}
