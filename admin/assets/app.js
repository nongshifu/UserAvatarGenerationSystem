// 管理后台公共 JS
// - Token 管理（localStorage）
// - 统一 fetch 封装（自动加 Authorization、统一错误处理）
// - 极简 DOM 辅助（$: querySelector, el: createElement, renderTable）
// - 路由：hashchange 切换页面

const API_BASE = '/admin-api';
const TOKEN_KEY = 'avatar_admin_token';
const USER_KEY = 'avatar_admin_user';

const Storage = {
  getToken: () => localStorage.getItem(TOKEN_KEY) || '',
  setToken: (t) => localStorage.setItem(TOKEN_KEY, t),
  getUser: () => JSON.parse(localStorage.getItem(USER_KEY) || 'null'),
  setUser: (u) => localStorage.setItem(USER_KEY, JSON.stringify(u)),
  clear: () => { localStorage.removeItem(TOKEN_KEY); localStorage.removeItem(USER_KEY); },
};

// 统一请求
async function api(path, options = {}) {
  const headers = Object.assign({ 'Content-Type': 'application/json' }, options.headers || {});
  const token = Storage.getToken();
  if (token) headers['Authorization'] = 'Bearer ' + token;
  const resp = await fetch(API_BASE + path, {
    method: options.method || 'GET',
    headers,
    body: options.body ? JSON.stringify(options.body) : undefined,
  });
  let data = null;
  try { data = await resp.json(); } catch (e) { /* non-json */ }
  if (!resp.ok || (data && data.code !== 0 && data.code !== undefined)) {
    const msg = (data && data.message) || `HTTP ${resp.status}`;
    // 仅在「已登录页面 + 非登录接口」遇到 401 才跳回登录页；
    // 登录页自身的 401（密码错误）只提示，避免错误一闪而过
    const onLoginPage = location.pathname.replace(/\/+$/, '').endsWith('/login.php')
      || location.pathname.replace(/\/+$/, '').endsWith('/login.html')
      || location.pathname.replace(/\/+$/, '').endsWith('/admin')
      || location.pathname.replace(/\/+$/, '').endsWith('/admin/');
    if (resp.status === 401 && !onLoginPage && path !== '/login') {
      Storage.clear();
      location.href = '/admin/login.php';
    }
    throw new Error(msg);
  }
  return data.data || data;
}

// DOM 辅助
const $  = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
function el(tag, attrs = {}, children = []) {
  const e = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs)) {
    if (k === 'class') e.className = v;
    else if (k === 'html') e.innerHTML = v;
    else if (k.startsWith('on') && typeof v === 'function') e.addEventListener(k.slice(2).toLowerCase(), v);
    else e.setAttribute(k, v);
  }
  (Array.isArray(children) ? children : [children]).forEach(c => {
    if (c == null) return;
    e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
  });
  return e;
}

// 渲染表格
function renderTable(container, columns, rows, options = {}) {
  const table = el('table', { class: 'data-table' });
  const thead = el('thead', {}, el('tr', {}, columns.map(c => el('th', {}, c.label))));
  const tbody = el('tbody');
  if (!rows.length) {
    tbody.appendChild(el('tr', {}, el('td', { class: 'empty', colspan: columns.length }, options.emptyText || '暂无数据')));
  }
  rows.forEach(row => {
    const tr = el('tr');
    columns.forEach(col => {
      const td = el('td');
      const val = typeof col.render === 'function' ? col.render(row) : row[col.key];
      // render 可返回：Node / Node 数组（操作列多个按钮）/ HTML 字符串 / 普通值
      if (val instanceof Node) {
        td.appendChild(val);
      } else if (Array.isArray(val)) {
        val.forEach(c => {
          if (c == null) return;
          td.appendChild(c instanceof Node ? c : document.createTextNode(String(c)));
        });
      } else if (typeof val === 'string') {
        td.innerHTML = val;
      } else if (val != null) {
        td.textContent = String(val);
      }
      tr.appendChild(td);
    });
    tbody.appendChild(tr);
  });
  table.appendChild(thead);
  table.appendChild(tbody);
  container.innerHTML = '';
  container.appendChild(table);
}

