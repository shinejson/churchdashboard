/**
 * PHP-subset interpreter: executes the AST produced by parser.cjs.
 *
 * Scope model
 * -----------
 * - Functions get their own scope; `global $x` pulls a variable in from the
 *   global scope (PHP semantics), and `$GLOBALS` is exposed as a live view.
 * - `require`/`include` resolve relative to the including file, honour
 *   `__DIR__`/`__FILE__`, and `*_once` variants are deduplicated.
 * - Output is captured into a buffer; `echo` appends to it.
 *
 * Deliberately unsupported (throws, so gaps are loud rather than silently
 * rendering wrong markup): classes/objects, method calls, namespaces, references,
 * heredoc, match, switch, generators, static variable caching.
 */

const fs = require('fs');
const path = require('path');
const { tokenize, Parser, PhpError } = require('./parser.cjs');
const {
  PHP_FUNCTIONS, PhpArray, PhpExit, PhpRuntimeError,
  isPhpArray, toPhpArray, phpTruthy, phpToString, phpConcat, phpEquals,
  PhpObject,
  isPhpObject,
} = require('./runtime.cjs');

class PhpInterpreterError extends Error {}

class Scope {
  constructor(parent = null, name = 'global') {
    this.vars = new Map();
    this.parent = parent;
    this.name = name;
    this.globals = []; // names imported via `global`
  }
  lookup(name) {
    let scope = this;
    while (scope) {
      if (scope.vars.has(name)) return scope;
      scope = scope.parent;
    }
    return null;
  }
  get(name) {
    if (this.globals.includes(name) && this.globalScope) return this.globalScope.vars.get(name) ?? null;
    const found = this.lookup(name);
    return found ? found.vars.get(name) : null;
  }
  has(name) {
    if (this.globals.includes(name) && this.globalScope) return this.globalScope.vars.has(name);
    return this.lookup(name) !== null;
  }
  set(name, value) {
    if (this.globals.includes(name) && this.globalScope) {
      this.globalScope.vars.set(name, value);
      return value;
    }
    this.vars.set(name, value);
    return value;
  }
  unset(name) {
    if (this.globals.includes(name) && this.globalScope) { this.globalScope.vars.delete(name); return; }
    const found = this.lookup(name);
    if (found) found.vars.delete(name);
  }
}

class Interpreter {
  constructor(options = {}) {
    this.rootDir = options.rootDir || process.cwd();
    this.predefined = options.predefined || {};   // $_SESSION, $_SERVER, ...
    this.constants = new Map(Object.entries(options.constants || {}));
    this.functions = new Map();                   // user-defined, global
    this.includedOnce = new Set();
    this.output = '';
    this.verbose = options.verbose !== false;
    this.allowUnsupported = options.allowUnsupported || false;
    this.maxSteps = options.maxSteps || 2000000;
    this.steps = 0;
    // Map of absolute path -> replacement PHP source for includes the subset
    // cannot execute (DB-backed files). See harness.cjs.
    this.mockIncludes = options.mockIncludes || new Map();

    this.globalScope = new Scope(null, 'global');
    for (const [name, value] of Object.entries(this.predefined)) {
      this.globalScope.vars.set(name, value);
    }
  }

  // ---- entry points ----

  runFile(file, scope = null) {
    const abs = path.resolve(this.rootDir, file);
    if (!fs.existsSync(abs)) throw new PhpInterpreterError(`Cannot open ${file} (resolved ${abs})`);
    const src = fs.readFileSync(abs, 'utf8');
    return this.runSource(src, abs, scope);
  }

  runSource(src, filename = 'inline', scope = null) {
    const tokens = tokenize(src);
    const parser = new Parser(tokens, filename);
    const program = parser.parseProgram();
    const useScope = scope || this.globalScope;
    useScope.currentFile = filename;
    const result = this.execBlock(program.body, useScope, filename);
    return result;
  }

  getOutput() { return this.output; }

  // ---- execution ----

  tick() {
    if (++this.steps > this.maxSteps) throw new PhpInterpreterError('Step limit exceeded (infinite loop?)');
  }

