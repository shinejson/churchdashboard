/**
 * Minimal PHP-subset interpreter (development tool, not shipped to browsers).
 *
 * Why this exists
 * ---------------
 * The sandbox has no PHP runtime, but the sidebar-page refactor moves real
 * markup into PHP renderers (frontend/layout/*). Guessing at their output is
 * how refactors break pages, so this file executes the subset of PHP the layout
 * uses and prints the rendered HTML. That output feeds the contract report and
 * the static preview.
 *
 * Supported subset (deliberately small - the layout sticks to it):
 *   - <?php ... ?> / <?= ... ?> segments interleaved with raw HTML
 *   - function declarations, `return`, early `return`
 *   - if / elseif / else, foreach (with `as $k => $v`), for, while, break, continue
 *   - echo / print, string concatenation with `.`
 *   - arrays: [] literals, nested, string and integer keys, append with []=
 *   - variables, constants via define(), global lookups
 *   - operators: . + - * / % === !== == != < > <= >= && || ! ?: ?? ternary
 *   - unary casts (string)/(int)/(bool)/(array)
 *   - short closures `static fn($x) => expr`, arrow closures `fn($x) => expr`
 *   - anonymous functions `function (...) { ... }`
 *   - property access on arrays ($a['b']), method calls are NOT supported
 *   - a library of PHP built-ins implemented in JS (see PHP_FUNCTIONS)
 *
 * Anything outside the subset throws with a clear message rather than silently
 * producing wrong HTML.
 */

const STRICT = process.env.PHP_STRICT !== '0';

class PhpError extends Error {}

// ---------------------------------------------------------------------------
// Tokenizer
// ---------------------------------------------------------------------------

const PUNCTUATORS = [
  '===', '!==', '??=', '<=>', '...',
  '==', '!=', '<>', '<=', '>=', '&&', '||', '??', '->', '=>', '++', '--',
  '+=', '-=', '*=', '/=', '.=',
  '+', '-', '*', '/', '%', '.', '=', '<', '>', '!', '?', ':', ';', ',',
  '(', ')', '[', ']', '{', '}', '@', '&', '|', '^', '~'
];

function isIdentStart(ch) { return /[A-Za-z_\\]/.test(ch); }
function isIdentChar(ch) { return /[A-Za-z0-9_\\]/.test(ch); }

