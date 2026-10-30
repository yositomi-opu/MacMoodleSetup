"""5.3 isolation and command routing without Docker; real smoke is a separate CI job."""
import json
from pathlib import Path
import subprocess
import unittest
from unittest.mock import patch
import test_windows_manager as helpers

manage = helpers.manage
ROOT = Path(__file__).resolve().parents[2]


class Moodle53Tests(unittest.TestCase):
    setUp = helpers.ManagerTests.setUp
    settings = helpers.ManagerTests.settings
    # Reuse the private temporary-directory setup, not the 4.5 action expectations.
    def test_53_uses_separate_project_env_sources_and_port(self):
        legacy = self.settings()
        legacy_bytes = (self.root / '.env').read_bytes()
        (self.root / 'docker/moodle53').mkdir()
        (self.root / 'docker/moodle53/sources.txt').write_text('test/moodle53 pinned .\n')
        m = helpers.FakeManager(self.root, version='53')
        m.action('setup');m.action('start');m.action('stop');m.action('restart');m.action('check')
        self.assertEqual(m.settings()['MOODLE_PORT'], '8090')
        saved = (self.root / '.env53').read_bytes()
        m.action('setup')
        self.assertEqual(saved, (self.root / '.env53').read_bytes())
        m.action('backup')
        folder = next((self.root / 'backups/moodle53').iterdir())
        self.assertEqual((folder / 'sources.txt').read_text(), 'test/moodle53 pinned .\n')
        self.assertEqual((folder / 'settings.env').read_bytes(), saved)
        self.assertEqual((self.root / '.env').read_bytes(), legacy_bytes)
        self.assertNotEqual(legacy['ADMIN_PASSWORD'], m.settings()['ADMIN_PASSWORD'])
        for call in m.calls:
            if '--project-name' in call:
                self.assertEqual(call[:8], ['docker', 'compose', '--project-name', 'macmoodlesetup53', '--env-file', '.env53', '-f', 'compose53.yaml'])

    def test_53_rejects_legacy_ports_before_compose(self):
        self.settings()
        m = helpers.FakeManager(self.root, version='53')
        m.settings(create=True)
        for port in ['8088', '8089']:
            raw=(self.root / '.env53').read_text()
            raw='\n'.join('MOODLE_PORT='+port if x.startswith('MOODLE_PORT=') else x for x in raw.splitlines())+'\n'
            (self.root / '.env53').write_text(raw)
            with self.assertRaisesRegex(ValueError, 'different port'):
                m.action('start')
        self.assertEqual(m.calls, [])

    def test_version_suffix_routes_each_action(self):
        calls=[]
        class Recorder:
            def __init__(self, version):self.version=version
            def action(self, action):calls.append((self.version, action))
        with patch.object(manage, 'Manager', Recorder):
            self.assertEqual(manage.main(['setup53','start53','check53']),0)
            self.assertEqual(manage.main(['--moodle','53','status']),0)
        self.assertEqual(calls,[('53','setup'),('53','start'),('53','check'),('53','status')])

    def test_sources_pinned_and_53_public_root(self):
        source=(ROOT/'docker/moodle53/sources.txt').read_text().splitlines()
        self.assertEqual(source[0],'moodle/moodle 42622298fe06f9626d988d60b2bf589bd8f850e8 .')
        for row in source:
            repo,sha,path=row.split()
            self.assertRegex(sha,r'^[a-f0-9]{40}$')
            if repo!='moodle/moodle':self.assertTrue(path.startswith('public/question/'))
        compose=(ROOT/'compose53.yaml').read_text()
        self.assertIn('postgres:17-bookworm@sha256:',compose)
        self.assertNotIn('postgres:16',compose)
        self.assertIn('127.0.0.1:${MOODLE_PORT:-8090}:80',compose)
        self.assertIn('entrypoint: []',compose)
        config=(ROOT/'docker/moodle53/config.php').read_text()
        self.assertIn("$CFG->sessioncookie = 'MoodleSTACK53'",config)
        self.assertIn('APACHE_DOCUMENT_ROOT=/var/www/html/public',(ROOT/'docker/moodle53/Dockerfile').read_text())
