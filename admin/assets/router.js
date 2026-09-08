// 多标签页路由
// - 每个模块打开为独立标签（类似浏览器标签），面板常驻 DOM，切换不丢失状态
// - 每个标签可独立刷新（重跑该页渲染函数）/ 关闭（移除面板）
// - 仪表盘固定，不可关闭

if (!Storage.getToken()) {
  location.href = '/admin/login.php';
} else {
  const user = Storage.getUser();
  $('#admin-name').textContent = user ? user.username : '管理员';
}

// ── 图标（内联 SVG，stroke 继承 currentColor，随主题/高亮变色） ──
const svg = (inner) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + inner + '</svg>';
const ICON = {
  dashboard: svg('<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>'),
  users:     svg('<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'),
  keys:      svg('<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>'),
  orders:    svg('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/>'),
  products:  svg('<polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>'),
  logs:      svg('<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>'),
  generations: svg('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'),
  avatars:   svg('<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/>'),
  prompts:   svg('<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><line x1="8" y1="9" x2="16" y2="9"/><line x1="8" y1="13" x2="13" y2="13"/>'),
  settings:  svg('<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'),
  // 设置模块独立图标
  site:      svg('<circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>'),
  coins:     svg('<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/>'),
  zap:       svg('<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>'),
  shield:    svg('<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>'),
  mail:      svg('<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>'),
  phone:     svg('<rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><line x1="12" y1="18" x2="12.01" y2="18"/>'),
};
const ICON_MOON  = svg('<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>');
const ICON_SUN   = svg('<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>');
const ICON_MENU  = svg('<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>');

// 菜单定义
const MENU = [
  { path: 'dashboard', label: '仪表盘', page: 'dashboard', icon: ICON.dashboard },
  { path: 'users',     label: '用户管理', page: 'users', icon: ICON.users },
  { path: 'keys',      label: '开发者 KEY', page: 'keys', icon: ICON.keys },
  { path: 'orders',    label: '订单管理', page: 'orders', icon: ICON.orders },
  { path: 'products',  label: '积分套餐', page: 'products', icon: ICON.products },
  { path: 'logs',      label: '积分流水', page: 'logs', icon: ICON.logs },
  { path: 'generations', label: '生成记录', page: 'generations', icon: ICON.generations },
  { path: 'avatars',   label: '头像池', page: 'avatars', icon: ICON.avatars },
  { path: 'prompts',   label: '提示词', page: 'prompts', icon: ICON.prompts },
  // ── 设置模块（原「系统设置」拆分为独立菜单） ──
  { path: 'set-site',   label: '站点设置', page: 'set_site',   icon: ICON.site },
  { path: 'set-points', label: '积分配置', page: 'set_points', icon: ICON.coins },
  { path: 'set-api',    label: 'API 生图', page: 'set_api',    icon: ICON.zap },
  { path: 'set-audit',  label: '审核设置', page: 'set_audit',  icon: ICON.shield },
  { path: 'set-mail',   label: '邮箱配置', page: 'set_mail',   icon: ICON.mail },
  { path: 'set-sms',    label: '短信配置', page: 'set_sms',    icon: ICON.phone },
];

// 标签状态：open 为已打开标签的 path（有序），active 为当前标签
const tabs = { open: [], active: null };

const content = $('#content');
const tabBar = $('#tab-bar');

function menuItem(path) {
  return MENU.find(m => m.path === path);
}

/** 打开标签：已存在则直接切换，不存在则新建面板并渲染 */
function openTab(path) {
  // 旧版「系统设置」单页书签 → 重定向到「站点设置」
  if (path === 'settings') path = 'set-site';
  const item = menuItem(path);
  if (!item) return;
  if (!tabs.open.includes(path)) {
    tabs.open.push(path);
    const panel = el('section', { class: 'tab-panel', 'data-page': path });
    panel.style.display = 'none';
    content.appendChild(panel);
    renderPage(path);
    renderTabBar();
  }
  activateTab(path);
  // 移动端点击菜单后自动收起抽屉
  document.body.classList.remove('sidebar-open');
}