function tokenize(src) {
  const tokens = [];
  let i = 0;
  let inPhp = false;
  let htmlStart = 0;

  const pushHtml = (end) => {
    if (end > htmlStart) tokens.push({ type: 'html', value: src.slice(htmlStart, end) });
  };

  while (i < src.length) {
    if (!inPhp) {
      if (src.startsWith('<?php', i)) {
        pushHtml(i);
        tokens.push({ type: 'phpOpen', value: '<?php' });
        i += 5;
        inPhp = true;
        continue;
      }
      if (src.startsWith('<?=', i)) {
        pushHtml(i);
        tokens.push({ type: 'phpOpen', value: '<?=' });
        i += 3;
        inPhp = true;
        continue;
      }
      i++;
      continue;
    }

    // Inside PHP
    if (src.startsWith('?>', i)) {
      tokens.push({ type: 'phpClose', value: '?>' });
      i += 2;
      inPhp = false;
      htmlStart = i;
      continue;
    }

    const ch = src[i];
    if (/\s/.test(ch)) { i++; continue; }

    // Comments
    if (src.startsWith('//', i) || src.startsWith('#', i)) {
      while (i < src.length && src[i] !== '\n') i++;
      continue;
    }
    if (src.startsWith('/*', i)) {
      const end = src.indexOf('*/', i + 2);
      i = end === -1 ? src.length : end + 2;
      continue;
    }

    // Strings
    if (ch === '"' || ch === "'") {
      const quote = ch;
      let j = i + 1;
      let value = '';
      const parts = [];
      let literal = '';
      while (j < src.length) {
        const c = src[j];
        if (c === '\\' && j + 1 < src.length) {
          const next = src[j + 1];
          const escapes = { n: '\n', t: '\t', r: '\r', '\\': '\\', '"': '"', "'": "'", '$': '$' };
          if (quote === '"') {
            literal += escapes[next] !== undefined ? escapes[next] : '\\' + next;
          } else {
            literal += (next === '\\' || next === "'") ? next : '\\' + next;
          }
          j += 2;
          continue;
        }
        if (quote === '"' && c === '$') {
          // Interpolation: "$var" or "{$expr}"
          parts.push({ type: 'lit', value: literal });
          literal = '';
          if (src[j + 1] === '{') {
            let depth = 1;
            let k = j + 2;
            let expr = '';
            while (k < src.length && depth > 0) {
              if (src[k] === '{') depth++;
              if (src[k] === '}') { depth--; if (depth === 0) break; }
              expr += src[k];
              k++;
            }
            parts.push({ type: 'expr', value: expr });
            j = k + 1;
            continue;
          }
          let k = j + 1;
          let name = '';
          while (k < src.length && isIdentChar(src[k])) { name += src[k]; k++; }
          // Handle $arr[key] / $arr[$k]
          let suffix = '';
          while (k < src.length && src[k] === '[') {
            let depth = 1;
            suffix += '[';
            k++;
            while (k < src.length && depth > 0) {
              if (src[k] === '[') depth++;
              if (src[k] === ']') { depth--; if (depth === 0) { suffix += ']'; k++; break; } }
              suffix += src[k];
              k++;
            }
          }
          parts.push({ type: 'expr', value: '$' + name + suffix });
          j = k;
          continue;
        }
        if (c === quote) { j++; break; }
        literal += c;
        j++;
      }
      parts.push({ type: 'lit', value: literal });
      tokens.push({
        type: 'string',
        quote,
        value: parts.length === 1 ? parts[0].value : null,
        parts: quote === '"' ? parts : null,
        raw: src.slice(i, j)
      });
      i = j;
      continue;
    }

    // Numbers
    if (/[0-9]/.test(ch)) {
      let j = i;
      let value = '';
      while (j < src.length && /[0-9_.eExXa-fA-F]/.test(src[j])) { value += src[j]; j++; }
      const isFloat = /[.eE]/.test(value) && !/^0[xX]/.test(value);
      tokens.push({ type: 'number', value: isFloat ? parseFloat(value) : parseInt(value.replace(/_/g, ''), value.startsWith('0x') ? 16 : 10) });
      i = j;
      continue;
    }

    // Variables (lexed before identifiers: `$` is not a PHP identifier
    // character, and checking first guarantees `$name` can never be swallowed
    // as an ident).
    if (ch === '$') {
      let j = i + 1;
      let value = '$';
      while (j < src.length && isIdentChar(src[j])) { value += src[j]; j++; }
      if (value.length > 1) {
        tokens.push({ type: 'var', value });
        i = j;
        continue;
      }
      // A bare `$` (e.g. inside a regex-ish string) is not a variable.
    }

    // Identifiers / keywords
    if (isIdentStart(ch)) {
      let j = i;
      let value = '';
      while (j < src.length && isIdentChar(src[j])) { value += src[j]; j++; }
      tokens.push({ type: 'ident', value });
      i = j;
      continue;
    }

    // Heredoc / nowdoc: `$x = <<<LABEL ... LABEL;` and `$x = <<<'LABEL' ... LABEL;`
    // Must be checked before the implicit-php-close rule below, since a heredoc
    // opens with `<<<`.
    if (src.startsWith('<<<', i)) {
      let j = i + 3;
      while (j < src.length && /[ \t]/.test(src[j])) j++;
      let nowdoc = false;
      const quote = src[j];
      if (quote === "'" || quote === '"') {
        nowdoc = quote === "'";
        j++;
      }
      let label = '';
      while (j < src.length && /[A-Za-z0-9_]/.test(src[j])) { label += src[j]; j++; }
      if (!label) throw new PhpError(`Malformed heredoc label at offset ${i}`);
      if (src[j] === "'" || src[j] === '"') j++;
      // Rest of the opening line (whitespace, then newline).
      while (j < src.length && src[j] !== '\n') j++;
      if (src[j] === '\n') j++;

      // Body runs until a line whose first non-whitespace is the closing label.
      const lines = [];
      let indent = 0;
      let closed = false;
      while (j < src.length) {
        const nl = src.indexOf('\n', j);
        const lineEnd = nl === -1 ? src.length : nl;
        const line = src.slice(j, lineEnd);
        const stripped = line.replace(/^[ \t]+/, '');
        if (stripped.startsWith(label)) {
          const after = stripped.slice(label.length);
          if (after === '' || after === ';' || /^[ \t]*;/.test(after)) {
            indent = line.length - stripped.length;
            closed = true;
            // Consume the label plus an optional `;`.
            j = lineEnd + (nl === -1 ? 0 : 1);
            while (j < src.length && /[ \t]/.test(src[j])) j++;
            if (src[j] === ';') j++;
            break;
          }
        }
        lines.push(line);
        if (nl === -1) { j = src.length; break; }
        j = nl + 1;
      }
      if (!closed) throw new PhpError(`Unterminated heredoc '${label}' starting at offset ${i}`);

      // PHP 7.3+ strips the closing indentation from every body line.
      const body = lines
        .map((line) => (indent > 0 ? line.slice(Math.min(indent, line.length - line.trimStart().length)) : line))
        .join('\n');

      if (nowdoc) {
        tokens.push({ type: 'string', quote: "'", value: body, parts: null, raw: body });
      } else {
        // Heredoc interpolates like a double-quoted string: reuse that machinery
        // by re-tokenizing the body as a quoted string.
        const inner = tokenize('<?php "' + body.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '" ?>');
        const strTok = inner.find((t) => t.type === 'string');
        tokens.push(strTok || { type: 'string', quote: '"', value: body, parts: null, raw: body });
      }
      i = j;
      continue;
    }

    // Inside PHP, a `<` that does not open a tag (`<?php` / `<?=`) is not PHP
    // source. Real PHP treats this as a parse error; here it means the file
    // left PHP mode without a closing `?>`, so switch back to HTML and let the
    // caller see the real structure instead of a misleading operator error.
    if (ch === '<' && !src.startsWith('<?php', i) && !src.startsWith('<?=', i)) {
      tokens.push({ type: 'phpClose', value: '(implicit)' });
      inPhp = false;
      htmlStart = i;
      continue;
    }

    const punct = PUNCTUATORS.find((p) => src.startsWith(p, i));
    if (punct) {
      tokens.push({ type: 'punct', value: punct });
      i += punct.length;
      continue;
    }

    throw new PhpError(`Unexpected character '${ch}' at offset ${i}: ${src.slice(Math.max(0, i - 40), i + 40)}`);
  }

  // Only emit trailing HTML when the file actually left PHP mode; a file that
  // ends inside `<?php ... ` has no trailing HTML (htmlStart is still 0).
  if (!inPhp) pushHtml(src.length);
  return tokens;
}

