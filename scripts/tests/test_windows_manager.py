"""Portable orchestration tests; no Docker daemon or Moodle installation is claimed."""
import importlib.util
import os
from pathlib import Path
import subprocess
import tempfile
import unittest
from unittest.mock import patch

spec = importlib.util.spec_from_file_location('manage_windows', Path(__file__).resolve().parents[1] / 'manage.py')
manage = importlib.util.module_from_spec(spec)
spec.loader.exec_module(manage)


class FakeManager(manage.Manager):
    def __init__(self, root, version='45'):
        super().__init__(root, version=version)
        self.calls = []
        self.fail = None

    def run(self, args, **kwargs):
        self.calls.append(list(args))
        if self.fail and self.fail(args):
            raise subprocess.CalledProcessError(23, args)
        if kwargs.get('stdout'):
            kwargs['stdout'].write(b'PGDMP\x00\xff\r\n' if 'pg_dump' in args else b'\x1f\x8b\x00\xff\r\n')
        return subprocess.CompletedProcess(args, 0, stdout='linux\n', stderr='')


class ManagerTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='Moodle setup 日本語 with spaces ')
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        (self.root / 'docker').mkdir()
        (self.root / 'docker/sources.txt').write_text('test/pinned abc .\n')
        self.manager = FakeManager(self.root)
        self.which = patch.object(manage.shutil, 'which', return_value='docker')
        self.which.start()
        self.addCleanup(self.which.stop)

    def settings(self):
        return self.manager.settings(create=True)

    def test_repeat_setup_preserves_settings_and_fixed_project(self):
        self.manager.action('setup')
        first = (self.root / '.env').read_bytes()
        self.manager.action('setup')
        self.assertEqual(first, (self.root / '.env').read_bytes())
        self.assertNotIn(b'\r', first)
        for call in self.manager.calls:
            if call[:2] == ['docker', 'compose'] and 'version' not in call:
                self.assertEqual(call[:8], ['docker', 'compose', '--project-name', 'macmoodlesetup', '--env-file', '.env', '-f', 'compose.yaml'])
                self.assertNotIn('down', call)
        if os.name != 'nt':
            self.assertEqual((self.root / '.env').stat().st_mode & 0o077, 0)

    def test_php_failure_stops_before_cron_and_preserves_data(self):
        self.settings()
        self.manager.fail = lambda args: '-r' in args
        with self.assertRaises(subprocess.CalledProcessError):
            self.manager.action('start')
        self.assertFalse(any('cron' in args for args in self.manager.calls))
        probe = self.manager.calls[-1]
        self.assertIn('display_errors=stderr', probe)
        self.assertIn('log_errors=0', probe)

    def test_setup_failure_shortcircuits_multiple_actions(self):
        calls = []
        with patch.object(manage.Manager, 'action', side_effect=lambda a: calls.append(a) or (_ for _ in ()).throw(ValueError('synthetic failure'))):
            self.assertEqual(manage.main(['setup', 'start']), 1)
        self.assertEqual(calls, ['setup'])

    def test_restart_and_start_keep_credentials_and_volumes(self):
        self.settings()
        before = (self.root / '.env').read_bytes()
        self.manager.action('restart')
        compose = [a[8:] for a in self.manager.calls if '--project-name' in a]
        self.assertEqual(compose[0], ['--profile', 'installed', 'stop'])
        self.assertEqual(compose[-1], ['--profile', 'installed', 'up', '-d', 'cron'])
        self.assertEqual(before, (self.root / '.env').read_bytes())

    def test_backup_bytes_preserved_and_does_not_restart_writers(self):
        self.settings()
        self.manager.action('backup')
        folder = next((self.root / 'backups').iterdir())
        self.assertEqual((folder / 'database.dump').read_bytes(), b'PGDMP\x00\xff\r\n')
        self.assertEqual((folder / 'moodledata.tar.gz').read_bytes(), b'\x1f\x8b\x00\xff\r\n')
        self.assertEqual((folder / 'settings.env').read_bytes(), (self.root / '.env').read_bytes())
        self.assertFalse((folder / 'FAILED').exists())
        self.assertFalse(any('up' in args for args in self.manager.calls))
        self.assertTrue(any('--entrypoint' in args and 'tar' in args for args in self.manager.calls))

    def test_failed_backup_is_marked_and_left_stopped(self):
        self.settings()
        self.manager.fail = lambda args: 'pg_dump' in args
        with self.assertRaises(subprocess.CalledProcessError):
            self.manager.action('backup')
        folder = next((self.root / 'backups').iterdir())
        self.assertTrue((folder / 'FAILED').exists())
        self.assertFalse(any('up' in args for args in self.manager.calls))

    def test_crlf_settings_and_invalid_values(self):
        (self.root / '.env').write_bytes(b'MOODLE_PORT=8090\r\nDB_PASSWORD=Db123\r\nADMIN_PASSWORD=Mm1!123\r\n')
        self.assertEqual(self.manager.settings()['MOODLE_PORT'], '8090')
        for text in ['MOODLE_PORT=99999\n', 'MOODLE_PORT=8088\nMOODLE_PORT=8089\n', 'DB_PASSWORD=$(not-executed)\n']:
            (self.root / '.env').write_text(text)
            with self.assertRaises(ValueError):
                self.manager.settings()

    def test_doctor_rejects_windows_container_engine_without_writing_settings(self):
        with patch.object(self.manager, 'run', return_value=subprocess.CompletedProcess([], 0, stdout='windows\n')):
            with self.assertRaisesRegex(ValueError, 'Linux containers'):
                self.manager.action('setup')
        self.assertFalse((self.root / '.env').exists())

    def test_doctor_missing_docker_and_unavailable_engine(self):
        with patch.object(manage.shutil, 'which', return_value=None):
            with self.assertRaisesRegex(ValueError, 'PATH'):
                self.manager.doctor()
        self.manager.fail = lambda args: 'info' in args
        with self.assertRaisesRegex(ValueError, 'Start Docker Desktop'):
            self.manager.doctor()

    def test_no_shell_and_case_insensitive_compose_env_isolation(self):
        with patch.dict(os.environ, {'db_password': 'other', 'ADMIN_PASSWORD': 'other', 'MOODLE_PORT': '9999'}):
            manager = manage.Manager(self.root)
        self.assertFalse(any(k.upper() in {'DB_PASSWORD', 'ADMIN_PASSWORD', 'MOODLE_PORT'} for k in manager.env))
        with patch.object(manage.subprocess, 'run') as run:
            manager.run(['docker', 'compose', 'build', 'web'])
        self.assertEqual(run.call_args.kwargs['cwd'], self.root)
        self.assertFalse(run.call_args.kwargs.get('shell', False))
        self.assertEqual(run.call_args.args[0], ['docker', 'compose', 'build', 'web'])


if __name__ == '__main__':
    unittest.main()
