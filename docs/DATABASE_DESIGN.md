# データベース設計書

## 概要

このドキュメントは、就労移行支援事業所向け利用者管理システムのデータベース設計を記述します。

## ER図の概念

```
staffs (スタッフ)
  ↓ 1:N
audit_logs (監査ログ)

users (利用者)
  ↓ 1:N
  ├── attendance_plans (出席予定)
  ├── attendance_records (出席実績)
  ├── daily_reports_morning (日報・朝)
  ├── daily_reports_evening (日報・夕)
  └── interviews (面談記録)

holidays (祝日マスタ) ← 単独テーブル
settings (設定マスタ) ← 単独テーブル
```

---

## テーブル一覧

### 1. users（利用者）

就労移行支援施設の利用者情報を管理するテーブル。

| カラム名 | 型 | NULL | デフォルト | 説明 |
|---------|-----|------|-----------|------|
| id | bigInteger | NO | AUTO_INCREMENT | 主キー |
| name | string | NO | - | 利用者氏名 |
| name_kana | string | YES | NULL | 利用者氏名（かな） |
| login_code | string | NO | - | 利用者ログインID（一意） |
| email | string | YES | NULL | メールアドレス（一意、NULL可） |
| email_verified_at | timestamp | YES | NULL | メール認証日時 |
| password | string | NO | - | パスワード（ハッシュ化） |
| start_date | date | YES | NULL | 利用開始日 |
| end_date | date | YES | NULL | 利用終了日 |
| care_notes_enc | longText | YES | NULL | 機微情報（アプリ層で暗号化） |
| created_by | unsignedBigInteger | YES | NULL | 作成者ID |
| updated_by | unsignedBigInteger | YES | NULL | 更新者ID |
| last_login_at | timestamp | YES | NULL | 最終ログイン日時 |
| failed_attempts | unsignedInteger | NO | 0 | ログイン失敗回数 |
| is_active | boolean | NO | true | 有効フラグ |
| remember_token | string | YES | NULL | Remember Me トークン |
| created_at | timestamp | YES | NULL | 作成日時 |
| updated_at | timestamp | YES | NULL | 更新日時 |
| deleted_at | timestamp | YES | NULL | 論理削除日時（ソフトデリート） |

**インデックス**
- UNIQUE: `login_code`, `email`
- INDEX: `(is_active, start_date, end_date)`

**特記事項**
- `care_notes_enc`には機微な個人情報を暗号化して保存
- ソフトデリート対応（論理削除）

---

### 2. staffs（スタッフ）

施設のスタッフ・管理者情報を管理するテーブル。

| カラム名 | 型 | NULL | デフォルト | 説明 |
|---------|-----|------|-----------|------|
| id | bigInteger | NO | AUTO_INCREMENT | 主キー |
| name | string | NO | - | スタッフ氏名 |
| email | string | NO | - | メールアドレス（一意） |
| password | string | NO | - | パスワード（ハッシュ化） |
| role | enum | NO | 'staff' | 役割（'admin', 'staff'） |
| two_factor_email_code | string(10) | YES | NULL | 2FA用メールコード |
| two_factor_expires_at | timestamp | YES | NULL | 2FAコード有効期限 |
| two_factor_confirmed_at | timestamp | YES | NULL | 2FA確認日時 |
| email_verified_at | timestamp | YES | NULL | メール認証日時 |
| last_login_at | timestamp | YES | NULL | 最終ログイン日時 |
| failed_attempts | unsignedInteger | NO | 0 | ログイン失敗回数 |
| is_active | boolean | NO | true | 有効フラグ |
| remember_token | string | YES | NULL | Remember Me トークン |
| created_at | timestamp | YES | NULL | 作成日時 |
| updated_at | timestamp | YES | NULL | 更新日時 |
| deleted_at | timestamp | YES | NULL | 論理削除日時（ソフトデリート） |

**インデックス**
- UNIQUE: `email`
- INDEX: `role`, `(role, is_active)`

**特記事項**
- RBAC（ロールベースアクセス制御）実装
- メールベースの2要素認証（2FA）対応
- ソフトデリート対応

---

### 3. audit_logs（監査ログ）

システム上の全操作を記録する監査ログテーブル。