// ---------------------------------------------------------------------------
// Parser
// ---------------------------------------------------------------------------

const KEYWORDS = new Set([
  'if', 'elseif', 'else', 'endif', 'foreach', 'endforeach', 'for', 'endfor',
  'while', 'endwhile', 'function', 'return', 'echo', 'print', 'break', 'continue',
  'true', 'false', 'null', 'array', 'as', 'static', 'global', 'new', 'throw',
  'try', 'catch', 'finally', 'declare', 'fn', 'use', 'require', 'require_once',
  'include', 'include_once', 'const', 'isset', 'empty', 'unset', 'list', 'enddeclare'
]);

class Parser {
  constructor(tokens, filename) {
    this.tokens = tokens;
    this.pos = 0;
    this.filename = filename;
  }

  peek(offset = 0) { return this.tokens[this.pos + offset] || null; }
  next() { return this.tokens[this.pos++] || null; }
  eof() { return this.pos >= this.tokens.length; }

  isPunct(value, offset = 0) {
    const t = this.peek(offset);
    return t && t.type === 'punct' && t.value === value;
  }
  isIdent(value, offset = 0) {
    const t = this.peek(offset);
    return t && t.type === 'ident' && t.value.toLowerCase() === value.toLowerCase();
  }
  expectPunct(value) {
    if (!this.isPunct(value)) {
      const t = this.peek();
      throw new PhpError(`Expected '${value}' but found ${t ? `${t.type} '${t.value}'` : 'EOF'} in ${this.filename}`);
    }
    return this.next();
  }
  expectIdent(value) {
    if (!this.isIdent(value)) {
      const t = this.peek();
      throw new PhpError(`Expected '${value}' but found ${t ? `${t.type} '${t.value}'` : 'EOF'} in ${this.filename}`);
    }
    return this.next();
  }

  parseProgram() {
    const body = [];
    while (!this.eof()) {
      const t = this.peek();
      if (t.type === 'html') { this.next(); body.push({ type: 'Raw', value: t.value }); continue; }
      if (t.type === 'phpOpen') {
        this.parsePhpSegment(body);
        continue;
      }
      throw new PhpError(`Unexpected token ${t.type} '${t.value}' in ${this.filename}`);
    }
    return { type: 'Program', body };
  }

  /**
   * Parse a `<?php ... ?>` or `<?= ... ?>` segment into statements.
   *
   * `<?=` is a short echo: the expression must become an Echo node, otherwise
   * it is evaluated and its value silently discarded (which is what made
   * `<?= $x ?>` render nothing inside if:/foreach: template blocks).
   *
   * `stopIdents` carries the terminators of an enclosing alternative-syntax
   * block. Templates write `<?php endif; ?>` / `<?php endforeach; ?>`, so the
   * keyword arrives INSIDE a php segment; without stopping on it the keyword is
   * parsed as a bare expression and the block swallows the rest of the file.
   */
  parsePhpSegment(body, stopIdents = null) {
    const open = this.next();
    if (open && open.value === '<?=') {
      body.push({ type: 'Echo', exprs: [this.parseExpression()] });
      if (this.isPunct(';')) this.next();
    } else {
      this.parseStatements(body, () => {
        const n = this.peek();
        if (!n || n.type === 'phpClose') return true;
        if (stopIdents && n.type === 'ident' && stopIdents.includes(n.value.toLowerCase())) return true;
        return false;
      });
    }
    if (this.peek() && this.peek().type === 'phpClose') this.next();
  }

  parseStatements(body, stop) {
    while (!this.eof() && !stop()) {
      const stmt = this.parseStatement();
      if (stmt) {
        if (Array.isArray(stmt)) body.push(...stmt);
        else body.push(stmt);
      }
      if (body.length && ['Return', 'Break', 'Continue', 'Throw'].includes(body[body.length - 1].type)) {
        // Keep parsing so trailing html/close tags are captured, but the
        // interpreter stops at the control node.
      }
    }
  }