  execBlock(body, scope, filename) {
    let returned = null;
    for (const node of body) {
      this.tick();
      const control = this.exec(node, scope, filename);
      if (control && control.type === '__return') { returned = control; break; }
      if (control && (control.type === '__break' || control.type === '__continue' || control.type === '__throw')) {
        returned = control; break;
      }
    }
    return returned;
  }

  exec(node, scope, filename) {
    if (!node) return null;
    filename = filename || scope.currentFile || 'inline';

    switch (node.type) {
      case 'Raw':
        this.output += node.value;
        return null;

      case 'Echo': {
        for (const expr of node.exprs) {
          const value = this.eval(expr, scope, filename);
          if (value === null || value === undefined) continue;
          this.output += isPhpArray(value) ? 'Array' : phpToString(value);
        }
        return null;
      }

      case 'ExpressionStatement':
        this.eval(node.expr, scope, filename);
        return null;

      case 'Block':
        return this.execBlock(node.body, scope, filename);

      case 'If': {
        for (const clause of node.clauses) {
          if (phpTruthy(this.eval(clause.condition, scope, filename))) {
            return this.execBlock(clause.body, scope, filename);
          }
        }
        if (node.elseBody) return this.execBlock(node.elseBody, scope, filename);
        return null;
      }

      case 'Foreach': {
        const source = this.eval(node.source, scope, filename);
        const entries = this.iterableEntries(source);
        for (const [key, value] of entries) {
          this.tick();
          this.assignTo(node.valueExpr, value, scope, filename);
          if (node.keyExpr) this.assignTo(node.keyExpr, key, scope, filename);
          const control = this.execBlock(node.body, scope, filename);
          if (control) {
            if (control.type === '__break') break;
            if (control.type === '__continue') continue;
            return control;
          }
        }
        return null;
      }

      case 'For': {
        for (const init of node.init) this.eval(init, scope, filename);
        for (;;) {
          this.tick();
          if (node.test && !phpTruthy(this.eval(node.test, scope, filename))) break;
          const control = this.execBlock(node.body, scope, filename);
          if (control) {
            if (control.type === '__break') { for (const u of node.update) this.eval(u, scope, filename); break; }
            if (control.type !== '__continue') return control;
          }
          for (const u of node.update) this.eval(u, scope, filename);
        }
        return null;
      }

      case 'While': {
        while (phpTruthy(this.eval(node.test, scope, filename))) {
          this.tick();
          const control = this.execBlock(node.body, scope, filename);
          if (control) {
            if (control.type === '__break') break;
            if (control.type === '__continue') continue;
            return control;
          }
        }
        return null;
      }

      case 'FunctionDecl': {
        this.functions.set(node.name.toLowerCase(), { node, definedIn: filename });
        return null;
      }

      case 'ConstDecl':
        this.constants.set(node.name, this.eval(node.value, scope, filename));
        return null;

      case 'Return':
        return { type: '__return', value: node.expr ? this.eval(node.expr, scope, filename) : null };

      case 'Break': return { type: '__break' };
      case 'Continue': return { type: '__continue' };

      case 'Throw': {
        const value = this.eval(node.expr, scope, filename);
        return { type: '__throw', value };
      }

      case 'Try': {
        const control = this.execBlock(node.body, scope, filename);
        if (control && control.type === '__throw') {
          if (node.catchClause) {
            if (node.catchClause.varName) scope.set(node.catchClause.varName, control.value);
            const catchControl = this.execBlock(node.catchClause.body, scope, filename);
            if (node.finallyBody) this.execBlock(node.finallyBody, scope, filename);
            return catchControl;
          }
        }
        if (node.finallyBody) this.execBlock(node.finallyBody, scope, filename);
        return control && control.type === '__throw' ? null : control;
      }

      case 'Global':
        scope.globals.push(...node.names);
        scope.globalScope = this.globalScope;
        return null;

      case 'Unset':
        for (const target of node.targets) this.unsetTarget(target, scope, filename);
        return null;

      case 'Require': {
        const resolved = this.eval(node.expr, scope, filename);
        return this.doInclude(phpToString(resolved), node.once, scope, filename);
      }

      default:
        this.eval(node, scope, filename);
        return null;
    }
  }

