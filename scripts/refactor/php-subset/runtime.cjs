/**
 * PHP built-ins implemented in JS for the subset interpreter.
 *
 * Only the functions the layout + pages actually call are here. A missing
 * function throws with its name so the gap is obvious instead of rendering
 * empty HTML.
 */

class PhpRuntimeError extends Error {}

/** PHP-style truthiness: '' , '0', 0, null, false, [] are falsy. */
function phpTruthy(value) {
  if (value === null || value === undefined || value === false) return false;
  if (typeof value === 'number') return value !== 0;
  if (typeof value === 'string') return value !== '' && value !== '0';
  if (Array.isArray(value)) return value.length > 0;
  // PhpArray wraps a Map: Object.keys() on a Map is always [], which used to
  // make every non-empty PHP array look falsy.
  if (isPhpArray(value)) return value.map.size > 0;
  return true;
}

/** Ordered PHP array: preserves insertion order and integer/string keys. */
class PhpArray {
  constructor(entries = []) {
    this.map = new Map();
    this.autoIndex = 0;
    for (const [k, v] of entries) this.set(k, v);
  }
  set(key, value) {
    if (key === null || key === undefined) {
      while (this.map.has(this.autoIndex)) this.autoIndex++;
      key = this.autoIndex++;
    }
    if (typeof key === 'string' && /^-?\d+$/.test(key)) key = parseInt(key, 10);
    if (typeof key === 'number' && Number.isInteger(key) && key >= this.autoIndex) this.autoIndex = key + 1;
    this.map.set(key, value);
    return this;
  }
  get(key) {
    if (typeof key === 'string' && /^-?\d+$/.test(key)) key = parseInt(key, 10);
    return this.map.has(key) ? this.map.get(key) : null;
  }
  has(key) {
    if (typeof key === 'string' && /^-?\d+$/.test(key)) key = parseInt(key, 10);
    return this.map.has(key);
  }
  unset(key) {
    if (typeof key === 'string' && /^-?\d+$/.test(key)) key = parseInt(key, 10);
    this.map.delete(key);
  }
  keys() { return [...this.map.keys()]; }
  values() { return [...this.map.values()]; }
  entries() { return [...this.map.entries()]; }
  get length() { return this.map.size; }
}

function isPhpArray(value) { return value instanceof PhpArray; }

/** Convert JS array <-> PhpArray at the boundary. */
function toPhpArray(value) {
  if (isPhpArray(value)) return value;
  if (Array.isArray(value)) return new PhpArray(value.map((v) => [null, v]));
  if (value && typeof value === 'object') return new PhpArray(Object.entries(value));
  return new PhpArray();
}

function toJs(value) {
  if (isPhpArray(value)) return Object.fromEntries(value.entries().map(([k, v]) => [k, toJs(v)]));
  if (Array.isArray(value)) return value.map(toJs);
  return value;
}

function phpToString(value) {
  if (value === null || value === undefined) return '';
  if (value === true) return '1';
  if (value === false) return '';
  if (typeof value === 'number') {
    if (Number.isInteger(value)) return String(value);
    // PHP prints floats without trailing zeros
    return String(parseFloat(value.toFixed(14)));
  }
  if (isPhpArray(value)) return 'Array';
  if (Array.isArray(value)) return 'Array';
  return String(value);
}

function phpConcat(a, b) { return phpToString(a) + phpToString(b); }

function phpEquals(a, b, strict) {
  if (strict) {
    if (typeof a !== typeof b) return false;
    if (isPhpArray(a) && isPhpArray(b)) return a.entries().length === b.entries().length && JSON.stringify(toJs(a)) === JSON.stringify(toJs(b));
    return a === b;
  }
  if (a === null || a === undefined) return b === null || b === undefined || b === false || b === '';
  if (b === null || b === undefined) return a === null || a === undefined || a === false || a === '';
  if (typeof a === 'number' || typeof b === 'number') return Number(a) === Number(b);
  if (typeof a === 'boolean' || typeof b === 'boolean') return phpTruthy(a) === phpTruthy(b);
  return phpToString(a) === phpToString(b);
}