  parseStatement() {
    const t = this.peek();
    if (!t) return null;

    if (t.type === 'punct' && t.value === ';') { this.next(); return null; }
    // `?>` implicitly terminates a statement in PHP. A `<?php` / `<?=` marker
    // reaching here means the caller is inside a template block; the close tag
    // ends the statement, and the open tag is left for the caller to parse.
    if (t.type === 'phpClose') { this.next(); return null; }
    // `?>` implicitly terminates a statement in PHP.
    if (t.type === 'phpClose') { this.next(); return null; }
    if (t.type === 'phpOpen') { this.next(); return null; }

    if (t.type === 'ident') {
      const word = t.value.toLowerCase();
      switch (word) {
        case 'if': return this.parseIf();
        case 'foreach': return this.parseForeach();
        case 'for': return this.parseFor();
        case 'while': return this.parseWhile();
        case 'function': return this.parseFunctionDecl();
        case 'return': this.next(); {
          const expr = this.isPunct(';') ? null : this.parseExpression();
          if (this.isPunct(';')) this.next();
          return { type: 'Return', expr };
        }
        case 'echo': case 'print': {
          this.next();
          const exprs = [this.parseExpression()];
          while (this.isPunct(',')) { this.next(); exprs.push(this.parseExpression()); }
          if (this.isPunct(';')) this.next();
          return { type: 'Echo', exprs };
        }
        case 'break': this.next(); {
          const n = this.isPunct(';') ? null : this.parseExpression();
          if (this.isPunct(';')) this.next();
          return { type: 'Break', expr: n };
        }
        case 'continue': this.next(); {
          const n = this.isPunct(';') ? null : this.parseExpression();
          if (this.isPunct(';')) this.next();
          return { type: 'Continue', expr: n };
        }
        case 'throw': this.next(); {
          const expr = this.parseExpression();
          if (this.isPunct(';')) this.next();
          return { type: 'Throw', expr };
        }
        case 'try': return this.parseTry();
        case 'global': this.next(); {
          const names = [];
          do { names.push(this.next().value); } while (this.isPunct(',') && this.next());
          if (this.isPunct(';')) this.next();
          return { type: 'Global', names };
        }
        case 'declare': {
          // declare(strict_types=1);
          this.next();
          this.expectPunct('(');
          while (!this.isPunct(')')) this.next();
          this.expectPunct(')');
          if (this.isPunct(';')) this.next();
          return null;
        }
        case 'const': {
          this.next();
          const name = this.next().value;
          this.expectPunct('=');
          const value = this.parseExpression();
          if (this.isPunct(';')) this.next();
          return { type: 'ConstDecl', name, value };
        }
        case 'require': case 'require_once': case 'include': case 'include_once': {
          this.next();
          if (this.isPunct('(')) { this.next(); const e = this.parseExpression(); this.expectPunct(')'); if (this.isPunct(';')) this.next(); return { type: 'Require', once: word.endsWith('once'), expr: e }; }
          const expr = this.parseExpression();
          if (this.isPunct(';')) this.next();
          return { type: 'Require', once: word.endsWith('once'), expr };
        }
        case 'static': {
          // `static function` (handled below) or `static $var = ...`
          if (this.isIdent('function', 1)) { this.next(); return this.parseFunctionDecl(true); }
          this.next();
          return this.parseStatement();
        }
        case 'unset': {
          this.next();
          this.expectPunct('(');
          const targets = [this.parseExpression()];
          while (this.isPunct(',')) { this.next(); targets.push(this.parseExpression()); }
          this.expectPunct(')');
          if (this.isPunct(';')) this.next();
          return { type: 'Unset', targets };
        }
        default: break;
      }
    }

    if (t.type === 'punct' && t.value === '{') {
      this.next();
      const body = [];
      while (!this.isPunct('}') && !this.eof()) {
        const s = this.parseStatement();
        if (s) body.push(s);
      }
      this.expectPunct('}');
      return { type: 'Block', body };
    }

    const expr = this.parseExpression();
    if (this.isPunct(';')) this.next();
    return { type: 'ExpressionStatement', expr };
  }

  parseIf() {
    this.expectIdent('if');
    const clauses = [];
    this.expectPunct('(');
    let condition = this.parseExpression();
    this.expectPunct(')');
    let body = this.parseBlockOrAlt('endif');
    clauses.push({ condition, body });
    let elseBody = null;

    while (this.isIdent('elseif')) {
      this.next();
      this.expectPunct('(');
      const c = this.parseExpression();
      this.expectPunct(')');
      const b = this.parseBlockOrAlt('endif');
      clauses.push({ condition: c, body: b });
    }
    if (this.isIdent('else')) {
      this.next();
      elseBody = this.parseBlockOrAlt('endif');
    }
    if (this.isIdent('endif')) { this.next(); if (this.isPunct(';')) this.next(); }
    return { type: 'If', clauses, elseBody };
  }