  doInclude(target, once, scope, filename) {
    const currentDir = path.dirname(filename);
    let abs = path.isAbsolute(target) ? target : path.resolve(currentDir, target);
    abs = path.normalize(abs);

    // Mocked includes stand in for files the subset cannot execute (they use
    // classes/PDO). The harness supplies stand-ins that define the same
    // functions and emit the same structural markup.
    if (this.mockIncludes && this.mockIncludes.has(abs)) {
      if (once && this.includedOnce.has(abs)) return null;
      if (once) this.includedOnce.add(abs);
      const mock = this.mockIncludes.get(abs);
      const previousFile = scope.currentFile;
      scope.currentFile = abs;
      const control = this.runSource(mock, abs, scope);
      scope.currentFile = previousFile;
      return control;
    }

    if (!fs.existsSync(abs)) {
      const msg = `include(${target}) failed: ${abs} not found (from ${filename})`;
      if (once) { this.output += `<!-- ${msg} -->`; return null; }
      throw new PhpInterpreterError(msg);
    }
    if (once && this.includedOnce.has(abs)) return null;
    if (once) this.includedOnce.add(abs);

    const src = fs.readFileSync(abs, 'utf8');
    const tokens = tokenize(src);
    const parser = new Parser(tokens, abs);
    const program = parser.parseProgram();

    // PHP includes share the including scope's variables.
    const previousFile = scope.currentFile;
    scope.currentFile = abs;
    const control = this.execBlock(program.body, scope, abs);
    scope.currentFile = previousFile;
    return control;
  }

  iterableEntries(value) {
    if (value === null || value === undefined) return [];
    if (isPhpArray(value)) return value.entries();
    if (Array.isArray(value)) return value.map((v, i) => [i, v]);
    if (typeof value === 'string') return [...value].map((c, i) => [i, c]);
    if (typeof value === 'object') return Object.entries(value);
    return [];
  }

  unsetTarget(node, scope, filename) {
    if (node.type === 'Variable') { scope.unset(node.name); return; }
    if (node.type === 'Index') {
      const container = this.evalReference(node.object, scope, filename);
      const key = node.index ? this.eval(node.index, scope, filename) : null;
      if (isPhpArray(container) && key !== null) container.unset(key);
    }
  }

  // ---- assignment targets ----

  assignTo(target, value, scope, filename) {
    if (!target) return value;
    if (target.type === 'Variable') return scope.set(target.name, value);

    if (target.type === 'Index') {
      const container = this.evalReference(target.object, scope, filename);
      const key = target.index === null ? null : this.eval(target.index, scope, filename);
      if (isPhpArray(container)) {
        container.set(key, value);
        return value;
      }
      if (Array.isArray(container)) {
        if (key === null) container.push(value);
        else container[key] = value;
        return value;
      }
      throw new PhpInterpreterError(`Cannot assign to index of non-array in ${filename}`);
    }

    throw this.unsupported(`assignment to ${target.type}`, filename);
  }

  evalReference(node, scope, filename) {
    if (node.type === 'Variable') {
      // $GLOBALS['x'] = ... writes straight into global scope; auto-vivifying
      // it to null here would turn the write into "assign to index of null".
      if (node.name === '$GLOBALS') return this.eval(node, scope, filename);
      if (!scope.has(node.name)) scope.set(node.name, null);
      return scope.get(node.name);
    }
    if (node.type === 'Index') {
      const container = this.evalReference(node.object, scope, filename);
      const key = node.index ? this.eval(node.index, scope, filename) : null;
      if (isPhpArray(container)) {
        if (!container.has(key)) container.set(key, null);
        return container.get(key);
      }
    }
    return this.eval(node, scope, filename);
  }

  unsupported(feature, filename) {
    const msg = `Unsupported PHP feature: ${feature} (in ${filename}). `
      + 'Extend the interpreter, or keep this construct out of frontend/layout/*.php.';
    if (this.allowUnsupported) {
      if (this.verbose) console.warn('  ! ' + msg);
      return new PhpInterpreterError(msg);
    }
    return new PhpInterpreterError(msg);
  }

