# Downloader

**日本語** | [English](#english)

PHP と SQLite3 だけで動作する、パスワード付きファイル配布システムです。  
管理画面からファイルをアップロードし、専用 URL とダウンロード用パスワードを配布相手に渡すだけで、安全にファイルを共有できます。

---

## 主な機能

- **パスワード付きダウンロード** — トークン付き URL に加え、ダウンロード用パスワード（10 文字）が必要
- **管理画面** — ファイルのアップロード・一覧・削除、配布 URL / パスワードのコピー
- **有効期限** — ファイルごとに期限を設定可能（無期限も可）
- **複数管理者** — 管理者アカウントの追加・更新・削除、表示言語の個別設定
- **多言語対応** — 日本語 / 英語（`lang/` にファイルを追加するだけで拡張可能）
- **セキュリティ** — CSRF 対策、レート制限、セッションタイムアウト、セキュリティ HTTP ヘッダー、パストラバーサル対策

## 対応ファイル形式

| 拡張子 | 用途の例 |
|--------|----------|
| PDF | 資料・請求書 |
| XLSX / XLS / CSV | 表計算データ |
| ZIP | 複数ファイルのまとめ |
| MP4 | 動画 |

MIME タイプと拡張子の両方で検証します。許可形式は `includes/config.php` の `ALLOWED_EXTENSIONS` / `ALLOWED_MIME_TYPES` で変更できます。

## 動作環境

- PHP 8.0 以上（`declare(strict_types=1)` 使用）
- SQLite3（PDO）
- Apache（`mod_rewrite` / `mod_headers` 推奨）
- 書き込み可能な `data/` と `storage/` ディレクトリ

共有ホスティング、VPS、MAMP / XAMPP など、PHP が動く環境で利用できます。

## ディレクトリ構成

```
downloader/
├── admin/           管理画面（ログイン・アップロード・アカウント管理）
├── assets/          CSS / JavaScript
├── data/            SQLite データベース（app.db）※ Git 管理外
├── includes/        共通 PHP（認証・DB・セキュリティ・i18n）
├── lang/            翻訳ファイル（ja.php, en.php）
├── storage/         アップロードされた実ファイル ※ Git 管理外
├── index.php        ダウンロードページ（パスワード入力）
├── download.php     ダウンロード処理
└── setup.php        初回セットアップ（1 回のみ）
```

## インストール

### 1. ファイルを配置

Web サーバーの document root 配下に本リポジトリを配置します。

```bash
git clone https://github.com/tsubu/downloader.git
cd downloader
```

`data/` と `storage/` は初回セットアップ時に自動作成されますが、事前に作成しておく場合は Web サーバーから書き込み可能にしてください。

```bash
mkdir -p data storage
chmod 750 data storage
```

### 2. Web サーバー設定

Apache を使用する場合、`.htaccess` により以下が有効になります。

- `includes/` への直接アクセス拒否
- ディレクトリリスティング無効化
- セキュリティ HTTP ヘッダー

PHP のアップロード上限は、サーバーの `upload_max_filesize` / `post_max_size` に依存します。必要に応じて `includes/config.php` の `SERVER_MAX_UPLOAD_SIZE` でも上限を指定できます。

### 3. 初回セットアップ

ブラウザで `setup.php` にアクセスし、最初の管理者アカウント（メールアドレス・パスワード）を登録します。

```
https://example.com/downloader/setup.php
```

セットアップ完了後は `setup.php` への POST が拒否され、自動的に管理画面へリダイレクトされます。`data/setup.lock` が作成されると再セットアップはできません。

## 使い方

### 管理者（ファイル配布側）

1. `/admin/` にアクセスしてログイン
2. ダッシュボードでファイルをアップロード（表示名・有効期限を指定）
3. 一覧から **配布 URL** と **ダウンロード用パスワード** をコピー
4. 受け取り側に URL とパスワードを別経路で伝える

### 受け取り側

1. 配布された URL をブラウザで開く
2. ダウンロード用パスワードを入力
3. ファイルがダウンロードされる（表示名がファイル名として使用されます）

## セキュリティについて

| 項目 | 内容 |
|------|------|
| 管理者パスワード | `password_hash()` でハッシュ化して保存 |
| ダウンロードパスワード | 平文で DB に保存（管理画面での確認・配布用）。ハッシュも保持 |
| ログイン試行 | 5 回失敗で 15 分ロック（IP + メールアドレス単位） |
| DL パスワード試行 | 5 回失敗で 15 分ロック（IP + トークン単位） |
| 管理セッション | 30 分のアイドルタイムアウト |
| CSRF | フォーム送信・ログアウト・セットアップで検証 |
| ストレージ | ランダムな保存名 + パストラバーサル検証 |

本番環境では **HTTPS の利用を強く推奨** します。HTTPS 時は HSTS ヘッダーも送信されます。

## 設定のカスタマイズ

`includes/config.php` で主な定数を変更できます。

| 定数 | 説明 | デフォルト |
|------|------|------------|
| `ALLOWED_EXTENSIONS` | 許可する拡張子 | pdf, xlsx, xls, csv, zip, mp4 |
| `TOKEN_LENGTH` | ダウンロード URL トークン長 | 32（16 進） |
| `DOWNLOAD_PASSWORD_LENGTH` | DL パスワード長 | 10 |
| `LOGIN_MAX_ATTEMPTS` | ログイン試行上限 | 5 |
| `LOGIN_LOCKOUT_SECONDS` | ログインロック時間（秒） | 900 |
| `SESSION_IDLE_TIMEOUT` | セッションタイムアウト（秒） | 1800 |
| `SERVER_MAX_UPLOAD_SIZE` | アップロード上限（例: `'64M'`） | `'0'`（未設定） |

## 多言語の追加

1. `lang/en.php` をコピーして `lang/{言語コード}.php` を作成
2. 各翻訳キーの値を置き換え
3. 管理画面のアカウント設定で言語を選択

対応ロケールは `lang/*.php` から自動検出されます。

## ライセンス

[MIT License](LICENSE) の下で公開しています。

## 作者

[tsubu](https://github.com/tsubu)

---

<a id="english"></a>

# Downloader

[日本語](#downloader) | **English**

A password-protected file distribution system built with PHP and SQLite3 only.  
Upload files from the admin panel, share a dedicated URL and download password with recipients, and distribute files securely.

---

## Features

- **Password-protected downloads** — Requires a token-based URL plus a 10-character download password
- **Admin panel** — Upload, list, and delete files; copy distribution URLs and passwords
- **Expiration dates** — Set a per-file expiry or allow unlimited access
- **Multiple administrators** — Add, update, and delete admin accounts; per-user display language
- **Multilingual UI** — Japanese and English (extend by adding files under `lang/`)
- **Security** — CSRF protection, rate limiting, session timeout, security HTTP headers, path traversal protection

## Supported file types

| Extension | Typical use |
|-----------|-------------|
| PDF | Documents, invoices |
| XLSX / XLS / CSV | Spreadsheets |
| ZIP | Bundled files |
| MP4 | Video |

Both MIME type and file extension are validated. Allowed types can be changed in `includes/config.php` via `ALLOWED_EXTENSIONS` and `ALLOWED_MIME_TYPES`.

## Requirements

- PHP 8.0 or later (`declare(strict_types=1)`)
- SQLite3 (PDO)
- Apache (`mod_rewrite` and `mod_headers` recommended)
- Writable `data/` and `storage/` directories

Works on shared hosting, VPS, MAMP, XAMPP, or any environment that runs PHP.

## Directory structure

```
downloader/
├── admin/           Admin panel (login, upload, account management)
├── assets/          CSS / JavaScript
├── data/            SQLite database (app.db) — not tracked by Git
├── includes/        Shared PHP (auth, DB, security, i18n)
├── lang/            Translation files (ja.php, en.php)
├── storage/         Uploaded files — not tracked by Git
├── index.php        Download page (password entry)
├── download.php     Download handler
└── setup.php        Initial setup (one-time only)
```

## Installation

### 1. Deploy the files

Place this repository under your web server’s document root.

```bash
git clone https://github.com/tsubu/downloader.git
cd downloader
```

The `data/` and `storage/` directories are created automatically during setup. You may create them beforehand and ensure the web server can write to them.

```bash
mkdir -p data storage
chmod 750 data storage
```

### 2. Web server configuration

With Apache, `.htaccess` enables:

- Blocking direct access to `includes/`
- Disabling directory listing
- Security HTTP headers

Upload limits depend on the server’s `upload_max_filesize` and `post_max_size`. You can also set a cap in `includes/config.php` via `SERVER_MAX_UPLOAD_SIZE`.

### 3. Initial setup

Open `setup.php` in a browser and register the first administrator account (email and password).

```
https://example.com/downloader/setup.php
```

After setup completes, POST requests to `setup.php` are rejected and you are redirected to the admin panel. Once `data/setup.lock` exists, setup cannot be run again.

## Usage

### Administrator (sender)

1. Log in at `/admin/`
2. Upload a file on the dashboard (set display name and expiry)
3. Copy the **distribution URL** and **download password** from the list
4. Share the URL and password with recipients through separate channels

### Recipient

1. Open the shared URL in a browser
2. Enter the download password
3. The file downloads (the display name is used as the filename)

## Security

| Item | Details |
|------|---------|
| Admin passwords | Stored with `password_hash()` |
| Download passwords | Stored in plain text in the DB (for admin display and distribution); hashes are also kept |
| Login attempts | Locked for 15 minutes after 5 failures (per IP + email) |
| Download password attempts | Locked for 15 minutes after 5 failures (per IP + token) |
| Admin session | 30-minute idle timeout |
| CSRF | Validated on form submissions, logout, and setup |
| Storage | Random stored filenames + path traversal checks |

**HTTPS is strongly recommended** in production. HSTS headers are sent when HTTPS is used.

## Configuration

Main constants in `includes/config.php`:

| Constant | Description | Default |
|----------|-------------|---------|
| `ALLOWED_EXTENSIONS` | Allowed file extensions | pdf, xlsx, xls, csv, zip, mp4 |
| `TOKEN_LENGTH` | Download URL token length | 32 (hex) |
| `DOWNLOAD_PASSWORD_LENGTH` | Download password length | 10 |
| `LOGIN_MAX_ATTEMPTS` | Max login attempts | 5 |
| `LOGIN_LOCKOUT_SECONDS` | Login lockout duration (seconds) | 900 |
| `SESSION_IDLE_TIMEOUT` | Session idle timeout (seconds) | 1800 |
| `SERVER_MAX_UPLOAD_SIZE` | Upload size cap (e.g. `'64M'`) | `'0'` (unset) |

## Adding languages

1. Copy `lang/en.php` to `lang/{locale-code}.php`
2. Replace the translation values
3. Select the language in the admin account settings

Supported locales are auto-detected from `lang/*.php`.

## License

Released under the [MIT License](LICENSE).

## Author

[tsubu](https://github.com/tsubu)