// 渲染分页
function renderPagination(container, pagination, onPageChange) {
  const { total, page, per_page, last_page } = pagination;
  container.innerHTML = '';
  if (total === 0) return;
  container.appendChild(el('span', { class: 'muted' }, `共 ${total} 条`));
  for (let p = 1; p <= last_page; p++) {
    const btn = el('button', {
      class: 'btn btn-sm' + (p === page ? ' btn-primary' : ' btn-ghost'),
      onclick: () => onPageChange(p),
    }, String(p));
    container.appendChild(btn);
  }
}

// 转义
function esc(s) {
  if (s == null) return '';
  return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

// 状态徽章
function badge(text, type = 'default') {
  return el('span', { class: `badge badge-${type}` }, text);
}

// Toast 提示
function toast(msg, type = 'info') {
  const t = el('div', { class: `toast toast-${type}` }, msg);
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}

// 确认对话框（异步）
function confirmDialog(msg) {
  return confirmModal({ message: msg, title: '请确认' });
}

// ── 统一模态框系统 ──
// Modal.open：底层，返回 { close }
// confirmModal：确认框 → Promise<boolean>
// formModal：表单框（text/number/textarea/select/switch）→ Promise<values|null>
// infoModal：信息框（可带复制内容）→ Promise<void>
const Modal = {
  open({ title = '', body, large = false, footer } = {}) {
    const overlay = el('div', { class: 'modal-overlay' });
    const modal = el('div', { class: 'modal' + (large ? ' modal-lg' : '') });

    const close = () => {
      overlay.remove();
      document.removeEventListener('keydown', onKey);
    };
    const onKey = (e) => {
      if (e.key === 'Escape') { close(); }
      if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON') {
        const ok = modal.querySelector('.modal-footer .btn-primary, .modal-footer .btn-danger-solid');
        if (ok) { e.preventDefault(); ok.click(); }
      }
    };
    // 点遮罩空白处关闭
    overlay.addEventListener('click', e => { if (e.target === overlay) close(); });
    document.addEventListener('keydown', onKey);

    modal.appendChild(el('div', { class: 'modal-header' }, [
      el('span', {}, title),
      el('button', { class: 'modal-close', 'aria-label': '关闭', onclick: close }, '×'),
    ]));

    const bodyEl = el('div', { class: 'modal-body' });
    if (typeof body === 'string') bodyEl.innerHTML = body;
    else if (body instanceof Node) bodyEl.appendChild(body);
    modal.appendChild(bodyEl);

    if (footer) {
      const foot = el('div', { class: 'modal-footer' });
      footer(foot, close);
      modal.appendChild(foot);
    }

    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    const first = modal.querySelector('input, select, textarea');
    if (first) setTimeout(() => first.focus(), 60);
    return { close, modal, bodyEl };
  },
};

function confirmModal({ title = '请确认', message = '', okText = '确定', cancelText = '取消', danger = false } = {}) {
  return new Promise(resolve => {
    Modal.open({
      title,
      body: el('div', { class: 'muted-text' }, message),
      footer(foot, close) {
        foot.appendChild(el('button', { class: 'btn', onclick: () => { close(); resolve(false); } }, cancelText));
        foot.appendChild(el('button', {
          class: 'btn ' + (danger ? 'btn-danger-solid' : 'btn-primary'),
          onclick: () => { close(); resolve(true); },
        }, okText));
      },
    });
  });
}