  // ---- expression evaluation ----

  eval(node, scope, filename) {
    filename = filename || scope.currentFile || 'inline';
    if (node === null || node === undefined) return null;

    switch (node.type) {
      case 'Literal': return node.value;
      case 'Constant': return this.evalConstant(node.name, filename);

      // `PDO::FETCH_ASSOC` and friends. Only the fetch-mode constants this repo
      // actually references are given their real values; anything else resolves
      // to a stable placeholder so a page still renders instead of aborting.
      case 'ClassConstant': {
        const cls = node.className ? String(node.className) : '';
        const member = String(node.name);
        const key = cls + '::' + member;
        const known = {
          'PDO::FETCH_ASSOC': 2,
          'PDO::FETCH_NUM': 3,
          'PDO::FETCH_BOTH': 4,
          'PDO::FETCH_OBJ': 5,
          'PDO::FETCH_CLASS': 8,
          'PDO::PARAM_STR': 2,
          'PDO::PARAM_INT': 1,
          'PDO::PARAM_BOOL': 5,
          'PDO::PARAM_NULL': 0,
          'PDO::ATTR_ERRMODE': 3,
          'PDO::ERRMODE_EXCEPTION': 2,
        };
        if (Object.prototype.hasOwnProperty.call(known, key)) return known[key];
        if (this.constants.has(key)) return this.constants.get(key);
        return key;
      }

      // A static method call on a class this harness does not model. It yields a
      // null object rather than aborting, so pages that only touch these inside
      // a DB try/catch still render their shell.
      case 'StaticCall': {
        this.eval(node.object, scope, filename);
        node.args.forEach((a) => this.eval(a, scope, filename));
        return null;
      }

      case 'String': {
        if (node.parts === null || node.parts === undefined) return node.value;
        return node.parts.map((part) =>
          part.type === 'lit' ? part.value : phpToString(this.eval(this.parseExprSnippet(part.value, filename), scope, filename))
        ).join('');
      }

      case 'Variable': {
        if (node.name === '$GLOBALS') {
          // PHP's $GLOBALS is a live view of global scope, not a copy: writing
          // $GLOBALS['x'] inside a function must be visible outside it. The
          // PhpArray is built once over the scope's own Map and reused, so both
          // reads and writes stay in sync.
          if (!this.globalsProxy) this.globalsProxy = new PhpArray();
          this.globalsProxy.map = this.globalScope.vars;
          return this.globalsProxy;
        }
        return scope.get(node.name);
      }

      case 'ArrayLiteral': {
        const arr = new PhpArray();
        for (const item of node.items) {
          const key = item.key ? this.eval(item.key, scope, filename) : null;
          arr.set(key, this.eval(item.value, scope, filename));
        }
        return arr;
      }

      case 'Assign': {
        const value = this.eval(node.value, scope, filename);
        if (node.op === '=') return this.assignTo(node.target, value, scope, filename);
        const current = this.eval(node.target, scope, filename);
        let next;
        switch (node.op) {
          case '+=': next = Number(current) + Number(value); break;
          case '-=': next = Number(current) - Number(value); break;
          case '*=': next = Number(current) * Number(value); break;
          case '/=': next = Number(current) / Number(value); break;
          case '.=': next = phpConcat(current, value); break;
          case '??=': next = current === null || current === undefined ? value : current; break;
          default: throw this.unsupported(`assignment operator ${node.op}`, filename);
        }
        return this.assignTo(node.target, next, scope, filename);
      }

      case 'Ternary': {
        const cond = this.eval(node.cond, scope, filename);
        if (node.consequent === null) {
          // Elvis: $a ?: $b
          return phpTruthy(cond) ? cond : this.eval(node.alternate, scope, filename);
        }
        return phpTruthy(cond) ? this.eval(node.consequent, scope, filename) : this.eval(node.alternate, scope, filename);
      }

      case 'Binary': return this.evalBinary(node, scope, filename);

      case 'Unary': {
        const operand = this.eval(node.operand, scope, filename);
        switch (node.op) {
          case '!': return !phpTruthy(operand);
          case '-': return -Number(operand);
          case '+': return Number(operand);
          case '~': return ~Number(operand);
          default: throw this.unsupported(`unary ${node.op}`, filename);
        }
      }

      case 'Cast': {
        const value = this.eval(node.operand, scope, filename);
        switch (node.cast) {
          case 'string': return phpToString(value);
          case 'int': case 'integer': return parseInt(Number(value), 10) || 0;
          case 'float': case 'double': return parseFloat(value) || 0;
          case 'bool': case 'boolean': return phpTruthy(value);
          case 'array': return isPhpArray(value) ? value : value === null ? new PhpArray() : new PhpArray([[null, value]]);
          case 'mixed': case 'iterable': case 'callable': case 'void': case 'never': return value;
          case 'object': return value;
          default: throw this.unsupported(`cast (${node.cast})`, filename);
        }
      }

      case 'IncDec': {
        const current = Number(this.eval(node.target, scope, filename)) || 0;
        const next = node.op === '++' ? current + 1 : current - 1;
        this.assignTo(node.target, next, scope, filename);
        return node.prefix ? next : current;
      }

      case 'Index': {
        const container = this.eval(node.object, scope, filename);
        const key = node.index ? this.eval(node.index, scope, filename) : null;
        return this.indexValue(container, key, filename);
      }

      case 'Call': {
        // A call whose callee is an expression (e.g. `$button(...)`) keeps the
        // node; a plain `name(...)` call passes the identifier string.
        const callee = node.calleeExpr || node.name;
        return this.callFunction(callee, node.args, scope, filename);
      }

      case 'Closure': {
        const captured = new Map();
        if (node.arrow) {
          // Arrow functions capture the whole enclosing scope by value.
          for (const [k, v] of scope.vars.entries()) captured.set(k, v);
        } else {
          for (const name of (node.uses || [])) captured.set(name, scope.get(name));
        }
        const interp = this;
        const fn = (...args) => interp.callClosure(node, args, scope, captured, filename);
        fn.phpClosure = node;
        return fn;
      }

      // Objects are not modelled: `new X()` yields a permissive PhpObject so a
      // page's own DB block runs to completion with empty results instead of
      // aborting the render. See PhpObject in runtime.cjs.
      case 'New': {
        node.args.forEach((a) => this.eval(a, scope, filename));
        return new PhpObject(node.className);
      }

      case 'MethodCall': {
        const object = this.eval(node.object, scope, filename);
        node.args.forEach((a) => this.eval(a, scope, filename));
        if (isPhpObject(object)) return object.call(node.method);
        throw this.unsupported(`method call ->${node.method}() on a non-object`, filename);
      }

      case 'Property': {
        const object = this.eval(node.object, scope, filename);
        if (isPhpObject(object)) {
          return object.props.has(node.name) ? object.props.get(node.name) : null;
        }
        throw this.unsupported(`property access ->${node.name} on a non-object`, filename);
      }

      case 'FunctionDecl':
        this.functions.set(node.name.toLowerCase(), { node, definedIn: filename });
        return null;

      default:
        throw this.unsupported(`expression ${node.type}`, filename);
    }
  }

