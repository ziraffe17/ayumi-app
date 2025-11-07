# AYUMI本番環境構築・運用ガイド

## 目次

1. [必要なリソース](#1-必要なリソース)
2. [インフラ構成パターン](#2-インフラ構成パターン)
3. [導入手順](#3-導入手順)
4. [セキュリティ対策](#4-セキュリティ対策)
5. [運用・監視](#5-運用監視)
6. [バックアップ・DR](#6-バックアップdr)
7. [コスト試算](#7-コスト試算)

---

## 1. 必要なリソース

### 1.1 ハードウェア・インフラ

#### 最小構成（利用者20名・職員5名）

| リソース | スペック | 用途 | 備考 |
|---------|---------|------|------|
| **Webサーバー** | 2コア CPU、4GB RAM、50GB SSD | Laravel実行 | 1台 |
| **DBサーバー** | 2コア CPU、4GB RAM、100GB SSD | MySQL 8.0 | 1台 |
| **Redisサーバー** | 1コア CPU、2GB RAM、10GB SSD | キャッシュ・セッション | Webサーバーと同居可 |
| **ネットワーク** | 固定IPアドレス | 外部公開 | HTTPS必須 |
| **ストレージ** | 200GB（増量可） | バックアップ | 外部ストレージ推奨 |

#### 推奨構成（利用者50名・職員10名）

| リソース | スペック | 用途 | 備考 |
|---------|---------|------|------|
| **Webサーバー** | 4コア CPU、8GB RAM、100GB SSD | Laravel実行 | 2台（冗長化） |
| **DBサーバー** | 4コア CPU、8GB RAM、500GB SSD | MySQL 8.0 | 1台（+ レプリカ1台） |
| **Redisサーバー** | 2コア CPU、4GB RAM、20GB SSD | キャッシュ・セッション | 1台（+ スタンバイ1台） |
| **ロードバランサー** | - | 負荷分散 | クラウドサービス利用 |
| **ストレージ** | 1TB | バックアップ | S3等のオブジェクトストレージ |

---

### 1.2 ソフトウェア要件

| カテゴリ | 必須バージョン | 備考 |
|---------|--------------|------|
| **OS** | Ubuntu 22.04 LTS / CentOS 8+ | Linuxサーバー推奨 |
| **Webサーバー** | Nginx 1.20+ | Apache も可 |
| **PHP** | 8.3+ | php-fpm, 各種拡張モジュール |
| **MySQL** | 8.0+ | MariaDB 10.6+ も可 |
| **Redis** | 7.0+ | キャッシュ・セッション |
| **Composer** | 2.5+ | PHP依存関係管理 |
| **Git** | 2.30+ | ソースコード管理 |
| **SSL証明書** | Let's Encrypt等 | HTTPS必須 |

---

### 1.3 外部サービス

| サービス | 用途 | 必須/推奨 | 備考 |
|---------|------|----------|------|
| **メールサーバー** | 2FA認証コード送信 | **必須** | SendGrid, AWS SES, Gmail SMTP等 |
| **ドメイン** | サービスURL | **必須** | 例: ayumi.example.com |
| **SSL証明書** | HTTPS化 | **必須** | Let's Encrypt（無料）推奨 |
| **監視サービス** | 稼働監視 | 推奨 | UptimeRobot, Datadog等 |
| **バックアップストレージ** | データバックアップ | 推奨 | AWS S3, Backblaze B2等 |
| **CDN** | 静的ファイル配信 | 任意 | Cloudflare（無料プラン可） |

---

### 1.4 人的リソース

| 役割 | 必要スキル | 工数 | タイミング |
|------|-----------|------|----------|
| **インフラエンジニア** | Linux, Docker, ネットワーク | 初期: 40時間 | 構築時 |
| **アプリエンジニア** | PHP, Laravel, MySQL | 初期: 20時間 | 設定・カスタマイズ |
| **運用担当** | 基本的なLinuxコマンド | 月5時間 | 運用開始後 |
| **セキュリティ担当** | セキュリティ監査 | 初期: 10時間 | 構築時・年次 |

---

## 2. インフラ構成パターン

### 2.1 パターンA: オンプレミス（小規模）

**対象:** 利用者20名以下、予算重視

```
┌─────────────────────────────────────────┐
│  インターネット                          │
└─────────────────────────────────────────┘
              ↓ HTTPS
┌─────────────────────────────────────────┐
│  ルーター（固定IP）                       │
└─────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────┐
│  物理サーバー（1台）                      │
│  ┌───────────────────────────────────┐  │
│  │ Docker Compose                   │  │
│  │  ├─ Nginx (Webサーバー)          │  │
│  │  ├─ PHP-FPM (Laravel)            │  │
│  │  ├─ MySQL 8.0                    │  │
│  │  └─ Redis                        │  │
│  └───────────────────────────────────┘  │
└─────────────────────────────────────────┘
```

**メリット:**
- ✅ 初期コスト低（月額0円、サーバー買い切り）
- ✅ データを自社で完全管理

**デメリット:**
- ❌ 冗長化困難（単一障害点）
- ❌ スケールアウト不可
- ❌ 停電・災害リスク

**推奨ハードウェア:**
- Dell PowerEdge T150（約15万円）
- CPU: Xeon E-2314 4コア
- RAM: 16GB
- SSD: 500GB

---

### 2.2 パターンB: クラウド（VPS）- 推奨

**対象:** 利用者50名以下、バランス重視

```
┌─────────────────────────────────────────┐
│  Cloudflare CDN (DDoS対策・SSL)         │
└─────────────────────────────────────────┘
              ↓ HTTPS
┌─────────────────────────────────────────┐
│  VPS (Conoha, さくら等)                  │
│  ┌───────────────────────────────────┐  │
│  │ Docker Compose                   │  │
│  │  ├─ Nginx                        │  │
│  │  ├─ PHP-FPM                      │  │
│  │  ├─ MySQL 8.0                    │  │
│  │  └─ Redis                        │  │
│  └───────────────────────────────────┘  │
└─────────────────────────────────────────┘
              ↓ バックアップ
┌─────────────────────────────────────────┐
│  オブジェクトストレージ（S3互換）         │
└─────────────────────────────────────────┘
```

**メリット:**
- ✅ 低コスト（月額3,000-10,000円）
- ✅ 簡単スケールアップ（RAM/CPU増設）
- ✅ 自動バックアップ機能
- ✅ 高速ネットワーク

**デメリット:**
- ❌ 単一障害点（冗長化は別途費用）

**推奨サービス:**
- **ConoHa VPS**: 4GB RAM / 月額2,633円
- **さくらVPS**: 4GB RAM / 月額3,520円
- **Vultr**: 4GB RAM / 月額$24（約3,600円）

---

### 2.3 パターンC: フルマネージドクラウド（AWS/GCP）

**対象:** 利用者100名以上、高可用性重視

```
┌─────────────────────────────────────────┐
│  Route 53 (DNS) + CloudFront (CDN)      │
└─────────────────────────────────────────┘
              ↓ HTTPS
┌─────────────────────────────────────────┐
│  ALB (Application Load Balancer)        │
└─────────────────────────────────────────┘
         ↓                    ↓
┌──────────────────┐  ┌──────────────────┐
│ ECS Fargate      │  │ ECS Fargate      │
│ (Laravel)        │  │ (Laravel)        │  ← 自動スケーリング
└──────────────────┘  └──────────────────┘
         ↓                    ↓
┌─────────────────────────────────────────┐
│  Amazon RDS (MySQL 8.0)                 │
│  - Multi-AZ 構成（冗長化）               │
└─────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────┐
│  ElastiCache (Redis)                    │
└─────────────────────────────────────────┘
              ↓
┌─────────────────────────────────────────┐
│  S3 (バックアップ・静的ファイル)          │
└─────────────────────────────────────────┘
```

**メリット:**
- ✅ 高可用性（99.99%以上）
- ✅ 自動スケーリング
- ✅ マネージドサービス（運用負荷低）
- ✅ グローバル展開可能

**デメリット:**
- ❌ 高コスト（月額5-20万円）
- ❌ AWS知識が必要

**推奨構成:**
- **Web**: ECS Fargate（2タスク）
- **DB**: RDS MySQL t3.medium（Multi-AZ）
- **Cache**: ElastiCache Redis t3.micro
- **Storage**: S3 Standard
- **月額**: 約10-15万円

---

## 3. 導入手順

### 3.1 事前準備（1週間前）

#### ✅ チェックリスト

- [ ] サーバー調達（VPS契約 or 物理サーバー購入）
- [ ] ドメイン取得（例: ayumi-tsusho.jp）
- [ ] SSL証明書準備（Let's Encrypt推奨）
- [ ] メールサーバー契約（SendGrid, AWS SES等）
- [ ] バックアップストレージ確保
- [ ] 運用担当者のアサイン

---

### 3.2 サーバー構築（1-2日）

#### Step 1: OS初期設定

```bash
# Ubuntu 22.04 の場合
sudo apt update
sudo apt upgrade -y

# タイムゾーン設定
sudo timedatectl set-timezone Asia/Tokyo

# ファイアウォール設定
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable

# 作業用ユーザー作成
sudo adduser ayumi
sudo usermod -aG sudo ayumi
```

#### Step 2: 必要ソフトウェアのインストール

```bash
# Docker & Docker Compose インストール
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# Docker Compose v2
sudo apt install docker-compose-plugin

# Git
sudo apt install git

# 再ログイン（Dockerグループ反映）
exit
```

#### Step 3: アプリケーションデプロイ

```bash
# リポジトリクローン
cd /opt
sudo git clone https://github.com/your-org/ayumi.git
sudo chown -R ayumi:ayumi ayumi
cd ayumi

# 本番用 .env 作成
cp .env.example .env.production
nano .env.production
```

**.env.production 設定例:**

```env
APP_NAME="AYUMI"
APP_ENV=production
APP_KEY=base64:GENERATE_THIS_KEY
APP_DEBUG=false
APP_URL=https://ayumi.example.com

APP_LOCALE=ja
APP_TIMEZONE=Asia/Tokyo

# データベース
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=tsusho
DB_USERNAME=tsu_prod
DB_PASSWORD=STRONG_PASSWORD_HERE

# キャッシュ・セッション
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=REDIS_PASSWORD_HERE
REDIS_PORT=6379

# メール（SendGrid例）
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=YOUR_SENDGRID_API_KEY
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@ayumi.example.com"
MAIL_FROM_NAME="${APP_NAME}"

# ログ
LOG_CHANNEL=daily
LOG_LEVEL=warning

# セキュリティ
BCRYPT_ROUNDS=12
```

#### Step 4: アプリケーションキー生成

```bash
docker compose -f docker-compose.production.yml run --rm app php artisan key:generate --show

# 出力されたキーを .env.production の APP_KEY に設定
```

#### Step 5: Docker起動

```bash
# 本番用 docker-compose.production.yml
docker compose -f docker-compose.production.yml up -d

# 依存関係インストール
docker compose -f docker-compose.production.yml exec app composer install --no-dev --optimize-autoloader

# マイグレーション実行
docker compose -f docker-compose.production.yml exec app php artisan migrate --force

# キャッシュ最適化
docker compose -f docker-compose.production.yml exec app php artisan config:cache
docker compose -f docker-compose.production.yml exec app php artisan route:cache
docker compose -f docker-compose.production.yml exec app php artisan view:cache
```

---

### 3.3 Nginx + SSL設定（半日）

#### Step 1: Nginx設定ファイル作成

```nginx
# /etc/nginx/sites-available/ayumi.conf

server {
    listen 80;
    server_name ayumi.example.com;

    # Let's Encrypt用
    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    # HTTPSへリダイレクト
    location / {
        return 301 https://$host$request_uri;
    }
}

server {
    listen 443 ssl http2;
    server_name ayumi.example.com;

    # SSL証明書
    ssl_certificate /etc/letsencrypt/live/ayumi.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ayumi.example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # セキュリティヘッダー
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # アクセスログ
    access_log /var/log/nginx/ayumi_access.log;
    error_log /var/log/nginx/ayumi_error.log;

    # ドキュメントルート
    root /opt/ayumi/src/public;
    index index.php;

    # Laravelルーティング
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 静的ファイルキャッシュ
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

#### Step 2: Let's Encrypt SSL証明書取得

```bash
# Certbot インストール
sudo apt install certbot python3-certbot-nginx

# SSL証明書取得
sudo certbot --nginx -d ayumi.example.com

# 自動更新設定（cron）
sudo certbot renew --dry-run
```

#### Step 3: Nginx有効化・再起動

```bash
sudo ln -s /etc/nginx/sites-available/ayumi.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

---

### 3.4 初期データ投入

```bash
# 管理者アカウント作成
docker compose exec app php artisan tinker

# Tinker内で実行
$staff = new \App\Models\Staff();
$staff->name = '管理者';
$staff->email = 'admin@example.com';
$staff->password = Hash::make('SECURE_PASSWORD');
$staff->role = 'admin';
$staff->is_active = true;
$staff->save();

# 祝日データ取込
docker compose exec app php artisan db:seed --class=HolidaySeeder

# 事業所設定
docker compose exec app php artisan tinker
\App\Models\Setting::set('facility_capacity', 20, 'integer', '事業所定員数');
```

---

### 3.5 動作確認（半日）

#### ✅ 確認項目チェックリスト

**基本機能:**
- [ ] HTTPSアクセス確認（https://ayumi.example.com）
- [ ] 職員ログイン（2FA メール受信確認）
- [ ] 利用者ログイン
- [ ] 出席予定登録
- [ ] 出席実績登録
- [ ] 日報入力（朝・夕）
- [ ] ダッシュボード表示
- [ ] CSV出力

**セキュリティ:**
- [ ] HTTPからHTTPSへのリダイレクト
- [ ] CSRF トークン動作
- [ ] 未認証アクセスの403/302
- [ ] 監査ログ記録

**パフォーマンス:**
- [ ] ページ読み込み速度（≤3秒）
- [ ] ダッシュボードAPI（≤2.5秒）

**エラーハンドリング:**
- [ ] 404ページ表示
- [ ] 500エラー時のログ記録

---

## 4. セキュリティ対策

### 4.1 必須対策

#### ✅ チェックリスト

**アプリケーションレベル:**
- [ ] `.env` ファイルのパーミッション（600）
- [ ] `APP_DEBUG=false` 設定
- [ ] `APP_ENV=production` 設定
- [ ] SESSION_ENCRYPT=true
- [ ] SESSION_SECURE_COOKIE=true
- [ ] BCRYPT_ROUNDS=12

**サーバーレベル:**
- [ ] SSH ポート変更（22 → 別ポート）
- [ ] SSH 公開鍵認証のみ許可
- [ ] ファイアウォール設定（ufw/iptables）
- [ ] fail2ban 導入（ブルートフォース対策）
- [ ] 自動セキュリティアップデート有効化

**データベース:**
- [ ] 外部からの直接アクセス禁止
- [ ] 強力なパスワード設定
- [ ] 不要なユーザー削除
- [ ] 定期的なバックアップ

**ネットワーク:**
- [ ] Cloudflare DDoS対策（推奨）
- [ ] IPアドレス制限（管理画面）
- [ ] レート制限設定

---

### 4.2 fail2ban 設定例

```bash
# fail2ban インストール
sudo apt install fail2ban

# 設定ファイル作成
sudo nano /etc/fail2ban/jail.local
```

**/etc/fail2ban/jail.local:**

```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
port = ssh
logpath = /var/log/auth.log

[nginx-limit-req]
enabled = true
filter = nginx-limit-req
logpath = /var/log/nginx/ayumi_error.log
maxretry = 10
```

```bash
sudo systemctl restart fail2ban
sudo fail2ban-client status
```

---

### 4.3 データ暗号化

**個人情報の暗号化確認:**

```bash
# Laravelの暗号化キー確認
docker compose exec app php artisan tinker

# 暗号化テスト
encrypt('test');  // 暗号化
decrypt('暗号化された文字列');  // 復号化
```

**暗号化対象カラム:**
- `users.care_notes_enc`
- `interviews.summary_enc`
- `interviews.detail_enc`

---

## 5. 運用・監視

### 5.1 日次運用タスク

| タスク | 頻度 | コマンド | 担当 |
|--------|------|---------|------|
| ログ確認 | 日次 | `tail -f /var/log/nginx/ayumi_error.log` | 運用担当 |
| ディスク使用率確認 | 日次 | `df -h` | 運用担当 |
| バックアップ確認 | 日次 | 後述 | 運用担当 |

---

### 5.2 週次運用タスク

| タスク | 頻度 | 内容 | 担当 |
|--------|------|------|------|
| セキュリティアップデート | 週次 | `sudo apt update && sudo apt upgrade` | インフラ担当 |
| 監査ログレビュー | 週次 | 異常アクセスの確認 | セキュリティ担当 |

---

### 5.3 監視設定

#### UptimeRobot（無料）による稼働監視

```
監視URL: https://ayumi.example.com
チェック間隔: 5分
アラート先: 運用担当者のメール
```

#### アラート設定

```bash
# ディスク使用率90%超でアラート
sudo nano /etc/cron.daily/disk-check
```

**/etc/cron.daily/disk-check:**

```bash
#!/bin/bash
USAGE=$(df -h / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ $USAGE -gt 90 ]; then
    echo "ディスク使用率が ${USAGE}% です" | mail -s "[AYUMI] ディスクアラート" admin@example.com
fi
```

---

### 5.4 ログ管理

```bash
# Laravelログのローテーション設定
sudo nano /etc/logrotate.d/ayumi
```

**/etc/logrotate.d/ayumi:**

```
/opt/ayumi/src/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
}
```

---

## 6. バックアップ・DR

### 6.1 バックアップ戦略

#### データベースバックアップ（日次）

```bash
# バックアップスクリプト作成
sudo nano /opt/backup/mysql_backup.sh
```

**/opt/backup/mysql_backup.sh:**

```bash
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/opt/backup/mysql"
DB_CONTAINER="php-selfmade-db-1"

# バックアップディレクトリ作成
mkdir -p $BACKUP_DIR

# MySQLダンプ
docker exec $DB_CONTAINER mysqldump -u root -ppassword tsusho \
    | gzip > $BACKUP_DIR/tsusho_$DATE.sql.gz

# 30日以上古いバックアップ削除
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete

# S3へアップロード（オプション）
# aws s3 cp $BACKUP_DIR/tsusho_$DATE.sql.gz s3://ayumi-backup/mysql/

echo "Backup completed: tsusho_$DATE.sql.gz"
```

```bash
chmod +x /opt/backup/mysql_backup.sh

# cron設定（毎日午前3時）
crontab -e
0 3 * * * /opt/backup/mysql_backup.sh >> /var/log/backup.log 2>&1
```

---

#### ファイルバックアップ

```bash
# rsync でバックアップ
sudo nano /opt/backup/file_backup.sh
```

**/opt/backup/file_backup.sh:**

```bash
#!/bin/bash
DATE=$(date +%Y%m%d)
BACKUP_DIR="/mnt/backup/ayumi"

# アプリケーションファイル
rsync -az --delete /opt/ayumi/ $BACKUP_DIR/app_$DATE/

# .env ファイル
cp /opt/ayumi/.env $BACKUP_DIR/env_$DATE

echo "File backup completed"
```

---

### 6.2 リストア手順

#### データベースリストア

```bash
# バックアップファイルから復元
gunzip < /opt/backup/mysql/tsusho_20251007_030000.sql.gz \
    | docker exec -i php-selfmade-db-1 mysql -u root -ppassword tsusho
```

#### アプリケーションリストア

```bash
# ファイル復元
rsync -az /mnt/backup/ayumi/app_20251007/ /opt/ayumi/

# 再起動
docker compose restart
```

---

### 6.3 ディザスタリカバリ（DR）

**RTO（目標復旧時間）: 4時間**
**RPO（目標復旧時点）: 24時間**

#### DR手順書

1. **バックアップサーバーへの切替**
   - DNS変更（TTL: 300秒）
   - バックアップサーバーで Docker起動

2. **データベース復元**
   - 最新のバックアップから復元
   - 整合性チェック

3. **動作確認**
   - ログイン確認
   - 基本機能確認

4. **利用者通知**
   - メールで復旧通知

---

## 7. コスト試算

### 7.1 初期費用

#### パターンA: オンプレミス

| 項目 | 金額 |
|------|------|
| サーバー（Dell PowerEdge T150） | 150,000円 |
| UPS（無停電電源装置） | 30,000円 |
| ルーター | 20,000円 |
| 構築作業（40時間 × 5,000円） | 200,000円 |
| **合計** | **400,000円** |

#### パターンB: VPS

| 項目 | 金額 |
|------|------|
| 構築作業（20時間 × 5,000円） | 100,000円 |
| **合計** | **100,000円** |

---

### 7.2 月額ランニングコスト

#### パターンA: オンプレミス

| 項目 | 月額 |
|------|------|
| 電気代（100W × 24h × 30日 × 27円/kWh） | 1,944円 |
| インターネット回線（固定IP） | 5,000円 |
| ドメイン（年額1,500円 ÷ 12） | 125円 |
| SSL証明書（Let's Encrypt無料） | 0円 |
| メール（SendGrid Free） | 0円 |
| **合計** | **7,069円/月** |

#### パターンB: VPS（ConoHa 4GB）

| 項目 | 月額 |
|------|------|
| VPS（4GB RAM） | 2,633円 |
| バックアップストレージ（100GB） | 500円 |
| ドメイン | 125円 |
| SSL証明書（Let's Encrypt無料） | 0円 |
| メール（SendGrid Free: 100通/日まで） | 0円 |
| 監視（UptimeRobot無料） | 0円 |
| **合計** | **3,258円/月** |

#### パターンC: AWS（フルマネージド）

| 項目 | 月額 |
|------|------|
| ECS Fargate（2タスク） | 50,000円 |
| RDS MySQL（t3.medium Multi-AZ） | 40,000円 |
| ElastiCache Redis（t3.micro） | 5,000円 |
| ALB | 3,000円 |
| S3（100GB） | 300円 |
| Route 53 | 600円 |
| CloudWatch | 2,000円 |
| データ転送 | 5,000円 |
| **合計** | **105,900円/月** |

---

### 7.3 3年間総コスト比較

| 項目 | オンプレミス | VPS | AWS |
|------|------------|-----|-----|
| 初期費用 | 400,000円 | 100,000円 | 100,000円 |
| 月額費用 | 7,069円 | 3,258円 | 105,900円 |
| 3年間総額 | **654,484円** | **217,288円** | **3,912,400円** |

**推奨: 小規模事業所（~50名）はVPSが最もコストパフォーマンス良好**

---

## 8. チェックリスト（本番リリース前）

### 8.1 セキュリティチェック

- [ ] `.env` のパーミッション確認（600）
- [ ] `APP_DEBUG=false` 設定
- [ ] HTTPS強制リダイレクト
- [ ] SSH公開鍵認証のみ
- [ ] ファイアウォール設定
- [ ] fail2ban 稼働確認
- [ ] データベースパスワード強度確認
- [ ] Redis パスワード設定
- [ ] セキュリティヘッダー確認

### 8.2 機能チェック

- [ ] 職員ログイン（2FA）
- [ ] 利用者ログイン
- [ ] 出席予定・実績登録
- [ ] 日報入力
- [ ] ダッシュボード表示
- [ ] CSV出力
- [ ] 監査ログ記録

### 8.3 運用体制チェック

- [ ] バックアップスクリプト稼働確認
- [ ] 監視アラート受信確認
- [ ] 運用手順書作成
- [ ] 緊急連絡先リスト作成
- [ ] DR手順の確認

### 8.4 ドキュメント

- [ ] 運用手順書
- [ ] トラブルシューティングガイド
- [ ] ユーザーマニュアル
- [ ] API仕様書

---

## 9. トラブルシューティング

### 9.1 よくある問題と対処法

#### 問題: ページが表示されない（500エラー）

**確認:**
```bash
# Laravelログ確認
docker compose logs app | tail -100

# Nginxエラーログ
sudo tail -f /var/log/nginx/ayumi_error.log
```

**原因1: パーミッション問題**
```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec app chmod -R 775 storage bootstrap/cache
```

**原因2: .env 設定ミス**
```bash
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
```

---

#### 問題: データベース接続エラー

**確認:**
```bash
docker compose exec db mysql -u root -p
# パスワード入力後、接続確認

SHOW DATABASES;
```

**原因: コンテナ間通信**
```bash
# ネットワーク確認
docker network ls
docker network inspect php-selfmade_app-network
```

---

#### 問題: メールが送信されない

**確認:**
```bash
# Laravelログでメール送信ログ確認
docker compose logs app | grep -i mail

# .env のメール設定確認
docker compose exec app php artisan config:show mail
```

**テスト送信:**
```bash
docker compose exec app php artisan tinker

# Tinker内で実行
Mail::raw('テストメール', function($message) {
    $message->to('test@example.com')->subject('テスト');
});
```

---

## 10. 参考資料

- [Laravel公式デプロイガイド](https://laravel.com/docs/deployment)
- [Nginx公式ドキュメント](https://nginx.org/en/docs/)
- [Let's Encrypt公式](https://letsencrypt.org/)
- [Docker公式ドキュメント](https://docs.docker.com/)

---

**ドキュメント作成日**: 2025年10月7日
**対象システム**: AYUMI v1.0
**次回更新予定**: 運用開始後3ヶ月