// ---------------------------------------------------------------------------
// Built-in function library
// ---------------------------------------------------------------------------

const PHP_FUNCTIONS = {
  htmlspecialchars: (value, flags = null, encoding = 'UTF-8') => {
    let s = phpToString(value);
    const quoteStyle = flags === 2 || flags === 3 ? 'ENT_QUOTES' : 'ENT_COMPAT';
    s = s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    if (quoteStyle === 'ENT_QUOTES') s = s.replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    else s = s.replace(/"/g, '&quot;');
    return s;
  },
  json_encode: (value, flags = 0) => JSON.stringify(toJs(value)),
  json_decode: (value, assoc = false) => {
    const parsed = JSON.parse(phpToString(value));
    if (assoc) {
      const build = (v) => {
        if (Array.isArray(v)) return new PhpArray(v.map((x) => [null, build(x)]));
        if (v && typeof v === 'object') return new PhpArray(Object.entries(v).map(([k, x]) => [k, build(x)]));
        return v;
      };
      return build(parsed);
    }
    return parsed;
  },
  implode: (glue, pieces) => {
    if (isPhpArray(pieces)) pieces = pieces.values();
    if (Array.isArray(pieces)) return pieces.map(phpToString).join(phpToString(glue));
    // Argument-swapped form: implode($array, $glue)
    if (isPhpArray(glue) || Array.isArray(glue)) {
      const arr = isPhpArray(glue) ? glue.values() : glue;
      return arr.map(phpToString).join(phpToString(pieces));
    }
    return '';
  },
  explode: (delimiter, string, limit = null) => {
    const parts = phpToString(string).split(phpToString(delimiter));
    const sliced = limit !== null && limit > 0 ? parts.slice(0, limit) : parts;
    return new PhpArray(sliced.map((v) => [null, v]));
  },
  trim: (value, chars = " \t\n\r\0\x0B") => {
    let s = phpToString(value);
    if (chars === " \t\n\r\0\x0B") return s.trim();
    const esc = [...chars].map((c) => c.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('');
    return s.replace(new RegExp(`^[${esc}]+`), '').replace(new RegExp(`[${esc}]+$`), '');
  },
  ltrim: (value, chars = " \t\n\r\0\x0B/") => phpToString(value).replace(new RegExp(`^[${[...chars].map((c) => c.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('')}]+`), ''),
  rtrim: (value, chars = " \t\n\r\0\x0B/") => phpToString(value).replace(new RegExp(`[${[...chars].map((c) => c.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('')}]+$`), ''),
  strlen: (value) => phpToString(value).length,
  strtolower: (value) => phpToString(value).toLowerCase(),
  strtoupper: (value) => phpToString(value).toUpperCase(),
  ucfirst: (value) => { const s = phpToString(value); return s.charAt(0).toUpperCase() + s.slice(1); },
  str_replace: (search, replace, subject) => {
    const searches = isPhpArray(search) ? search.values() : Array.isArray(search) ? search : [search];
    const replaces = isPhpArray(replace) ? replace.values() : Array.isArray(replace) ? replace : [replace];
    let out = phpToString(subject);
    searches.forEach((needle, i) => {
      out = out.split(phpToString(needle)).join(phpToString(replaces[i] ?? replaces[replaces.length - 1] ?? ''));
    });
    return out;
  },
  str_starts_with: (haystack, needle) => phpToString(haystack).startsWith(phpToString(needle)),
  str_ends_with: (haystack, needle) => phpToString(haystack).endsWith(phpToString(needle)),
  str_contains: (haystack, needle) => phpToString(haystack).includes(phpToString(needle)),
  strpos: (haystack, needle, offset = 0) => {
    const idx = phpToString(haystack).indexOf(phpToString(needle), Number(offset));
    return idx === -1 ? false : idx;
  },
  substr: (string, start, length = null) => {
    const s = phpToString(string);
    let from = Number(start);
    if (from < 0) from = Math.max(0, s.length + from);
    if (length === null || length === undefined) return s.slice(from);
    const len = Number(length);
    if (len < 0) return s.slice(from, Math.max(from, s.length + len));
    return s.slice(from, from + len);
  },
  sprintf: (format, ...args) => {
    let i = 0;
    return phpToString(format).replace(/%(\d+)?[sdfr%]/g, (match, width) => {
      if (match === '%%') return '%';
      const arg = args[i++];
      let out;
      if (match.includes('d')) out = String(parseInt(Number(arg), 10));
      else if (match.includes('f')) out = String(parseFloat(arg));
      else out = phpToString(arg);
      if (width) out = out.padStart(Number(width), ' ');
      return out;
    });
  },
  preg_match: (pattern, subject) => {
    const m = parseRegex(pattern);
    return m.regex.test(phpToString(subject)) ? 1 : 0;
  },
  preg_replace: (pattern, replacement, subject) => {
    const m = parseRegex(pattern);
    return phpToString(subject).replace(new RegExp(m.source, m.flags.includes('g') ? m.flags : m.flags + 'g'), phpToString(replacement).replace(/\$(\d)/g, '$$$1'));
  },
  array_key_exists: (key, array) => toPhpArray(array).has(key),
  array_keys_exists: (key, array) => toPhpArray(array).has(key),
  array_search: (needle, haystack, strict = false) => {
    for (const [k, v] of toPhpArray(haystack).entries()) {
      if (phpEquals(v, needle, strict)) return k;
    }
    return false;
  },
  array_unshift: (array, ...values) => {
    const out = new PhpArray(values.map((v) => [null, v]));
    for (const v of toPhpArray(array).values()) out.set(null, v);
    array.map = out.map;
    return array.map.size;
  },
  array_push_like: () => null,
  array_reverse: (array) => new PhpArray(toPhpArray(array).values().reverse().map((v) => [null, v])),
  array_unique: (array) => {
    const out = new PhpArray();
    const seen = new Set();
    for (const [k, v] of toPhpArray(array).entries()) {
      const sig = JSON.stringify(toJs(v));
      if (!seen.has(sig)) { seen.add(sig); out.set(k, v); }
    }
    return out;
  },
  array_values: (array) => new PhpArray(toPhpArray(array).values().map((v) => [null, v])),
  array_keys: (array) => new PhpArray(toPhpArray(array).keys().map((v) => [null, v])),
  array_map: (callback, ...arrays) => {
    const source = toPhpArray(arrays[0]);
    const out = new PhpArray();
    for (const [k, v] of source.entries()) out.set(k, callback(v));
    return out;
  },
  array_filter: (array, callback = null) => {
    const source = toPhpArray(array);
    const out = new PhpArray();
    for (const [k, v] of source.entries()) {
      const keep = callback ? phpTruthy(callback(v, k)) : phpTruthy(v);
      if (keep) out.set(k, v);
    }
    return out;
  },
  array_merge: (...arrays) => {
    const out = new PhpArray();
    for (const array of arrays) {
      for (const [k, v] of toPhpArray(array).entries()) {
        if (typeof k === 'number') out.set(null, v);
        else out.set(k, v);
      }
    }
    return out;
  },
  in_array: (needle, haystack, strict = false) =>
    toPhpArray(haystack).values().some((v) => phpEquals(v, needle, strict)),
  count: (value) => {
    if (value === null || value === undefined) return 0;
    if (isPhpArray(value)) return value.length;
    if (Array.isArray(value)) return value.length;
    return 1;
  },
  is_array: (value) => isPhpArray(value) || Array.isArray(value),
  is_int: (value) => typeof value === 'number' && Number.isInteger(value),
  is_integer: (value) => typeof value === 'number' && Number.isInteger(value),
  is_float: (value) => typeof value === 'number' && !Number.isInteger(value),
  is_double: (value) => typeof value === 'number',
  is_object: (value) => value !== null && typeof value === 'object' && !isPhpArray(value) && !Array.isArray(value),
  is_callable: (value) => typeof value === 'function',
  is_scalar: (value) => ['string', 'number', 'boolean'].includes(typeof value),
  gettype: (value) => {
    if (value === null || value === undefined) return 'NULL';
    if (typeof value === 'boolean') return 'boolean';
    if (typeof value === 'number') return Number.isInteger(value) ? 'integer' : 'double';
    if (typeof value === 'string') return 'string';
    if (isPhpArray(value) || Array.isArray(value)) return 'array';
    return 'object';
  },
  min: (...args) => Math.min(...(args.length === 1 ? toPhpArray(args[0]).values() : args).map(Number)),
  max: (...args) => Math.max(...(args.length === 1 ? toPhpArray(args[0]).values() : args).map(Number)),
  abs: (value) => Math.abs(Number(value)),
  round: (value, precision = 0) => {
    const f = Math.pow(10, Number(precision));
    return Math.round(Number(value) * f) / f;
  },
  floor: (value) => Math.floor(Number(value)),
  ceil: (value) => Math.ceil(Number(value)),
  range: (start, end, step = 1) => {
    const out = new PhpArray();
    for (let i = Number(start); i <= Number(end); i += Number(step)) out.set(null, i);
    return out;
  },
  join: (glue, pieces) => PHP_FUNCTIONS.implode(glue, pieces),
  print_r: (value) => JSON.stringify(toJs(value), null, 2),
  var_dump: (value) => JSON.stringify(toJs(value), null, 2),
  var_export: (value) => JSON.stringify(toJs(value)),
  strip_tags: (value) => phpToString(value).replace(/<[^>]*>/g, ''),
  nl2br: (value) => phpToString(value).replace(/\n/g, '<br />\n'),
  ucwords: (value) => phpToString(value).replace(/\b[a-z]/g, (c) => c.toUpperCase()),
  str_pad: (input, length, pad = ' ', type = 'right') => {
    const s = phpToString(input);
    const n = Math.max(0, Number(length) - s.length);
    const fill = phpToString(pad).repeat(n).slice(0, n);
    if (type === 'left' || type === 0) return fill + s;
    if (type === 'both' || type === 2) {
      const l = Math.floor(n / 2);
      return phpToString(pad).repeat(l).slice(0, l) + s + fill.slice(0, n - l);
    }
    return s + fill;
  },
  substr_count: (haystack, needle) => phpToString(haystack).split(phpToString(needle)).length - 1,
  str_repeat: (input, multiplier) => phpToString(input).repeat(Math.max(0, Number(multiplier))),
  array_column: (array, key) => new PhpArray(toPhpArray(array).values().map((row) => [null, isPhpArray(row) ? row.get(key) : null])),
  array_chunk: (array, size) => {
    const out = new PhpArray();
    const values = toPhpArray(array).values();
    for (let i = 0; i < values.length; i += Number(size)) {
      out.set(null, new PhpArray(values.slice(i, i + Number(size)).map((v) => [null, v])));
    }
    return out;
  },
  array_diff: (a, b) => {
    const drop = new Set(toPhpArray(b).values().map((v) => JSON.stringify(v)));
    const out = new PhpArray();
    for (const [k, v] of toPhpArray(a).entries()) if (!drop.has(JSON.stringify(v))) out.set(k, v);
    return out;
  },
  array_intersect: (a, b) => {
    const keep = new Set(toPhpArray(b).values().map((v) => JSON.stringify(v)));
    const out = new PhpArray();
    for (const [k, v] of toPhpArray(a).entries()) if (keep.has(JSON.stringify(v))) out.set(k, v);
    return out;
  },
  sort: (array) => {
    const values = toPhpArray(array).values().sort();
    array.map = new PhpArray(values.map((v) => [null, v])).map;
    return true;
  },
  usort: (array, callback) => {
    const values = toPhpArray(array).values().sort((x, y) => Number(callback(x, y)));
    array.map = new PhpArray(values.map((v) => [null, v])).map;
    return true;
  },
  ksort: (array) => {
    const entries = toPhpArray(array).entries().sort((a, b) => (a[0] > b[0] ? 1 : -1));
    array.map = new Map(entries);
    return true;
  },
  array_walk: (array, callback) => {
    for (const [k, v] of toPhpArray(array).entries()) callback(v, k);
    return true;
  },
  array_reduce: (array, callback, initial = null) => {
    let acc = initial;
    for (const v of toPhpArray(array).values()) acc = callback(acc, v);
    return acc;
  },
  reset: (array) => { const v = toPhpArray(array).values(); return v.length ? v[0] : false; },
  current: (array) => { const v = toPhpArray(array).values(); return v.length ? v[0] : false; },
  array_pad: (array, length, value) => {
    const out = toPhpArray(array);
    const n = Math.abs(Number(length));
    while (out.length < n) out.set(null, value);
    return out;
  },
  array_fill: (start, count, value) => {
    const out = new PhpArray();
    for (let i = 0; i < Number(count); i++) out.set(Number(start) + i, value);
    return out;
  },
  array_combine: (keys, values) => {
    const ks = toPhpArray(keys).values();
    const vs = toPhpArray(values).values();
    return new PhpArray(ks.map((k, i) => [k, vs[i]]));
  },
  str_split: (str, length = 1) => new PhpArray(phpToString(str).match(new RegExp(`.{1,${Number(length)}}`, 'g')) || []),
  chr: (code) => String.fromCharCode(Number(code)),
  ord: (char) => phpToString(char).charCodeAt(0),
  rand: (min = 0, max = 1) => Math.floor(Math.random() * (Number(max) - Number(min) + 1)) + Number(min),
  mt_rand: (min = 0, max = 1) => PHP_FUNCTIONS.rand(min, max),
  md5: (value) => require('crypto').createHash('md5').update(phpToString(value)).digest('hex'),
  sha1: (value) => require('crypto').createHash('sha1').update(phpToString(value)).digest('hex'),
  base64_encode: (value) => Buffer.from(phpToString(value), 'utf8').toString('base64'),
  base64_decode: (value) => Buffer.from(phpToString(value), 'base64').toString('utf8'),
  urlencode: (value) => encodeURIComponent(phpToString(value)),
  rawurlencode: (value) => encodeURIComponent(phpToString(value)),
  urldecode: (value) => decodeURIComponent(phpToString(value)),
  http_build_query: (data) => new URLSearchParams(Object.entries(toJs(data)).map(([k, v]) => [k, String(v)])).toString(),
  is_string: (value) => typeof value === 'string',
  is_null: (value) => value === null || value === undefined,
  is_bool: (value) => typeof value === 'boolean',
  is_numeric: (value) => !isNaN(parseFloat(value)) && isFinite(value),
  intval: (value) => parseInt(Number(value), 10) || 0,
  floatval: (value) => parseFloat(value) || 0,
  strval: (value) => phpToString(value),
  boolval: (value) => phpTruthy(value),
  number_format: (number, decimals = 0) =>
    Number(number).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals }),
  date: (format, timestamp = null) => formatDate(format, timestamp === null ? new Date() : new Date(Number(timestamp) * 1000)),
  time: () => Math.floor(Date.now() / 1000),
  filemtime: () => 1700000000,
  is_file: () => false,
  is_dir: () => false,
  file_exists: () => false,
  dirname: (path, levels = 1) => {
    let p = phpToString(path).replace(/\\/g, '/');
    for (let i = 0; i < Number(levels); i++) {
      p = p.replace(/\/[^/]*\/?$/, '') || '/';
    }
    return p || '/';
  },
  basename: (path) => phpToString(path).split('/').filter(Boolean).pop() || '',
  rtrim_slash: (path) => phpToString(path).replace(/\/+$/, ''),
  class_exists: () => false,
  header: () => undefined,
  headers_sent: () => false,
  exit: () => { throw new PhpExit(); },
  die: () => { throw new PhpExit(); },
  error_log: (message) => { process.env.PHP_QUIET !== '1' && console.error('[php error_log] ' + phpToString(message)); return true; },
  ob_start: () => true,
  ob_end_clean: () => true,
  ob_get_clean: () => '',
  http_response_code: (code = null) => (code === null ? 200 : Number(code)),
  bin2hex: () => 'mock-csrf-token',
  random_bytes: (n) => 'x'.repeat(Number(n)),
  hash_equals: (a, b) => phpToString(a) === phpToString(b),
  session_status: () => 2,
  session_start: () => true,
  session_name: () => 'MOCKSESSID',
  session_save_path: () => '/tmp',
  session_set_cookie_params: () => true,
  array_slice: (array, offset, length = null) => {
    const values = toPhpArray(array).values().slice(Number(offset), length === null ? undefined : Number(offset) + Number(length));
    return new PhpArray(values.map((v) => [null, v]));
  },
  end: (array) => { const values = toPhpArray(array).values(); return values.length ? values[values.length - 1] : false; },
  microtime: () => '0.00000000 1700000000',
  uniqid: (prefix = '') => phpToString(prefix) + 'mock1234',
  mb_strtolower: (value) => phpToString(value).toLowerCase(),
};

