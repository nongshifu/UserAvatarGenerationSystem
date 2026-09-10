// 全部页面渲染函数
// 注册到 window.PAGES

window.PAGES = {};

// ── 仪表盘 ──────────────────────────────────────
window.PAGES.dashboard = async function (root) {
  root.innerHTML = '<div class="stat-grid" id="stat"></div><div class="chart-grid" id="charts"></div>';
  // 注意：多标签下面板常驻 DOM，子元素查找必须限定在 root 内，避免命中其他标签的同 ID 元素
  try {
    const data = await api('/dashboard');
    const s = data.stat;
    const cards = [
      ['今日生成', s.today_generate],
      ['今日注册', s.today_register],
      ['今日充值额', '¥' + Number(s.today_recharge).toFixed(2)],
      ['头像池总量', s.avatar_pool_total],
      ['待审核订单', s.pending_orders],
      ['待审核头像', s.pending_audit],
      ['用户总数', s.user_total],
    ];
    const statEl = root.querySelector('#stat');
    cards.forEach(([label, val]) => {
      statEl.appendChild(el('div', { class: 'stat-card' }, [
        el('div', { class: 'label' }, label),
        el('div', { class: 'value' }, String(val)),
      ]));
    });

    // ── 全站走势图表（4 张卡片）──
    const charts = root.querySelector('#charts');
    const empty = { generations: [], registers: [], recharges: [], points: [] };
    const series = Object.assign({}, empty, data.series || {});
    const chartCard = (title, mountEl) => el('div', { class: 'chart-card' }, [
      el('div', { class: 'chart-head' }, title),
      mountEl,
    ]);

    // 生成记录：生成数 / 成功 / 失败
    const gBody = el('div');
    mountChart(gBody, {
      series: series.generations,
      ssKey: 'ad_chart_generations',
      metrics: {
        total:   { label: '生成数', color: '--primary', type: 'line' },
        success: { label: '成功',   color: '--success', type: 'bar' },
        failed:  { label: '失败',   color: '--danger',  type: 'bar' },
      },
      summary: d => {
        let t = 0, ok = 0, fail = 0;
        d.forEach(r => { t += r.total; ok += r.success; fail += r.failed; });
        const rate = t > 0 ? Math.round(ok / t * 100) : 0;
        return `合计 <b>${t}</b> · 成功 <b>${ok}</b> · 失败 <b>${fail}</b> · 成功率 <b>${rate}%</b>`;
      },
    });
    charts.appendChild(chartCard('生成记录', gBody));

    // 用户注册：注册数
    const rBody = el('div');
    mountChart(rBody, {
      series: series.registers,
      ssKey: 'ad_chart_registers',
      metrics: { total: { label: '注册数', color: '--primary', type: 'line' } },
      summary: d => {
        let t = 0;
        d.forEach(r => { t += r.total; });
        return `期间新增注册 <b>${t}</b> 人`;
      },
    });
    charts.appendChild(chartCard('用户注册', rBody));

    // 充值记录：充值额（曲线）/ 笔数（柱）
    const pBody = el('div');
    mountChart(pBody, {
      series: series.recharges,
      ssKey: 'ad_chart_recharges',
      metrics: {
        amount: { label: '充值额', color: '--success', type: 'line', money: true },
        cnt:    { label: '充值笔数', color: '--primary', type: 'bar' },
      },
      summary: d => {
        let amt = 0, c = 0;
        d.forEach(r => { amt += Number(r.amount); c += r.cnt; });
        return `期间充值 <b>¥${amt.toFixed(2)}</b> · 共 <b>${c}</b> 笔`;
      },
    });
    charts.appendChild(chartCard('充值记录', pBody));

    // 全站积分流水：收入 / 支出 / 净变动
    const ptBody = el('div');
    mountChart(ptBody, {
      series: series.points,
      ssKey: 'ad_chart_points',
      metrics: {
        income:  { label: '收入',   color: '--success', type: 'bar' },
        expense: { label: '支出',   color: '--danger',  type: 'bar' },
        net:     { label: '净变动', color: '--primary', type: 'bar', sign: true },
      },
      summary: d => {
        let inc = 0, exp = 0;
        d.forEach(r => { inc += r.income; exp += r.expense; });
        const net = inc - exp;
        return `收入 <b>+${inc}</b> · 支出 <b>-${exp}</b> · 净变动 <b>${net >= 0 ? '+' : ''}${net}</b>`;
      },
    });
    charts.appendChild(chartCard('积分流水', ptBody));
  } catch (e) {
    root.appendChild(el('div', { class: 'card' }, '加载失败：' + esc(e.message)));
  }
};

// ── 用户管理 ──────────────────────────────────────
window.PAGES.users = function (root) {
  let state = { keyword: '', role: '', status: '', page: 1 };
  async function load() {
    const params = new URLSearchParams({ page: state.page, per_page: 20 });
    if (state.keyword) params.set('keyword', state.keyword);
    if (state.role)   params.set('role', state.role);
    if (state.status !== '') params.set('status', state.status);
    try {
      const data = await api('/users?' + params);
      render(data);
    } catch (e) { toast(e.message, 'error'); }
  }
  function render(data) {
    root.innerHTML = '';
    // 工具栏
    const tb = el('div', { class: 'toolbar' });
    tb.appendChild(el('input', { id: 'kw', placeholder: '用户名/邮箱/手机号', value: state.keyword, oninput: e => state.keyword = e.target.value }));
    const roleSel = el('select', { id: 'role', onchange: e => { state.role = e.target.value; state.page = 1; load(); } }, [
      el('option', { value: '' }, '全部角色'),
      el('option', { value: 'user' }, '用户'),
      el('option', { value: 'developer' }, '开发者'),
      el('option', { value: 'admin' }, '管理员'),
    ]);
    roleSel.value = state.role;
    tb.appendChild(roleSel);
    const stSel = el('select', { id: 'st', onchange: e => { state.status = e.target.value; state.page = 1; load(); } }, [
      el('option', { value: '' }, '全部状态'),
      el('option', { value: '1' }, '正常'),
      el('option', { value: '0' }, '禁用'),
    ]);
    stSel.value = state.status;
    tb.appendChild(stSel);
    tb.appendChild(el('button', { class: 'btn-primary', onclick: () => { state.page = 1; load(); } }, '搜索'));
    root.appendChild(tb);

    const tblWrap = el('div', { class: 'card' });
    renderTable(tblWrap, [
      { key: 'id', label: 'ID' },
      { key: 'username', label: '用户名' },
      { key: 'nickname', label: '昵称', render: r => r.nickname || el('span', { class: 'muted' }, '—') },
      { key: 'email', label: '邮箱', render: r => r.email || el('span', { class: 'muted' }, '—') },
      { key: 'role', label: '角色', render: r => {
        const map = { admin: ['管理员', 'danger'], developer: ['开发者', 'warning'], user: ['用户', 'default'] };
        const [t, tp] = map[r.role] || [r.role, 'default'];
        return badge(t, tp);
      } },
      { key: 'points', label: '当前积分', render: r => el('b', { style: 'color:#7b5cff' }, String(r.points ?? 0)) },
      { key: 'status', label: '状态', render: r => badge(r.status == 1 ? '正常' : '禁用', r.status == 1 ? 'success' : 'danger') },
      { key: 'created_at', label: '注册时间' },
      {
        key: 'op', label: '操作',
        render: r => [
          el('button', { class: 'btn btn-sm', onclick: () => editUser(r) }, '编辑'),
          el('button', { class: 'btn btn-sm btn-primary', onclick: () => adjustPoints(r) }, '调整积分'),
        ],
      },
    ], data.list);
    root.appendChild(tblWrap);

    const pg = el('div', { class: 'pagination' });
    renderPagination(pg, data.pagination, p => { state.page = p; load(); });
    root.appendChild(pg);
  }

  async function editUser(r) {
    const values = await formModal({
      title: `编辑用户 · ${r.username}`,
      large: true,
      fields: [
        { name: 'nickname', label: '昵称', type: 'text', value: r.nickname || '' },
        { name: 'email', label: '邮箱', type: 'text', value: r.email || '', required: true },
        { name: 'phone', label: '手机号', type: 'text', value: r.phone || '' },
        {
          name: 'role', label: '角色', type: 'select', value: r.role || 'user',
          options: [
            { value: 'user', label: '普通用户' },
            { value: 'developer', label: '开发者' },
            { value: 'admin', label: '管理员' },
          ],
        },
        { name: 'status', label: '账号正常（关闭=禁用登录）', type: 'switch', value: r.status == 1 },
        { name: 'password', label: '重置密码（留空则不修改，至少 6 位）', type: 'text', value: '', placeholder: '不修改请留空' },
      ],
      okText: '保存修改',
    });
    if (!values) return;
    const body = {
      nickname: values.nickname || '',
      email: values.email || '',
      phone: values.phone || '',
      role: values.role,
      status: values.status ? 1 : 0,
    };
    if (values.password) body.password = values.password;
    try {
      await api(`/users/${r.id}`, { method: 'PUT', body });
      toast('用户信息已保存', 'success');
      load();
    } catch (e) { toast(e.message, 'error'); }
  }

  async function adjustPoints(r) {
    const values = await formModal({
      title: `调整积分 · ${r.username}（当前 ${r.points ?? 0} 积分）`,
      fields: [
        {
          name: 'type', label: '操作类型', type: 'select', value: 'add',
          options: [
            { value: 'add', label: '加分（充值/奖励）' },
            { value: 'sub', label: '扣分（扣减/惩罚）' },
          ],
        },
        { name: 'amount', label: '数量（正整数）', type: 'number', value: 10, step: 1, min: 1, required: true },
        { name: 'remark', label: '备注', type: 'textarea', placeholder: '如：活动奖励、违规扣减等' },
      ],
      okText: '确认调整',
    });
    if (!values) return;
    try {
      const res = await api(`/users/${r.id}/points`, { method: 'POST', body: { type: values.type, amount: values.amount, remark: values.remark || '' } });
      toast('调整成功，最新余额 ' + res.balance + ' 积分', 'success');
      load();
    } catch (e) { toast(e.message, 'error'); }
  }
  load();
};