  indexValue(container, key, filename) {
    if (container === null || container === undefined) return null;
    if (isPhpArray(container)) return container.get(key);
    if (Array.isArray(container)) {
      if (key === null) return null;
      const idx = typeof key === 'number' ? key : parseInt(key, 10);
      return Number.isNaN(idx) ? null : container[idx] ?? null;
    }
    if (typeof container === 'string') {
      const idx = Number(key);
      return container[idx] ?? null;
    }
    if (typeof container === 'object') return container[key] ?? null;
    return null;
  }

  evalBinary(node, scope, filename) {
    const op = node.op;
    if (op === '&&') return phpTruthy(this.eval(node.left, scope, filename)) && phpTruthy(this.eval(node.right, scope, filename));
    if (op === '||') return phpTruthy(this.eval(node.left, scope, filename)) || phpTruthy(this.eval(node.right, scope, filename));
    if (op === '??') {
      const left = this.evalNullSafe(node.left, scope, filename);
      return left === null || left === undefined ? this.eval(node.right, scope, filename) : left;
    }

    const left = this.eval(node.left, scope, filename);
    const right = this.eval(node.right, scope, filename);

    switch (op) {
      case '.': return phpConcat(left, right);
      case '+':
        if (isPhpArray(left) || isPhpArray(right)) {
          const out = new PhpArray(toPhpArray(left).entries());
          for (const [k, v] of toPhpArray(right).entries()) if (!out.has(k)) out.set(k, v);
          return out;
        }
        return Number(left) + Number(right);
      case '-': return Number(left) - Number(right);
      case '*': return Number(left) * Number(right);
      case '/': {
        const divisor = Number(right);
        if (divisor === 0) throw new PhpRuntimeError('Division by zero');
        return Number(left) / divisor;
      }
      case '%': return Number(left) % Number(right);
      case '===': return phpEquals(left, right, true);
      case '!==': return !phpEquals(left, right, true);
      case '==': return phpEquals(left, right, false);
      case '!=': return !phpEquals(left, right, false);
      case '<': return this.compare(left, right) < 0;
      case '>': return this.compare(left, right) > 0;
      case '<=': return this.compare(left, right) <= 0;
      case '>=': return this.compare(left, right) >= 0;
      case '<=>': return this.compare(left, right);
      case '&': return Number(left) & Number(right);
      case '|': return Number(left) | Number(right);
      case '^': return Number(left) ^ Number(right);
      default: throw this.unsupported(`operator ${op}`, filename);
    }
  }

