/** All amounts travel as integer cents; formatting happens only at the edge. */
export function money(cents, currency = 'EUR') {
    return new Intl.NumberFormat('en', {
        style: 'currency',
        currency,
    }).format((cents ?? 0) / 100);
}

/** "12.34" | "12,34" -> 1234. Returns NaN for unparseable input. */
export function toCents(value) {
    const normalized = String(value ?? '').trim().replace(',', '.');
    if (normalized === '') return NaN;
    const parsed = Number.parseFloat(normalized);
    return Number.isFinite(parsed) ? Math.round(parsed * 100) : NaN;
}

/** Renders a {currency: cents} map as "€1,234.56 · $99.00". */
export function moneyMap(byCurrency) {
    const entries = Object.entries(byCurrency ?? {});
    if (entries.length === 0) return money(0);
    return entries.map(([currency, cents]) => money(cents, currency)).join(' · ');
}