  parseBlockOrAlt(endKeyword) {
    if (this.isPunct('{')) {
      this.next();
      const body = [];
      while (!this.isPunct('}') && !this.eof()) {
        const s = this.parseStatement();
        if (s) body.push(s);
      }
      this.expectPunct('}');
      return body;
    }
    if (this.isPunct(':')) {
      this.next();
      const body = [];
      while (!this.eof()) {
        const t = this.peek();
        if (t.type === 'ident' && ['else', 'elseif', endKeyword].includes(t.value.toLowerCase())) break;
        // Template blocks interleave raw HTML with `?>` / `<?php` / `<?=`,
        // so an embedded PHP segment is parsed exactly as parseProgram would.
        // (`<?=` used to be dropped here, which discarded the echoed value.)
        if (t.type === 'html') { this.next(); body.push({ type: 'Raw', value: t.value }); continue; }
        if (t.type === 'phpOpen') {
          this.parsePhpSegment(body, ['else', 'elseif', endKeyword]);
          continue;
        }
        if (t.type === 'phpClose') { this.next(); continue; }
        const stmt = this.parseStatement();
        if (stmt) body.push(stmt);
      }
      return body;
    }
    const s = this.parseStatement();
    return s ? [s] : [];
  }

  parseForeach() {
    this.expectIdent('foreach');
    this.expectPunct('(');
    const source = this.parseExpression();
    this.expectIdent('as');
    let keyExpr = null;
    let valueExpr = this.parseExpression();
    if (this.isPunct('=>')) { this.next(); keyExpr = valueExpr; valueExpr = this.parseExpression(); }
    this.expectPunct(')');
    const body = this.parseBlockOrAlt('endforeach');
    if (this.isIdent('endforeach')) { this.next(); if (this.isPunct(';')) this.next(); }
    return { type: 'Foreach', source, keyExpr, valueExpr, body };
  }

  parseFor() {
    this.expectIdent('for');
    this.expectPunct('(');
    const init = this.isPunct(';') ? [] : this.parseExpressionList();
    this.expectPunct(';');
    const test = this.isPunct(';') ? null : this.parseExpression();
    this.expectPunct(';');
    const update = this.isPunct(')') ? [] : this.parseExpressionList();
    this.expectPunct(')');
    const body = this.parseBlockOrAlt('endfor');
    if (this.isIdent('endfor')) { this.next(); if (this.isPunct(';')) this.next(); }
    return { type: 'For', init, test, update, body };
  }

  parseWhile() {
    this.expectIdent('while');
    this.expectPunct('(');
    const test = this.parseExpression();
    this.expectPunct(')');
    const body = this.parseBlockOrAlt('endwhile');
    if (this.isIdent('endwhile')) { this.next(); if (this.isPunct(';')) this.next(); }
    return { type: 'While', test, body };
  }

  parseTry() {
    this.expectIdent('try');
    const body = this.parseBracedBlock();
    let catchClause = null;
    if (this.isIdent('catch')) {
      this.next();
      this.expectPunct('(');
      // Catch types are identifiers (`Throwable`, `Exception`), possibly
      // union-separated. They are never arbitrary expressions.
      const types = [];
      while (!this.eof() && this.peek().type !== 'var' && !this.isPunct(')')) {
        const t = this.next();
        if (t.type === 'ident') types.push(t.value);
      }
      let varName = null;
      if (this.peek() && this.peek().type === 'var') varName = this.next().value;
      this.expectPunct(')');
      const catchBody = this.parseBracedBlock();
      catchClause = { types, varName, body: catchBody };
    }
    let finallyBody = null;
    if (this.isIdent('finally')) { this.next(); finallyBody = this.parseBracedBlock(); }
    return { type: 'Try', body, catchClause, finallyBody };
  }

  parseBracedBlock() {
    this.expectPunct('{');
    const body = [];
    while (!this.isPunct('}') && !this.eof()) {
      const s = this.parseStatement();
      if (s) body.push(s);
    }
    this.expectPunct('}');
    return body;
  }

  parseFunctionDecl(isStatic = false) {
    this.expectIdent('function');
    // `function &name()` returns by reference. The reference semantics are not
    // modelled (the layout avoids them), but the token must parse.
    let returnsByRef = false;
    if (this.isPunct('&')) { this.next(); returnsByRef = true; }
    let name = null;
    if (this.peek() && this.peek().type === 'ident') name = this.next().value;
    const params = this.parseParams();
    // Anonymous functions may capture outer variables: `function ($x) use ($y) {}`
    let uses = [];
    if (this.isIdent('use')) {
      this.next();
      this.expectPunct('(');
      while (!this.isPunct(')') && !this.eof()) {
        if (this.isPunct('&')) this.next();
        const captured = this.next();
        if (captured && captured.type === 'var') uses.push(captured.value);
        if (this.isPunct(',')) this.next();
      }
      this.expectPunct(')');
    }
    let returnType = null;
    if (this.isPunct(':')) {
      this.next();
      if (this.isPunct('?')) { this.next(); returnType = '?' + this.next().value; }
      else returnType = this.next().value;
    }
    const body = this.parseBracedBlock();
    if (name) return { type: 'FunctionDecl', name, params, body, isStatic, returnType, returnsByRef };
    return { type: 'ExpressionStatement', expr: { type: 'Closure', params, body, uses, returnType } };
  }

