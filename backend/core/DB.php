<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * 轻量 DB 与查询构造器
 * - 单例 PDO 连接
 * - 链式 where / orderBy / limit / offset
 * - insert / update / delete / all / first / paginate
 * - 事务支持
 *
 * 用法：
 *   DB::table('users')->where('id', 1)->first();
 *   DB::table('users')->insert(['username' => 'a']);
 */
final class DB
{
    private static ?PDO $pdo = null;
    /** @var int 当前事务嵌套深度（0 = 无活动事务） */
    private static int $txDepth = 0;

    /** @var array<string,string> 当前查询上下文 */
    private string $table = '';
    /**
     * where 条件
     * 每条结构：['and'|'or', column, value, operator]
     * 其中 column/value/operator 可为特殊：operator==='raw' 时，column 为完整表达式，value 为 null
     */
    private array $wheres = [];
    /** @var array<int,string> order by 片段 */
    private array $orders = [];
    private ?int $limit  = null;
    private ?int $offset = null;
    /** @var array<string,mixed> 绑定参数 */
    private array $bindings = [];
    /** @var string[] 选择的列 */
    private array $select  = ['*'];
    /** 占位符自增计数，避免同列重复命名冲突 */
    private int $phIndex = 0;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /** 工厂入口 */
    public static function table(string $table): self
    {
        return new self($table);
    }

