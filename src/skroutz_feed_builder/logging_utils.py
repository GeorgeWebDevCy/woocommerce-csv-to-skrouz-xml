from __future__ import annotations

import logging
from logging.handlers import RotatingFileHandler
import os
from pathlib import Path


LOGGER_NAME = "skroutz_feed_builder"
LOG_FILE_NAME = "skroutz-feed-builder.log"
DEFAULT_LOG_LEVEL = logging.DEBUG
DEFAULT_ACTIVITY_LOG_LEVEL = logging.INFO


def default_log_dir() -> Path:
    local_app_data = os.environ.get("LOCALAPPDATA")
    if local_app_data:
        return Path(local_app_data) / "SkroutzFeedBuilder" / "logs"
    return Path.home() / ".skroutz-feed-builder" / "logs"


def get_logger(name: str | None = None) -> logging.Logger:
    if not name:
        return logging.getLogger(LOGGER_NAME)
    if name == LOGGER_NAME or name.startswith(f"{LOGGER_NAME}."):
        return logging.getLogger(name)
    return logging.getLogger(f"{LOGGER_NAME}.{name}")


def configure_logging(log_dir: str | Path | None = None, logger_name: str = LOGGER_NAME) -> tuple[logging.Logger, Path]:
    directory = Path(log_dir) if log_dir is not None else default_log_dir()
    directory.mkdir(parents=True, exist_ok=True)
    log_path = directory / LOG_FILE_NAME

    logger = logging.getLogger(logger_name)
    logger.setLevel(DEFAULT_LOG_LEVEL)
    logger.propagate = False

    existing_file_handler = next(
        (
            handler
            for handler in logger.handlers
            if getattr(handler, "_skroutz_file_handler", False) and Path(getattr(handler, "baseFilename")) == log_path
        ),
        None,
    )

    if existing_file_handler is None:
        file_handler = RotatingFileHandler(log_path, maxBytes=1_000_000, backupCount=5, encoding="utf-8")
        file_handler._skroutz_file_handler = True  # type: ignore[attr-defined]
        file_handler.setLevel(DEFAULT_LOG_LEVEL)
        file_handler.setFormatter(logging.Formatter("%(asctime)s %(levelname)-7s %(name)s %(message)s"))
        logger.addHandler(file_handler)

    return logger, log_path