  parseParams() {
    this.expectPunct('(');
    const params = [];
    while (!this.isPunct(')') && !this.eof()) {
      let byRef = false;
      let variadic = false;
      let typeHint = null;
      // Consume modifiers/type hints until the parameter's variable name.
      // Identifiers here are type names even when they collide with statement
      // keywords (`string`, `array`, `int`, `object`), so they are not treated
      // as keywords inside a parameter list.
      while (!this.eof()) {
        const t = this.peek();
        if (!t) break;
        if (t.type === 'var') break;
        if (t.type === 'ident') {
          if (['function', 'fn', 'use'].includes(t.value.toLowerCase())) break;
          typeHint = (typeHint ? typeHint + '|' : '') + this.next().value;
          continue;
        }
        if (t.type === 'punct' && t.value === '?') { this.next(); typeHint = '?' + (typeHint ?? ''); continue; }
        if (t.type === 'punct' && t.value === '&') { this.next(); byRef = true; continue; }
        if (t.type === 'punct' && t.value === '...') { this.next(); variadic = true; continue; }
        if (t.type === 'punct' && (t.value === '|' || t.value === ',')) { this.next(); continue; }
        break;
      }
      const varToken = this.next();
      if (!varToken || varToken.type !== 'var') {
        throw new PhpError(`Expected parameter name, found ${varToken ? `${varToken.type} '${varToken.value}'` : 'EOF'} in ${this.filename}`);
      }
      let defaultValue = null;
      if (this.isPunct('=')) { this.next(); defaultValue = this.parseExpression(); }
      params.push({ name: varToken.value, defaultValue, byRef, variadic, typeHint });
      if (this.isPunct(',')) this.next();
    }
    this.expectPunct(')');
    return params;
  }

  parseExpressionList() {
    const list = [this.parseExpression()];
    while (this.isPunct(',')) { this.next(); list.push(this.parseExpression()); }
    return list;
  }

  // ---- expressions ----

  parseExpression() { return this.parseAssign(); }

  parseAssign() {
    const left = this.parseTernary();
    const ops = ['=', '+=', '-=', '*=', '/=', '.=', '??='];
    const t = this.peek();
    if (t && t.type === 'punct' && ops.includes(t.value)) {
      this.next();
      const right = this.parseAssign();
      return { type: 'Assign', op: t.value, target: left, value: right };
    }
    return left;
  }

  parseTernary() {
    const cond = this.parseNullCoalesce();
    if (this.isPunct('?')) {
      this.next();
      const consequent = this.isPunct(':') ? null : this.parseAssign();
      this.expectPunct(':');
      const alternate = this.parseAssign();
      return { type: 'Ternary', cond, consequent, alternate };
    }
    return cond;
  }

  parseNullCoalesce() {
    let left = this.parseLogicalOr();
    while (this.isPunct('??')) {
      this.next();
      const right = this.parseLogicalOr();
      left = { type: 'Binary', op: '??', left, right };
    }
    return left;
  }

  parseLogicalOr() {
    let left = this.parseLogicalAnd();
    while (this.isPunct('||') || this.isIdent('or')) {
      const op = this.next().value === 'or' ? '||' : '||';
      const right = this.parseLogicalAnd();
      left = { type: 'Binary', op, left, right };
    }
    return left;
  }

  parseLogicalAnd() {
    let left = this.parseBitwiseOr();
    while (this.isPunct('&&') || this.isIdent('and')) {
      this.next();
      const right = this.parseBitwiseOr();
      left = { type: 'Binary', op: '&&', left, right };
    }
    return left;
  }

  parseBitwiseOr() {
    let left = this.parseEquality();
    while (this.isPunct('|') || this.isPunct('^')) {
      const op = this.next().value;
      left = { type: 'Binary', op, left, right: this.parseEquality() };
    }
    return left;
  }

  parseEquality() {
    let left = this.parseComparison();
    while (this.isPunct('===') || this.isPunct('!==') || this.isPunct('==') || this.isPunct('!=') || this.isPunct('<>')) {
      const op = this.next().value;
      left = { type: 'Binary', op: op === '<>' ? '!=' : op, left, right: this.parseComparison() };
    }
    return left;
  }

  parseComparison() {
    let left = this.parseConcat();
    while (this.isPunct('<') || this.isPunct('>') || this.isPunct('<=') || this.isPunct('>=') || this.isPunct('<=>')) {
      const op = this.next().value;
      left = { type: 'Binary', op, left, right: this.parseConcat() };
    }
    return left;
  }

  parseConcat() {
    let left = this.parseAdditive();
    while (this.isPunct('.')) {
      this.next();
      left = { type: 'Binary', op: '.', left, right: this.parseAdditive() };
    }
    return left;
  }

  parseAdditive() {
    let left = this.parseMultiplicative();
    while (this.isPunct('+') || this.isPunct('-')) {
      const op = this.next().value;
      left = { type: 'Binary', op, left, right: this.parseMultiplicative() };
    }
    return left;
  }