// ── 开发者 KEY ──────────────────────────────────────
window.PAGES.keys = function (root) {
  let state = { keyword: '', status: '', page: 1 };
  async function load() {
    const params = new URLSearchParams({ page: state.page, per_page: 20 });
    if (state.keyword) params.set('keyword', state.keyword);
    if (state.status !== '') params.set('status', state.status);
    try {
      const data = await api('/keys?' + params);
      render(data);
    } catch (e) { toast(e.message, 'error'); }
  }
  function render(data) {
    root.innerHTML = '';
    const tb = el('div', { class: 'toolbar' });
    tb.appendChild(el('input', { placeholder: '用户名/邮箱/手机号', value: state.keyword, oninput: e => state.keyword = e.target.value }));
    tb.appendChild(el('button', { class: 'btn-primary', onclick: () => { state.page = 1; load(); } }, '搜索'));
    root.appendChild(tb);

    const wrap = el('div', { class: 'card' });
    renderTable(wrap, [
      { key: 'id', label: 'ID' },
      { key: 'username', label: '用户', render: r => r.username || ('#' + r.user_id) },
      { key: 'api_key_masked', label: 'API Key' },
      { key: 'name', label: '名称' },
      { key: 'status', label: '状态', render: r => badge(r.status == 1 ? '正常' : '禁用', r.status == 1 ? 'success' : 'danger') },
      { key: 'rate_limit', label: 'QPS' },
      { key: 'daily_limit', label: '日限额' },
      { key: 'used_today', label: '今日已用' },
      { key: 'op', label: '操作', render: r => [
        el('button', { class: 'btn btn-sm', onclick: () => reset(r) }, '重置'),
        el('button', { class: 'btn btn-sm', onclick: () => toggle(r) }, r.status == 1 ? '禁用' : '启用'),
      ] },
    ], data.list);
    root.appendChild(wrap);

    const pg = el('div', { class: 'pagination' });
    renderPagination(pg, data.pagination, p => { state.page = p; load(); });
    root.appendChild(pg);
  }
  async function reset(r) {
    const ok = await confirmModal({
      title: '重置 API Key',
      message: `确定重置该 Key 吗？旧 Key 将立即失效，已接入的应用需要同步更换。`,
      okText: '确定重置', danger: true,
    });
    if (!ok) return;
    try {
      const res = await api(`/keys/${r.id}/reset`, { method: 'POST' });
      await infoModal({
        title: '重置成功',
        message: '新 Key 仅展示这一次，请立即复制保存：',
        copyText: res.api_key,
        okText: '已保存',
      });
      load();
    } catch (e) { toast(e.message, 'error'); }
  }
  async function toggle(r) {
    try { await api(`/keys/${r.id}/toggle`, { method: 'POST' }); toast('已切换', 'success'); load(); }
    catch (e) { toast(e.message, 'error'); }
  }
  load();
};