    /** 获取 PDO 单例 */
    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $cfg      = self::dbConfig();
        $dsn      = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset']);
        $options  = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $options);
        } catch (PDOException $e) {
            throw new \RuntimeException('DB connect failed: ' . $e->getMessage(), 500, $e);
        }
        return self::$pdo;
    }

    /** 重置查询上下文（每次 table() 已自动 new） */
    private function reset(): void
    {
        $this->wheres   = [];
        $this->orders   = [];
        $this->limit    = null;
        $this->offset   = null;
        $this->bindings = [];
        $this->select   = ['*'];
        $this->phIndex  = 0;
    }

    public function select(string ...$columns): self
    {
        $this->select = $columns;
        return $this;
    }

    public function where(string $column, mixed $value, string $operator = '='): self
    {
        $ph = $this->nextPh($column);
        $this->wheres[] = ['and', $column, $value, $operator, $ph];
        $this->bindings[$ph] = $value;
        return $this;
    }

    /** OR 条件 */
    public function orWhere(string $column, mixed $value, string $operator = '='): self
    {
        $ph = $this->nextPh($column);
        $this->wheres[] = ['or', $column, $value, $operator, $ph];
        $this->bindings[$ph] = $value;
        return $this;
    }

    /**
     * 原生 WHERE 片段（参数绑定，防注入）
     * 用于需要括号分组的 OR 搜索，例如：
     *   ->whereRaw('(`order_no` LIKE :kw1 OR `product_name` LIKE :kw2)', ['kw1' => '%x%', 'kw2' => '%x%'])
     */
    public function whereRaw(string $expression, array $bindings = []): self
    {
        $this->wheres[] = ['and', $expression, null, 'raw', null];
        foreach ($bindings as $name => $val) {
            $this->bindings[$name] = $val;
        }
        return $this;
    }

    /** 生成唯一占位符名 */
    private function nextPh(string $column): string
    {
        return ':' . preg_replace('/[^a-zA-Z0-9_]/', '_', $column) . '_' . (++$this->phIndex);
    }

    /** whereIn，values 为数组 */
    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            $this->wheres[] = ['and', '1=0', null, 'raw', null];
            return $this;
        }
        $phNames = [];
        foreach (array_values($values) as $v) {
            $name            = $this->nextPh($column . '_in');
            $phNames[]       = $name;
            $this->bindings[$name] = $v;
        }
        $this->wheres[] = ['and', $column . ' IN (' . implode(',', $phNames) . ')', null, 'raw', null];
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = ['and', $column . ' IS NULL', null, 'raw', null];
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = ['and', $column . ' IS NOT NULL', null, 'raw', null];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = $column . ' ' . strtoupper($direction);
        return $this;
    }

    public function limit(int $limit, ?int $offset = null): self
    {
        $this->limit  = $limit;
        if ($offset !== null) {
            $this->offset = $offset;
        }
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /** 拼接 SELECT SQL */
    private function buildSelectSql(): string
    {
        $cols  = implode(', ', $this->select);
        $sql   = "SELECT {$cols} FROM `{$this->table}`";
        $where = $this->buildWhere();
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        if (!empty($this->orders)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . (int)$this->limit;
        }
        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . (int)$this->offset;
        }
        return $sql;
    }

    private function buildWhere(): string
    {
        if (empty($this->wheres)) {
            return '';
        }
        $parts = [];
        foreach ($this->wheres as $w) {
            [$connector, $col, , $op, $ph] = $w;
            $clause = $op === 'raw'
                ? $col // 已是完整表达式（含已注册的占位符）
                : sprintf('`%s` %s %s', $col, $op, $ph);
            if ($parts === []) {
                $parts[] = $clause; // 首条不需要连接词
            } else {
                $parts[] = strtoupper($connector) . ' ' . $clause;
            }
        }
        return implode(' ', $parts);
    }

    /** 执行 SELECT，返回全部行 */
    public function all(): array
    {
        $stmt = self::pdo()->prepare($this->buildSelectSql());
        $this->bindAll($stmt);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $this->reset();
        return $rows;
    }

    /** 第一行 */
    public function first(): ?array
    {
        $this->limit = 1;
        $rows = $this->all();
        return $rows[0] ?? null;
    }

    /** 取单个值（首行首列） */
    public function value(string $column): mixed
    {
        $row = $this->select($column)->first();
        if (!$row) {
            return null;
        }
        return $row[$column] ?? null;
    }

    /** count（不清空 where 等查询上下文，便于 paginate 复用） */
    public function count(): int
    {
        $prevSelect = $this->select;
        $this->select = ['COUNT(*) AS cnt'];
        $sql = $this->buildSelectSql();
        $stmt = self::pdo()->prepare($sql);
        $this->bindAll($stmt);
        $stmt->execute();
        $row = $stmt->fetch();
        $this->select = $prevSelect;
        return (int)($row['cnt'] ?? 0);
    }

    /**
     * 分页查询
     * @return array{list:array,pagination:array}
     */
    public function paginate(int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $total = $this->count();
        $lastPage = max(1, (int)ceil($total / $perPage));
        $this->limit($perPage, ($page - 1) * $perPage);
        $list = $this->all();
        return [
            'list'       => $list,
            'pagination' => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => $lastPage,
            ],
        ];
    }

    /** INSERT，返回 lastInsertId */
    public function insert(array $data): int
    {
        $cols = array_keys($data);
        $phs  = array_map(fn($c) => ':' . $c, $cols);
        $sql  = sprintf('INSERT INTO `%s` (%s) VALUES (%s)',
            $this->table,
            implode(', ', array_map(fn($c) => "`{$c}`", $cols)),
            implode(', ', $phs)
        );
        $stmt = self::pdo()->prepare($sql);
        foreach ($data as $col => $val) {
            $stmt->bindValue(':' . $col, $val);
        }
        $stmt->execute();
        $this->reset();
        return (int)self::pdo()->lastInsertId();
    }

    /** UPDATE，基于 where */
    public function update(array $data): int
    {
        if (empty($data)) {
            return 0;
        }
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = "`{$col}` = :set_{$col}";
        }
        $sql  = sprintf('UPDATE `%s` SET %s', $this->table, implode(', ', $sets));
        $where = $this->buildWhere();
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        $stmt = self::pdo()->prepare($sql);
        foreach ($data as $col => $val) {
            $stmt->bindValue(':set_' . $col, $val);
        }
        $this->bindAll($stmt);
        $stmt->execute();
        $affected = $stmt->rowCount();
        $this->reset();
        return $affected;
    }

    /** DELETE，基于 where */
    public function delete(): int
    {
        $sql  = "DELETE FROM `{$this->table}`";
        $where = $this->buildWhere();
        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }
        $stmt = self::pdo()->prepare($sql);
        $this->bindAll($stmt);
        $stmt->execute();
        $affected = $stmt->rowCount();
        $this->reset();
        return $affected;
    }

    /** 执行原生 SQL */
    public static function raw(string $sql, array $bindings = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($bindings as $k => $v) {
            $stmt->bindValue(is_int($k) ? $k + 1 : $k, $v);
        }
        $stmt->execute();
        return $stmt;
    }

    /**
     * 事务（支持嵌套：内层用 SAVEPOINT）
     * - 最外层开启真实事务；内层只建保存点
     * - 内层异常回滚到保存点后继续抛出，由最外层决定整体回滚
     * - 最外层成功统一 commit
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        if (self::$txDepth === 0) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec('SAVEPOINT sp' . self::$txDepth);
        }
        self::$txDepth++;
        try {
            $result = $fn();
            self::$txDepth--;
            if (self::$txDepth === 0) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT sp' . self::$txDepth);
            }
            return $result;
        } catch (\Throwable $e) {
            self::$txDepth--;
            if (self::$txDepth === 0) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } else {
                $pdo->exec('ROLLBACK TO SAVEPOINT sp' . self::$txDepth);
            }
            throw $e;
        }
    }

    /** 绑定 where 参数到 PDOStatement */
    private function bindAll(PDOStatement $stmt): void
    {
        foreach ($this->bindings as $name => $val) {
            $stmt->bindValue($name, $val);
        }
    }

    private static function dbConfig(): array
    {
        // 优先环境变量（宝塔/Nginx fastcgi_param），其次配置文件
        $file = ROOT_PATH . '/config/database.php';
        $cfg  = is_file($file) ? require $file : [];
        return [
            'host'     => getenv('DB_HOST')     ?: ($cfg['host']     ?? '127.0.0.1'),
            'port'     => getenv('DB_PORT')     ?: ($cfg['port']     ?? '3306'),
            'database' => getenv('DB_DATABASE') ?: ($cfg['database'] ?? 'avatar'),
            'username' => getenv('DB_USERNAME') ?: ($cfg['username'] ?? 'root'),
            'password' => getenv('DB_PASSWORD') ?: ($cfg['password'] ?? ''),
            'charset'  => getenv('DB_CHARSET')  ?: ($cfg['charset']  ?? 'utf8mb4'),
        ];
    }
}