  compare(left, right) {
    if (typeof left === 'number' || typeof right === 'number') {
      const a = Number(left); const b = Number(right);
      return a < b ? -1 : a > b ? 1 : 0;
    }
    const a = phpToString(left); const b = phpToString(right);
    return a < b ? -1 : a > b ? 1 : 0;
  }

  /** Evaluate an operand without throwing when a link in the chain is null. */
  evalNullSafe(node, scope, filename) {
    try {
      return this.eval(node, scope, filename);
    } catch (e) {
      if (e instanceof PhpRuntimeError) return null;
      throw e;
    }
  }

  evalConstant(name, filename) {
    // Magic constants resolve against the file currently being executed, not
    // the entry file - otherwise an included file's `__DIR__ . '/x.php'` points
    // at the includer's directory and the include silently misses.
    if (name === '__DIR__') return path.dirname(filename);
    if (name === '__FILE__') return filename;

    if (this.constants.has(name)) return this.constants.get(name);
    const upper = name.toUpperCase();
    const builtin = {
      PHP_EOL: '\n', PHP_INT_MAX: 9223372036854775807, PHP_VERSION: '8.2.0',
      ENT_QUOTES: 3, ENT_COMPAT: 2, ENT_HTML5: 48, PHP_SESSION_ACTIVE: 2,
      DIRECTORY_SEPARATOR: '/', PATH_SEPARATOR: ':',
    };
    if (Object.prototype.hasOwnProperty.call(builtin, upper)) return builtin[upper];
    // Unquoted bare words are constants in PHP; treat unknown ones as strings
    // so markup like `class=foo` never appears, but log it.
    if (this.verbose) console.warn(`  ! undefined constant ${name} in ${filename}`);
    return name;
  }

  parseExprSnippet(snippet, filename) {
    const tokens = tokenize(`<?php ${snippet}`);
    const parser = new Parser(tokens, filename + ':interpolation');
    parser.next(); // <?php
    return parser.parseExpression();
  }

  // ---- calls ----

  callClosure(node, args, definingScope, captured, filename) {
    const scope = new Scope(this.globalScope, 'closure');
    scope.currentFile = filename;
    for (const [name, value] of captured.entries()) scope.vars.set(name, value);

    node.params.forEach((param, i) => {
      let value;
      if (param.variadic) {
        value = new PhpArray(args.slice(i).map((v) => [null, v]));
      } else {
        value = i < args.length ? args[i] : (param.defaultValue ? this.eval(param.defaultValue, definingScope, filename) : null);
      }
      scope.vars.set(param.name, value);
    });

    const control = this.execBlock(node.body, scope, filename);
    return control && control.type === '__return' ? control.value : null;
  }