// ── 订单管理 ──────────────────────────────────────
window.PAGES.orders = function (root) {
  let state = { status: '', keyword: '', page: 1 };
  async function load() {
    const params = new URLSearchParams({ page: state.page, per_page: 20 });
    if (state.status) params.set('status', state.status);
    if (state.keyword.trim()) params.set('keyword', state.keyword.trim());
    try {
      const data = await api('/orders?' + params);
      render(data);
    } catch (e) { toast(e.message, 'error'); }
  }
  function render(data) {
    root.innerHTML = '';
    const tb = el('div', { class: 'toolbar' });
    const stSel = el('select', { onchange: e => { state.status = e.target.value; state.page = 1; load(); } }, [
      el('option', { value: '' }, '全部状态'),
      el('option', { value: 'pending' }, '待审核'),
      el('option', { value: 'paid' }, '已支付'),
      el('option', { value: 'refunded' }, '已退款'),
      el('option', { value: 'closed' }, '已关闭'),
    ]);
    stSel.value = state.status;
    tb.appendChild(stSel);
    tb.appendChild(el('input', { placeholder: '订单号 / 用户名 / 邮箱 / 手机号', value: state.keyword, oninput: e => state.keyword = e.target.value }));
    tb.appendChild(el('button', { class: 'btn-primary', onclick: () => { state.page = 1; load(); } }, '搜索'));
    root.appendChild(tb);

    const wrap = el('div', { class: 'card' });
    renderTable(wrap, [
      { key: 'id', label: 'ID' },
      { key: 'order_no', label: '订单号' },
      { key: 'username', label: '用户', render: r => r.username || ('#' + r.user_id) },
      { key: 'product_name', label: '套餐' },
      { key: 'amount', label: '金额', render: r => '¥' + Number(r.amount).toFixed(2) },
      { key: 'points', label: '积分' },
      { key: 'status', label: '状态', render: r => {
        const map = { pending: ['待审核','warning'], paid: ['已支付','success'], refunded: ['已退款','info'], closed: ['已关闭','default'] };
        const m = map[r.status] || [r.status, 'default'];
        return badge(m[0], m[1]);
      } },
      { key: 'created_at', label: '下单时间' },
      { key: 'op', label: '操作', render: r => {
        const btns = [];
        if (r.status === 'pending') {
          btns.push(el('button', { class: 'btn btn-sm btn-primary', onclick: () => audit(r, 'paid') }, '确认到账'));
          btns.push(el('button', { class: 'btn btn-sm', onclick: () => audit(r, 'reject') }, '驳回'));
        }
        if (r.status === 'paid') {
          btns.push(el('button', { class: 'btn btn-sm btn-danger', onclick: () => refund(r) }, '退款'));
        }
        return btns.length ? btns : el('span', { class: 'muted' }, '—');
      } },
    ], data.list);
    root.appendChild(wrap);

    const pg = el('div', { class: 'pagination' });
    renderPagination(pg, data.pagination, p => { state.page = p; load(); });
    root.appendChild(pg);
  }
  async function audit(r, action) {
    const isPaid = action === 'paid';
    const values = await formModal({
      title: isPaid ? '确认到账' : '驳回订单',
      fields: [
        {
          name: 'remark', label: isPaid ? '备注（选填）' : '驳回原因',
          type: 'textarea', required: !isPaid,
          placeholder: isPaid ? '如：微信扫码已收款 ¥' + Number(r.amount).toFixed(2) : '请填写驳回原因，用户可见',
        },
      ],
      okText: isPaid ? '确认到账并发放积分' : '确认驳回',
    });
    if (!values) return;
    try { await api(`/orders/${r.id}/audit`, { method: 'POST', body: { action, remark: values.remark || '' } }); toast('已处理', 'success'); load(); }
    catch (e) { toast(e.message, 'error'); }
  }
  async function refund(r) {
    const ok = await confirmModal({
      title: '确认退款',
      message: `订单 ${r.order_no}：将扣回用户该订单获得的 ${r.points} 积分（余额不足部分记负数流水），订单标记为「已退款」。`,
      okText: '确认退款', danger: true,
    });
    if (!ok) return;
    try { await api(`/orders/${r.id}/refund`, { method: 'POST' }); toast('已退款', 'success'); load(); }
    catch (e) { toast(e.message, 'error'); }
  }
  load();
};

// ── 积分套餐 ──────────────────────────────────────
window.PAGES.products = function (root) {
  async function load() {
    try {
      const data = await api('/points/products?page=1&per_page=50');
      render(data);
    } catch (e) { toast(e.message, 'error'); }
  }
  function render(data) {
    root.innerHTML = '';
    const tb = el('div', { class: 'toolbar' });
    tb.appendChild(el('button', { class: 'btn-primary', onclick: () => edit() }, '+ 新增套餐'));
    root.appendChild(tb);
    const wrap = el('div', { class: 'card' });
    renderTable(wrap, [
      { key: 'id', label: 'ID' },
      { key: 'name', label: '名称' },
      { key: 'price', label: '价格', render: r => '¥' + Number(r.price).toFixed(2) },
      { key: 'points', label: '积分' },
      { key: 'bonus_points', label: '赠送' },
      { key: 'sort', label: '排序' },
      { key: 'status', label: '状态', render: r => badge(r.status == 1 ? '上架' : '下架', r.status == 1 ? 'success' : 'info') },
      { key: 'op', label: '操作', render: r => [
        el('button', { class: 'btn btn-sm', onclick: () => edit(r) }, '编辑'),
        el('button', { class: 'btn btn-sm btn-danger', onclick: () => destroy(r) }, '删除'),
      ] },
    ], data.list);
    root.appendChild(wrap);
  }
  async function edit(r = {}) {
    const isNew = !r.id;
    const values = await formModal({
      title: isNew ? '新增套餐' : '编辑套餐',
      large: true,
      fields: [
        { name: 'name', label: '套餐名称', type: 'text', value: r.name || '', placeholder: '如：10元/100积分', required: true },
        { name: 'price', label: '价格（元）', type: 'number', value: r.price ?? 10, step: '0.01', min: 0, required: true },
        { name: 'points', label: '积分数量', type: 'number', value: r.points ?? 100, step: 1, min: 0, required: true },
        { name: 'bonus_points', label: '赠送积分', type: 'number', value: r.bonus_points ?? 0, step: 1, min: 0 },
        { name: 'sort', label: '排序（越小越靠前）', type: 'number', value: r.sort ?? 0, step: 1, min: 0 },
        { name: 'status', label: '上架状态', type: 'switch', value: r.status == null ? 1 : Number(r.status) },
      ],
      okText: isNew ? '创建' : '保存',
    });
    if (!values) return;
    try {
      const body = {
        name: values.name,
        price: Number(values.price) || 0,
        points: Number(values.points) || 0,
        bonus_points: Number(values.bonus_points) || 0,
        sort: Number(values.sort) || 0,
        status: values.status,
      };
      if (isNew) {
        await api('/points/products', { method: 'POST', body });
      } else {
        await api('/points/products/' + r.id, { method: 'PUT', body });
      }
      toast('已保存', 'success');
      load();
    } catch (e) { toast(e.message, 'error'); }
  }
  async function destroy(r) {
    const ok = await confirmModal({
      title: '删除套餐',
      message: `确认删除套餐「${r.name}」吗？删除后用户充值页不再展示，已下单的订单不受影响。`,
      okText: '确认删除', danger: true,
    });
    if (!ok) return;
    try { await api('/points/products/' + r.id, { method: 'DELETE' }); toast('已删除', 'success'); load(); }
    catch (e) { toast(e.message, 'error'); }
  }
  load();
};

// ── 积分流水 ──────────────────────────────────────
window.PAGES.logs = function (root) {
  let state = { keyword: '', type: '', page: 1 };
  async function load() {
    const params = new URLSearchParams({ page: state.page, per_page: 20 });
    if (state.keyword.trim()) params.set('keyword', state.keyword.trim());
    if (state.type) params.set('type', state.type);
    try {
      const data = await api('/points/logs?' + params);
      render(data);
    } catch (e) { toast(e.message, 'error'); }
  }
  function render(data) {
    root.innerHTML = '';
    const tb = el('div', { class: 'toolbar' });
    tb.appendChild(el('input', { placeholder: '用户名/邮箱/手机号', value: state.keyword, oninput: e => state.keyword = e.target.value }));
    const sel = el('select', { onchange: e => { state.type = e.target.value; state.page = 1; load(); } }, [
      el('option', { value: '' }, '全部类型'),
      el('option', { value: 'register' }, '注册赠送'),
      el('option', { value: 'recharge' }, '充值'),
      el('option', { value: 'consume' }, '消耗'),
      el('option', { value: 'refund' }, '退款'),
      el('option', { value: 'admin_add' }, '管理员增加'),
      el('option', { value: 'admin_sub' }, '管理员扣减'),
    ]);
    sel.value = state.type;
    tb.appendChild(sel);
    tb.appendChild(el('button', { class: 'btn-primary', onclick: () => { state.page = 1; load(); } }, '搜索'));
    root.appendChild(tb);

    const wrap = el('div', { class: 'card' });
    renderTable(wrap, [
      { key: 'id', label: 'ID' },
      { key: 'username', label: '用户', render: r => r.username || ('#' + r.user_id) },
      { key: 'type', label: '类型' },
      { key: 'change', label: '变动', render: r => badge((r.change > 0 ? '+' : '') + r.change, r.change > 0 ? 'success' : 'danger') },
      { key: 'balance', label: '余额' },
      { key: 'remark', label: '备注' },
      { key: 'created_at', label: '时间' },
    ], data.list);
    root.appendChild(wrap);

    const pg = el('div', { class: 'pagination' });
    renderPagination(pg, data.pagination, p => { state.page = p; load(); });
    root.appendChild(pg);
  }
  load();
};