/** 切换标签：仅显隐面板，不重新渲染，保留各标签状态（搜索条件/分页/滚动位置） */
function activateTab(path) {
  if (!tabs.open.includes(path)) return;
  tabs.active = path;
  $$('.tab-panel', content).forEach(p => {
    p.style.display = p.dataset.page === path ? '' : 'none';
  });
  $$('.tab-item', tabBar).forEach(t => {
    t.classList.toggle('active', t.dataset.page === path);
  });
  renderMenuActive(path);
  const item = menuItem(path);
  $('#page-title').textContent = item ? item.label : '';
  if (location.hash !== '#' + path) location.hash = path;
}

/** 关闭标签：移除面板；若关的是当前标签，切到相邻标签 */
function closeTab(path) {
  const idx = tabs.open.indexOf(path);
  if (idx === -1 || path === 'dashboard') return;
  tabs.open.splice(idx, 1);
  const panel = content.querySelector('.tab-panel[data-page="' + path + '"]');
  if (panel) panel.remove();
  renderTabBar();
  if (tabs.active === path) {
    const next = tabs.open[Math.min(idx, tabs.open.length - 1)] || 'dashboard';
    openTab(next);
  }
}

/** 刷新标签：只重跑该标签的渲染函数（等同该页"重新加载"），不影响其他标签 */
function refreshTab(path) {
  const panel = content.querySelector('.tab-panel[data-page="' + path + '"]');
  if (panel) renderPage(path, panel);
}

/** 渲染指定标签的页面内容 */
function renderPage(path, panelEl) {
  const panel = panelEl || content.querySelector('.tab-panel[data-page="' + path + '"]');
  if (!panel) return;
  const item = menuItem(path);
  panel.innerHTML = '';
  const fn = window.PAGES && window.PAGES[item.page];
  if (typeof fn === 'function') {
    try {
      fn(panel);
    } catch (e) {
      panel.innerHTML = '';
      panel.appendChild(el('div', { class: 'card' }, '页面渲染失败：' + esc(e.message)));
    }
  } else {
    panel.appendChild(el('div', { class: 'card' }, '页面未实现：' + esc(item.page)));
  }
}

/** 渲染标签栏（图标 + 标题 + 刷新按钮 + 关闭按钮） */
function renderTabBar() {
  tabBar.innerHTML = '';
  tabs.open.forEach(path => {
    const item = menuItem(path);
    if (!item) return;
    const tab = el('div', {
      class: 'tab-item' + (tabs.active === path ? ' active' : ''),
      'data-page': path,
    });
    tab.appendChild(el('span', { class: 'tab-icon', html: item.icon }));
    tab.appendChild(el('span', {
      class: 'tab-title',
      title: '切换到「' + item.label + '」',
      onclick: () => activateTab(path),
    }, item.label));
    tab.appendChild(el('button', {
      class: 'tab-btn tab-refresh',
      title: '刷新该标签',
      onclick: (e) => { e.stopPropagation(); refreshTab(path); },
    }, '⟳'));
    if (path !== 'dashboard') {
      tab.appendChild(el('button', {
        class: 'tab-btn tab-close',
        title: '关闭该标签',
        onclick: (e) => { e.stopPropagation(); closeTab(path); },
      }, '×'));
    }
    tabBar.appendChild(tab);
  });
}

/** 渲染左侧菜单（图标 + 文字） */
function renderMenu() {
  const menu = $('#menu');
  menu.innerHTML = '';
  MENU.forEach(item => {
    menu.appendChild(el('a', {
      href: '#' + item.path,
      class: tabs.active === item.path ? 'active' : '',
      title: item.label,
      onclick: (e) => { e.preventDefault(); openTab(item.path); },
    }, [
      el('span', { class: 'menu-icon', html: item.icon }),
      el('span', { class: 'menu-text' }, item.label),
    ]));
  });
}

