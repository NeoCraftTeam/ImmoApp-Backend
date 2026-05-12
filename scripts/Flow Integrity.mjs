#!/usr/bin/env node
/**
 * ═══════════════════════════════════════════════════════════════
 *  ImmoApp Flow Integrity Scanner v2.1 — ÉDITION ULTIME
 * ═══════════════════════════════════════════════════════════════
 *
 * Trace CHAQUE connexion dans la base de code Laravel comme un système nerveux :
 *   Modèle → Action → Contrôleur → Route → Policy → Ressource → Request → Test
 *
 * Intégration Pipeline :
 *   - Calcul du Score de Santé (Health Score)
 *   - Gestion de Baseline (différence entre l'état actuel et l'état sauvegardé)
 *   - Détection de vulnérabilités de sécurité (Injections SQL)
 *   - Résumé style Pipeline CI/CD
 *
 * Usage :
 *   node scripts/flow-integrity.mjs              # Scan complet
 *   node scripts/flow-integrity.mjs --json       # Rapport JSON
 *   node scripts/flow-integrity.mjs --save-baseline # Sauvegarder l'état actuel
 *   node scripts/flow-integrity.mjs --diff       # Comparer avec la baseline
 *   node scripts/flow-integrity.mjs --model Ad   # Tracer un modèle
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT = path.resolve(__dirname, '..');
const BASELINE_FILE = '.flow-integrity-baseline.json';

// ════════════════════════════════════════════
//  COULEURS ANSI (Optimisées pour la visibilité)
// ════════════════════════════════════════════

const NO_COLOR = process.env.NO_COLOR !== undefined || process.argv.includes('--no-color');
const c = NO_COLOR ? {
  red: s => s, green: s => s, yellow: s => s, blue: s => s, cyan: s => s,
  magenta: s => s, dim: s => s, bold: s => s, underline: s => s,
} : {
  red: s => `\x1b[31m${s}\x1b[0m`,
  green: s => `\x1b[32m${s}\x1b[0m`,
  yellow: s => `\x1b[33m${s}\x1b[0m`,
  blue: s => `\x1b[34m${s}\x1b[0m`,
  cyan: s => `\x1b[36m${s}\x1b[0m`,
  magenta: s => `\x1b[35m${s}\x1b[0m`,
  dim: s => `\x1b[2m${s}\x1b[0m`,
  bold: s => `\x1b[1m${s}\x1b[0m`,
  underline: s => `\x1b[4m${s}\x1b[0m`,
};

// ════════════════════════════════════════════
//  UTILITAIRES DE FICHIERS & CACHE (Optimisation)
// ════════════════════════════════════════════

const _fileCache = new Map();
function readFile(relPath) {
  if (_fileCache.has(relPath)) return _fileCache.get(relPath);
  try {
    const content = fs.readFileSync(path.join(ROOT, relPath), 'utf8');
    _fileCache.set(relPath, content);
    return content;
  } catch {
    _fileCache.set(relPath, null);
    return null;
  }
}

function walkDir(relDir, exts = ['.php']) {
  const dir = path.join(ROOT, relDir);
  if (!fs.existsSync(dir)) return [];
  const results = [];
  (function walk(d) {
    for (const entry of fs.readdirSync(d, { withFileTypes: true })) {
      if (entry.name === 'vendor' || entry.name === 'node_modules' || entry.name === '.git' || entry.name === 'storage') continue;
      const full = path.join(d, entry.name);
      if (entry.isDirectory()) walk(full);
      else if (exts.some(e => entry.name.endsWith(e))) {
        results.push(full.replace(ROOT + path.sep, '').replace(/\\/g, '/'));
      }
    }
  })(dir);
  return results;
}

// ════════════════════════════════════════════════════════
//  PARSERS (Analyse Robuste de la Structure Laravel)
// ════════════════════════════════════════════════════════

function parseModels() {
  const modelFiles = walkDir('app/Models');
  const models = {};
  for (const file of modelFiles) {
    const src = readFile(file);
    if (!src) continue;
    const nameMatch = src.match(/class\s+(\w+)/);
    if (!nameMatch) continue;
    const modelName = nameMatch[1];
    const tableMatch = src.match(/protected\s+\$table\s*=\s*['"]([^'"]+)['"]/);
    const tableName = tableMatch ? tableMatch[1] : modelName.toLowerCase();
    const relations = [];
    const relRe = /public\s+function\s+(\w+)\s*\(\)\s*:\s*(BelongsTo|HasMany|HasOne|BelongsToMany|MorphTo|MorphMany)/g;
    let rm;
    while ((rm = relRe.exec(src)) !== null) relations.push({ name: rm[1], type: rm[2] });
    models[modelName] = { name: modelName, table: tableName, file, relations };
  }
  return models;
}

function parseActions() {
  const actionFiles = walkDir('app/Actions');
  const actions = {};
  for (const file of actionFiles) {
    const src = readFile(file);
    if (!src) continue;
    const nameMatch = src.match(/class\s+(\w+)/);
    if (!nameMatch) continue;
    const actionName = nameMatch[1];
    const modelsUsed = [];
    const useRe = /use\s+App\\Models\\(\w+);/g;
    let um;
    while ((um = useRe.exec(src)) !== null) modelsUsed.push(um[1]);
    actions[actionName] = { name: actionName, file, modelsUsed };
  }
  return actions;
}

function parseControllers() {
  const controllerFiles = walkDir('app/Http/Controllers');
  const controllers = {};
  for (const file of controllerFiles) {
    const src = readFile(file);
    if (!src) continue;
    const nameMatch = src.match(/class\s+(\w+)/);
    if (!nameMatch) continue;
    const controllerName = nameMatch[1];
    const methods = [];
    const methodRe = /public\s+function\s+(\w+)\s*\(/g;
    let mm;
    while ((mm = methodRe.exec(src)) !== null) {
      if (!['__construct', 'middleware'].includes(mm[1])) methods.push(mm[1]);
    }
    const modelsUsed = [];
    const useModelRe = /use\s+App\\Models\\(\w+);/g;
    let um;
    while ((um = useModelRe.exec(src)) !== null) modelsUsed.push(um[1]);
    const requestsUsed = [];
    const useRequestRe = /use\s+App\\Http\\Requests\\(?:Api\\V\d\\)?(\w+);/g;
    let ur;
    while ((ur = useRequestRe.exec(src)) !== null) requestsUsed.push(ur[1]);
    controllers[controllerName] = { name: controllerName, file, methods, modelsUsed, requestsUsed };
  }
  return controllers;
}

function parseRoutes() {
  const routeFiles = ['routes/api.php', 'routes/web.php'];
  const routes = [];
  for (const file of routeFiles) {
    const src = readFile(file);
    if (!src) continue;
    
    // Syntaxe classique Route::get('path', [Controller::class, 'method'])
    const routeRe = /Route::(get|post|put|patch|delete)\s*\(\s*['"]([^'"]+)['"]\s*,\s*\[\s*(\w+)::class\s*,\s*['"](\w+)['"]/g;
    let rm;
    while ((rm = routeRe.exec(src)) !== null) {
      routes.push({ method: rm[1].toUpperCase(), path: rm[2], controller: rm[3], action: rm[4], file });
    }

    // Syntaxe Groupe Controller
    const controllerGroupRe = /Route::(?:middleware\([^)]*\)->)?controller\((\w+)::class\)->group\(function\s*\(\)\s*:?\s*void\s*\{([\s\S]*?)\}\);/g;
    let cgm;
    while ((cgm = controllerGroupRe.exec(src)) !== null) {
      const controller = cgm[1];
      const body = cgm[2];
      const innerRe = /Route::(get|post|put|patch|delete)\s*\(\s*['"]([^'"]+)['"]\s*,\s*['"](\w+)['"]\)/g;
      let im;
      while ((im = innerRe.exec(body)) !== null) {
        routes.push({ method: im[1].toUpperCase(), path: im[2], controller, action: im[3], file });
      }
    }

    // Syntaxe Groupe Prefixé
    const prefixGroupRe = /Route::prefix\(['"]([^'"]+)['"]\)->(?:middleware\([^)]*\)->)?controller\((\w+)::class\)->group\(function\s*\(\)\s*:?\s*void\s*\{([\s\S]*?)\}\);/g;
    let pgm;
    while ((pgm = prefixGroupRe.exec(src)) !== null) {
      const prefix = pgm[1];
      const controller = pgm[2];
      const body = pgm[3];
      const innerRe = /Route::(get|post|put|patch|delete)\s*\(\s*['"]([^'"]+)['"]\s*,\s*['"](\w+)['"]\)/g;
      let im;
      while ((im = innerRe.exec(body)) !== null) {
        routes.push({ method: im[1].toUpperCase(), path: `${prefix}/${im[2]}`.replace(/\/+/g, '/'), controller, action: im[3], file });
      }
    }
    // Route::apiResource — génère index, store, show, update, destroy
    const apiResourceRe = /Route::apiResource\s*\(\s*['"]([^'"]+)['"]\s*,\s*(\w+)::class/g;
    let arrm;
    while ((arrm = apiResourceRe.exec(src)) !== null) {
      const controller = arrm[2];
      for (const method of ['index', 'store', 'show', 'update', 'destroy']) {
        routes.push({ method: 'RESOURCE', path: arrm[1], controller, action: method, file });
      }
    }

    // Route::resource — génère index, create, store, show, edit, update, destroy
    const resourceRe = /Route::resource\s*\(\s*['"]([^'"]+)['"]\s*,\s*(\w+)::class/g;
    let rrrm;
    while ((rrrm = resourceRe.exec(src)) !== null) {
      const controller = rrrm[2];
      for (const method of ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']) {
        routes.push({ method: 'RESOURCE', path: rrrm[1], controller, action: method, file });
      }
    }  }
  return routes;
}

function parseTests() {
  const testFiles = walkDir('tests/Feature');
  const coverage = { endpoints: new Set(), models: new Set() };
  for (const file of testFiles) {
    const src = readFile(file);
    if (!src) continue;
    const epRe = /\$(?:this->)?(?:get|post|put|patch|delete)(?:Json)?\(\s*['"]([^'"]+)['"]/g;
    let em;
    while ((em = epRe.exec(src)) !== null) coverage.endpoints.add(em[1].replace(/\{[^}]+\}/g, '*'));
    const modRe = /(\w+)::factory\(/g;
    let mm;
    while ((mm = modRe.exec(src)) !== null) coverage.models.add(mm[1]);
  }
  return coverage;
}

// ════════════════════════════════════════════════════════
//  SCORE DE SANTÉ & GESTION DE LA BASELINE
// ════════════════════════════════════════════════════════

function computeHealthScore(findings, inventory) {
  const errorW = findings.filter(f => f.severity === 'error').length * 10;
  const warnW = findings.filter(f => f.severity === 'warning').length * 3;
  const infoW = findings.filter(f => f.severity === 'info').length * 0.5;
  const penalty = errorW + warnW + infoW;
  const size = (inventory.models || 1) + (inventory.controllers || 1) + (inventory.routes || 1);
  return Math.max(0, Math.min(100, Math.round(100 - (penalty / size) * 50)));
}

function saveBaseline(findings, inventory) {
  const data = {
    timestamp: new Date().toISOString(),
    findings: findings.map(f => `${f.category}::${f.message}`),
    inventory,
  };
  fs.writeFileSync(path.join(ROOT, BASELINE_FILE), JSON.stringify(data, null, 2));
  return data;
}

function loadBaseline() {
  try {
    return JSON.parse(fs.readFileSync(path.join(ROOT, BASELINE_FILE), 'utf8'));
  } catch {
    return null;
  }
}

// ════════════════════════════════════════════════════════
//  EXÉCUTION PRINCIPALE (Interface en Français)
// ════════════════════════════════════════════════════════

async function main() {
  const isJson = process.argv.includes('--json');
  const shouldSaveBaseline = process.argv.includes('--save-baseline');
  const shouldDiff = process.argv.includes('--diff');
  const modelFilter = process.argv.includes('--model') ? process.argv[process.argv.indexOf('--model') + 1] : null;

  if (!isJson) {
    console.log(c.bold(c.cyan('\n ═══════════════════════════════════════════════════════════')));
    console.log(c.bold(c.cyan('  ImmoApp Flow Integrity Scanner v2.1 — ÉDITION ULTIME')));
    console.log(c.bold(c.cyan(' ═══════════════════════════════════════════════════════════\n')));
  }

  const models = parseModels();
  const actions = parseActions();
  const controllers = parseControllers();
  const routes = parseRoutes();
  const tests = parseTests();
  const findings = [];

  // --- Vérifications d'Intégrité ---

  // Pré-calcul : index de tous les fichiers PHP de app/ pour les vérifications de référence
  const allAppPhpFiles = walkDir('app');

  // 1. Composants Orphelins
  // Cherche dans TOUS les fichiers PHP de app/ (controllers, actions, policies, mails, observers, autres models...)
  for (const [name, model] of Object.entries(models)) {
    const isReferenced = allAppPhpFiles.some(file => {
      if (file === model.file) return false; // ignorer le fichier du modèle lui-même
      const src = readFile(file);
      if (!src) return false;
      // Vérifie : import qualifié, FQCN, instantiation, type-hint, appel statique
      return (
        src.includes(`\\${name}`) ||
        src.includes(`${name}::`) ||
        src.includes(`${name} $`) ||
        src.includes(`(${name} `) ||
        new RegExp(`[^\\w]${name}[^\\w]`).test(src)
      );
    });
    if (!isReferenced && !tests.models.has(name)) {
      findings.push({ severity: 'warning', category: 'modèle-orphelin', message: `Le modèle "${name}" semble inutilisé.` });
    }
  }

  // 2. Actions non routées
  // Heuristique : si le contrôleur est importé dans un fichier de routes ET la méthode
  // apparaît comme string littéral n'importe où dans ce fichier → considérée routée.
  // Cela couvre les chaînes prefix()->controller()->middleware()->group() et groupes imbriqués.
  const routeFiles = ['routes/api.php', 'routes/web.php'];
  const routeFileContents = routeFiles.map(f => ({ file: f, src: readFile(f) || '' }));

  for (const [name, controller] of Object.entries(controllers)) {
    // Trouver le(s) fichier(s) de routes qui importent ce contrôleur
    const relevantRouteSrc = routeFileContents
      .filter(({ src }) => src.includes(`${name}::class`) || src.includes(`${name}Controller`))
      .map(({ src }) => src)
      .join('\n');

    for (const method of controller.methods) {
      // Vérification 1 : route explicitement parsée
      const hasExplicitRoute = routes.some(r => r.controller === name && r.action === method);
      // Vérification 2 : méthode apparaît comme string dans le(s) fichier(s) de routes pertinents
      const appearsAsString = relevantRouteSrc.includes(`'${method}'`) || relevantRouteSrc.includes(`"${method}"`);
      if (!hasExplicitRoute && !appearsAsString && !method.startsWith('_')) {
        findings.push({ severity: 'info', category: 'action-non-routée', message: `L'action "${name}@${method}" n'a pas de route définie.` });
      }
    }
  }

  // 3. Routes non testées
  for (const route of routes) {
    const cleanPath = route.path.replace(/\{[^}]+\}/g, '*');
    const isTested = [...tests.endpoints].some(te => te.includes(cleanPath) || cleanPath.includes(te));
    if (!isTested) findings.push({ severity: 'warning', category: 'route-non-testée', message: `La route [${route.method}] ${route.path} n'a pas de test de fonctionnalité correspondant.` });
  }

  // 4. Sécurité : Requêtes SQL brutes
  for (const [name, ctrl] of Object.entries(controllers)) {
    const src = readFile(ctrl.file);
    if (src && (src.includes('DB::raw') || src.includes('DB::select'))) {
      findings.push({ severity: 'error', category: 'sécurité-requête-brute', message: `Le contrôleur "${name}" utilise des requêtes DB brutes. Risque d'injection SQL.` });
    }
  }

  const inventory = { 
    models: Object.keys(models).length, 
    actions: Object.keys(actions).length, 
    controllers: Object.keys(controllers).length, 
    routes: routes.length, 
    testedEndpoints: tests.endpoints.size 
  };

  const healthScore = computeHealthScore(findings, inventory);

  if (shouldSaveBaseline) {
    saveBaseline(findings, inventory);
    console.log(c.green(`✅ Baseline sauvegardée dans ${BASELINE_FILE}`));
    return;
  }

  if (isJson) {
    console.log(JSON.stringify({ inventaire: inventory, résultats: findings, scoreSanté: healthScore }, null, 2));
    return;
  }

  // --- Affichage du Résumé ---
  console.log(c.bold('▸ Inventaire du Système'));
  console.log(`  ${c.blue('Modèles :')}      ${inventory.models}`);
  console.log(`  ${c.blue('Actions :')}      ${inventory.actions}`);
  console.log(`  ${c.blue('Contrôleurs :')}  ${inventory.controllers}`);
  console.log(`  ${c.blue('Routes :')}       ${inventory.routes}`);
  console.log(`  ${c.blue('Tests :')}        ${inventory.testedEndpoints} endpoints couverts\n`);

  const scoreColor = healthScore > 90 ? c.green : (healthScore > 70 ? c.yellow : c.red);
  console.log(`${c.bold('▸ Score de Santé :')} ${scoreColor(healthScore + '/100')}\n`);

  if (shouldDiff) {
    const baseline = loadBaseline();
    if (!baseline) {
      console.log(c.red('❌ Aucune baseline trouvée. Lancez avec --save-baseline d\'abord.'));
    } else {
      const currentKeys = new Set(findings.map(f => `${f.category}::${f.message}`));
      const baseKeys = new Set(baseline.findings);
      const newFindings = findings.filter(f => !baseKeys.has(`${f.category}::${f.message}`));
      const fixedCount = [...baseKeys].filter(k => !currentKeys.has(k)).length;

      console.log(c.bold('▸ Différence vs Baseline'));
      console.log(`  Nouveaux problèmes : ${newFindings.length > 0 ? c.red(newFindings.length) : c.green('0')}`);
      console.log(`  Problèmes résolus :  ${c.green(fixedCount)}\n`);
      
      if (newFindings.length > 0) {
        console.log(c.bold('--- Nouveaux Résultats ---'));
        newFindings.forEach(f => {
          const color = f.severity === 'error' ? c.red : (f.severity === 'warning' ? c.yellow : c.dim);
          console.log(`${color(`[${f.severity.toUpperCase()}]`)} ${c.bold(f.category)} : ${f.message}`);
        });
        console.log('');
      }
    }
  } else {
    console.log(c.bold('--- Résultats d\'Intégrité ---'));
    findings.sort((a, b) => a.severity === 'error' ? -1 : 1).slice(0, 30).forEach(f => {
      const color = f.severity === 'error' ? c.red : (f.severity === 'warning' ? c.yellow : c.dim);
      console.log(`${color(`[${f.severity.toUpperCase()}]`)} ${c.bold(f.category)} : ${f.message}`);
    });
    if (findings.length > 30) console.log(c.dim(`... et ${findings.length - 30} autres résultats.`));
  }

  if (modelFilter && models[modelFilter]) {
    const m = models[modelFilter];
    console.log(c.bold(c.magenta(`\n--- Trace du Modèle : ${modelFilter} ---`)));
    console.log(`  Fichier :   ${m.file}`);
    console.log(`  Table :     ${m.table}`);
    console.log(`  Relations : ${m.relations.map(r => `${r.name} (${r.type})`).join(', ') || 'Aucune'}`);
    const usedBy = Object.values(controllers).filter(c => c.modelsUsed.includes(modelFilter)).map(c => c.name);
    console.log(`  Utilisé par : ${usedBy.join(', ') || 'Aucun contrôleur'}`);
  }

  console.log(c.bold(c.cyan('\n ═══════════════════════════════════════════════════════════\n')));
}

main().catch(err => { console.error(err); process.exit(1); });
