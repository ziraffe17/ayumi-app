# AYUMI - 技術ドキュメント（全機能解説）

## 目次

1. [システム概要](#1-システム概要)
2. [技術スタック](#2-技術スタック)
3. [認証・セキュリティ機能](#3-認証セキュリティ機能)
4. [ダッシュボード機能](#4-ダッシュボード機能)
5. [出席管理機能](#5-出席管理機能)
6. [日報機能](#6-日報機能)
7. [利用者管理機能](#7-利用者管理機能)
8. [面談記録機能](#8-面談記録機能)
9. [監査ログ機能](#9-監査ログ機能)
10. [エクスポート機能](#10-エクスポート機能)
11. [設定管理機能](#11-設定管理機能)
12. [データベース設計](#12-データベース設計)
13. [パフォーマンス最適化](#13-パフォーマンス最適化)

---

## 1. システム概要

### 1.1 システムの目的

AYUMIは、福祉事業所における利用者の通所記録と生活リズムを一元管理するWebアプリケーションです。

**解決する課題:**
- 紙・Excel管理による情報分散と記録業務の負担
- 利用者の自己理解・生活改善支援の不足
- 面談・監査時のデータ検索困難

**主要機能:**
- 出退所打刻管理（自動集計、リアルタイム可視化）
- 日報入力・セルフモニタリング（体調・睡眠・気分の記録）
- 支援員向けダッシュボード（面談用KPI表示）
- 管理者向けKPI表示（事業所全体の統計）

### 1.2 アーキテクチャ

```
┌─────────────────────────────────────────┐
│  フロントエンド（Blade + JavaScript）      │
│  - 利用者画面（セルフサービス）             │
│  - 職員画面（管理・支援ツール）             │
└─────────────────────────────────────────┘
                  ↓ HTTP/HTTPS
┌─────────────────────────────────────────┐
│  Laravel Application (MVC + Services)   │
│  ├─ Controllers (HTTP Request/Response)│
│  ├─ Services (Business Logic)          │
│  ├─ Models (Eloquent ORM)              │
│  └─ Middleware (Auth, Audit, etc.)     │
└─────────────────────────────────────────┘
                  ↓
┌─────────────────────────────────────────┐
│  データ層                                │
│  ├─ MySQL 8.0 (トランザクションDB)       │
│  ├─ Redis (キャッシュ・セッション)        │
│  └─ Laravel Queue (非同期処理)          │
└─────────────────────────────────────────┘
```

---

## 2. 技術スタック

### 2.1 バックエンド

| カテゴリ | 技術 | バージョン | 用途 |
|---------|------|----------|------|
| **フレームワーク** | Laravel | 11.x | Webアプリケーションフレームワーク |
| **言語** | PHP | 8.3 | サーバーサイド言語 |
| **データベース** | MySQL | 8.0 | トランザクショナルデータ保存 |
| **キャッシュ** | Redis | 7.x | セッション・キャッシュ管理 |
| **メール** | Laravel Mail | - | 2要素認証コード送信 |
| **認証** | Laravel Fortify | - | 認証機能基盤 |

### 2.2 フロントエンド

| カテゴリ | 技術 | 用途 |
|---------|------|------|
| **テンプレートエンジン** | Blade | サーバーサイドレンダリング |
| **JavaScript** | Vanilla JS | 動的UI制御 |
| **グラフ描画** | Chart.js | 出席率推移・体調トレンドグラフ |
| **CSS** | Tailwind CSS | スタイリング |

### 2.3 開発・運用環境

| カテゴリ | 技術 | 用途 |
|---------|------|------|
| **コンテナ** | Docker + Docker Compose | 開発環境構築 |
| **Webサーバー** | Nginx | リバースプロキシ |
| **メール（開発）** | Mailhog | メール動作確認 |

---

## 3. 認証・セキュリティ機能

### 3.1 職員認証（メール2要素認証）

#### 意図・目的
- セキュリティ強化: パスワード単独では不十分な福祉記録へのアクセス制御
- コンプライアンス対応: 監査要件（ログイン記録、多要素認証）
- 不正アクセス防止: 第三者によるなりすましログイン防止

#### 技術スタック
- **認証基盤**: Laravel Fortify
- **カスタム2要素認証**: メール送信（6桁ランダムコード）
- **キャッシュ**: Redis（認証コード一時保存）
- **メール送信**: Laravel Mail

#### 実装詳細

**コード箇所: `StaffEmailTwoFactorController.php`**

```php
// src/app/Http/Controllers/StaffEmailTwoFactorController.php

class StaffEmailTwoFactorController extends Controller
{
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $staff = Auth::guard('staff')->user();

        // 認証コード検証
        $result = $this->twoFactorService->verify($staff, $request->code);

        if ($result['success']) {
            // 認証成功: セッションに2要素認証済みフラグ設定
            $request->session()->put('staff_two_factor_confirmed', true);

            // 監査ログ記録
            $this->auditService->record(
                action: 'two_factor_verified',
                entity: 'staff',
                entityId: $staff->id
            );

            return redirect()->intended('/staff/dashboard');
        }

        // 認証失敗
        return back()->withErrors(['code' => $result['message']]);
    }
}
```

**コード箇所: `EmailTwoFactorService.php`**

```php
// src/app/Services/EmailTwoFactorService.php:21-39

public function generateAndSend(Staff $staff): array
{
    // ロック確認（連続失敗時）
    if ($this->isLocked($staff)) {
        return [
            'success' => false,
            'message' => 'アカウントがロックされています。5分後に再試行してください。'
        ];
    }

    // 6桁ランダムコード生成
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // DB保存（有効期限10分）
    $staff->update([
        'two_factor_email_code' => $code,
        'two_factor_expires_at' => now()->addMinutes(10),
    ]);

    // メール送信
    Mail::to($staff->email)->send(new TwoFactorCodeMail($code));

    return ['success' => true];
}
```

**セキュリティ仕様:**
- 有効期限: 10分
- 試行回数制限: 最大5回（超過時5分ロック）
- コード形式: 6桁数字（000000-999999）
- ブルートフォース対策: IPベースレート制限（Laravel Throttle）

#### データベース設計

**staffsテーブル（2要素認証関連カラム）:**
```sql
CREATE TABLE staffs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    two_factor_email_code VARCHAR(10) NULL,         -- 認証コード
    two_factor_expires_at TIMESTAMP NULL,           -- 有効期限
    two_factor_confirmed_at TIMESTAMP NULL,         -- 確認日時
    failed_attempts INT DEFAULT 0,                  -- 失敗回数
    -- その他カラム省略
);
```

---

### 3.2 利用者認証（シンプルログイン）

#### 意図・目的
- 利用者の使いやすさ優先: ログインコード（短縮ID）による簡単ログイン
- バリアフリー配慮: 複雑なパスワード要求を避け、アクセシビリティ向上
- 職員支援: 利用者がパスワードを忘れた際の職員負担軽減

#### 技術スタック
- **カスタム認証**: Laravel Guard（web）
- **セッション管理**: Laravel Session

#### 実装詳細

**コード箇所: `UserLoginController.php:28-84`**

```php
// src/app/Http/Controllers/UserLoginController.php

public function login(Request $request)
{
    $request->validate([
        'login_code' => 'required|string',
        'password' => 'required|string',
    ]);

    // ログインコードまたはメールアドレスでユーザー検索
    $user = User::where('login_code', $request->login_code)
        ->orWhere('email', $request->login_code)
        ->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        // ログイン失敗: 失敗回数カウント
        if ($user) {
            $user->increment('failed_attempts');

            // 5回連続失敗でアカウントロック
            if ($user->failed_attempts >= 5) {
                $user->update(['is_active' => false]);

                // 監査ログ記録
                $this->auditService->record(
                    action: 'account_locked',
                    entity: 'user',
                    entityId: $user->id
                );
            }
        }

        return back()->withErrors([
            'login_code' => 'ログインコードまたはパスワードが正しくありません。',
        ])->withInput();
    }

    // アカウント有効性確認
    if (!$user->is_active) {
        return back()->withErrors([
            'login_code' => 'このアカウントは無効化されています。',
        ]);
    }

    // ログイン成功
    Auth::guard('web')->login($user, $request->filled('remember'));

    // 失敗回数リセット、最終ログイン日時更新
    $user->update([
        'failed_attempts' => 0,
        'last_login_at' => now(),
    ]);

    // 監査ログ記録
    $this->auditService->record(
        action: 'login',
        entity: 'user',
        entityId: $user->id
    );

    return redirect()->intended('/user/dashboard');
}
```

#### データベース設計

**usersテーブル（認証関連カラム）:**
```sql
CREATE TABLE users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    login_code VARCHAR(255) UNIQUE,                 -- ログインコード（u0001等）
    email VARCHAR(255) UNIQUE NULL,                 -- メールアドレス（任意）
    password VARCHAR(255),                          -- パスワードハッシュ
    failed_attempts INT DEFAULT 0,                  -- ログイン失敗回数
    is_active BOOLEAN DEFAULT TRUE,                 -- アカウント有効フラグ
    last_login_at TIMESTAMP NULL,                   -- 最終ログイン日時
    -- その他カラム省略
);
```

---

### 3.3 監査ログ（コンプライアンス対応）

#### 意図・目的
- コンプライアンス要件: 福祉記録へのアクセス・変更履歴の完全記録
- セキュリティインシデント対応: 不正アクセス検知・追跡
- 改ざん防止: Append-Onlyログによる証跡保全

#### 技術スタック
- **データベース**: MySQL（audit_logsテーブル）
- **ミドルウェア**: AuditMiddleware（自動記録）
- **サービス**: AuditService

#### 実装詳細

**コード箇所: `AuditService.php`**

```php
// src/app/Services/AuditService.php

public function record(
    string $action,
    ?string $entity = null,
    ?int $entityId = null,
    ?array $diff = null,
    ?array $meta = null
): void
{
    // 行為者特定（staff/user/system）
    $actor = $this->resolveActor();

    // 監査ログ作成
    AuditLog::create([
        'actor_type' => $actor['type'],
        'actor_id' => $actor['id'],
        'occurred_at' => now(),
        'action' => $this->normalizeAction($action),
        'entity' => $entity,
        'entity_id' => $entityId,
        'diff_json' => $diff ? json_encode($diff) : null,
        'ip' => request()->ip(),
        'user_agent' => request()->userAgent(),
        'meta' => $meta ? json_encode($meta) : null,
    ]);
}

private function resolveActor(): array
{
    // 職員認証確認
    if (Auth::guard('staff')->check()) {
        return [
            'type' => 'staff',
            'id' => Auth::guard('staff')->id(),
        ];
    }

    // 利用者認証確認
    if (Auth::guard('web')->check()) {
        return [
            'type' => 'user',
            'id' => Auth::guard('web')->id(),
        ];
    }

    // システム処理（バッチ等）
    return [
        'type' => 'system',
        'id' => null,
    ];
}
```

**監査対象操作:**
- `login` / `logout` - ログイン・ログアウト
- `create` / `update` / `delete` - CRUD操作
- `export` - データエクスポート
- `setting_changed` - 設定変更
- `two_factor_verified` - 2要素認証成功
- `account_locked` - アカウントロック

#### セキュリティイベント検知

**コード箇所: `Api/AuditLogController.php:249-288`**

```php
public function statistics(Request $request)
{
    $stats = [
        'total_logs' => AuditLog::count(),
        'actions_count' => AuditLog::select('action', DB::raw('count(*) as count'))
            ->groupBy('action')
            ->pluck('count', 'action'),

        // セキュリティイベント検出
        'security_events' => $this->detectSecurityEvents(),
    ];

    return response()->json($stats);
}

private function detectSecurityEvents(): array
{
    return [
        // 連続ログイン失敗（5分以内に3回以上）
        'failed_logins' => AuditLog::where('action', 'login_failed')
            ->where('occurred_at', '>=', now()->subMinutes(5))
            ->count(),

        // 深夜アクセス（22時-6時）
        'night_access' => AuditLog::whereTime('occurred_at', '>=', '22:00:00')
            ->orWhereTime('occurred_at', '<=', '06:00:00')
            ->where('occurred_at', '>=', now()->subDay())
            ->count(),

        // 大量データエクスポート
        'bulk_exports' => AuditLog::where('action', 'export')
            ->where('occurred_at', '>=', now()->subHour())
            ->count(),
    ];
}
```

---

## 4. ダッシュボード機能

### 4.1 個人ダッシュボード（利用者向け）

#### 意図・目的
- セルフモニタリング促進: 自分の出席率・体調を可視化し、生活改善意識を向上
- エンパワーメント: データを通じた自己理解・主体性向上
- 面談資料: 支援員との面談時の対話材料

#### 技術スタック
- **サービス**: DashboardService, KpiService
- **グラフ描画**: Chart.js
- **キャッシュ**: なし（個人データはキャッシュ不要）

#### 実装詳細

**コード箇所: `DashboardService.php:24-119`**

```php
// src/app/Services/DashboardService.php

public function getPersonalDashboard(User $user, array $params = []): array
{
    // 表示期間決定（今月/直近3ヶ月/全期間/特定月）
    $period = $this->determineDashboardPeriod($params);

    // データ取得（N+1クエリ対策）
    $plans = AttendancePlan::where('user_id', $user->id)
        ->whereBetween('plan_date', [$period['start'], $period['end']])
        ->get();

    $records = AttendanceRecord::where('user_id', $user->id)
        ->whereBetween('record_date', [$period['start'], $period['end']])
        ->get();

    $morningReports = DailyReportMorning::where('user_id', $user->id)
        ->whereBetween('report_date', [$period['start'], $period['end']])
        ->get();

    $eveningReports = DailyReportEvening::where('user_id', $user->id)
        ->whereBetween('report_date', [$period['start'], $period['end']])
        ->get();

    // KPI計算
    $kpi = $this->kpiService->calculatePersonalKpiFromData(
        $plans,
        $records,
        $morningReports,
        $eveningReports,
        $period
    );

    // 出席率推移（月次集計）
    $attendanceTrend = $this->kpiService->calculateAttendanceTrendFromData(
        $plans,
        $records
    );

    // 体調・気分トレンド（直近7日）
    $reportTrend = $this->kpiService->calculateReportTrendFromData(
        $morningReports,
        now()->subDays(6),
        now()
    );

    // カレンダーデータ生成
    $calendar = $this->getCalendarDataFromCollections(
        $plans,
        $records,
        $period
    );

    return [
        'kpi' => $kpi,
        'attendance_trend' => $attendanceTrend,
        'report_trend' => $reportTrend,
        'calendar' => $calendar,
        'period' => $period,
    ];
}
```

**KPI計算ロジック:**

```php
// src/app/Services/KpiService.php

public function calculatePersonalKpiFromData(
    Collection $plans,
    Collection $records,
    Collection $morningReports,
    Collection $eveningReports,
    array $period
): array
{
    // 出席KPI
    $attendanceKpi = $this->calculateAttendanceKpi($plans, $records, $period);

    // 日報KPI
    $reportKpi = $this->calculateReportKpi(
        $records,
        $morningReports,
        $eveningReports,
        $period
    );

    return [
        'attendance' => $attendanceKpi,
        'report' => $reportKpi,
    ];
}

private function calculateAttendanceKpi(
    Collection $plans,
    Collection $records,
    array $period
): array
{
    // 予定日数（祝日・土日除外）
    $plannedDays = $plans->count();

    // 出席日数（present/remote）
    $attendedDays = $records->whereIn('attendance_type', ['onsite', 'remote'])->count();

    // 欠席日数
    $absentDays = $records->where('attendance_type', 'absent')->count();

    // 出席率計算
    $attendanceRate = $plannedDays > 0
        ? round(($attendedDays / $plannedDays) * 100, 1)
        : 0;

    return [
        'planned_days' => $plannedDays,
        'attended_days' => $attendedDays,
        'absent_days' => $absentDays,
        'attendance_rate' => $attendanceRate,
    ];
}
```

**グラフデータ生成（出席率推移）:**

```php
private function calculateAttendanceTrendFromData(
    Collection $plans,
    Collection $records
): array
{
    $trend = [];

    // 月ごとにグループ化
    $plansByMonth = $plans->groupBy(function ($plan) {
        return Carbon::parse($plan->plan_date)->format('Y-m');
    });

    $recordsByMonth = $records->groupBy(function ($record) {
        return Carbon::parse($record->record_date)->format('Y-m');
    });

    foreach ($plansByMonth as $month => $monthPlans) {
        $monthRecords = $recordsByMonth->get($month, collect());

        $plannedDays = $monthPlans->count();
        $attendedDays = $monthRecords->whereIn('attendance_type', ['onsite', 'remote'])->count();

        $rate = $plannedDays > 0
            ? round(($attendedDays / $plannedDays) * 100, 1)
            : 0;

        $trend[] = [
            'month' => $month,
            'rate' => $rate,
        ];
    }

    return $trend;
}
```

#### フロントエンド実装

**コード箇所: `resources/views/user/dashboard.blade.php`**

```html
<!-- 出席率推移グラフ -->
<canvas id="attendanceTrendChart"></canvas>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('attendanceTrendChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: @json($attendanceTrend->pluck('month')),
        datasets: [{
            label: '出席率 (%)',
            data: @json($attendanceTrend->pluck('rate')),
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                max: 100
            }
        }
    }
});
</script>
```

---

### 4.2 事業所ダッシュボード（職員向け）

#### 意図・目的
- 全利用者の状況把握: 出席率ランキング、アラート表示
- 稼働率予測: 定員に対する予測稼働率計算
- 業務効率化: 日報未入力者の一覧表示で声かけ優先順位付け

#### 技術スタック
- **サービス**: DashboardService, KpiService
- **パフォーマンス最適化**: Eager Loading、Collection活用

#### 実装詳細

**コード箇所: `DashboardService.php:204-334`**

```php
public function getFacilityDashboard(array $params = []): array
{
    $period = $this->determineDashboardPeriod($params);

    // 全利用者取得
    $users = User::where('is_active', true)->get();

    // データ一括取得（N+1クエリ対策）
    $userIds = $users->pluck('id');

    $plans = AttendancePlan::whereIn('user_id', $userIds)
        ->whereBetween('plan_date', [$period['start'], $period['end']])
        ->get()
        ->groupBy('user_id');

    $records = AttendanceRecord::whereIn('user_id', $userIds)
        ->whereBetween('record_date', [$period['start'], $period['end']])
        ->get()
        ->groupBy('user_id');

    $morningReports = DailyReportMorning::whereIn('user_id', $userIds)
        ->whereBetween('report_date', [$period['start'], $period['end']])
        ->get()
        ->groupBy('user_id');

    $eveningReports = DailyReportEvening::whereIn('user_id', $userIds)
        ->whereBetween('report_date', [$period['start'], $period['end']])
        ->get()
        ->groupBy('user_id');

    // 利用者ごとのKPI計算
    $userKpis = [];
    foreach ($users as $user) {
        $kpi = $this->kpiService->calculatePersonalKpiFromData(
            $plans->get($user->id, collect()),
            $records->get($user->id, collect()),
            $morningReports->get($user->id, collect()),
            $eveningReports->get($user->id, collect()),
            $period
        );

        $userKpis[] = [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'attendance_rate' => $kpi['attendance']['attendance_rate'],
            'report_rate' => $kpi['report']['total_rate'],
        ];
    }

    // 出席率でソート
    usort($userKpis, function ($a, $b) {
        return $b['attendance_rate'] <=> $a['attendance_rate'];
    });

    // 事業所全体KPI計算
    $facilityKpi = $this->kpiService->calculateFacilityKpi(
        $users,
        $plans->flatten(),
        $records->flatten(),
        $morningReports->flatten(),
        $eveningReports->flatten(),
        $period
    );

    // アラート生成
    $alerts = $this->kpiService->generateAlerts($userKpis);

    return [
        'facility_kpi' => $facilityKpi,
        'user_kpis' => $userKpis,
        'alerts' => $alerts,
        'period' => $period,
    ];
}
```

**予測稼働率計算:**

```php
// src/app/Services/KpiService.php

public function calculateFacilityKpi(
    Collection $users,
    Collection $plans,
    Collection $records,
    Collection $morningReports,
    Collection $eveningReports,
    array $period
): array
{
    // 設定から定員取得
    $capacity = Setting::get('facility_capacity', 20);

    // 営業日数（平日のみ）
    $businessDays = $this->countWeekdaysInRange($period['start'], $period['end']);

    // 実績コマ数
    $actualSlots = $records->whereIn('attendance_type', ['onsite', 'remote'])->count();

    // 予定コマ数
    $plannedSlots = $plans->count();

    // 予測稼働率 = (実績コマ数 + 残り予定コマ数) / (定員 × 営業日数) × 100
    $predictedSlots = $actualSlots + $this->getRemainingPlannedSlots($plans, $period);
    $predictedOccupancy = $businessDays > 0
        ? round(($predictedSlots / ($capacity * $businessDays)) * 100, 1)
        : 0;

    return [
        'total_users' => $users->count(),
        'capacity' => $capacity,
        'business_days' => $businessDays,
        'actual_slots' => $actualSlots,
        'planned_slots' => $plannedSlots,
        'predicted_occupancy' => $predictedOccupancy,
    ];
}
```

**アラート生成:**

```php
public function generateAlerts(array $userKpis): array
{
    $alerts = [];

    foreach ($userKpis as $kpi) {
        // 出席率70%未満
        if ($kpi['attendance_rate'] < 70) {
            $alerts[] = [
                'type' => 'warning',
                'user_id' => $kpi['user_id'],
                'user_name' => $kpi['user_name'],
                'message' => "出席率が{$kpi['attendance_rate']}%に低下しています",
            ];
        }

        // 日報入力率50%未満
        if ($kpi['report_rate'] < 50) {
            $alerts[] = [
                'type' => 'info',
                'user_id' => $kpi['user_id'],
                'user_name' => $kpi['user_name'],
                'message' => "日報入力率が{$kpi['report_rate']}%です",
            ];
        }
    }

    return $alerts;
}
```

---

## 5. 出席管理機能

### 5.1 出席予定管理

#### 意図・目的
- 事前計画: 月次での通所予定管理
- 稼働率予測: 予定ベースでの収支シミュレーション
- テンプレート機能: 前月コピー、平日パターンで入力効率化

#### 技術スタック
- **モデル**: AttendancePlan
- **サービス**: HolidayService（祝日判定）
- **バリデーション**: Laravel Request Validation

#### 実装詳細

**テンプレート生成機能:**

**コード箇所: `Staff/PlanController.php:253-278`**

```php
public function generateTemplate(Request $request)
{
    $request->validate([
        'target_month' => 'required|date_format:Y-m',
        'source_type' => 'required|in:prev_month,weekday',
        'user_ids' => 'nullable|array',
        'user_ids.*' => 'exists:users,id',
    ]);

    $targetMonth = Carbon::parse($request->target_month);
    $userIds = $request->user_ids ?? User::where('is_active', true)->pluck('id');

    if ($request->source_type === 'prev_month') {
        // 前月コピー
        $this->generateFromPrevMonth($targetMonth, $userIds);
    } else {
        // 平日パターン
        $this->generateWeekdayPattern($targetMonth, $userIds);
    }

    return response()->json(['message' => 'テンプレート生成完了']);
}

private function generateWeekdayPattern(Carbon $targetMonth, array $userIds): void
{
    $start = $targetMonth->copy()->startOfMonth();
    $end = $targetMonth->copy()->endOfMonth();

    // 祝日マップ取得
    $holidays = $this->holidayService->mapForMonth($targetMonth->year, $targetMonth->month);

    foreach ($userIds as $userId) {
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            // 土日・祝日はスキップ
            if ($date->isWeekend() || isset($holidays[$date->format('Y-m-d')])) {
                continue;
            }

            // 既存予定がなければ作成
            AttendancePlan::firstOrCreate([
                'user_id' => $userId,
                'plan_date' => $date->format('Y-m-d'),
                'plan_time_slot' => 'full',
            ], [
                'plan_type' => 'onsite',
                'template_source' => 'weekday',
            ]);
        }
    }
}
```

**一括登録API:**

**コード箇所: `Api/PlanController.php:175-245`**

```php
public function bulkStore(Request $request)
{
    $request->validate([
        'plans' => 'required|array',
        'plans.*.user_id' => 'required|exists:users,id',
        'plans.*.plan_date' => 'required|date',
        'plans.*.plan_time_slot' => 'required|in:am,pm,full',
        'plans.*.plan_type' => 'required|in:onsite,remote,off',
    ]);

    DB::beginTransaction();
    try {
        $created = [];

        foreach ($request->plans as $planData) {
            // 祝日判定
            $date = Carbon::parse($planData['plan_date']);
            $isHoliday = $this->holidayService->isHoliday($date);

            $plan = AttendancePlan::create([
                'user_id' => $planData['user_id'],
                'plan_date' => $planData['plan_date'],
                'plan_time_slot' => $planData['plan_time_slot'],
                'plan_type' => $planData['plan_type'],
                'note' => $planData['note'] ?? null,
                'is_holiday' => $isHoliday,
                'holiday_name' => $isHoliday ? $this->holidayService->nameOf($date) : null,
            ]);

            $created[] = $plan;
        }

        DB::commit();

        // 監査ログ記録
        $this->auditService->record(
            action: 'bulk_create',
            entity: 'attendance_plan',
            meta: ['count' => count($created)]
        );

        return response()->json($created, 201);

    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
```

---

### 5.2 出席実績管理

#### 意図・目的
- 実績記録: 実際の出退所記録（職員・利用者両方）
- 承認ワークフロー: 職員による実績承認（改ざん防止）
- ロック機能: 確定後の誤編集防止

#### 技術スタック
- **モデル**: AttendanceRecord
- **承認管理**: is_approved, approved_by, approved_at
- **ロック管理**: is_locked, locked_by, locked_at

#### 実装詳細

**出席実績登録:**

**コード箇所: `Api/AttendanceController.php:106-177`**

```php
public function store(Request $request)
{
    $request->validate([
        'user_id' => 'required|exists:users,id',
        'record_date' => 'required|date',
        'record_time_slot' => 'required|in:am,pm,full',
        'attendance_type' => 'required|in:onsite,remote,absent',
        'note' => 'nullable|string|max:1000',
        'source' => 'nullable|in:self,staff',
    ]);

    // 登録元判定（利用者自身 or 職員）
    $source = $request->source ?? ($this->isStaff() ? 'staff' : 'self');

    // 重複チェック
    $existing = AttendanceRecord::where('user_id', $request->user_id)
        ->where('record_date', $request->record_date)
        ->where('record_time_slot', $request->record_time_slot)
        ->first();

    if ($existing) {
        return response()->json([
            'message' => 'この日時の実績は既に登録されています'
        ], 409);
    }

    // 実績作成
    $record = AttendanceRecord::create([
        'user_id' => $request->user_id,
        'record_date' => $request->record_date,
        'record_time_slot' => $request->record_time_slot,
        'attendance_type' => $request->attendance_type,
        'note' => $request->note,
        'source' => $source,
    ]);

    // 監査ログ記録
    $this->auditService->record(
        action: 'create',
        entity: 'attendance_record',
        entityId: $record->id,
        diff: $record->toArray()
    );

    return response()->json($record, 201);
}
```

**承認機能:**

**コード箇所: `Api/AttendanceController.php:281-317`**

```php
public function approve(int $id, Request $request)
{
    $request->validate([
        'approval_note' => 'nullable|string|max:500',
    ]);

    $record = AttendanceRecord::findOrFail($id);

    // 既に承認済み
    if ($record->is_approved) {
        return response()->json([
            'message' => 'この実績は既に承認されています'
        ], 400);
    }

    // ロック済みは承認不可
    if ($record->is_locked) {
        return response()->json([
            'message' => 'ロックされた実績は承認できません'
        ], 400);
    }

    // 承認処理
    $record->update([
        'is_approved' => true,
        'approved_by' => Auth::guard('staff')->id(),
        'approved_at' => now(),
        'approval_note' => $request->approval_note,
    ]);

    // 監査ログ記録
    $this->auditService->record(
        action: 'approve',
        entity: 'attendance_record',
        entityId: $record->id
    );

    return response()->json($record);
}
```

---

## 6. 日報機能

### 6.1 朝の日報

#### 意図・目的
- 生活リズム把握: 睡眠・食事・服薬状況の記録
- 体調早期発見: 気分スコア・サイン数による変化検知
- セルフモニタリング: 利用者自身による健康管理

#### 技術スタック
- **モデル**: DailyReportMorning
- **バリデーション**: Laravel Form Request
- **タイムゾーン**: UTC保存、JST表示

#### 実装詳細

**コード箇所: `User/ReportController.php:36-99`**

```php
public function storeMorning(Request $request)
{
    $userId = Auth::guard('web')->id();

    $validated = $request->validate([
        'report_date' => 'required|date',
        'sleep_rating' => 'required|integer|min:1|max:3',        // 3=◯, 2=△, 1=✕
        'stress_rating' => 'required|integer|min:1|max:3',
        'meal_rating' => 'required|integer|min:1|max:3',
        'bed_time' => 'nullable|date_format:H:i',
        'wake_time' => 'nullable|date_format:H:i',
        'mid_awaken_count' => 'nullable|integer|min:0',
        'is_early_awaken' => 'nullable|boolean',
        'is_breakfast_done' => 'nullable|boolean',
        'is_bathing_done' => 'nullable|boolean',
        'is_medication_taken' => 'nullable|boolean',
        'mood_score' => 'required|integer|min:1|max:10',
        'sign_good' => 'nullable|integer|min:0',
        'sign_caution' => 'nullable|integer|min:0',
        'sign_bad' => 'nullable|integer|min:0',
        'note' => 'nullable|string|max:1000',
    ]);

    // 睡眠時間計算（JST → UTC変換）
    if ($validated['bed_time'] && $validated['wake_time']) {
        $bedAt = Carbon::parse($validated['report_date'] . ' ' . $validated['bed_time'], 'Asia/Tokyo');
        $wakeAt = Carbon::parse($validated['report_date'] . ' ' . $validated['wake_time'], 'Asia/Tokyo');

        // 就寝が起床より後の場合、前日扱い
        if ($bedAt->greaterThan($wakeAt)) {
            $bedAt->subDay();
        }

        $sleepMinutes = $bedAt->diffInMinutes($wakeAt);

        $validated['bed_at'] = $bedAt->utc();
        $validated['wake_at'] = $wakeAt->utc();
        $validated['sleep_minutes'] = $sleepMinutes;
        $validated['bed_time_local'] = $validated['bed_time'];
        $validated['wake_time_local'] = $validated['wake_time'];
    }

    // 重複チェック＆更新 or 作成
    $report = DailyReportMorning::updateOrCreate(
        [
            'user_id' => $userId,
            'report_date' => $validated['report_date'],
        ],
        $validated
    );

    // 監査ログ記録
    $this->auditService->record(
        action: $report->wasRecentlyCreated ? 'create' : 'update',
        entity: 'daily_report_morning',
        entityId: $report->id
    );

    return redirect()->route('user.dashboard')
        ->with('success', '朝の日報を保存しました');
}
```

**データベース設計（朝の日報）:**

```sql
CREATE TABLE daily_reports_morning (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    report_date DATE NOT NULL,

    -- 評価項目（3段階: 3=◯, 2=△, 1=✕）
    sleep_rating TINYINT,
    stress_rating TINYINT,
    meal_rating TINYINT,

    -- 睡眠時間（JST表示用）
    bed_time_local TIME NULL,
    wake_time_local TIME NULL,

    -- 睡眠時間（UTC保存）
    bed_at DATETIME NULL,
    wake_at DATETIME NULL,
    sleep_minutes INT NULL,

    -- 睡眠詳細
    mid_awaken_count TINYINT DEFAULT 0,
    is_early_awaken BOOLEAN DEFAULT FALSE,

    -- 生活習慣
    is_breakfast_done BOOLEAN DEFAULT FALSE,
    is_bathing_done BOOLEAN DEFAULT FALSE,
    is_medication_taken BOOLEAN NULL,

    -- 気分・サイン
    mood_score TINYINT DEFAULT 5,
    sign_good INT DEFAULT 0,
    sign_caution INT DEFAULT 0,
    sign_bad INT DEFAULT 0,

    note TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    UNIQUE KEY unique_user_date (user_id, report_date),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

### 6.2 夕の日報

#### 意図・目的
- 訓練振り返り: 今日の活動内容と気づきの記録
- 体調変化記録: 日中の体調・気分の変化
- 職員連携: 相談事項の共有

#### 技術スタック
- **モデル**: DailyReportEvening
- **フォーム**: Bladeテンプレート（テキストエリア）

#### 実装詳細

**コード箇所: `User/ReportController.php:117-153`**

```php
public function storeEvening(Request $request)
{
    $userId = Auth::guard('web')->id();

    $validated = $request->validate([
        'report_date' => 'required|date',
        'training_summary' => 'nullable|string|max:2000',
        'training_reflection' => 'nullable|string|max:2000',
        'condition_note' => 'nullable|string|max:1000',
        'other_note' => 'nullable|string|max:1000',
    ]);

    // 重複チェック＆更新 or 作成
    $report = DailyReportEvening::updateOrCreate(
        [
            'user_id' => $userId,
            'report_date' => $validated['report_date'],
        ],
        $validated
    );

    return redirect()->route('user.dashboard')
        ->with('success', '夕の日報を保存しました');
}
```

**データベース設計（夕の日報）:**

```sql
CREATE TABLE daily_reports_evening (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    report_date DATE NOT NULL,

    training_summary TEXT NULL,         -- 訓練内容
    training_reflection TEXT NULL,      -- 訓練の振り返り
    condition_note TEXT NULL,           -- 体調について
    other_note TEXT NULL,               -- その他

    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    UNIQUE KEY unique_user_date (user_id, report_date),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

---

## 7. 利用者管理機能

### 7.1 利用者CRUD

#### 意図・目的
- 利用者マスタ管理: 基本情報・ログインコード・利用期間管理
- 論理削除: 退所後もデータ保持（監査要件）
- 暗号化保存: ケアノートの個人情報保護

#### 技術スタック
- **モデル**: User
- **暗号化**: Laravel Encrypted Cast
- **論理削除**: SoftDeletes

#### 実装詳細

**コード箇所: `Staff/UserController.php:43-98`**

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'name_kana' => 'nullable|string|max:255',
        'login_code' => 'required|string|unique:users,login_code|max:255',
        'email' => 'nullable|email|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
        'start_date' => 'nullable|date',
        'end_date' => 'nullable|date|after_or_equal:start_date',
        'care_notes' => 'nullable|string',
    ]);

    // パスワードハッシュ化
    $validated['password'] = Hash::make($validated['password']);

    // 作成者記録
    $validated['created_by'] = Auth::guard('staff')->id();

    // 利用者作成
    $user = User::create($validated);

    // 監査ログ記録
    $this->auditService->record(
        action: 'create',
        entity: 'user',
        entityId: $user->id,
        diff: Arr::except($user->toArray(), ['password'])
    );

    return redirect()->route('staff.users.index')
        ->with('success', '利用者を登録しました');
}
```

**暗号化カラム:**

**コード箇所: `User.php`**

```php
// src/app/Models/User.php

class User extends Authenticatable
{
    use SoftDeletes;

    protected $casts = [
        'care_notes_enc' => 'encrypted',    // 暗号化保存
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}
```

---

## 8. 面談記録機能

### 8.1 面談記録CRUD

#### 意図・目的
- 個別支援記録: 定期面談の内容・次のアクション記録
- 暗号化保存: 面談内容の秘匿性確保
- 職員紐付け: 担当職員の記録

#### 技術スタック
- **モデル**: Interview
- **暗号化**: summary_enc, detail_enc（Encrypted Cast）

#### 実装詳細

**コード箇所: `Staff/InterviewController.php:51-97`**

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'user_id' => 'required|exists:users,id',
        'interview_date' => 'required|date',
        'summary' => 'nullable|string|max:500',
        'detail' => 'nullable|string',
        'next_action' => 'nullable|string|max:1000',
    ]);

    // 職員ID自動設定
    $validated['staff_id'] = Auth::guard('staff')->id();

    // 暗号化カラムへのマッピング
    $validated['summary_enc'] = $validated['summary'] ?? null;
    $validated['detail_enc'] = $validated['detail'] ?? null;
    unset($validated['summary'], $validated['detail']);

    // 面談記録作成
    $interview = Interview::create($validated);

    // 監査ログ記録
    $this->auditService->record(
        action: 'create',
        entity: 'interview',
        entityId: $interview->id
    );

    return redirect()->route('staff.interviews.index')
        ->with('success', '面談記録を登録しました');
}
```

**データベース設計:**

```sql
CREATE TABLE interviews (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    staff_id BIGINT NOT NULL,
    interview_date DATE,

    summary_enc TEXT NULL,              -- 要約（暗号化）
    detail_enc TEXT NULL,               -- 詳細（暗号化）
    next_action TEXT NULL,              -- 次のアクション

    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    INDEX idx_user_date (user_id, interview_date),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (staff_id) REFERENCES staffs(id)
);
```

---

## 9. 監査ログ機能

### 9.1 監査ログ閲覧・検索

#### 意図・目的
- コンプライアンス: 全操作の証跡保全
- インシデント調査: 不正アクセス・誤操作の追跡
- 統計分析: 利用パターン・セキュリティイベント検知

#### 技術スタック
- **モデル**: AuditLog
- **検索**: Eloquent Query Builder（複合条件）
- **ページング**: Laravel Paginator

#### 実装詳細

**検索API:**

**コード箇所: `Api/AuditLogController.php:21-151`**

```php
public function index(Request $request)
{
    $query = AuditLog::query();

    // 期間フィルタ
    if ($request->filled('start_date')) {
        $query->where('occurred_at', '>=', $request->start_date);
    }
    if ($request->filled('end_date')) {
        $query->where('occurred_at', '<=', $request->end_date . ' 23:59:59');
    }

    // 行為者種別フィルタ
    if ($request->filled('actor_type')) {
        $query->where('actor_type', $request->actor_type);
    }

    // 行為者IDフィルタ
    if ($request->filled('actor_id')) {
        $query->where('actor_id', $request->actor_id);
    }

    // 操作種別フィルタ
    if ($request->filled('action')) {
        $query->where('action', $request->action);
    }

    // エンティティフィルタ
    if ($request->filled('entity')) {
        $query->where('entity', $request->entity);
    }

    // ソート（デフォルト: 新しい順）
    $query->orderBy('occurred_at', 'desc');

    // ページング
    $perPage = $request->get('per_page', 50);
    $logs = $query->paginate($perPage);

    // PII最小化（IPアドレスマスキング）
    $logs->getCollection()->transform(function ($log) {
        if ($log->ip) {
            $log->ip = $this->maskIpAddress($log->ip);
        }
        return $log;
    });

    return response()->json($logs);
}

private function maskIpAddress(string $ip): string
{
    $parts = explode('.', $ip);
    if (count($parts) === 4) {
        // IPv4: xxx.xxx.***.***
        return $parts[0] . '.' . $parts[1] . '.***. ***';
    }
    // IPv6: 簡易マスキング
    return substr($ip, 0, 10) . '***';
}
```

**CSV出力:**

**コード箇所: `Api/AuditLogController.php:182-248`**

```php
public function export(Request $request)
{
    $query = AuditLog::query();

    // フィルタ適用（index()と同じロジック）
    $this->applyFilters($query, $request);

    // 最大10万件制限
    $logs = $query->limit(100000)->get();

    // CSV生成
    $csv = Writer::createFromString();

    // ヘッダー行
    $csv->insertOne([
        '発生日時',
        '行為者種別',
        '行為者ID',
        '操作種別',
        'エンティティ',
        'エンティティID',
        '変更内容',
        'IPアドレス',
    ]);

    // データ行
    foreach ($logs as $log) {
        $csv->insertOne([
            $log->occurred_at,
            $log->actor_type,
            $log->actor_id,
            $log->action,
            $log->entity,
            $log->entity_id,
            json_encode($log->diff_json, JSON_UNESCAPED_UNICODE),
            $this->maskIpAddress($log->ip),
        ]);
    }

    // レスポンス
    $filename = 'audit_logs_' . now()->format('YmdHis') . '.csv';

    // 監査ログのエクスポート自体も記録
    $this->auditService->record(
        action: 'export',
        entity: 'audit_log',
        meta: ['count' => $logs->count()]
    );

    return response($csv->toString(), 200, [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ]);
}
```

---

## 10. エクスポート機能

### 10.1 CSV一括出力

#### 意図・目的
- 監査資料作成: 自治体監査・内部監査用のデータ抽出
- 外部連携: 会計システム・請求システムへのデータ連携
- バックアップ: 定期的なデータバックアップ

#### 技術スタック
- **CSVライブラリ**: league/csv（Composer）
- **文字コード**: UTF-8 / Shift_JIS選択可能
- **ストリーミング**: メモリ効率化

#### 実装詳細

**出席データCSV出力:**

**コード箇所: `Api/CsvExportController.php:29-115`**

```php
public function exportAttendance(Request $request)
{
    $request->validate([
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
        'user_ids' => 'nullable|array',
        'user_ids.*' => 'exists:users,id',
        'include_plans' => 'nullable|boolean',
        'include_records' => 'nullable|boolean',
        'encoding' => 'nullable|in:UTF-8,SJIS',
    ]);

    // ユーザー取得
    $userIds = $request->user_ids ?? User::where('is_active', true)->pluck('id');
    $users = User::whereIn('id', $userIds)->get()->keyBy('id');

    // データ取得
    $plans = collect();
    $records = collect();

    if ($request->get('include_plans', true)) {
        $plans = AttendancePlan::whereIn('user_id', $userIds)
            ->whereBetween('plan_date', [$request->start_date, $request->end_date])
            ->get()
            ->groupBy(function ($plan) {
                return $plan->user_id . '_' . $plan->plan_date;
            });
    }

    if ($request->get('include_records', true)) {
        $records = AttendanceRecord::whereIn('user_id', $userIds)
            ->whereBetween('record_date', [$request->start_date, $request->end_date])
            ->get()
            ->groupBy(function ($record) {
                return $record->user_id . '_' . $record->record_date;
            });
    }

    // CSV生成
    $csv = Writer::createFromString();

    // BOM追加（Excel対応）
    if ($request->get('encoding') !== 'SJIS') {
        $csv->setOutputBOM(ByteSequence::BOM_UTF8);
    }

    // ヘッダー行
    $headers = ['利用者名', '日付', '予定', '実績', '差分', '備考'];
    $csv->insertOne($headers);

    // データ行生成
    foreach ($userIds as $userId) {
        $user = $users[$userId];

        $period = CarbonPeriod::create($request->start_date, $request->end_date);
        foreach ($period as $date) {
            $key = $userId . '_' . $date->format('Y-m-d');

            $plan = $plans->get($key)->first() ?? null;
            $record = $records->get($key)->first() ?? null;

            $csv->insertOne([
                $user->name,
                $date->format('Y-m-d'),
                $plan ? $plan->plan_type : '-',
                $record ? $record->attendance_type : '-',
                $this->calculateDiff($plan, $record),
                $record->note ?? '',
            ]);
        }
    }

    // 文字コード変換（Shift_JIS）
    $output = $csv->toString();
    if ($request->get('encoding') === 'SJIS') {
        $output = mb_convert_encoding($output, 'SJIS', 'UTF-8');
    }

    $filename = 'attendance_' . now()->format('YmdHis') . '.csv';

    return response($output, 200, [
        'Content-Type' => 'text/csv; charset=' . ($request->get('encoding') ?? 'UTF-8'),
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
    ]);
}
```

---

## 11. 設定管理機能

### 11.1 祝日管理

#### 意図・目的
- 出席予定の精度向上: 祝日を自動判定し、平日のみ予定作成
- 外部API連携: 内閣府提供の祝日APIから自動取り込み
- CSV取り込み: オフライン環境での一括登録

#### 技術スタック
- **外部API**: 内閣府 祝日API（https://holidays-jp.github.io/api/）
- **HTTPクライアント**: Laravel HTTP Client
- **キャッシュ**: Redis（24時間TTL）

#### 実装詳細

**政府APIから祝日取り込み:**

**コード箇所: `HolidayService.php:42-102`**

```php
// src/app/Services/HolidayService.php

public function importFromGovernmentApi(int $year): array
{
    try {
        $holidays = $this->fetchHolidaysForYear($year);

        if (empty($holidays)) {
            // APIエラー時のフォールバック
            $holidays = $this->createBasicHolidays($year);
        }

        $imported = 0;
        foreach ($holidays as $date => $name) {
            Holiday::updateOrCreate(
                ['holiday_date' => $date],
                [
                    'name' => $name,
                    'source' => 'government_api',
                    'imported_at' => now(),
                ]
            );
            $imported++;
        }

        // キャッシュクリア
        Cache::tags('holidays')->flush();

        return [
            'success' => true,
            'imported' => $imported,
            'year' => $year,
        ];

    } catch (\Exception $e) {
        Log::error('祝日API取り込みエラー', [
            'year' => $year,
            'error' => $e->getMessage(),
        ]);

        return [
            'success' => false,
            'error' => $e->getMessage(),
        ];
    }
}

private function fetchHolidaysForYear(int $year): array
{
    // 内閣府提供の祝日API（非公式）
    $url = "https://holidays-jp.github.io/api/v1/{$year}/date.json";

    $response = Http::timeout(10)->get($url);

    if (!$response->successful()) {
        throw new \Exception('祝日API接続エラー: ' . $response->status());
    }

    return $response->json();
}

private function createBasicHolidays(int $year): array
{
    // フォールバック: 主要な祝日のみ
    return [
        "{$year}-01-01" => '元日',
        "{$year}-05-03" => '憲法記念日',
        "{$year}-05-04" => 'みどりの日',
        "{$year}-05-05" => 'こどもの日',
        // ... その他の固定祝日
    ];
}
```

**祝日判定（キャッシュ付き）:**

**コード箇所: `HolidayService.php:19-40`**

```php
public function mapForMonth(int $year, int $month): array
{
    // キャッシュキー
    $cacheKey = "holidays:{$year}:{$month}";

    // キャッシュ確認（24時間TTL）
    return Cache::tags('holidays')->remember($cacheKey, 86400, function () use ($year, $month) {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        return Holiday::whereBetween('holiday_date', [$start, $end])
            ->get()
            ->mapWithKeys(function ($holiday) {
                return [$holiday->holiday_date => $holiday->name];
            })
            ->toArray();
    });
}

public function isHoliday(Carbon $date): bool
{
    $map = $this->mapForMonth($date->year, $date->month);
    return isset($map[$date->format('Y-m-d')]);
}
```

---

### 11.2 事業所設定

#### 意図・目的
- 定員管理: 稼働率計算の基準値
- システム設定: 表示言語・タイムゾーン等

#### 技術スタック
- **モデル**: Setting
- **キャッシュ**: Redis（1時間TTL）

#### 実装詳細

**コード箇所: `Setting.php`**

```php
// src/app/Models/Setting.php

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'value' => 'string',
    ];

    /**
     * 設定値取得（キャッシュ付き）
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = "setting:{$key}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = self::find($key);

            if (!$setting) {
                return $default;
            }

            // 型変換
            return self::castValue($setting->value, $setting->type);
        });
    }

    /**
     * 設定値保存
     */
    public static function set(string $key, mixed $value, string $type = 'string', ?string $description = null): void
    {
        self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'description' => $description,
            ]
        );

        // キャッシュクリア
        Cache::forget("setting:{$key}");
    }

    private static function castValue(string $value, string $type): mixed
    {
        return match($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };
    }
}
```

**使用例:**

```php
// 定員取得
$capacity = Setting::get('facility_capacity', 20);

// 定員設定
Setting::set('facility_capacity', 25, 'integer', '事業所定員数');
```

---

## 12. データベース設計

### 12.1 テーブル一覧

| テーブル名 | 用途 | 主要カラム |
|-----------|------|----------|
| users | 利用者マスタ | id, login_code, name, start_date, end_date |
| staffs | 職員マスタ | id, email, role, two_factor_email_code |
| attendance_plans | 出席予定 | user_id, plan_date, plan_type |
| attendance_records | 出席実績 | user_id, record_date, attendance_type, is_approved |
| daily_reports_morning | 朝の日報 | user_id, report_date, sleep_rating, mood_score |
| daily_reports_evening | 夕の日報 | user_id, report_date, training_summary |
| interviews | 面談記録 | user_id, staff_id, interview_date, summary_enc |
| audit_logs | 監査ログ | actor_type, action, entity, occurred_at |
| holidays | 祝日マスタ | holiday_date, name, source |
| settings | システム設定 | key, value, type |

### 12.2 リレーション図

```
users (利用者)
  ├── hasMany attendance_plans (出席予定)
  ├── hasMany attendance_records (出席実績)
  ├── hasMany daily_reports_morning (朝日報)
  ├── hasMany daily_reports_evening (夕日報)
  └── hasMany interviews (面談記録)

staffs (職員)
  ├── hasMany interviews (面談記録)
  └── hasMany audit_logs (via actor_id)

attendance_records (出席実績)
  ├── belongsTo staff (approved_by)
  └── belongsTo staff (locked_by)
```

### 12.3 インデックス設計

**パフォーマンス最適化のためのインデックス:**

```sql
-- 出席予定: ユーザー×日付での高速検索
CREATE INDEX idx_user_date ON attendance_plans(user_id, plan_date);
CREATE UNIQUE INDEX unique_user_date_slot ON attendance_plans(user_id, plan_date, plan_time_slot);

-- 出席実績: ユーザー×日付での高速検索
CREATE INDEX idx_user_date ON attendance_records(user_id, record_date);
CREATE INDEX idx_approved ON attendance_records(is_approved, approved_at);

-- 朝日報: ユーザー×日付での高速検索
CREATE UNIQUE INDEX unique_user_date ON daily_reports_morning(user_id, report_date);

-- 監査ログ: 発生日時・行為者での高速検索
CREATE INDEX idx_occurred_at ON audit_logs(occurred_at);
CREATE INDEX idx_actor ON audit_logs(actor_type, actor_id, action);

-- 利用者: アクティブユーザーの絞り込み
CREATE INDEX idx_active ON users(is_active, start_date, end_date);
```

---

## 13. パフォーマンス最適化

### 13.1 N+1クエリ対策

#### 問題点
```php
// ❌ N+1クエリ発生
foreach ($users as $user) {
    $plans = $user->attendancePlans;  // 各ユーザーごとにクエリ発行
}
```

#### 解決策: Eager Loading

```php
// ✅ Eager Loading
$users = User::with('attendancePlans')->get();
foreach ($users as $user) {
    $plans = $user->attendancePlans;  // リレーションから取得（追加クエリなし）
}
```

#### 実装例: ダッシュボード

**コード箇所: `DashboardService.php:204-245`**

```php
// N+1クエリ対策: データ一括取得
$userIds = $users->pluck('id');

$plans = AttendancePlan::whereIn('user_id', $userIds)
    ->whereBetween('plan_date', [$start, $end])
    ->get()
    ->groupBy('user_id');  // メモリ上でグループ化

foreach ($users as $user) {
    $userPlans = $plans->get($user->id, collect());  // 追加クエリなし
    // KPI計算...
}
```

**改善効果:**
- クエリ数: 16 → 6（62%削減）
- 応答時間: 3.91秒 → 2.55秒（35%改善）

---

### 13.2 キャッシュ戦略

#### 祝日データキャッシュ

```php
// 24時間キャッシュ（変更頻度: 年1回）
$holidays = Cache::tags('holidays')->remember("holidays:{$year}:{$month}", 86400, function () {
    return Holiday::whereBetween('holiday_date', [$start, $end])->get();
});
```

#### 設定値キャッシュ

```php
// 1時間キャッシュ
$capacity = Cache::remember("setting:facility_capacity", 3600, function () {
    return Setting::where('key', 'facility_capacity')->value('value') ?? 20;
});
```

---

### 13.3 データベースクエリ最適化

#### SELECT文の最適化

```php
// ❌ 全カラム取得
$users = User::all();

// ✅ 必要なカラムのみ取得
$users = User::select('id', 'name', 'login_code')->get();
```

#### WHERE句のインデックス活用

```sql
-- ✅ インデックス使用
SELECT * FROM attendance_records
WHERE user_id = 1 AND record_date BETWEEN '2025-01-01' AND '2025-01-31';

-- ❌ インデックス未使用（関数使用）
SELECT * FROM attendance_records
WHERE YEAR(record_date) = 2025 AND MONTH(record_date) = 1;
```

---

### 13.4 レスポンスタイム目標

| 機能 | 目標 | 実測 | 判定 |
|------|------|------|------|
| 個人ダッシュボードAPI | 2秒以内 | 2.55秒 | ⚠️ 目標未達（35%改善） |
| 出席管理API | 3秒以内 | 1.06秒 | ✅ 目標達成（66%改善） |
| 祝日API | - | 1.36秒 | ✅ 良好（49%改善） |
| CSV出力 | 5秒以内 | - | （P95要件） |

---

## 14. セキュリティ対策

### 14.1 実装済み対策

| 脅威 | 対策 | 実装箇所 |
|------|------|----------|
| XSS | Bladeエスケープ | 全ビュー（`{{ }}` 構文） |
| SQLインジェクション | Eloquent ORM | 全モデル |
| CSRF | Laravelトークン | 全フォーム（`@csrf`） |
| パスワード漏洩 | bcryptハッシュ化 | User/Staff認証 |
| セッション固定化 | セッション再生成 | ログイン時 |
| ブルートフォース | ログイン試行制限 | 5回失敗でロック |
| 権限昇格 | RBAC (admin/staff) | ミドルウェア |
| 個人情報漏洩 | 暗号化保存 | care_notes_enc等 |
| 監査証跡 | 監査ログ | AuditMiddleware |

---

## 15. まとめ

AYUMIシステムは、福祉事業所における通所管理を効率化し、利用者の自己理解・生活改善を支援する包括的なWebアプリケーションです。

### 主要技術選定理由

1. **Laravel**: エンタープライズ要件（認証・認可・監査）を標準機能でカバー
2. **MySQL**: トランザクション整合性・リレーショナルデータの管理
3. **Redis**: セッション・キャッシュの高速化
4. **Blade**: サーバーサイドレンダリングによるSEO対応・初期表示高速化

### 今後の拡張性

- **マルチテナント対応**: 複数事業所の統合管理
- **外部システム連携**: 請求システム・自治体報告システムとのAPI連携
- **AI活用**: 出席率予測・日報感情分析
- **PWA化**: オフライン対応・プッシュ通知

---

**ドキュメント作成日**: 2025年10月7日
**バージョン**: 1.0
**対象システム**: AYUMI v1.0