| カラム名 | 型 | NULL | デフォルト | 説明 |
|---------|-----|------|-----------|------|
| id | bigInteger | NO | AUTO_INCREMENT | 主キー |
| actor_type | string(20) | YES | NULL | 操作者種別（'staff', 'user' 等） |
| actor_id | unsignedBigInteger | YES | NULL | 操作者ID |
| occurred_at | timestamp | NO | CURRENT_TIMESTAMP | 操作発生日時 |
| action | enum | NO | - | 操作種別 |
| entity | string(50) | YES | NULL | 対象エンティティ種別 |
| entity_id | unsignedBigInteger | YES | NULL | 対象エンティティID |
| diff_json | json | YES | NULL | 変更差分（JSON形式） |
| ip | string(45) | YES | NULL | IPアドレス（IPv6対応） |
| user_agent | string(500) | YES | NULL | ユーザーエージェント |
| meta | json | YES | NULL | その他メタ情報（JSON形式） |

**アクション種別（enum: action）**
- `login` - ログイン
- `logout` - ログアウト
- `create` - 作成
- `update` - 更新
- `delete` - 削除
- `export` - エクスポート
- `setting` - 設定変更
- `two_factor_email_sent` - 2FAメール送信
- `two_factor_email_verified` - 2FA認証成功
- `two_factor_email_failed` - 2FA認証失敗

**インデックス**
- INDEX: `occurred_at`, `(actor_type, actor_id, action)`

**特記事項**
- セキュリティ監査・コンプライアンス対応
- 個人情報アクセス履歴の追跡

---

### 4. attendance_plans（出席予定）

利用者の出席予定を管理するテーブル。

| カラム名 | 型 | NULL | デフォルト | 説明 |
|---------|-----|------|-----------|------|
| id | bigInteger | NO | AUTO_INCREMENT | 主キー |
| user_id | unsignedBigInteger | NO | - | 利用者ID（外部キー） |
| plan_date | date | NO | - | 予定日 |
| plan_time_slot | enum | NO | - | 時間帯（'am', 'pm', 'full'） |
| plan_type | enum | NO | 'onsite' | 出席種別（'onsite', 'remote', 'off'） |
| note | text | YES | NULL | 備考 |
| is_holiday | boolean | NO | false | 祝日フラグ |
| holiday_name | string(50) | YES | NULL | 祝日名 |
| template_source | enum | YES | NULL | テンプレート元（'prev_month', 'weekday'） |
| created_at | timestamp | YES | NULL | 作成日時 |
| updated_at | timestamp | YES | NULL | 更新日時 |

**インデックス**
- UNIQUE: `(user_id, plan_date, plan_time_slot)` (名前: uniq_user_plan_date_slot)
- INDEX: `(user_id, plan_date)` (名前: idx_user_plan_date)

**外部キー**
- `user_id` → `users.id`

**特記事項**
- 午前（am）、午後（pm）、終日（full）の3区分
- 現地出席、リモート、休みの3種類に対応
- テンプレート機能（前月コピー、平日パターン）

---

### 5. attendance_records（出席実績）

利用者の実際の出席記録を管理するテーブル。

| カラム名 | 型 | NULL | デフォルト | 説明 |
|---------|-----|------|-----------|------|
| id | bigInteger | NO | AUTO_INCREMENT | 主キー |
| user_id | unsignedBigInteger | NO | - | 利用者ID（外部キー） |
| record_date | date | NO | - | 出席日（JST基準） |
| record_time_slot | enum | NO | - | 時間帯（'am', 'pm', 'full'） |
| attendance_type | enum | NO | - | 出席種別（'onsite', 'remote', 'absent'） |
| note | text | YES | NULL | 備考 |
| source | enum | NO | 'self' | 登録元（'self', 'staff'） |
| is_approved | boolean | NO | false | 承認済みフラグ |
| approved_by | unsignedBigInteger | YES | NULL | 承認者ID（外部キー） |
| approved_at | timestamp | YES | NULL | 承認日時 |
| approval_note | text | YES | NULL | 承認時メモ |
| is_locked | boolean | NO | false | ロック済みフラグ |
| locked_by | unsignedBigInteger | YES | NULL | ロック者ID（外部キー） |
| locked_at | timestamp | YES | NULL | ロック日時 |
| created_at | timestamp | YES | NULL | 作成日時 |
| updated_at | timestamp | YES | NULL | 更新日時 |

**インデックス**
- UNIQUE: `(user_id, record_date, record_time_slot)` (名前: uniq_user_record_date_slot)
- INDEX: `(user_id, record_date)` (名前: idx_user_record_date)
- INDEX: `(is_approved, approved_at)` (名前: idx_approval)
- INDEX: `(is_locked, locked_at)` (名前: idx_lock)