/** 仅更新菜单高亮（切换标签时用，避免整棵菜单重建） */
function renderMenuActive(path) {
  $$('#menu a').forEach(a => {
    a.classList.toggle('active', a.getAttribute('href') === '#' + path);
  });
}

// ── 主题切换（暗色 / 亮色，持久化到 localStorage） ──
function currentTheme() {
  return document.documentElement.getAttribute('data-theme') || 'light';
}
function renderThemeBtn() {
  const btn = $('#theme-toggle');
  if (!btn) return;
  const dark = currentTheme() === 'dark';
  btn.innerHTML = dark ? ICON_SUN : ICON_MOON;
  btn.title = dark ? '切换为亮色主题' : '切换为暗色主题';
}
$('#theme-toggle') && ($('#theme-toggle').onclick = () => {
  const next = currentTheme() === 'dark' ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', next);
  localStorage.setItem('avatar_admin_theme', next);
  renderThemeBtn();
});
renderThemeBtn();

// ── 顶栏缓存快捷操作 ──
const ICON_REFRESH = svg('<polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>');
const ICON_TRASH   = svg('<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>');
const flushBtn = $('#cache-flush-btn');
if (flushBtn) {
  flushBtn.innerHTML = ICON_REFRESH;
  flushBtn.onclick = async () => {
    const ok = await confirmModal({ title: '刷新站点缓存', message: '清空风格/价格、头像池等 Redis 缓存，下次访问自动重建。确定？', okText: '刷新' });
    if (!ok) return;
    try { const r = await api('/cache/flush', { method: 'POST' }); toast('缓存已刷新（清除 ' + r.deleted_keys + ' 个键）', 'success'); }
    catch (e) { toast(e.message, 'error'); }
  };
}
const cleanBtn = $('#cache-clean-btn');
if (cleanBtn) {
  cleanBtn.innerHTML = ICON_TRASH;
  cleanBtn.onclick = async () => {
    const ok = await confirmModal({ title: '清理上传缓存', message: '删除 7 天前上传的参考原图（不影响已生成的头像），可释放服务器空间。确定？', okText: '清理', danger: true });
    if (!ok) return;
    try {
      const r = await api('/cache/clean-uploads', { method: 'POST', body: { days: 7 } });
      toast('已清理 ' + r.deleted + ' 个文件，释放 ' + r.freed_mb + 'MB', 'success');
    } catch (e) { toast(e.message, 'error'); }
  };
}

// ── 侧边栏：桌面端折叠为纯图标，移动端抽屉式弹出 ──
$('#sidebar-toggle') && ($('#sidebar-toggle').innerHTML = ICON_MENU);
$('#sidebar-toggle') && ($('#sidebar-toggle').onclick = () => {
  if (window.innerWidth <= 768) {
    document.body.classList.toggle('sidebar-open');
  } else {
    const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
    localStorage.setItem('avatar_admin_sidebar', collapsed ? '1' : '0');
  }
});
$('#sidebar-mask') && ($('#sidebar-mask').onclick = () => {
  document.body.classList.remove('sidebar-open');
});

// 浏览器前进/后退：同步到标签
window.addEventListener('hashchange', () => {
  const path = location.hash.slice(1) || 'dashboard';
  if (path !== tabs.active) openTab(path);
});

$('#logout-btn').addEventListener('click', async () => {
  const ok = await confirmModal({ title: '退出登录', message: '确定退出当前管理后台账号吗？', okText: '退出' });
  if (ok) {
    Storage.clear();
    location.href = '/admin/login.php';
  }
});

// 初始化：菜单 + 打开地址栏对应标签（默认仪表盘）
renderMenu();
openTab(location.hash.slice(1) || 'dashboard');
