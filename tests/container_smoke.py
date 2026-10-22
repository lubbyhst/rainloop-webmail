#!/usr/bin/env python3
"""Exercise a disposable image; never mounts production data or uses real accounts."""
import argparse
import pathlib
import subprocess
import time
import unittest
import urllib.error
import urllib.request


def docker(*args):
    return subprocess.check_output(["docker", *args], text=True).strip()


class ContainerSmoke(unittest.TestCase):
    image = "rainloop:test"

    @classmethod
    def setUpClass(cls):
        cls.container = None
        tests = pathlib.Path(__file__).resolve().parent
        cls.container = docker(
            "run", "--detach", "--read-only", "--cap-drop=ALL",
            "--security-opt=no-new-privileges", "--memory=256m", "--pids-limit=64",
            "--tmpfs", "/tmp:rw,nosuid,noexec,size=64m",
            "--tmpfs", "/rainloop/data:rw,nosuid,noexec,size=32m,uid=33,gid=33",
            "--mount", f"type=bind,source={tests},target=/tests,readonly",
            "--mount", f"type=bind,source={tests / 'runtime.php'},target=/rainloop/runtime-test.php,readonly",
            "--publish", "127.0.0.1::8080", cls.image,
        )
        try:
            port = docker("port", cls.container, "8080/tcp").rsplit(":", 1)[1]
            cls.base = "http://127.0.0.1:" + port
            for _ in range(60):
                try:
                    if cls.request("/healthz")[0] == 200:
                        break
                except (OSError, urllib.error.URLError):
                    pass
                time.sleep(1)
            else:
                raise AssertionError("Apache/PHP did not become ready")
            cls.version = docker(
                "exec", cls.container, "php", "-r",
                "echo basename(glob('/rainloop/rainloop/v/*', GLOB_ONLYDIR)[0]);",
            )
        except BaseException:
            print(docker("logs", cls.container))
            docker("rm", "--force", cls.container)
            cls.container = None
            raise

    @classmethod
    def tearDownClass(cls):
        if cls.container:
            print(docker("logs", cls.container))
            docker("rm", "--force", cls.container)

    @classmethod
    def request(cls, path):
        try:
            with urllib.request.urlopen(cls.base + path, timeout=10) as response:
                return response.status, response.headers, response.read()
        except urllib.error.HTTPError as error:
            return error.code, error.headers, error.read()

    def test_patched_php_in_cli_and_apache(self):
        self.assertIn("Runtime checks passed", docker("exec", self.container, "php", "/tests/runtime.php"))
        status, headers, body = self.request("/runtime-test.php")
        self.assertEqual(status, 200, body)
        self.assertEqual(body, b"Runtime checks passed\n")
        self.assertNotIn("X-Powered-By", headers)

    def test_application_and_admin_pages(self):
        for path in ["/", "/?admin"]:
            with self.subTest(path=path):
                status, headers, body = self.request(path)
                self.assertEqual(status, 200, body[:500])
                self.assertIn(b'id="rl-app"', body)
                self.assertNotRegex(body, rb"(?i)(fatal error|missing version directory|\[30[12]\])")
                self.assertNotIn("X-Powered-By", headers)

    def test_built_assets(self):
        for asset in ["js/min/app.min.js", "js/min/admin.min.js", "css/app.min.css"]:
            with self.subTest(asset=asset):
                status, _, body = self.request(f"/rainloop/v/{self.version}/static/{asset}")
                self.assertEqual(status, 200)
                self.assertGreater(len(body), 1000)

    def test_private_files_are_denied(self):
        for path in ["/data/", "/data/INSTALLED", "/data/%5fdata%5f/_default_/configs/application.ini",
                     f"/rainloop/v/{self.version}/app/templates/Index.html",
                     f"/rainloop/v/{self.version}/include.php", "/.git/config", "/package.json"]:
            with self.subTest(path=path):
                self.assertIn(self.request(path)[0], [403, 404])

    def test_mail_library_compatibility(self):
        self.assertIn("PHP mail compatibility tests passed", docker("exec", self.container, "php", "/tests/php-unit.php"))

    def test_imap_retrieval_and_smtp_submission(self):
        self.assertIn("IMAP retrieval and SMTP submission tests passed",
                      docker("exec", self.container, "php", "/tests/mail-transport.php"))

    def test_unprivileged_and_healthy(self):
        self.assertEqual(docker("exec", self.container, "id", "-u"), "33")
        self.assertEqual(self.request("/healthz")[2], b"ok\n")
        subprocess.run(["docker", "exec", self.container, "sh", "-c", "test ! -w /rainloop/index.php"], check=True)


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--image", default="rainloop:test")
    args, remaining = parser.parse_known_args()
    ContainerSmoke.image = args.image
    unittest.main(argv=[__file__, *remaining], verbosity=2)