**外部キー**
- `user_id` → `users.id` (onDelete: CASCADE)
- `approved_by` → `staffs.id` (onDelete: SET NULL)
- `locked_by` → `staffs.id` (onDelete: SET NULL)

**特記事項**
- 利用者自身またはスタッフが登録可能
- スタッフによる承認ワークフロー
- 月次締め後のロック機能

---

### 6. daily_reports_morning（日報・朝）

利用者の朝の健康状態・生活習慣を記録するテーブル。

| カラム名 | 型 | NULL | デフォルト | 説明 |
|---------|-----|------|-----------|------|
| id | bigInteger | NO | AUTO_INCREMENT | 主キー |
| user_id | unsignedBigInteger | NO | - | 利用者ID（外部キー） |
| report_date | date | NO | - | 報告日 |
| **基本評価（3段階：◯=3, △=2, ✕=1）** |||||
| sleep_rating | tinyInteger unsigned | NO | - | 睡眠評価 |
| stress_rating | tinyInteger unsigned | NO | - | ストレス評価 |
| meal_rating | tinyInteger unsigned | NO | - | 食事評価 |
| **睡眠詳細** |||||
| bed_time_local | time | YES | NULL | 就寝時刻（JST、HH:mm） |
| wake_time_local | time | YES | NULL | 起床時刻（JST、HH:mm） |
| bed_at | datetime | YES | NULL | 就寝日時（UTC保存） |
| wake_at | datetime | YES | NULL | 起床日時（UTC保存） |
| sleep_minutes | integer | YES | NULL | 睡眠時間（分、0-960） |
| **睡眠の質** |||||
| mid_awaken_count | tinyInteger unsigned | NO | 0 | 中途覚醒回数（0-10） |
| is_early_awaken | boolean | NO | false | 早朝覚醒フラグ |
| **生活習慣** |||||
| is_breakfast_done | boolean | NO | false | 朝食摂取フラグ |
| is_bathing_done | boolean | NO | false | 入浴実施フラグ |
| is_medication_taken | boolean | YES | NULL | 服薬フラグ（NULL=習慣なし） |
| **気分・体調** |||||
| mood_score | tinyInteger unsigned | NO | 5 | 気分スコア（1-10） |
| sign_good | integer | NO | 0 | 良好なサイン数 |
| sign_caution | integer | NO | 0 | 注意サイン数 |
| sign_bad | integer | NO | 0 | 不調サイン数 |
| **相談・連絡** |||||
| note | text | YES | NULL | 備考・相談内容 |
| created_at | timestamp | YES | NULL | 作成日時 |
| updated_at | timestamp | YES | NULL | 更新日時 |

**インデックス**
- UNIQUE: `(user_id, report_date)` (名前: uniq_user_morning_date)
- INDEX: `(user_id, report_date)`

**外部キー**
- `user_id` → `users.id`

**特記事項**
- 健康管理・メンタルヘルスケア機能
- 睡眠時刻はJST入力後、UTC変換して保存
- 3段階評価：◯（良好）=3、△（普通）=2、✕（不良）=1

---

### 7. daily_reports_evening（日報・夕）

利用者の夕方の訓練内容・振り返りを記録するテーブル。

| カラム名 | 型 | NULL | デフォルト | 説明 |
|---------|-----|------|-----------|------|
| id | bigInteger | NO | AUTO_INCREMENT | 主キー |
| user_id | unsignedBigInteger | NO | - | 利用者ID（外部キー） |
| report_date | date | NO | - | 報告日 |
| training_summary | text | YES | NULL | 訓練内容 |
| training_reflection | text | YES | NULL | 訓練の振り返り |
| condition_note | text | YES | NULL | 体調について |
| other_note | text | YES | NULL | その他 |
| created_at | timestamp | YES | NULL | 作成日時 |
| updated_at | timestamp | YES | NULL | 更新日時 |

**インデックス**
- UNIQUE: `(user_id, report_date)` (名前: uniq_user_evening_date)

**外部キー**
- `user_id` → `users.id`

**特記事項**
- 訓練の進捗管理・記録
- 自己評価・振り返り機能

---

### 8. interviews（面談記録）

スタッフと利用者の面談記録を管理するテーブル。