class PhpExit extends Error {}

function parseRegex(pattern) {
  const s = phpToString(pattern);
  const delim = s[0];
  const last = s.lastIndexOf(delim);
  const source = s.slice(1, last);
  const flags = s.slice(last + 1).replace(/[^gimsuy]/g, '');
  return { source, flags, regex: new RegExp(source, flags) };
}

function formatDate(format, date) {
  const pad = (n) => String(n).padStart(2, '0');
  const map = {
    Y: date.getFullYear(),
    y: String(date.getFullYear()).slice(-2),
    m: pad(date.getMonth() + 1),
    n: date.getMonth() + 1,
    d: pad(date.getDate()),
    j: date.getDate(),
    H: pad(date.getHours()),
    G: date.getHours(),
    i: pad(date.getMinutes()),
    s: pad(date.getSeconds()),
    U: Math.floor(date.getTime() / 1000),
  };
  return phpToString(format).replace(/[YymndjHGisU]/g, (c) => String(map[c]));
}

/**
 * A permissive stand-in for a PHP object instance.
 *
 * The preview harness has no database and models no classes, but pages such as
 * report.php do their own PDO work inside a try/catch before rendering. Rather
 * than aborting the whole render on the first `new Database()`, object creation
 * yields one of these: every method call returns another PhpObject (so a
 * `$db->getConnection()->query(...)` chain just works) and every property read
 * yields null.
 *
 * Fetch-shaped methods return null so `if (!$permission || ...)` guards take the
 * same "no row" branch they would against an empty database, and the page
 * renders with empty option lists instead of pretending there is data.
 */
class PhpObject {
  constructor(className = 'stdClass') {
    this.className = String(className);
    this.props = new Map();
  }

  call(method) {
    const name = String(method);
    // Only fetch-shaped methods and rowCount report "no data". Every other call
    // must return another PhpObject so a chain keeps working: short-circuiting
    // `getConnection()` to null would break `$db->getConnection()->query(...)`,
    // which is the exact pattern report.php uses.
    if (/^fetch(All|Assoc|Column|Row|Object)?$/.test(name)) return null;
    if (name === 'rowCount') return 0;
    return new PhpObject(this.className);
  }
}

/** True for the harness's object stand-in. */
function isPhpObject(value) {
  return value instanceof PhpObject;
}

module.exports = {
  PHP_FUNCTIONS,
  PhpArray,
  PhpObject,
  isPhpObject,
  PhpExit,
  PhpRuntimeError,
  isPhpArray,
  toPhpArray,
  toJs,
  phpTruthy,
  phpToString,
  phpConcat,
  phpEquals,
};
