from fastapi import FastAPI

from app.config import get_settings

app = FastAPI(title="DevAudit AI - Analysis Engine")


@app.get("/health")
def health() -> dict[str, str]:
    settings = get_settings()
    return {"status": "ok", "service": settings.service_name}
