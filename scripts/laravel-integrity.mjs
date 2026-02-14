#!/usr/bin/env node
/**
 * ═══════════════════════════════════════════════════════════════
 *  Laravel + Next.js Integrity Scanner
 * ═══════════════════════════════════════════════════════════════
 *
 * Scans Laravel Routes/Controllers and Next.js Client calls to find:
 * 1. Broken Links (Client calls endpoint -> No Route)
 * 2. Unused Endpoints (Route defined -> No Client call)
 * 3. Orphaned Controller Methods (Method exists -> No Route)
 *
 * Usage: node scripts/laravel-integrity.mjs
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT = path.resolve(__dirname, '..');
const FRONTEND_ROOT = path.join(ROOT, 'keyhome-frontend-next/src');
const BACKEND_ROOT = ROOT;

const c = {
    red: s => `\x1b[31m${s}\x1b[0m`,
    green: s => `\x1b[32m${s}\x1b[0m`,
    yellow: s => `\x1b[33m${s}\x1b[0m`,
    blue: s => `\x1b[34m${s}\x1b[0m`,
    dim: s => `\x1b[2m${s}\x1b[0m`,
    bold: s => `\x1b[1m${s}\x1b[0m`,
};

function readFile(p) {
    try { return fs.readFileSync(p, 'utf8'); } catch { return null; }
}

function walkDir(dir, exts) {
    let results = [];
    if (!fs.existsSync(dir)) return results;
    const list = fs.readdirSync(dir);
    list.forEach(file => {
        file = path.join(dir, file);
        const stat = fs.statSync(file);
        if (stat && stat.isDirectory()) results = results.concat(walkDir(file, exts));
        else if (exts.some(e => file.endsWith(e))) results.push(file);
    });
    return results;
}

// ── LAYER 1: PARSE LARAVEL ROUTES ──
function parseLaravelRoutes() {
    console.log(c.blue('Parsing Laravel Routes...'));
    const src = readFile(path.join(BACKEND_ROOT, 'routes/api.php'));
    if (!src) return [];

    const routes = [];
    const lines = src.split('\n');
    let currentPrefix = 'api/v1'; // Default prefix based on RouteServiceProvider usually, assuming api.php is /api
    let prefixStack = ['api/v1'];
    let controllerStack = [];

    // Regex helpers
    // Matches: Route::get('url', [Controller::class, 'method'])
    // or Route::controller(C::class)->group(...)
    // or Route::prefix('p')->group(...)

    lines.forEach(line => {
        const clean = line.trim();
        // Skip comments
        if (clean.startsWith('//') || clean.startsWith('*')) return;

        // Track prefix
        const prefixMatch = clean.match(/Route::prefix\(['"]([^'"]+)['"]\)/);
        if (prefixMatch) {
            // This is naive; assumes group closure follows immediately or eventually
            // Proper parsing requires AST, but regex heuristic works for standard file structure
            // checks indentation to "guess" scope usually
        }

        // Track controller group
        // Route::controller(UserController::class)
        const ctrlGroupMatch = clean.match(/Route::.*controller\(([^:]+)::class\)/);
        if (ctrlGroupMatch) {
            // simplified: we assume typical block structure
        }

        // Match individual route
        // Route::(get|post|put|delete|patch)('uri', ...
        const methodMatch = clean.match(/Route::(get|post|put|delete|patch|resource)\(['"]([^'"]+)['"]/);
        if (methodMatch) {
            const method = methodMatch[1];
            const uri = methodMatch[2];
            
            // Try to find controller/method
            let action = 'closure';
            
            // Case A: [Controller::class, 'method']
            const arrMatch = clean.match(/\[([^:]+)::class,\s*['"]([^'"]+)['"]/);
            if (arrMatch) {
                action = `${arrMatch[1]}@${arrMatch[2]}`;
            } else {
                // Case B: 'method' (inside controller group) - Hard to track without AST
                // Case C: 'Controller@method' (old style)
                const strMatch = clean.match(/['"]([\w\\]+@[\w]+)['"]/);
                if (strMatch) action = strMatch[1];
            }

            // Clean URI (remove wildcards for matching)
            // Laravel: /users/{id} -> Client: /users/${id}
            // We want a standardized pattern: /users/:id
            let cleanUri = (currentPrefix + '/' + uri).replace(/\/+/g, '/');
            // Remove 'v1' duplicates if api.php is purely v1
            
            routes.push({
                method: method.toUpperCase(),
                rawUri: cleanUri,
                pattern: cleanUri.replace(/\{[^}]+\}/g, ':param'),
                action,
                line: clean
            });
        }
    });
    
    // Better approach: Run `php artisan route:list --json` to get REAL truth
    // Parsing php file with regex is fragile.
    // Let's rely on artisan if possible.
    return routes;
}

// ── ALTERNATIVE: PARSE ARTISAN OUTPUT ──
import { execSync } from 'child_process';
function getArtisanRoutes() {
    try {
        console.log(c.blue('Executing `php artisan route:list --json`...'));
        const output = execSync('php artisan route:list --json', { cwd: BACKEND_ROOT, stdio: ['ignore', 'pipe', 'ignore'] }).toString();
        const json = JSON.parse(output);
        return json.map(r => ({
            method: r.method.split('|')[0], // GET|HEAD -> GET
            uri: r.uri,
            pattern: r.uri.replace(/\{[^}]+\}/g, ':param'), // standard placeholder
            action: r.action,
            name: r.name
        })).filter(r => r.uri.startsWith('api')); // Only API routes
    } catch (e) {
        console.log(c.red('Failed to run artisan route:list. Fallback to regex? No, failing.'));
        console.log(e.message);
        return [];
    }
}

// ── LAYER 2: PARSE NEXT.JS CLIENT ──
function parseClientCalls() {
    console.log(c.blue('Scanning Next.js Client...'));
    console.log(c.dim(`Looking in: ${FRONTEND_ROOT}`));
    
    if (!fs.existsSync(FRONTEND_ROOT)) {
        console.log(c.red(`Frontend root not found: ${FRONTEND_ROOT}`));
        return [];
    }

    const files = walkDir(FRONTEND_ROOT, ['.ts', '.tsx', '.js', '.jsx']);
    console.log(c.dim(`Found ${files.length} frontend files.`));
    
    const calls = [];

    files.forEach(file => {
        const src = readFile(file);
        if (!src) return;

        // Matches: axios.get('/v1/users', ...) or fetch(`${API_URL}/v1/users`)
        // Helper to normalize URL
        const extractUrl = (raw) => {
            // Handle templating: /users/${id} -> /users/:param
            let url = raw.replace(/\$\{[^}]+\}/g, ':param');
            // Remove leading / or variable prefix if obvious
            // e.g. `${NEXT_PUBLIC_API_URL}/v1/...` -> /v1/...
            url = url.replace(/^[^{]*NEXT_PUBLIC_API_URL}?/, '');
            // Ensure leading slash for comparison
            if (!url.startsWith('/')) url = '/' + url;
            // Remove /api if present (Laravel routes might include it or not depending on parsing)
            return url;
        };

        // Regex for axios methods: .get(quote content quote)
        // Group 2 is the quote char, Group 3 is the content
        const axiosRe = /\.(get|post|put|delete|patch)\s*\(\s*(['"`])((?:(?!\2).)*)\2/g;
        
        let m;
        while ((m = axiosRe.exec(src)) !== null) {
            let rawUrl = m[3];
            let url = extractUrl(rawUrl);
            
            calls.push({
                method: m[1].toUpperCase(),
                pattern: url,
                file: path.relative(ROOT, file),
                raw: rawUrl
            });
        }
    });
    
    console.log(c.dim(`Found ${calls.length} API calls in client code.`));
    return calls;
}

// ── LAYER 3: PARSE CONTROLLERS ──
function parseControllers() {
    console.log(c.blue('Scanning Controllers...'));
    const dir = path.join(BACKEND_ROOT, 'app/Http/Controllers');
     const files = walkDir(dir, ['.php']);
     const methods = [];
     
     files.forEach(file => {
         const src = readFile(file);
         // Find class name
         const nsMatch = src.match(/namespace ([^;]+);/);
         const classMatch = src.match(/class\s+(\w+)/);
         if (!nsMatch || !classMatch) return;
         
         const fullClass = `${nsMatch[1]}\\${classMatch[1]}`;
         
         // Find public methods
         const methodRe = /public\s+function\s+(\w+)\s*\(/g;
         let m;
         while ((m = methodRe.exec(src)) !== null) {
             const name = m[1];
             if (name.startsWith('__')) continue;
             methods.push({
                 action: `${fullClass}@${name}`,
                 file: path.relative(ROOT, file),
                 name
             });
         }
     });
     return methods;
}

// ── MAIN ANALYSIS ──
function analyze() {
    const routes = getArtisanRoutes();
    const clientCalls = parseClientCalls();
    const controllerMethods = parseControllers();
    
    const findings = [];
    
    // Normalize logic
    // Route pattern from artisan: "api/v1/users/:param"
    // Client pattern extracted: "/v1/users/:param" or "/api/v1/users/:param"
    
    const normalize = (uri) => {
        let clean = uri.replace(/^\//, ''); // remove leading default slash
        if (!clean.startsWith('api/')) clean = 'api/' + clean; // force api/ prefix
        return clean.replace(/\/+/g, '/');
    };

    const routeMap = new Set(routes.map(r => `${r.method}:${normalize(r.pattern)}`));
    const actionMap = new Set(routes.map(r => r.action));

    // CHECK 1: Broken Links (Client -> ???)
    clientCalls.forEach(call => {
        // Only verify calls that look like API calls (start with /api or /v1)
        if (!call.pattern.includes('/api') && !call.pattern.includes('/v1')) return;
        
        const norm = normalize(call.pattern);
        const key = `${call.method}:${norm}`;
        
        // Try strict match
        if (routeMap.has(key)) return;
        
        // Try loose match (maybe method mismatch or slight path var diff)
        // If we can't find it -> Broken Link
        findings.push({
            type: 'BROKEN_LINK',
            severity: 'error',
            message: `Client calls ${call.method} "${call.pattern}" but no matching API route found.`,
            loc: call.file
        });
    });

    // CHECK 2: Unused Endpoints (Route -> ???)
    routes.forEach(route => {
        if (route.method === 'HEAD') return; // ignore HEAD
        const norm = normalize(route.pattern);
        const key = `${route.method}:${norm}`;
        
        // Check if ANY client call matches this
        const used = clientCalls.some(c => {
            const cNorm = normalize(c.pattern);
            return cNorm === norm && (c.method === route.method || c.method === 'ALL'); // rudimentary
        });
        
        if (!used) {
            // Check if it's a standard filament or auth route we might not see in client code directly
            if (route.uri.includes('filament')) return;
            if (route.uri.includes('sanctum')) return;
            
            findings.push({
                type: 'UNUSED_ROUTE',
                severity: 'warning',
                message: `Route ${route.method} "${route.uri}" defined but no client usage found.`,
                loc: route.action
            });
        }
    });

    // CHECK 3: Orphaned Controller Methods
    controllerMethods.forEach(m => {
        if (!actionMap.has(m.action)) {
            findings.push({
                type: 'ORPHAN_METHOD',
                severity: 'info',
                message: `Controller method "${m.action}" exists but is not linked to any route.`,
                loc: m.file
            });
        }
    });

    // REPORT
    console.log(c.bold(`\nIntegrity Scan Results`));
    console.log(c.dim(`Scanned ${routes.length} routes, ${clientCalls.length} client calls, ${controllerMethods.length} controller methods.\n`));
    
    if (findings.length === 0) {
        console.log(c.green('✅ All clear! No issues found.'));
    } else {
        const errors = findings.filter(f => f.severity === 'error');
        const warnings = findings.filter(f => f.severity === 'warning');
        const infos = findings.filter(f => f.severity === 'info');

        errors.forEach(f => console.log(`${c.red('● ERR')} ${f.message} ${c.dim(f.loc)}`));
        warnings.forEach(f => console.log(`${c.yellow('● WARN')} ${f.message} ${c.dim(f.loc)}`));
        infos.forEach(f => console.log(`${c.blue('● INFO')} ${f.message} ${c.dim(f.loc)}`));
        
        console.log(`\n${c.red(errors.length + ' Errors')} ${c.yellow(warnings.length + ' Warnings')} ${c.blue(infos.length + ' Infos')}`);
    }
}

analyze();