// ── 生成记录 ──────────────────────────────────────
window.PAGES.generations = function (root) {
  let state = { keyword: '', status: '', page: 1 };
  async function load() {
    const params = new URLSearchParams({ page: state.page, per_page: 20 });
    if (state.keyword.trim()) params.set('keyword', state.keyword.trim());
    if (state.status) params.set('status', state.status);
    try {
      const data = await api('/generations?' + params);
      render(data);
    } catch (e) { toast(e.message, 'error'); }
  }
  function render(data) {
    root.innerHTML = '';
    const tb = el('div', { class: 'toolbar' });
    tb.appendChild(el('input', { placeholder: '用户名/邮箱/手机号', value: state.keyword, oninput: e => state.keyword = e.target.value }));
    const stSel = el('select', { onchange: e => { state.status = e.target.value; state.page = 1; load(); } }, [
      el('option', { value: '' }, '全部状态'),
      el('option', { value: 'pending' }, '等待中'),
      el('option', { value: 'processing' }, '处理中'),
      el('option', { value: 'success' }, '成功'),
      el('option', { value: 'failed' }, '失败'),
    ]);
    stSel.value = state.status;
    tb.appendChild(stSel);
    tb.appendChild(el('button', { class: 'btn-primary', onclick: () => { state.page = 1; load(); } }, '搜索'));
    root.appendChild(tb);

    // avatar_id => {result_url, result_thumb_url}；缺失 = 头像已被删除
    const avatarMap = data.avatars || {};
    const miniPh = (text, deleted, title) => {
      const p = el('span', { class: 'gen-mini-ph' + (deleted ? ' deleted' : '') }, text);
      if (title) p.title = title;
      return p;
    };
    const buildImages = r => {
      const imgs = [];
      if (r.origin_image_url) imgs.push({ src: r.origin_image_url, label: '原图' });
      const av = r.avatar_id ? avatarMap[Number(r.avatar_id)] : null;
      if (av) imgs.push({ src: av.result_url || av.result_thumb_url, label: '生成图' });
      return imgs;
    };
    const miniThumb = (r, which) => {
      // which: 'origin' | 'result'
      const aid = r.avatar_id ? Number(r.avatar_id) : 0;
      const av = aid ? avatarMap[aid] : null;
      if (which === 'origin') {
        if (!r.origin_image_url) return miniPh('—', false, '无原图');
        const im = el('img', { src: r.origin_image_url, class: 'gen-mini-thumb', alt: '原图', title: '点击预览原图' });
        im.addEventListener('click', () => openImageViewer({ images: buildImages(r), start: 0 }));
        im.addEventListener('error', () => im.replaceWith(miniPh('—', false, '原图已缺失')));
        return im;
      }
      // 生成图
      if (r.status !== 'success' || !aid) return miniPh(r.status === 'failed' ? '✕' : '⏳', false, r.status === 'failed' ? '生成失败' : '生成中');
      if (!av) return miniPh('已删除', true, '头像已被删除');
      const im = el('img', { src: av.result_thumb_url || av.result_url, class: 'gen-mini-thumb', alt: '生成图', title: '点击预览生成图' });
      im.addEventListener('click', () => openImageViewer({ images: buildImages(r), start: r.origin_image_url ? 1 : 0 }));
      im.addEventListener('error', () => im.replaceWith(miniPh('已删除', true, '头像已被删除')));
      return im;
    };

    const wrap = el('div', { class: 'card' });
    renderTable(wrap, [
      { key: 'id', label: 'ID' },
      { key: 'username', label: '用户', render: r => r.username || ('#' + r.user_id) },
      { key: 'key_id', label: 'KEY' },
      { key: 'avatar_id', label: '头像ID' },
      { key: 'cost_points', label: '消耗' },
      { key: 'status', label: '状态', render: r => {
        const map = { pending: ['等待','warning'], processing: ['处理','info'], success: ['成功','success'], failed: ['失败','danger'] };
        const m = map[r.status] || [r.status, 'default'];
        return badge(m[0], m[1]);
      } },
      { key: 'error_msg', label: '错误' },
      { key: 'created_at', label: '时间' },
      { key: 'imgs', label: '图片', render: r => el('div', { style: 'display:flex;gap:6px;align-items:center' }, [
          miniThumb(r, 'origin'),
          miniThumb(r, 'result'),
        ]) },
    ], data.list);
    root.appendChild(wrap);

    const pg = el('div', { class: 'pagination' });
    renderPagination(pg, data.pagination, p => { state.page = p; load(); });
    root.appendChild(pg);
  }
  load();
};