  parseMultiplicative() {
    let left = this.parseUnary();
    while (this.isPunct('*') || this.isPunct('/') || this.isPunct('%')) {
      const op = this.next().value;
      left = { type: 'Binary', op, left, right: this.parseUnary() };
    }
    return left;
  }

  /**
   * Is the cursor on a cast prefix?
   *
   * PHP casts are parenthesised - `(string)$x`, `(int)$y`, `?array` never
   * appears in a cast - so the shape to match is `(` IDENT `)` where IDENT is
   * a type name. A plain grouped expression `($a ?? '')` does not match, which
   * is what lets parsePrimary fall through to its `(` branch.
   */
  isCastStart(offset = 0) {
    const castTypes = ['string', 'int', 'integer', 'bool', 'boolean', 'array', 'float', 'double', 'object', 'mixed', 'iterable', 'callable', 'void', 'never'];
    if (!this.isPunct('(', offset)) return false;
    const t = this.peek(offset + 1);
    if (!t || t.type !== 'ident' || !castTypes.includes(t.value.toLowerCase())) return false;
    return this.isPunct(')', offset + 2);
  }

  /** Consume a cast prefix `(type)` and parse its operand. */
  parseCast() {
    if (!this.isCastStart()) {
      const t = this.peek();
      throw new PhpError(`parseCast called on ${t ? `${t.type} '${t.value}'` : 'EOF'} in ${this.filename}`);
    }
    this.expectPunct('(');
    const cast = this.next().value.toLowerCase();
    this.expectPunct(')');
    return { type: 'Cast', cast, operand: this.parseUnary() };
  }

  parseUnary() {
    const t = this.peek();
    if (t && t.type === 'punct' && ['!', '-', '+', '~'].includes(t.value)) {
      this.next();
      return { type: 'Unary', op: t.value, operand: this.parseUnary() };
    }
    if (t && t.type === 'punct' && t.value === '@') { this.next(); return this.parseUnary(); }
    if (this.isCastStart()) {
      return this.parseCast();
    }
    if (t && t.type === 'ident' && (t.value.toLowerCase() === 'isset' || t.value.toLowerCase() === 'empty') && this.isPunct('(', 1)) {
      const op = this.next().value.toLowerCase();
      this.expectPunct('(');
      const args = this.parseExpressionList();
      this.expectPunct(')');
      return { type: 'Call', name: op, args, isLangConstruct: true };
    }
    if (t && t.type === 'punct' && (t.value === '++' || t.value === '--')) {
      this.next();
      return { type: 'IncDec', op: t.value, target: this.parsePostfix(), prefix: true };
    }
    return this.parsePostfix();
  }

  /**
   * True when the cursor sits on `::`.
   *
   * The tokenizer has no `::` punct, so a static access arrives as two adjacent
   * `:` tokens - which is also why this has to be checked explicitly rather than
   * with isPunct('::').
   */
  isStaticAccess() {
    return this.isPunct(':') && this.peek(1) && this.peek(1).type === 'punct' && this.peek(1).value === ':';
  }

  parsePostfix() {
    let node = this.parsePrimary();
    for (;;) {
      // `PDO::FETCH_ASSOC`, `self::$x`, `Foo::bar()`: static class member access.
      // report.php's database reads use PDO's fetch-mode constants.
      if (this.isStaticAccess()) {
        this.next();
        this.next();
        const nameToken = this.next();
        if (this.isPunct('(')) {
          this.next();
          const args = this.isPunct(')') ? [] : this.parseExpressionList();
          this.expectPunct(')');
          node = { type: 'StaticCall', className: node.type === 'Constant' ? node.name : null, object: node, method: nameToken.value, args };
          continue;
        }
        node = {
          type: 'ClassConstant',
          className: node.type === 'Constant' ? node.name : null,
          object: node,
          name: nameToken.value,
        };
        continue;
      }
      // `$closure(...)`: a variable (or any expression) in call position.
      if (this.isPunct('(')) {
        this.next();
        const args = this.isPunct(')') ? [] : this.parseExpressionList();
        this.expectPunct(')');
        node = { type: 'Call', calleeExpr: node, name: null, args };
        continue;
      }
      if (this.isPunct('[')) {
        this.next();
        const index = this.isPunct(']') ? null : this.parseExpression();
        this.expectPunct(']');
        node = { type: 'Index', object: node, index };
        continue;
      }
      if (this.isPunct('->')) {
        this.next();
        const nameToken = this.next();
        if (this.isPunct('(')) {
          this.next();
          const args = this.isPunct(')') ? [] : this.parseExpressionList();
          this.expectPunct(')');
          node = { type: 'MethodCall', object: node, method: nameToken.value, args };
          continue;
        }
        node = { type: 'Property', object: node, name: nameToken.value };
        continue;
      }
      if (this.isPunct('++') || this.isPunct('--')) {
        const op = this.next().value;
        node = { type: 'IncDec', op, target: node, prefix: false };
        continue;
      }
      break;
    }
    return node;
  }

