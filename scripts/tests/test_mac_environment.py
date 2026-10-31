"""Exercise the real Bash launcher with Docker/OS doubles; never reads user settings."""
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]


@unittest.skipIf(os.name == 'nt', 'Bash launcher is for macOS')
class MacEnvironmentTest(unittest.TestCase):
    def test_foreign_shell_credentials_do_not_override_settings(self):
        with tempfile.TemporaryDirectory(prefix='Moodle Mac shell ') as folder:
            root = Path(folder)
            (root / 'scripts').mkdir()
            shutil.copyfile(ROOT / 'scripts/manage.sh', root / 'scripts/manage.sh')
            settings = b'MOODLE_PORT=8088\nDB_PASSWORD=generated-test-value\nADMIN_PASSWORD=Mm1!test-value\n'
            (root / '.env').write_bytes(settings)
            binary = root / 'bin'
            binary.mkdir()
            (binary / 'uname').write_text('#!/bin/sh\nprintf Darwin\n')
            # No Compose implementation: assert its interpolation environment directly.
            (binary / 'docker').write_text('''#!/bin/sh
if [ "$1" = compose ] && [ "$2" != version ]; then
  [ "${DB_PASSWORD+x}" != x ] || exit 81
  [ "${ADMIN_PASSWORD+x}" != x ] || exit 82
  [ "${MOODLE_PORT+x}" != x ] || exit 83
fi
exit 0
''')
            for file in binary.iterdir():
                file.chmod(0o755)
            environment = dict(os.environ, PATH=str(binary) + os.pathsep + os.environ['PATH'],
                               DB_PASSWORD='foreign-db', ADMIN_PASSWORD='foreign-admin', MOODLE_PORT='9999')
            for action in ('setup', 'start', 'restart', 'check', 'stop'):
                result = subprocess.run(['bash', 'scripts/manage.sh', action], cwd=root, env=environment,
                                        capture_output=True, text=True)
                self.assertEqual(result.returncode, 0, result.stderr)
                self.assertNotIn('foreign-', result.stdout + result.stderr)
                self.assertEqual((root / '.env').read_bytes(), settings)


if __name__ == '__main__':
    unittest.main()