// ── 头像池 ──────────────────────────────────────
window.PAGES.avatars = function (root) {
  let state = { scope: 'all', style_id: '', keyword: '', date_from: '', date_to: '', page: 1, styles: [] };

  // 初始化：加载风格列表（用于下拉展示中文名）
  (async () => {
    try {
      const r = await api('/prompts');
      state.styles = (r.styles || []).map(s => ({ id: s.id, name: s.name }));
    } catch (e) { /* 风格加载失败不影响列表，只是不显示中文名 */ }
    load();
  })();

  function buildParams() {
    const p = new URLSearchParams({ page: state.page, per_page: 24 });
    if (state.scope) p.set('scope', state.scope);
    if (state.style_id) p.set('style_id', state.style_id);
    if (state.keyword.trim()) p.set('keyword', state.keyword.trim());
    if (state.date_from) p.set('date_from', state.date_from);
    if (state.date_to) p.set('date_to', state.date_to);
    return p;
  }

  async function load() {
    try {
      const data = await api('/avatars?' + buildParams());
      render(data);
    } catch (e) { toast(e.message, 'error'); }
  }

  function render(data) {
    root.innerHTML = '';
    const tb = el('div', { class: 'toolbar', style: 'flex-wrap:wrap;gap:8px;align-items:center' });
    // 范围
    const scopeSel = el('select', { onchange: e => { state.scope = e.target.value; state.page = 1; load(); } }, [
      el('option', { value: 'all' }, '全部'),
      el('option', { value: 'public' }, '公共池'),
      el('option', { value: 'private' }, '非公共'),
    ]);
    scopeSel.value = state.scope;
    tb.appendChild(scopeSel);
    // 风格下拉
    const styleSel = el('select', { onchange: e => { state.style_id = e.target.value; } });
    styleSel.appendChild(el('option', { value: '' }, '全部风格'));
    state.styles.forEach(s => styleSel.appendChild(el('option', { value: String(s.id) }, s.name)));
    styleSel.value = state.style_id;
    tb.appendChild(styleSel);
    // 用户搜索
    const kw = el('input', { placeholder: '用户名/邮箱/手机号', value: state.keyword, oninput: e => state.keyword = e.target.value });
    tb.appendChild(kw);
    // 日期范围
    tb.appendChild(el('input', { type: 'date', value: state.date_from, onchange: e => state.date_from = e.target.value }));
    tb.appendChild(el('span', { class: 'muted' }, '至'));
    tb.appendChild(el('input', { type: 'date', value: state.date_to, onchange: e => state.date_to = e.target.value }));
    // 操作按钮
    tb.appendChild(el('button', { class: 'btn-primary', onclick: () => { state.page = 1; load(); } }, '筛选'));
    tb.appendChild(el('button', { class: 'btn', onclick: () => {
      state.scope = 'all'; state.style_id = ''; state.keyword = ''; state.date_from = ''; state.date_to = ''; state.page = 1;
      load();
    } }, '重置'));
    root.appendChild(tb);

    const wrap = el('div', { class: 'card' });
    const grid = el('div', { class: 'avatar-grid' });
    if (!data.list.length) {
      grid.appendChild(el('div', { class: 'muted' }, '暂无头像'));
    }
    data.list.forEach(av => {
      const card = el('div', { class: 'avatar-card' });
      const im = el('img', { src: av.result_thumb_url || av.result_url, loading: 'lazy', title: '点击放大预览' });
      // 预览：生成图 + 参考原图对比（原图缺失时只展示生成图）
      im.addEventListener('click', () => {
        const imgs = [{ src: av.result_url || av.result_thumb_url, label: '生成图' }];
        if (av.origin_url) imgs.push({ src: av.origin_url, label: '参考原图' });
        openImageViewer({ images: imgs, start: 0 });
      });
      card.appendChild(im);
      const meta = el('div', { class: 'meta' }, [
        el('span', {}, '#' + av.id + ' '),
        badge(av.is_public == 1 ? '公共' : '私有', av.is_public == 1 ? 'success' : 'info'),
      ]);
      if (av.style_name) {
        meta.appendChild(el('span', { class: 'muted', style: 'margin-left:6px;font-size:12px' }, '· ' + av.style_name));
      }
      card.appendChild(meta);
      card.appendChild(el('div', { style: 'padding:4px 8px 8px;display:flex;gap:4px' }, [
        el('button', { class: 'btn btn-sm', onclick: () => togglePublic(av) }, av.is_public == 1 ? '取消公共' : '设为公共'),
        el('button', { class: 'btn btn-sm btn-danger', onclick: () => destroy(av) }, '删除'),
      ]));
      grid.appendChild(card);
    });
    wrap.appendChild(grid);
    root.appendChild(wrap);

    const pg = el('div', { class: 'pagination' });
    renderPagination(pg, data.pagination, p => { state.page = p; load(); });
    root.appendChild(pg);
  }
  async function togglePublic(av) {
    try { await api(`/avatars/${av.id}/public`, { method: 'POST', body: { is_public: av.is_public == 1 ? 0 : 1 } }); toast('已切换', 'success'); load(); }
    catch (e) { toast(e.message, 'error'); }
  }
  async function destroy(av) {
    const ok = await confirmModal({
      title: '删除头像',
      message: `确认删除头像 #${av.id} 吗？该操作不可恢复。`,
      okText: '确认删除', danger: true,
    });
    if (!ok) return;
    try { await api('/avatars/' + av.id, { method: 'DELETE' }); toast('已删除', 'success'); load(); }
    catch (e) { toast(e.message, 'error'); }
  }
  // load() 在风格列表加载后自动调用
};

// ── 系统设置：按模块拆分为独立左侧菜单（站点/积分/API生图/审核/邮箱/短信） ──
// 字段类型：text / password / textarea / number / bool
const SETTING_GROUPS = [
  {
    key: 'basic', label: '站点设置', desc: '站点名称、域名、备案、版权等基础信息',
    fields: [
      { key: 'site_name', label: '站点名称', type: 'text', placeholder: '头像引擎' },
      { key: 'site_url', label: '站点 URL', type: 'text', placeholder: 'https://myradar.cn', hint: '必填！需为公网可访问的完整域名（结尾不带 /）。队列 Worker 生成头像时要用它拼接原图地址供豆包服务器下载；不填时命令行会回退成 localhost 导致生成失败' },
      { key: 'site_logo', label: '站点 Logo URL', type: 'text', placeholder: '留空使用默认图标', hint: '填写图片地址后替换导航栏 Logo' },
      { key: 'icp', label: 'ICP 备案号', type: 'text', placeholder: '如：滇ICP备19001414号', hint: '显示在全站底部，自动链接到工信部备案网站' },
      { key: 'customer_service', label: '客服联系方式', type: 'text', placeholder: '微信 / QQ / 客服链接' },
      { key: 'footer_text', label: '底部版权文案', type: 'textarea', placeholder: '留空使用默认文案', hint: '支持占位符 {year}（当前年份）和 {site}（站点名称）' },
      { key: 'analytics_code', label: '统计代码', type: 'textarea', placeholder: '如百度统计 / CNZZ 的 JS 代码', hint: '将输出到页面底部，不会的可留空' },
    ],
  },
  {
    key: 'points', label: '积分配置', desc: '新用户赠送与生成消耗',
    fields: [
      { key: 'register_bonus', label: '注册赠送积分', type: 'number', hint: '新用户注册后自动到账' },
      { key: 'default_cost', label: '默认生成消耗', type: 'number', hint: '未选择付费风格时，每次生成扣除的积分' },
      { key: 'min_balance', label: '最低余额阈值', type: 'number', hint: '低于该积分时禁止生成（预留）' },
    ],
  },
  {
    key: 'api', label: 'API 生图配置', desc: '豆包（火山方舟）图生图接口',
    fields: [
      { key: 'ark_api_key', label: '豆包 API Key', type: 'password', placeholder: '在火山方舟控制台获取', hint: '修改后需重启队列 Worker 生效' },
      { key: 'ark_model', label: '模型', type: 'text', placeholder: 'doubao-seedream-5-0-260128' },
      { key: 'image_size', label: '图片尺寸', type: 'text', placeholder: '2k', hint: '填小写：1k / 2k / 3k / 4k，或 宽x高（如 1024x1024），以模型支持为准' },
      { key: 'watermark', label: '生成图片水印', type: 'bool', hint: '开启后豆包输出图带 AI 生成水印' },
    ],
  },
  {
    key: 'audit', label: '审核设置', desc: '上传图片预审 + 生成结果内容审核',
    fields: [
      { key: 'precheck_enabled', label: '上传前 AI 图片预审', type: 'bool', hint: '开启后，上传图片先调用豆包图像理解模型判断是否合规、是否为适合做头像的人物照片，未通过则拒绝生成且不扣积分。复用「API 生图配置」中的豆包 Key' },
      { key: 'precheck_model', label: '预审模型（图像理解）', type: 'text', placeholder: 'doubao-seed-2-0-lite-260428', hint: '豆包视觉理解模型名，留空使用默认 doubao-seed-2-0-lite-260428' },
      { key: 'precheck_prompt', label: '审核提示词', type: 'textarea', placeholder: '留空使用默认规则：判断图片是否合规且为适合做头像的真人/人物照片', hint: '告诉 AI 审核规则即可；系统会自动追加固定 JSON 输出格式要求，不要在此写格式要求' },
      { key: 'precheck_fail_open', label: '审核服务异常时放行', type: 'bool', hint: '开启：豆包审核接口超时/报错时仍允许生成（避免堵业务）；关闭：异常时一律拒绝生成（最严格）' },
      { key: 'enabled', label: '生成结果内容审核', type: 'bool', hint: '头像生成后再过一遍内容安全接口；关闭后所有生成结果直接入池' },
      { key: 'auto_approve', label: '审核通过自动入公共池', type: 'bool' },
      { key: 'refund_on_reject', label: '审核驳回自动退还积分', type: 'bool' },
    ],
  },
  {
    key: 'mail', label: '邮箱配置', desc: 'SMTP 发信，用于邮箱验证码找回密码',
    fields: [
      { key: 'mail_enabled', label: '启用邮箱发信', type: 'bool', hint: '关闭后无法通过邮箱验证码找回密码' },
      { key: 'smtp_host', label: 'SMTP 服务器', type: 'text', placeholder: 'smtp.qq.com', hint: 'QQ 邮箱填 smtp.qq.com，163 填 smtp.163.com' },
      { key: 'smtp_port', label: 'SMTP 端口', type: 'number', placeholder: '465', hint: 'SSL 推荐 465（如用 587/25 请确认服务器已放行）' },
      { key: 'smtp_user', label: '发件邮箱', type: 'text', placeholder: 'yourname@qq.com' },
      { key: 'smtp_pass', label: 'SMTP 授权码', type: 'password', placeholder: '邮箱后台生成的授权码，不是登录密码', hint: 'QQ 邮箱：设置 → 账号 → POP3/SMTP 服务开启后获取授权码' },
      { key: 'from_name', label: '发件人显示名', type: 'text', placeholder: '留空使用站点名称' },
    ],
  },
  {
    key: 'sms', label: '短信配置', desc: '接口盒子 apihz.cn 短信验证码，用于绑定手机与短信找回密码',
    fields: [
      { key: 'sms_enabled', label: '启用短信验证码', type: 'bool', hint: '关闭后无法发送短信验证码' },
      { key: 'sms_force_verify', label: '强制手机验证才能生成', type: 'bool', hint: '开启后，控制台与 API（开发者 KEY）生成头像时，账号必须已绑定并验证手机，否则拒绝生成' },
      { key: 'apihz_id', label: '接口盒子开发者 ID', type: 'text', placeholder: 'apihz.cn 个人资料中查看' },
      { key: 'apihz_key', label: '接口盒子通讯秘钥', type: 'password', placeholder: 'apihz.cn 个人资料中查看' },
      { key: 'apihz_url', label: '短信代发接口地址', type: 'text', placeholder: 'https://cn.apihz.cn/api/sms/dfapi.php', hint: '留空使用默认地址' },
      { key: 'sms_dynamic', label: '启用动态秘钥验证', type: 'bool', hint: '在接口盒子后台开启动态秘钥后才需开启' },
      { key: 'sms_dmsg', label: '动态秘钥预留信息（dmsg）', type: 'text', placeholder: '接口盒子后台设置的预留信息参数' },
    ],
  },
];