  parsePrimary() {
    const t = this.peek();
    if (!t) throw new PhpError('Unexpected EOF in ' + this.filename);

    if (t.type === 'number') { this.next(); return { type: 'Literal', value: t.value }; }
    if (t.type === 'string') { this.next(); return { type: 'String', value: t.value, parts: t.parts, quote: t.quote }; }
    if (t.type === 'var') { this.next(); return { type: 'Variable', name: t.value }; }

    // A cast prefix looks like '(' to the generic branch below, so it is
    // checked first: `(string)($value ?? '')` casts a grouped expression.
    if (this.isCastStart()) {
      return this.parseCast();
    }

    if (t.type === 'punct' && t.value === '(') {
      this.next();
      const expr = this.parseExpression();
      this.expectPunct(')');
      return expr;
    }

    if (t.type === 'punct' && t.value === '[') {
      this.next();
      return this.parseArrayLiteral(']');
    }

    if (t.type === 'ident') {
      const word = t.value.toLowerCase();

      if (word === 'true' || word === 'false' || word === 'null') {
        this.next();
        return { type: 'Literal', value: word === 'true' ? true : word === 'false' ? false : null };
      }

      if (word === 'array' && this.isPunct('(', 1)) {
        this.next(); this.next();
        return this.parseArrayLiteral(')');
      }

      if (word === 'fn' || (word === 'static' && this.isIdent('fn', 1))) {
        if (word === 'static') this.next();
        this.next(); // 'fn'
        return this.parseArrowFn();
      }

      // `static function (...) use (...) { ... }` is a closure expression.
      // Without this branch `static` fell through to the bare-constant rule and
      // `$fn = static function () {...}` bound the STRING "static", leaving the
      // closure as a dead expression.
      if (word === 'static' && this.isIdent('function', 1)) {
        this.next(); // 'static'
        this.next(); // 'function'
        const staticParams = this.parseParams();
        let staticUses = [];
        if (this.isIdent('use')) {
          this.next();
          this.expectPunct('(');
          while (!this.isPunct(')') && !this.eof()) {
            if (this.isPunct('&')) this.next();
            const captured = this.next();
            if (captured && captured.type === 'var') staticUses.push(captured.value);
            if (this.isPunct(',')) this.next();
          }
          this.expectPunct(')');
        }
        if (this.isPunct(':')) {
          this.next();
          if (this.isPunct('?')) this.next();
          this.next(); // return type name
        }
        const staticBody = this.parseBracedBlock();
        return { type: 'Closure', params: staticParams, body: staticBody, uses: staticUses, isStatic: true };
      }

      if (word === 'function') {
        this.next();
        const params = this.parseParams();
        let uses = [];
        if (this.isIdent('use')) {
          this.next();
          this.expectPunct('(');
          while (!this.isPunct(')')) {
            if (this.isPunct('&')) this.next();
            uses.push(this.next().value);
            if (this.isPunct(',')) this.next();
          }
          this.expectPunct(')');
        }
        let returnType = null;
        if (this.isPunct(':')) { this.next(); if (this.isPunct('?')) { this.next(); returnType = '?' + this.next().value; } else returnType = this.next().value; }
        const body = this.parseBracedBlock();
        return { type: 'Closure', params, body, uses, returnType };
      }

      if (word === 'new') {
        this.next();
        const className = this.next().value;
        let args = [];
        if (this.isPunct('(')) { this.next(); args = this.isPunct(')') ? [] : this.parseExpressionList(); this.expectPunct(')'); }
        return { type: 'New', className, args };
      }

      // A type hint sitting in expression position (`array $extra`, `string $x`)
      // is not a call and not a constant: hand parsing back to parseUnary, which
      // knows how to consume the cast and then the variable.
      if (this.peek(1) && this.peek(1).type === 'var') {
        return this.parseUnary();
      }

      // Function call
      if (this.isPunct('(', 1)) {
        const name = this.next().value;
        this.next();
        const args = this.isPunct(')') ? [] : this.parseExpressionList();
        this.expectPunct(')');
        return { type: 'Call', name, args };
      }

      // Constant
      this.next();
      return { type: 'Constant', name: t.value };
    }

    throw new PhpError(`Unexpected token ${t.type} '${t.value}' in ${this.filename}`);
  }

  parseArrowFn() {
    const params = this.parseParams();
    // Arrow functions may declare a return type: fn(string $p): string => ...
    if (this.isPunct(':')) {
      this.next();
      if (this.isPunct('?')) this.next();
      this.next(); // type name
    }
    this.expectPunct('=>');
    const expr = this.parseAssign();
    return { type: 'Closure', params, body: [{ type: 'Return', expr }], uses: [], arrow: true };
  }

  parseArrayLiteral(closer) {
    const items = [];
    while (!this.isPunct(closer) && !this.eof()) {
      const first = this.parseExpression();
      if (this.isPunct('=>')) {
        this.next();
        const value = this.parseExpression();
        items.push({ key: first, value, byRef: false });
      } else {
        items.push({ key: null, value: first, byRef: false });
      }
      if (this.isPunct(',')) this.next();
    }
    this.expectPunct(closer);
    return { type: 'ArrayLiteral', items };
  }
}

module.exports = { tokenize, Parser, PhpError, STRICT };
