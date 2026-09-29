import { readdirSync, readFileSync, writeFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const swaps = [
    ['w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm', 'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs outline-none focus-visible:ring-2 focus-visible:ring-ring/30'],
    ['mb-1 block text-sm font-medium text-slate-700', 'mb-1.5 block text-sm font-medium'],
    ['inline-flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium bg-slate-900 text-white hover:bg-slate-700', 'inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90'],
    ['inline-flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium border border-slate-300 hover:bg-slate-50', 'inline-flex h-8 items-center justify-center rounded-md border border-input bg-background px-3 text-sm font-medium shadow-xs hover:bg-accent'],
    ['inline-flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium border border-slate-300 text-slate-700 hover:bg-slate-50', 'inline-flex h-8 items-center justify-center rounded-md border border-input bg-background px-3 text-sm font-medium shadow-xs hover:bg-accent'],
    ['inline-flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium px-2 py-1 text-xs border border-red-300 text-red-700 hover:bg-red-50', 'inline-flex h-8 items-center justify-center rounded-md border border-destructive/30 px-3 text-xs font-medium text-destructive hover:bg-destructive/10'],
    ['inline-flex items-center justify-center rounded-md px-3 py-1.5 text-sm font-medium px-2 py-1 text-xs border border-slate-300 hover:bg-slate-50', 'inline-flex h-8 items-center justify-center rounded-md border border-input bg-background px-3 text-xs font-medium hover:bg-accent'],
    ['rounded-lg border border-slate-200 bg-white shadow-sm', 'rounded-xl border border-border bg-card text-card-foreground shadow-sm'],
    ['text-slate-500', 'text-muted-foreground'],
    ['text-slate-700', 'text-foreground'],
    ['text-red-600', 'text-destructive'],
    ['text-emerald-600', 'text-foreground'],
    ['bg-red-50 text-red-800', 'border border-destructive/30 bg-destructive/10 text-destructive'],
    ['bg-emerald-50 text-emerald-800', 'border border-border bg-background'],
    ['bg-amber-50 text-amber-900', 'border border-border bg-background'],
    ['divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200', 'divide-y divide-border overflow-hidden rounded-xl border border-border bg-card'],
    ['flex items-center justify-between bg-white px-3 py-2', 'flex items-center justify-between bg-card px-3 py-2'],
];

function walk(dir, out = []) {
    for (const name of readdirSync(dir)) {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) walk(path, out);
        else if (name.endsWith('.twig') && name !== 'layout.twig') out.push(path);
    }
    return out;
}

for (const file of walk('app/views/admin')) {
    let text = readFileSync(file, 'utf8');
    for (const [from, to] of swaps) text = text.split(from).join(to);
    writeFileSync(file, text);
}
console.log('updated admin views');
