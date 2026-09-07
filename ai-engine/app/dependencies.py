from functools import lru_cache

from app.ai.factory import build_provider
from app.ai.provider import AIProvider
from app.config import get_settings


@lru_cache
def get_provider() -> AIProvider:
    return build_provider(get_settings())