/** 渲染单个配置分组页（各独立菜单共用） */
async function renderSettingPage(root, groupKey) {
  root.innerHTML = '<div class="card">加载中...</div>';
  let data;
  try {
    data = await api('/settings');
  } catch (e) { root.innerHTML = ''; root.appendChild(el('div', { class: 'card' }, '加载失败：' + esc(e.message))); return; }

  const g = SETTING_GROUPS.find(x => x.key === groupKey);
  if (!g) { root.innerHTML = ''; root.appendChild(el('div', { class: 'card' }, '未知配置模块：' + esc(groupKey))); return; }
  root.innerHTML = '';

  const card = el('div', { class: 'card' });
  card.appendChild(el('div', { class: 'card-title', style: 'display:flex;flex-direction:column;align-items:flex-start;gap:4px' }, [
    el('span', {}, g.label),
    el('span', { class: 'muted', style: 'font-size:12px;font-weight:400' }, g.desc),
  ]));

  const values = {};
  g.fields.forEach(f => {
    const raw = data[g.key] && data[g.key][f.key] != null ? data[g.key][f.key] : '';
    const v = f.type === 'bool' ? (String(raw) === '1' || String(raw) === 'true') : String(raw ?? '');
    values[f.key] = v;

    const row = el('div', { class: 'form-row' });
    row.appendChild(el('label', { style: 'margin-bottom:6px;font-weight:600' }, f.label));

    let ctrl;
    if (f.type === 'textarea') {
      ctrl = el('textarea', { rows: 3, placeholder: f.placeholder || '', style: 'width:100%;font-size:13px;resize:vertical' });
      ctrl.value = v;
      ctrl.oninput = e => { values[f.key] = e.target.value; };
    } else if (f.type === 'bool') {
      const wrap = el('label', { class: 'm-switch-row', style: 'padding:4px 0' });
      ctrl = el('input', { type: 'checkbox' });
      ctrl.checked = v;
      ctrl.onchange = e => { values[f.key] = e.target.checked; };
      wrap.appendChild(ctrl);
      wrap.appendChild(el('span', {}, v ? '已开启' : '已关闭'));
      ctrl.addEventListener('change', () => { wrap.querySelector('span:last-child').textContent = ctrl.checked ? '已开启' : '已关闭'; });
      row.appendChild(wrap);
      if (f.hint) row.appendChild(el('p', { class: 'muted', style: 'font-size:12px;margin:4px 0 0' }, f.hint));
      card.appendChild(row);
      return;
    } else {
      const inputType = f.type === 'number' ? 'number' : (f.type === 'password' ? 'password' : 'text');
      ctrl = el('input', { type: inputType, placeholder: f.placeholder || '', autocomplete: f.type === 'password' ? 'new-password' : '' });
      ctrl.value = v;
      ctrl.oninput = e => { values[f.key] = e.target.value; };
    }
    row.appendChild(ctrl);
    if (f.hint) row.appendChild(el('p', { class: 'muted', style: 'font-size:12px;margin:6px 0 0' }, f.hint));
    card.appendChild(row);
  });

  const btnRow = el('div', { style: 'display:flex;justify-content:flex-end;margin-top:14px;padding-top:14px;border-top:1px solid var(--border)' });
  const btn = el('button', { class: 'btn btn-primary' }, '保存' + g.label);
  btn.onclick = async () => {
    const items = {};
    g.fields.forEach(f => { items[f.key] = f.type === 'bool' ? (values[f.key] ? '1' : '0') : values[f.key]; });
    btn.disabled = true;
    const oldText = btn.textContent;
    btn.textContent = '保存中…';
    try {
      await api('/settings', { method: 'PUT', body: { group: g.key, items } });
      toast(g.label + '已保存', 'success');
    } catch (e) { toast(e.message, 'error'); }
    finally { btn.disabled = false; btn.textContent = oldText; }
  };
  btnRow.appendChild(btn);
  card.appendChild(btnRow);
  root.appendChild(card);

  // 缓存维护放在「站点设置」页底部
  if (groupKey === 'basic') {
    const cacheCard = el('div', { class: 'card' });
    cacheCard.appendChild(el('div', { class: 'card-title', style: 'display:flex;flex-direction:column;align-items:flex-start;gap:4px' }, [
      el('span', {}, '缓存维护'),
      el('span', { class: 'muted', style: 'font-size:12px;font-weight:400' }, '修改配置或价格后如前台未更新，可手动刷新'),
    ]));
    const cacheBtns = el('div', { style: 'display:flex;gap:10px;flex-wrap:wrap' });
    cacheBtns.appendChild(el('button', {
      class: 'btn',
      onclick: async () => {
        const ok = await confirmModal({ title: '刷新站点缓存', message: '清空风格/价格、头像池等 Redis 缓存，下次访问自动重建。确定？', okText: '刷新' });
        if (!ok) return;
        try { const r = await api('/cache/flush', { method: 'POST' }); toast('缓存已刷新（清除 ' + r.deleted_keys + ' 个键）', 'success'); }
        catch (e) { toast(e.message, 'error'); }
      },
    }, '刷新站点缓存'));
    cacheBtns.appendChild(el('button', {
      class: 'btn',
      onclick: async () => {
        const ok = await confirmModal({ title: '清理上传缓存', message: '删除 7 天前上传的参考原图（不影响已生成的头像），可释放服务器空间。确定？', okText: '清理' });
        if (!ok) return;
        try {
          const r = await api('/cache/clean-uploads', { method: 'POST', body: { days: 7 } });
          toast('已清理 ' + r.deleted + ' 个文件，释放 ' + r.freed_mb + 'MB', 'success');
        } catch (e) { toast(e.message, 'error'); }
      },
    }, '清理上传缓存（7天前）'));
    cacheCard.appendChild(cacheBtns);
    root.appendChild(cacheCard);
  }
}