function formModal({ title, fields = [], okText = '保存', cancelText = '取消', large = false } = {}) {
  // fields: [{ name, label, type: text|number|textarea|select|switch, value, options, placeholder, required, step, min }]
  return new Promise(resolve => {
    const inputs = {};
    const body = el('div');
    fields.forEach(f => {
      const field = el('div', { class: 'm-field' });
      if (f.type === 'switch') {
        const input = el('input', { type: 'checkbox' });
        input.checked = !!f.value;
        inputs[f.name] = input;
        field.appendChild(el('label', { class: 'm-switch-row' }, [input, el('span', {}, f.label)]));
      } else {
        field.appendChild(el('label', {}, f.label));
        let input;
        if (f.type === 'textarea') {
          input = el('textarea', { placeholder: f.placeholder || '' });
        } else if (f.type === 'select') {
          input = el('select', {}, (f.options || []).map(o => el('option', { value: o.value }, o.label)));
        } else {
          const attrs = { type: f.type === 'number' ? 'number' : 'text', placeholder: f.placeholder || '' };
          if (f.step != null) attrs.step = f.step;
          if (f.min != null) attrs.min = f.min;
          input = el('input', attrs);
        }
        if (f.value != null) input.value = f.value;
        inputs[f.name] = input;
        field.appendChild(input);
      }
      body.appendChild(field);
    });

    Modal.open({
      title, body, large,
      footer(foot, close) {
        foot.appendChild(el('button', { class: 'btn', onclick: () => { close(); resolve(null); } }, cancelText));
        foot.appendChild(el('button', {
          class: 'btn btn-primary',
          onclick: () => {
            const values = {};
            for (const f of fields) {
              const node = inputs[f.name];
              if (f.type === 'switch') values[f.name] = node.checked ? 1 : 0;
              else if (f.type === 'number') values[f.name] = node.value === '' ? null : Number(node.value);
              else values[f.name] = node.value.trim();
            }
            for (const f of fields) {
              if (f.required && (values[f.name] === '' || values[f.name] == null)) {
                toast('请填写「' + f.label + '」', 'error');
                inputs[f.name].focus();
                return;
              }
            }
            close();
            resolve(values);
          },
        }, okText));
      },
    });
  });
}

function infoModal({ title = '提示', message = '', okText = '我知道了', copyText = '' } = {}) {
  return new Promise(resolve => {
    const body = el('div');
    if (message) body.appendChild(el('div', { class: 'muted-text' }, message));
    if (copyText) {
      const code = el('code', {}, copyText);
      const copyBtn = el('button', { class: 'btn btn-sm' }, '复制');
      copyBtn.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(copyText);
        } catch (e) {
          // 兼容旧浏览器
          const ta = document.createElement('textarea');
          ta.value = copyText; document.body.appendChild(ta); ta.select();
          document.execCommand('copy'); ta.remove();
        }
        copyBtn.textContent = '已复制';
        setTimeout(() => { copyBtn.textContent = '复制'; }, 1500);
      });
      body.appendChild(el('div', { class: 'm-copy-box' }, [code, copyBtn]));
    }
    Modal.open({
      title, body, large: !!copyText,
      footer(foot, close) {
        foot.appendChild(el('button', { class: 'btn btn-primary', onclick: () => { close(); resolve(); } }, okText));
      },
    });
  });
}

