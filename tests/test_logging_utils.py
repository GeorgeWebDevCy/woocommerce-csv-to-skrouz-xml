from __future__ import annotations

import tempfile
import unittest
from pathlib import Path

from skroutz_feed_builder.logging_utils import configure_logging


class LoggingTests(unittest.TestCase):
    def test_configure_logging_creates_and_writes_log_file(self) -> None:
        with tempfile.TemporaryDirectory() as tmp_dir:
            logger, log_path = configure_logging(log_dir=tmp_dir, logger_name="skroutz_feed_builder_test")
            logger.info("test log entry")
            for handler in logger.handlers:
                handler.flush()
            self.assertEqual(log_path, Path(tmp_dir) / "skroutz-feed-builder.log")
            self.assertTrue(log_path.exists())
            self.assertIn("test log entry", log_path.read_text(encoding="utf-8"))
            for handler in list(logger.handlers):
                logger.removeHandler(handler)
                handler.close()


if __name__ == "__main__":
    unittest.main()