// 六个独立设置菜单页
window.PAGES.set_site   = root => renderSettingPage(root, 'basic');
window.PAGES.set_sms    = root => renderSettingPage(root, 'sms');
window.PAGES.set_mail   = root => renderSettingPage(root, 'mail');
window.PAGES.set_points = root => renderSettingPage(root, 'points');
window.PAGES.set_api    = root => renderSettingPage(root, 'api');
window.PAGES.set_audit  = root => renderSettingPage(root, 'audit');
// 旧版「系统设置」单页入口兼容（旧书签/链接仍可打开站点设置）
window.PAGES.settings   = root => renderSettingPage(root, 'basic');

// ── 提示词管理 ──────────────────────────────────────
window.PAGES.prompts = async function (root) {
  root.innerHTML = '<div class="card">加载中...</div>';
  let data;
  try {
    data = await api('/prompts');
  } catch (e) { root.innerHTML = ''; root.appendChild(el('div', { class: 'card' }, '加载失败：' + esc(e.message))); return; }
  root.innerHTML = '';

  // 工具栏
  const tb = el('div', { class: 'toolbar' });
  tb.appendChild(el('div', { class: 'muted', style: 'flex:1' }, '提示词会在生成头像时发送给 AI 模型；修改立即生效，重置可恢复系统默认预设。'));
  tb.appendChild(el('button', {
    class: 'btn btn-danger',
    onclick: async () => {
      const ok = await confirmModal({
        title: '全部重置为默认',
        message: '将所有风格提示词恢复为系统内置预设，自定义修改会丢失，确定继续？',
        okText: '全部重置', danger: true,
      });
      if (!ok) return;
      try { await api('/prompts/reset-all', { method: 'POST' }); toast('已全部重置', 'success'); window.PAGES.prompts(root); }
      catch (e) { toast(e.message, 'error'); }
    },
  }, '全部重置默认'));
  root.appendChild(tb);

  // ── 全局积分参数 ──
  const p = data.points || {};
  const ptCard = el('div', { class: 'card' });
  ptCard.appendChild(el('div', { class: 'card-title' }, '积分规则'));
  const ptField = (key, label, hint) => {
    const input = el('input', { type: 'number', min: '0', value: p[key] != null ? p[key] : '', style: 'width:110px' });
    input.dataset.key = key;
    return el('div', { style: 'display:flex;align-items:center;gap:10px;margin-bottom:10px' }, [
      el('label', { style: 'width:130px;margin:0;flex-shrink:0' }, label),
      input,
      el('span', { class: 'muted', style: 'font-size:12px' }, hint),
    ]);
  };
  const ptFields = [
    ptField('register_bonus', '注册赠送积分', '新用户注册后自动到账的积分'),
    ptField('default_cost', '默认生成消耗', '未选择付费风格时，每次生成扣的积分'),
    ptField('min_balance', '最低余额阈值', '低于该值时禁止生成（预留）'),
  ];
  ptFields.forEach(f => ptCard.appendChild(f));
  ptCard.appendChild(el('div', { style: 'display:flex;justify-content:flex-end;margin-top:4px' }, [
    el('button', {
      class: 'btn btn-primary',
      onclick: async () => {
        const items = {};
        ptCard.querySelectorAll('input[data-key]').forEach(i => { items[i.dataset.key] = i.value; });
        try { await api('/settings', { method: 'PUT', body: { group: 'points', items } }); toast('积分规则已保存', 'success'); }
        catch (e) { toast(e.message, 'error'); }
      },
    }, '保存积分规则'),
  ]));
  root.appendChild(ptCard);

  // ── 基础模板 ──
  const tplCard = el('div', { class: 'card' });
  tplCard.appendChild(el('div', { class: 'card-title', style: 'display:flex;align-items:center;gap:8px;flex-wrap:wrap' }, [
    el('span', {}, '基础模板（未选择风格时使用）'),
    data.template.customized ? badge('已自定义', 'warning') : badge('默认', 'info'),
  ]));
  const tplArea = el('textarea', { style: 'width:100%;min-height:110px;font-size:13px;line-height:1.6;resize:vertical' });
  tplArea.value = data.template.prompt;
  tplCard.appendChild(tplArea);
  const tplBtns = el('div', { style: 'margin-top:10px;display:flex;gap:8px;justify-content:flex-end' });
  tplBtns.appendChild(el('button', {
    class: 'btn',
    onclick: async () => {
      const ok = await confirmModal({ title: '重置基础模板', message: '恢复为系统默认提示词？', okText: '重置' });
      if (!ok) return;
      try { const r = await api('/prompts/template/reset', { method: 'POST' }); tplArea.value = r.prompt; toast('已重置', 'success'); }
      catch (e) { toast(e.message, 'error'); }
    },
  }, '重置默认'));
  tplBtns.appendChild(el('button', {
    class: 'btn btn-primary',
    onclick: async () => {
      if (!tplArea.value.trim()) { toast('提示词不能为空', 'error'); return; }
      try { await api('/prompts/template', { method: 'PUT', body: { prompt: tplArea.value.trim() } }); toast('已保存', 'success'); }
      catch (e) { toast(e.message, 'error'); }
    },
  }, '保存'));
  tplCard.appendChild(tplBtns);
  root.appendChild(tplCard);

  // ── 各风格提示词（动态：新增 / 改名 / 排序 / 停用 / 删除） ──
  const styleHead = el('div', { class: 'toolbar' });
  styleHead.appendChild(el('div', { class: 'muted', style: 'flex:1' }, '风格类型按排序权重升序展示在前台生成页；停用后用户不可见，但历史生成记录不受影响。'));
  styleHead.appendChild(el('button', {
    class: 'btn btn-primary',
    onclick: async () => {
      const values = await formModal({
        title: '新增风格类型',
        large: true,
        okText: '创建',
        fields: [
          { name: 'name', label: '风格名称', type: 'text', required: true, placeholder: '如：吉卜力风、3D 盲盒、国风手绘' },
          { name: 'cost_points', label: '每次生成消耗（积分，0 = 使用全局默认消耗）', type: 'number', value: 0, min: 0 },
          { name: 'sort', label: '排序权重（数字越小越靠前，留空自动排到末尾）', type: 'number', placeholder: '自动' },
          { name: 'status', label: '启用（前台生成页立即可见）', type: 'switch', value: 1 },
          { name: 'prompt', label: '提示词（发送给 AI 的核心指令，英文效果最佳）', type: 'textarea', required: true, placeholder: 'Transform this portrait photo into ...' },
        ],
      });
      if (!values) return;
      try {
        await api('/prompts/styles', { method: 'POST', body: values });
        toast('风格已创建', 'success');
        window.PAGES.prompts(root);
      } catch (e) { toast(e.message, 'error'); }
    },
  }, '＋ 新增风格'));
  root.appendChild(styleHead);

  data.styles.forEach(s => {
    const card = el('div', { class: 'card' });
    const title = el('div', { class: 'card-title', style: 'display:flex;align-items:center;gap:8px;flex-wrap:wrap' }, [
      el('span', {}, '#' + s.id),
      s.customized ? badge('提示词已自定义', 'warning') : badge('默认预设', 'info'),
      !s.is_builtin ? badge('自定义风格', 'default') : null,
      s.status != 1 ? badge('已停用', 'danger') : null,
    ].filter(Boolean));
    card.appendChild(title);

    // 名称 / 排序 / 状态
    const nameInput = el('input', { value: s.name, maxlength: '50', style: 'width:200px;font-weight:600' });
    const sortInput = el('input', { type: 'number', value: s.sort, min: '0', style: 'width:90px' });
    const statusSel = el('select', { style: 'width:110px' }, [
      el('option', { value: '1' }, '启用'),
      el('option', { value: '0' }, '停用'),
    ]);
    statusSel.value = String(s.status);
    card.appendChild(el('div', { style: 'display:flex;align-items:center;gap:10px;margin-bottom:10px;padding:10px 12px;background:var(--card-2);border:1px solid var(--border);border-radius:8px;flex-wrap:wrap' }, [
      el('label', { style: 'margin:0;flex-shrink:0;font-weight:600' }, '名称'),
      nameInput,
      el('label', { style: 'margin:0 0 0 8px;flex-shrink:0;font-weight:600' }, '排序权重'),
      sortInput,
      el('label', { style: 'margin:0 0 0 8px;flex-shrink:0;font-weight:600' }, '状态'),
      statusSel,
    ]));

    // 积分消耗设置行
    const costInput = el('input', { type: 'number', min: '0', value: s.cost_points, style: 'width:100px' });
    card.appendChild(el('div', { style: 'display:flex;align-items:center;gap:10px;margin-bottom:12px;padding:10px 12px;background:var(--card-2);border:1px solid var(--border);border-radius:8px' }, [
      el('label', { style: 'margin:0;flex-shrink:0;font-weight:600' }, '每次生成消耗'),
      costInput,
      el('span', {}, '积分'),
      el('span', { class: 'muted', style: 'font-size:12px;margin-left:auto' }, s.cost_points > 0 ? '付费风格，0 则使用全局默认消耗' : '0 = 使用全局默认消耗（' + (data.points && data.points.default_cost != null ? data.points.default_cost : 10) + ' 积分）'),
    ]));

    const area = el('textarea', { style: 'width:100%;min-height:130px;font-size:13px;line-height:1.6;resize:vertical' });
    area.value = s.prompt;
    card.appendChild(area);

    const btns = el('div', { style: 'margin-top:10px;display:flex;gap:8px;justify-content:flex-end;align-items:center' });
    btns.appendChild(el('span', { class: 'muted', style: 'margin-right:auto;font-size:12px' }, '保存后立即生效；停用或删除不影响已有生成记录'));
    btns.appendChild(el('button', {
      class: 'btn btn-danger',
      onclick: async () => {
        const ok = await confirmModal({
          title: '删除风格「' + s.name + '」',
          message: '删除后前台生成页将不再显示该风格，且操作不可恢复（历史生成记录会保留，查看历史不受影响）。确定删除？',
          okText: '删除', danger: true,
        });
        if (!ok) return;
        try {
          await api('/prompts/styles/' + s.id, { method: 'DELETE' });
          toast('风格已删除', 'success');
          window.PAGES.prompts(root);
        } catch (e) { toast(e.message, 'error'); }
      },
    }, '删除风格'));
    btns.appendChild(el('button', {
      class: 'btn',
      onclick: async () => {
        const ok = await confirmModal({
          title: '重置「' + s.name + '」提示词',
          message: s.is_builtin ? '恢复为该风格的系统内置预设？名称、积分价格、排序不受影响。' : '该风格为自定义风格，将恢复为通用卡通预设，确定？',
          okText: '重置',
        });
        if (!ok) return;
        try { const r = await api('/prompts/styles/' + s.id + '/reset', { method: 'POST' }); area.value = r.prompt; toast('已重置为默认', 'success'); }
        catch (e) { toast(e.message, 'error'); }
      },
    }, '重置提示词'));
    btns.appendChild(el('button', {
      class: 'btn btn-primary',
      onclick: async () => {
        const name = nameInput.value.trim();
        if (!name) { toast('风格名称不能为空', 'error'); nameInput.focus(); return; }
        if (!area.value.trim()) { toast('提示词不能为空', 'error'); area.focus(); return; }
        const cost = parseInt(costInput.value, 10);
        if (isNaN(cost) || cost < 0) { toast('积分消耗须为不小于 0 的整数', 'error'); costInput.focus(); return; }
        const sortVal = sortInput.value === '' ? null : parseInt(sortInput.value, 10);
        if (sortVal !== null && (isNaN(sortVal) || sortVal < 0)) { toast('排序权重须为不小于 0 的整数', 'error'); sortInput.focus(); return; }
        try {
          await api('/prompts/styles/' + s.id, {
            method: 'PUT',
            body: {
              name,
              prompt: area.value.trim(),
              cost_points: cost,
              sort: sortVal,
              status: parseInt(statusSel.value, 10),
            },
          });
          toast('已保存', 'success');
          window.PAGES.prompts(root);
        } catch (e) { toast(e.message, 'error'); }
      },
    }, '保存'));
    card.appendChild(btns);
    root.appendChild(card);
  });

  // ── 颜色 / 形状后缀 ──
  const dimCard = el('div', { class: 'card' });
  dimCard.appendChild(el('div', { class: 'card-title' }, '颜色 / 形状后缀（追加在核心提示词末尾，英文短语效果最佳）'));
  const buildSuffixRows = (list, type) => list.map(item => {
    const input = el('input', { value: item.prompt_suffix, style: 'flex:1;min-width:0' });
    const row = el('div', { class: 'form-row', style: 'display:flex;gap:10px;align-items:center' }, [
      el('label', { style: 'width:90px;margin:0;flex-shrink:0' }, item.name),
      input,
      el('button', {
        class: 'btn btn-sm',
        onclick: async () => {
          try { await api('/prompts/suffix', { method: 'PUT', body: { type, id: item.id, prompt_suffix: input.value.trim() } }); toast('已保存', 'success'); }
          catch (e) { toast(e.message, 'error'); }
        },
      }, '保存'),
    ]);
    return row;
  });
  dimCard.appendChild(el('div', { class: 'muted', style: 'margin:4px 0 10px;font-size:13px' }, '颜色：'));
  buildSuffixRows(data.colors, 'color').forEach(r => dimCard.appendChild(r));
  dimCard.appendChild(el('div', { class: 'muted', style: 'margin:14px 0 10px;font-size:13px' }, '形状：'));
  buildSuffixRows(data.shapes, 'shape').forEach(r => dimCard.appendChild(r));
  root.appendChild(dimCard);
};
