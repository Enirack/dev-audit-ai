from fastapi import FastAPI

from app.config import get_settings
from app.routers.ai import router as ai_router

app = FastAPI(title="DevAudit AI - Analysis Engine")
app.include_router(ai_router)


@app.get("/health")
def health() -> dict[str, str]:
    settings = get_settings()
    return {"status": "ok", "service": settings.service_name}
