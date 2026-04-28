// Formatters de fecha forzados a zona horaria Chile (independiente del browser).

const TZ = 'America/Santiago';

const ABSOLUTE_FMT = new Intl.DateTimeFormat('es-CL', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: TZ,
});

export function formatAbsoluteDate(s) {
    if (!s) return '—';
    return ABSOLUTE_FMT.format(new Date(s));
}

export function formatRelativeDate(s) {
    if (!s) return '—';
    const diffSec = Math.round((Date.now() - new Date(s).getTime()) / 1000);
    if (diffSec < 0) return 'en el futuro';
    if (diffSec < 30) return 'recién';
    if (diffSec < 60) return `hace ${diffSec} s`;
    if (diffSec < 3600) return `hace ${Math.round(diffSec / 60)} min`;
    if (diffSec < 86400) return `hace ${Math.round(diffSec / 3600)} h`;
    if (diffSec < 604800) return `hace ${Math.round(diffSec / 86400)} d`;
    return formatAbsoluteDate(s);
}

export function formatNumber(n) {
    if (n === null || n === undefined) return '—';
    return new Intl.NumberFormat('es-CL').format(n);
}
