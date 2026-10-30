#!/usr/bin/env python3
"""Native Windows management; no Bash, OpenSSL or PowerShell binary redirection."""
import argparse
from datetime import datetime
import os
from pathlib import Path
import re
import secrets
import shutil
import subprocess
import sys
import webbrowser

ROOT = Path(__file__).resolve().parents[1]
ACTIONS = ('doctor', 'setup', 'start', 'stop', 'restart', 'check', 'logs', 'status', 'open', 'credentials', 'backup')


class Manager:
    def __init__(self, root=ROOT):
        self.root = Path(root)
        # Compose interpolation must not pick up credentials from another project.
        self.env = {k: v for k, v in os.environ.items()
                    if k.upper() not in {'DB_PASSWORD', 'ADMIN_PASSWORD', 'MOODLE_PORT'}}

    def run(self, args, **kwargs):
        return subprocess.run(args, cwd=self.root, env=self.env, check=True, **kwargs)

    def compose(self, *args, **kwargs):
        return self.run(['docker', 'compose', '--project-name', 'macmoodlesetup',
                         '--env-file', '.env', '-f', 'compose.yaml', *args], **kwargs)

    def doctor(self):
        if not shutil.which('docker'):
            raise ValueError('Docker is not on PATH. Install Docker Desktop, then reopen PowerShell. / Docker Desktopを導入し、PowerShellを開き直してください。')
        self.run(['docker', 'compose', 'version'])
        try:
            result = self.run(['docker', 'info', '--format', '{{.OSType}}'],
                              capture_output=True, text=True)
        except subprocess.CalledProcessError:
            raise ValueError('Docker is not ready. Start Docker Desktop and wait for its engine. / Docker Desktopを起動し、エンジン起動を待ってください。') from None
        if result.stdout.strip() != 'linux':
            raise ValueError('Use Linux containers in Docker Desktop. / Docker DesktopをLinuxコンテナーに切り替えてください。')
        print('Docker Compose and Linux engine: OK / Dockerの準備完了', flush=True)

    def settings(self, create=False):
        path = self.root / '.env'
        if create and not path.exists():
            # Exclusive creation: repeat setup cannot replace existing passwords.
            with open(path, 'x', encoding='utf-8', newline='\n', opener=lambda p, f: os.open(p, f, 0o600)) as stream:
                stream.write('MOODLE_PORT=8088\nDB_PASSWORD=' + secrets.token_hex(24)
                             + '\nADMIN_PASSWORD=Mm1!' + secrets.token_hex(16) + '\n')
        if not path.is_file():
            raise ValueError('Run .\\moodle.cmd setup first. / 先にsetupを実行してください。')
        values = {}
        for line in path.read_text(encoding='utf-8-sig').splitlines():
            line = line.strip()
            if not line or line.startswith('#'):
                continue
            key, sep, value = line.partition('=')
            if not sep or key not in {'MOODLE_PORT', 'DB_PASSWORD', 'ADMIN_PASSWORD'} or key in values:
                raise ValueError('Invalid or duplicate .env setting. / .envの項目が不正または重複しています。')
            if not re.fullmatch(r'[A-Za-z0-9!._-]+', value):
                raise ValueError('Unsupported .env value; use generated settings. / .envの値を確認してください（自動生成値を推奨）。')
            values[key] = value
        if set(values) != {'MOODLE_PORT', 'DB_PASSWORD', 'ADMIN_PASSWORD'}:
            raise ValueError('Missing .env settings. / .envの必須項目が不足しています。')
        if not values['MOODLE_PORT'].isdigit() or not 1024 <= int(values['MOODLE_PORT']) <= 65535:
            raise ValueError('MOODLE_PORT must be 1024–65535. / ポートは1024〜65535を指定してください。')
        return values

    def start(self, port):
        self.compose('up', '-d', 'db', 'maxima', 'web')
        self.compose('exec', '-T', '--user', 'www-data', 'web', 'php',
                     '-d', 'display_errors=stderr', '-d', 'log_errors=0', '-r',
                     'define("CLI_SCRIPT", true); require "/var/www/html/config.php"; if (empty($CFG->version)) {exit(1);}')
        self.compose('--profile', 'installed', 'up', '-d', 'cron')
        print('Moodle: http://localhost:' + port, flush=True)

    def backup(self):
        folder = self.root / 'backups' / datetime.now().strftime('%Y%m%d-%H%M%S-%f')
        folder.mkdir(parents=True, mode=0o700)
        try:
            self.compose('stop', 'web', 'cron')
            # Open bytes directly: Windows PowerShell's text encoding cannot corrupt dumps.
            with (folder / 'database.dump').open('wb') as stream:
                self.compose('exec', '-T', 'db', 'pg_dump', '-U', 'moodle', '-d', 'moodle', '-Fc', stdout=stream)
            with (folder / 'moodledata.tar.gz').open('wb') as stream:
                self.compose('run', '--rm', '--no-deps', '-T', '--user', 'root', '--entrypoint', 'tar',
                             'web', '-czf', '-', '-C', '/var/moodledata', '.', stdout=stream)
            shutil.copyfile(self.root / '.env', folder / 'settings.env')
            (folder / 'settings.env').chmod(0o600)
            shutil.copyfile(self.root / 'docker/sources.txt', folder / 'sources.txt')
            with (folder / 'images.txt').open('wb') as stream:
                self.compose('config', '--images', stdout=stream)
        except Exception:
            (folder / 'FAILED').write_text('Incomplete backup. Do not use for restoration.\n', encoding='utf-8')
            raise
        finally:
            print('Services remain stopped. Use start after checking the backup. / サービスは停止したままです。確認後にstartで再開してください。', flush=True)
        print('Backup saved / バックアップ保存: ' + str(folder), flush=True)

    def action(self, action):
        if action == 'doctor':
            self.doctor()
            return
        if action in ('credentials', 'open'):
            values = self.settings()
            url = 'http://localhost:' + values['MOODLE_PORT']
            if action == 'credentials':
                print('URL: ' + url + '\nUsername: admin\nInitial password: ' + values['ADMIN_PASSWORD'])
            else:
                print(url)
                webbrowser.open(url)
            return
        if action != 'setup':
            self.settings()  # Fail before contacting Docker when setup is missing.
        self.doctor()
        values = self.settings(create=action == 'setup')
        if action == 'setup':
            self.compose('build', 'web')
            self.compose('stop', 'cron')
            self.compose('up', '-d', 'db', 'maxima', 'web')
            self.compose('exec', '-T', '--user', 'www-data', 'web', 'bash', '/opt/macmoodle/bootstrap.sh')
            self.compose('exec', '-T', '--user', 'www-data', 'web', 'php', '/opt/macmoodle/check.php')
            print('Setup complete. Run start, then credentials. / setup完了。startとcredentialsを実行してください。', flush=True)
        elif action == 'start':
            self.start(values['MOODLE_PORT'])
        elif action == 'stop':
            self.compose('--profile', 'installed', 'stop')
        elif action == 'restart':
            self.compose('--profile', 'installed', 'stop')
            self.start(values['MOODLE_PORT'])
        elif action == 'check':
            self.compose('exec', '-T', '--user', 'www-data', 'web', 'php', '/opt/macmoodle/check.php')
        elif action == 'logs':
            self.compose('--profile', 'installed', 'logs', '--tail=80')
        elif action == 'status':
            self.compose('--profile', 'installed', 'ps')
        elif action == 'backup':
            self.backup()


def main(argv=None):
    parser = argparse.ArgumentParser(description='MoodleSTACKSetup: native Windows commands / Windows用管理コマンド')
    parser.add_argument('actions', nargs='+', choices=ACTIONS)
    args = parser.parse_args(argv)
    manager = Manager()
    try:
        for action in args.actions:
            manager.action(action)
    except subprocess.CalledProcessError as error:
        print('Docker command failed; subsequent actions were not run. / Dockerコマンドが失敗したため、後続処理を中止しました。', file=sys.stderr)
        return error.returncode or 1
    except (OSError, ValueError) as error:
        print(str(error), file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