// ── 通用走势图表（Canvas 折线/柱状，支持指标与时间范围切换、悬停提示、双主题） ──
// cfg: { series:[{date,...}], metrics:{key:{label,color,varName,type:'line'|'bar',sign?}},
//        initial:{metric,range}, ssKey, summary(data)=>html }
window.mountChart = function (container, cfg) {
  const metrics = cfg.metrics;
  const state = { metric: Object.keys(metrics)[0], range: 30, hover: -1 };
  try {
    const saved = JSON.parse(sessionStorage.getItem(cfg.ssKey || '') || 'null');
    if (saved && metrics[saved.metric] && saved.range >= 0) { state.metric = saved.metric; state.range = saved.range; }
  } catch (e) { /* 忽略 */ }
  if (cfg.initial && metrics[cfg.initial.metric]) {
    if (!sessionStorage.getItem(cfg.ssKey)) state.metric = cfg.initial.metric;
    if (cfg.initial.range != null) state.range = cfg.initial.range;
  }
  const save = () => { try { sessionStorage.setItem(cfg.ssKey || '', JSON.stringify({ metric: state.metric, range: state.range })); } catch (e) { /* 忽略 */ } };

  const themeVar = getComputedStyle(document.documentElement);
  const colorOf = v => (v && v.startsWith('--')) ? (themeVar.getPropertyValue(v).trim() || '#8b73ff') : (v || '#8b73ff');
  const rgba = (hex, a) => {
    const h = hex.replace('#', '');
    const r = parseInt(h.slice(0, 2), 16), g = parseInt(h.slice(2, 4), 16), b = parseInt(h.slice(4, 6), 16);
    return `rgba(${r},${g},${b},${a})`;
  };
  const PAD = { l: 48, r: 14, t: 14, b: 24 };

  container.innerHTML = '';
  const barEl = el('div', { class: 'chart-bar' });
  const chipsEl = el('div', { class: 'chart-chips' });
  const rangeEl = el('div', { class: 'chart-chips' });
  const summaryEl = el('div', { class: 'chart-summary muted' });
  const wrap = el('div', { style: 'position:relative' });
  const cv = el('canvas', { style: 'width:100%;height:220px;display:block;touch-action:pan-y' });
  const tip = el('div', { class: 'chart-tip' });
  tip.hidden = true;
  wrap.appendChild(cv); wrap.appendChild(tip);
  barEl.appendChild(chipsEl); barEl.appendChild(rangeEl);
  container.appendChild(barEl); container.appendChild(summaryEl); container.appendChild(wrap);

  const slice = () => state.range > 0 ? cfg.series.slice(-state.range) : cfg.series;
  const niceMax = v => {
    if (v <= 0) return 1;
    const p = Math.pow(10, Math.floor(Math.log10(v)));
    const n = v / p;
    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * p;
  };

  function draw() {
    const dpr = window.devicePixelRatio || 1;
    const w = wrap.clientWidth || 300, h = 220;
    cv.width = w * dpr; cv.height = h * dpr;
    const ctx = cv.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);
    if (!cfg.series || !cfg.series.length) return;

    const data = slice();
    const m = metrics[state.metric];
    const color = colorOf(m.color);
    const gridColor = themeVar.getPropertyValue('--border').trim() || 'rgba(128,128,128,.25)';
    const textColor = themeVar.getPropertyValue('--muted').trim() || '#909399';
    const plotW = w - PAD.l - PAD.r, plotH = h - PAD.t - PAD.b;
    const n = data.length;
    const step = plotW / Math.max(n, 1);
    const vals = data.map(d => d[state.metric] ?? 0);

    const negMax = m.sign ? niceMax(Math.max(0, ...vals.map(v => -v))) : 0;
    const vmax = niceMax(Math.max(0, ...vals));
    const vmin = -negMax;
    const y = v => PAD.t + plotH - (v - vmin) / (vmax - vmin) * plotH;
    const x = i => PAD.l + (i + 0.5) * step;

    // 网格 + Y 轴
    ctx.font = '11px -apple-system,system-ui,sans-serif';
    ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
    for (let t = 0; t <= 4; t++) {
      const v = vmin + (vmax - vmin) * t / 4;
      const yy = y(v);
      ctx.strokeStyle = gridColor; ctx.lineWidth = 1; ctx.globalAlpha = .6;
      ctx.beginPath(); ctx.moveTo(PAD.l, yy); ctx.lineTo(w - PAD.r, yy); ctx.stroke();
      ctx.globalAlpha = 1;
      ctx.fillStyle = textColor;
      ctx.fillText(String(Math.round(v)), PAD.l - 8, yy);
    }
    if (vmin < 0) {
      ctx.strokeStyle = textColor; ctx.globalAlpha = .5;
      ctx.beginPath(); ctx.moveTo(PAD.l, y(0)); ctx.lineTo(w - PAD.r, y(0)); ctx.stroke();
      ctx.globalAlpha = 1;
    }
    // X 轴日期（稀疏）
    ctx.textAlign = 'center'; ctx.textBaseline = 'top';
    const tickN = Math.min(6, n);
    for (let i = 0; i < tickN; i++) {
      const idx = tickN === 1 ? Math.floor(n / 2) : Math.round(i * (n - 1) / (tickN - 1));
      ctx.fillStyle = textColor;
      ctx.fillText(data[idx].date.slice(5), x(idx), h - PAD.b + 6);
    }
    // 悬停参考线
    if (state.hover >= 0 && state.hover < n) {
      ctx.strokeStyle = color; ctx.globalAlpha = .5; ctx.setLineDash([4, 4]);
      ctx.beginPath(); ctx.moveTo(x(state.hover), PAD.t); ctx.lineTo(x(state.hover), h - PAD.b); ctx.stroke();
      ctx.setLineDash([]); ctx.globalAlpha = 1;
    }

    if (m.type === 'bar') {
      const bw = Math.max(2, Math.min(18, step * 0.62));
      for (let j = 0; j < n; j++) {
        const v = vals[j];
        if (v === 0 && j !== state.hover) continue;
        const c = m.sign ? (v >= 0 ? colorOf('--success') : colorOf('--danger')) : color;
        const top = Math.min(y(0), y(v)), hgt = Math.max(Math.abs(y(0) - y(v)), 1);
        const g = ctx.createLinearGradient(0, top, 0, top + hgt);
        g.addColorStop(0, c); g.addColorStop(1, rgba(c.startsWith('#') ? c : '#8b73ff', .3));
        ctx.fillStyle = j === state.hover ? color : g;
        ctx.fillRect(x(j) - bw / 2, top, bw, hgt);
      }
    } else {
      const lg = ctx.createLinearGradient(0, PAD.t, 0, PAD.t + plotH);
      lg.addColorStop(0, rgba(color, .32)); lg.addColorStop(1, rgba(color, .03));
      ctx.beginPath();
      vals.forEach((v, k) => { if (k === 0) ctx.moveTo(x(k), y(v)); else ctx.lineTo(x(k), y(v)); });
      ctx.lineTo(x(n - 1), y(vmin)); ctx.lineTo(x(0), y(vmin)); ctx.closePath();
      ctx.fillStyle = lg; ctx.fill();
      const lp = new Path2D();
      vals.forEach((v, k) => { if (k === 0) lp.moveTo(x(k), y(v)); else lp.lineTo(x(k), y(v)); });
      ctx.strokeStyle = color; ctx.lineWidth = 2; ctx.lineJoin = 'round';
      ctx.stroke(lp);
      if (n <= 40 || state.hover >= 0) {
        vals.forEach((v, k) => {
          if (n > 40 && k !== state.hover) return;
          ctx.beginPath();
          ctx.arc(x(k), y(v), k === state.hover ? 4.5 : 2.5, 0, Math.PI * 2);
          ctx.fillStyle = k === state.hover ? '#fff' : color;
          ctx.fill();
        });
      }
    }
  }

  function renderSummary() {
    if (typeof cfg.summary === 'function') summaryEl.innerHTML = cfg.summary(slice());
  }

  function showTip(i, mouseX) {
    const d = slice()[i];
    if (!d) { tip.hidden = true; return; }
    const m = metrics[state.metric];
    const v = d[state.metric] ?? 0;
    const shown = m.money ? '¥' + Number(v).toFixed(2) : v;
    tip.innerHTML = `<b>${d.date}</b><br>${m.label}：<b>${shown}</b>`;
    tip.hidden = false;
    tip.style.left = Math.min(Math.max(mouseX - tip.offsetWidth / 2, 4), wrap.clientWidth - tip.offsetWidth - 4) + 'px';
  }

  function idxFromEvent(e) {
    const rect = cv.getBoundingClientRect();
    const step = (rect.width - PAD.l - PAD.r) / Math.max(slice().length, 1);
    const i = Math.floor((e.clientX - rect.left - PAD.l) / step);
    return (i >= 0 && i < slice().length) ? i : -1;
  }
  let pressing = false;
  const onMove = e => {
    const i = idxFromEvent(e);
    if (i !== state.hover) { state.hover = i; draw(); }
    if (i >= 0) showTip(i, e.clientX - cv.getBoundingClientRect().left); else tip.hidden = true;
    if (pressing && e.pointerType === 'touch') e.preventDefault();
  };
  cv.addEventListener('pointerdown', e => { pressing = true; onMove(e); });
  cv.addEventListener('pointermove', onMove);
  cv.addEventListener('pointerup', () => { pressing = false; });
  cv.addEventListener('pointerleave', () => { if (!pressing) { state.hover = -1; tip.hidden = true; draw(); } pressing = false; });

  // 指标切换 chips
  const metricChips = {};
  Object.entries(metrics).forEach(([key, m]) => {
    const b = el('button', { class: 'chart-chip', type: 'button' }, m.label);
    if (m.color) {
      const dot = el('i');
      dot.style.background = colorOf(m.color);
      b.insertBefore(dot, b.firstChild);
    }
    b.addEventListener('click', () => {
      state.metric = key; state.hover = -1;
      save(); syncChips(); draw();
    });
    metricChips[key] = b;
    chipsEl.appendChild(b);
  });
  // 范围 chips
  const rangeChips = {};
  [[7, '近7天'], [30, '近30天'], [90, '近90天']].forEach(([r, label]) => {
    const b = el('button', { class: 'chart-chip', type: 'button' }, label);
    b.addEventListener('click', () => {
      state.range = r; state.hover = -1;
      save(); syncChips(); draw(); renderSummary();
    });
    rangeChips[r] = b;
    rangeEl.appendChild(b);
  });
  function syncChips() {
    Object.entries(metricChips).forEach(([k, b]) => b.classList.toggle('active', k === state.metric));
    Object.entries(rangeChips).forEach(([k, b]) => b.classList.toggle('active', Number(k) === state.range));
  }

  let rz = null;
  window.addEventListener('resize', () => { clearTimeout(rz); rz = setTimeout(() => { state.hover = -1; tip.hidden = true; draw(); }, 150); });

  syncChips(); renderSummary(); draw();
};