| カラム名 | 型 | NULL | デフォルト | 説明 |
|---------|-----|------|-----------|------|
| id | bigInteger | NO | AUTO_INCREMENT | 主キー |
| user_id | unsignedBigInteger | NO | - | 利用者ID（外部キー） |
| interview_at | datetime | NO | - | 面談日時（UTC保存） |
| summary | text | YES | NULL | 面談内容サマリー |
| next_action | text | YES | NULL | 次回アクション |
| created_by | unsignedBigInteger | YES | NULL | 作成者ID |
| updated_by | unsignedBigInteger | YES | NULL | 更新者ID |
| created_at | timestamp | YES | NULL | 作成日時 |
| updated_at | timestamp | YES | NULL | 更新日時 |

**インデックス**
- INDEX: `(user_id, interview_at)`

**外部キー**
- `user_id` → `users.id`

**特記事項**
- 面談記録の管理
- 日時はUTC保存、JST表示はアプリ層で変換

---

### 9. holidays（祝日マスタ）

日本の祝日情報を管理するマスタテーブル。

| カラム名 | 型 | NULL | デフォルト | 説明 |
|---------|-----|------|-----------|------|
| holiday_date | date | NO | - | 祝日日付（主キー、JST基準） |
| name | string(50) | NO | - | 祝日名 |
| source | enum | NO | 'manual' | 登録元 |
| imported_at | datetime | YES | NULL | 取込日時（UTC） |

**登録元種別（enum: source）**
- `government_api` - 政府API経由
- `csv` - CSV取込
- `manual` - 手動登録

**インデックス**
- PRIMARY KEY: `holiday_date`

**特記事項**
- 祝日カレンダー機能
- 政府APIまたはCSVからの一括取込対応

---

### 10. settings（設定マスタ）

システム設定を管理するKey-Valueストアテーブル。

| カラム名 | 型 | NULL | デフォルト | 説明 |
|---------|-----|------|-----------|------|
| key | string(100) | NO | - | 設定キー（主キー） |
| value | text | YES | NULL | 設定値 |
| type | string(20) | NO | 'string' | データ型 |
| description | text | YES | NULL | 設定の説明 |
| created_at | timestamp | YES | NULL | 作成日時 |
| updated_at | timestamp | YES | NULL | 更新日時 |

**データ型種別（type）**
- `string` - 文字列
- `integer` - 整数
- `boolean` - 真偽値
- `json` - JSON形式

**インデックス**
- PRIMARY KEY: `key`

**初期データ**
- `facility_capacity`: 事業所の定員数（デフォルト: 20）

**特記事項**
- 柔軟な設定管理
- アプリ層で型変換を実施

---

## システムテーブル（Laravel標準）

以下のテーブルはLaravelフレームワークの標準機能で使用されます（詳細は省略）。

- `jobs` - キュージョブ管理
- `job_batches` - バッチジョブ管理
- `cache` - キャッシュストレージ
- `cache_locks` - キャッシュロック管理
- `personal_access_tokens` - APIトークン管理（Laravel Sanctum）

---

## セキュリティ・コンプライアンス対応

### 個人情報保護

1. **暗号化**
   - `users.care_notes_enc`: 機微情報はアプリ層で暗号化

2. **監査ログ**
   - `audit_logs`: 全操作を記録（GDPR対応）

3. **アクセス制御**
   - `staffs.role`: RBAC実装（admin / staff）
   - `staffs.two_factor_*`: 2要素認証

4. **ソフトデリート**
   - `users`, `staffs`: 論理削除で履歴保持

### 認証・認可

- パスワードは全てハッシュ化
- ログイン失敗回数制限（`failed_attempts`）
- セッション管理（`remember_token`）
- メールベース2FA（スタッフのみ）

---

## タイムゾーン方針

- **データベース保存**: 全てUTC
- **ユーザー入力/表示**: JST（アプリ層で変換）
- **例外**: `*_time_local` カラムはJST入力値をそのまま保存（時刻のみ）

---

## 運用上の注意点

1. **出席実績のロック機能**
   - 月次締め後は `attendance_records.is_locked = true` で編集不可

2. **祝日の自動反映**
   - `holidays` テーブルは定期的に政府APIから更新

3. **定員管理**
   - `settings.facility_capacity` で施設定員を管理

4. **バックアップ**
   - 個人情報を含むため、暗号化バックアップ必須

---

## 更新履歴

| 日付 | 版 | 変更内容 |
|------|-----|---------|
| 2025-10-17 | 1.0 | 初版作成 |