  callFunction(nameNode, argNodes, scope, filename) {
    // `$closure(...)` - evaluate the variable and invoke the closure it holds.
    if (nameNode && typeof nameNode === 'object') {
      const args = argNodes.map((argNode) => this.eval(argNode, scope, filename));
      const target = this.eval(nameNode, scope, filename);
      if (typeof target === 'function') return target(...args);
      throw new PhpInterpreterError('Attempted to call a non-function value in ' + filename);
    }

    const name = nameNode;
    const lower = name.toLowerCase();

    // Language constructs handled specially.
    if (lower === 'isset') {
      return argNodes.every((argNode) => {
        const value = this.evalNullSafe(argNode, scope, filename);
        return value !== null && value !== undefined;
      });
    }
    if (lower === 'empty') {
      const value = this.evalNullSafe(argNodes[0], scope, filename);
      return !phpTruthy(value);
    }
    if (lower === 'define') {
      const constName = phpToString(this.eval(argNodes[0], scope, filename));
      const value = this.eval(argNodes[1], scope, filename);
      this.constants.set(constName, value);
      return true;
    }
    if (lower === 'defined') {
      const constName = phpToString(this.eval(argNodes[0], scope, filename));
      return this.constants.has(constName);
    }
    if (lower === 'constant') {
      return this.evalConstant(phpToString(this.eval(argNodes[0], scope, filename)), filename);
    }
    if (lower === 'function_exists') {
      const fnName = phpToString(this.eval(argNodes[0], scope, filename)).toLowerCase();
      return this.functions.has(fnName) || typeof PHP_FUNCTIONS[fnName] === 'function';
    }

    const args = argNodes.map((argNode) => this.eval(argNode, scope, filename));

    if (this.functions.has(lower)) {
      const { node } = this.functions.get(lower);
      const fnScope = new Scope(this.globalScope, lower);
      fnScope.currentFile = filename;
      node.params.forEach((param, i) => {
        let value;
        if (param.variadic) value = new PhpArray(args.slice(i).map((v) => [null, v]));
        else value = i < args.length ? args[i] : (param.defaultValue ? this.eval(param.defaultValue, scope, filename) : null);
        fnScope.vars.set(param.name, value);
      });
      const control = this.execBlock(node.body, fnScope, filename);
      if (control && control.type === '__throw') {
        throw new PhpRuntimeError(phpToString(control.value && control.value.message ? control.value.message : control.value));
      }
      return control && control.type === '__return' ? control.value : null;
    }

    if (lower === 'gettype') {
      const v = args[0];
      if (v === null || v === undefined) return 'NULL';
      if (typeof v === 'boolean') return 'boolean';
      if (typeof v === 'number') return Number.isInteger(v) ? 'integer' : 'double';
      if (typeof v === 'string') return 'string';
      if (isPhpArray(v) || Array.isArray(v)) return 'array';
      if (typeof v === 'function') return 'object';
      return 'object';
    }

    if (typeof PHP_FUNCTIONS[lower] === 'function') {
      try {
        return PHP_FUNCTIONS[lower](...args);
      } catch (e) {
        // exit()/die() is control flow, not a failure: rewriting it into a
        // PhpRuntimeError lost the fact that the page stopped on purpose and
        // produced a blank error message everywhere.
        if (e instanceof PhpExit) throw e;
        if (e instanceof PhpInterpreterError) throw e;
        throw new PhpRuntimeError(`${lower}() failed: ${e.message}`);
      }
    }

    if (this.allowUnsupported) {
      if (this.verbose) console.warn(`  ! undefined function ${name}() in ${filename}`);
      return null;
    }
    throw new PhpInterpreterError(`Undefined function ${name}() called in ${filename}. Add it to runtime.cjs or avoid it in the layout.`);
  }
}

module.exports = { Interpreter, PhpInterpreterError, PhpArray };
