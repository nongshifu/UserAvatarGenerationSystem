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