// ── 全屏图片查看器（放大/缩小/拖动/双指捏合/双击，多图切换） ──
// 用法：openImageViewer({ images:[{src,label}], start:0 })
window.openImageViewer = function ({ images = [], start = 0 } = {}) {
  if (!images.length) return;
  let cur = Math.min(Math.max(start, 0), images.length - 1);
  let scale = 1, tx = 0, ty = 0;

  const ov = document.createElement('div');
  ov.className = 'img-viewer';
  ov.innerHTML = `
    <div class="iv-top">
      <div class="iv-tabs"></div>
      <div class="iv-actions">
        <button type="button" class="iv-btn" data-act="out" aria-label="缩小">－</button>
        <span class="iv-scale">100%</span>
        <button type="button" class="iv-btn" data-act="in" aria-label="放大">＋</button>
        <button type="button" class="iv-btn" data-act="reset">重置</button>
        <button type="button" class="iv-btn" data-act="close" aria-label="关闭">✕</button>
      </div>
    </div>
    <div class="iv-stage"><img class="iv-img" alt="预览"></div>`;
  const tabs = ov.querySelector('.iv-tabs');
  const scaleEl = ov.querySelector('.iv-scale');
  const stage = ov.querySelector('.iv-stage');
  const img = ov.querySelector('.iv-img');
  document.body.appendChild(ov);
  const prevOverflow = document.body.style.overflow;
  document.body.style.overflow = 'hidden';

  function apply() {
    img.style.transform = `translate(${tx}px, ${ty}px) scale(${scale})`;
    scaleEl.textContent = Math.round(scale * 100) + '%';
  }
  function resetView() { scale = 1; tx = 0; ty = 0; apply(); }
  function clampPos() {
    if (scale <= 1) { tx = 0; ty = 0; return; }
    const r = img.getBoundingClientRect();
    const mx = (r.width * scale - window.innerWidth) / 2 + 60;
    const my = (r.height * scale - window.innerHeight) / 2 + 60;
    tx = Math.min(mx, Math.max(-mx, tx));
    ty = Math.min(my, Math.max(-my, ty));
  }
  function zoomAt(cx, cy, factor) {
    const ns = Math.min(8, Math.max(1, scale * factor));
    const k = ns / scale;
    tx = cx - (cx - tx) * k;
    ty = cy - (cy - ty) * k;
    scale = ns;
    clampPos(); apply();
  }
  function switchTo(i) {
    cur = i; resetView();
    tabs.querySelectorAll('.iv-tab').forEach((t, k) => t.classList.toggle('active', k === cur));
    img.src = images[cur].src;
  }
  function close() {
    document.removeEventListener('keydown', onKey);
    document.body.style.overflow = prevOverflow;
    ov.remove();
  }
  function onKey(e) { if (e.key === 'Escape') close(); }

  // 顶部：图片切换 tabs + 按钮
  if (images.length > 1) {
    images.forEach((im, i) => {
      const t = el('button', { class: 'iv-tab', type: 'button' }, im.label || ('图' + (i + 1)));
      t.addEventListener('click', () => switchTo(i));
      tabs.appendChild(t);
    });
  } else {
    const t = el('span', { class: 'iv-tab active' }, images[0].label || '预览');
    tabs.appendChild(t);
  }
  ov.querySelector('.iv-actions').addEventListener('click', e => {
    const act = e.target.getAttribute && e.target.getAttribute('data-act');
    if (!act) return;
    const cx = window.innerWidth / 2, cy = window.innerHeight / 2;
    if (act === 'in') zoomAt(cx, cy, 1.25);
    else if (act === 'out') zoomAt(cx, cy, 0.8);
    else if (act === 'reset') resetView();
    else if (act === 'close') close();
  });

  // 滚轮缩放（以光标为中心）
  stage.addEventListener('wheel', e => {
    e.preventDefault();
    zoomAt(e.clientX, e.clientY, e.deltaY < 0 ? 1.15 : 1 / 1.15);
  }, { passive: false });

  // 双击 1x ↔ 2.5x
  img.addEventListener('dblclick', e => {
    e.preventDefault();
    if (scale > 1) resetView();
    else zoomAt(e.clientX, e.clientY, 2.5 / scale);
  });

  // 拖动 + 双指捏合
  const pts = new Map();
  let pinchDist = 0;
  stage.addEventListener('pointerdown', e => {
    stage.setPointerCapture(e.pointerId);
    pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pts.size === 2) {
      const [a, b] = [...pts.values()];
      pinchDist = Math.hypot(a.x - b.x, a.y - b.y);
    }
  });
  stage.addEventListener('pointermove', e => {
    if (!pts.has(e.pointerId)) return;
    const prev = pts.get(e.pointerId);
    pts.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pts.size === 1) {
      if (scale > 1) {
        tx += e.clientX - prev.x;
        ty += e.clientY - prev.y;
        clampPos(); apply();
        img.classList.add('dragging');
      }
    } else if (pts.size === 2) {
      const [a, b] = [...pts.values()];
      const d = Math.hypot(a.x - b.x, a.y - b.y);
      if (pinchDist > 0 && d > 0) {
        const cx = (a.x + b.x) / 2, cy = (a.y + b.y) / 2;
        zoomAt(cx, cy, d / pinchDist);
      }
      pinchDist = d;
    }
  });
  const endPointer = e => { pts.delete(e.pointerId); pinchDist = 0; img.classList.remove('dragging'); };
  stage.addEventListener('pointerup', endPointer);
  stage.addEventListener('pointercancel', endPointer);

  // 阻止 Safari 手势缩放页面 / 长按菜单
  ['gesturestart', 'gesturechange'].forEach(ev => ov.addEventListener(ev, e => e.preventDefault()));
  ov.addEventListener('contextmenu', e => e.preventDefault());
  // 点空白关闭
  ov.addEventListener('click', e => { if (e.target === ov || e.target === stage) close(); });
  document.addEventListener('keydown', onKey);

  img.onerror = () => { img.alt = '图片加载失败'; img.style.visibility = 'hidden'; };
  img.onload = () => { img.style.visibility = 'visible'; };
  switchTo(cur);
};
